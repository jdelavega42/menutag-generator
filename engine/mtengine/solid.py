"""Exact 2D silhouettes and the Z slab stack of the piece.

The solid is a stack of prismatic slabs. Each slab is described
COMPOSITIONALLY — base silhouette, minus carve features, plus addition
features — where every feature is a strictly-contained, disjoint analytic
region. Sections and caps are assembled from the very same ring objects
without ANY boolean overlay in the meshing path: GEOS overlays renode
collinear vertices and would break vertex sharing between walls and caps.
No CSG anywhere (spec §2.4). Every critical height sits on the layer lattice
with the explicit 1e-9 tolerance (contract 03 §3).
"""

from __future__ import annotations

import math
from dataclasses import dataclass, field

from shapely import affinity
from shapely.geometry import MultiPolygon, Polygon
from shapely.ops import unary_union

from . import constants as C
from .errors import UserParameterError
from .layers import LayerGrid, tol_ceil, tol_round_int
from .logo import fit_into, load_logo
from .params import Params
from .qrsym import QrSymbol, build_qr_symbol, carve_logo_window


# ---------------------------------------------------------------------------
# Ring normalisation: shared-exactly-by-construction coordinates
# ---------------------------------------------------------------------------

def _clean_ring(coords) -> list[tuple[float, float]] | None:
    """Round to 1e-6, drop duplicate and collinear vertices. Applied ONCE per
    feature ring: walls and caps then reuse identical coordinates."""
    pts = [(round(float(x), 6), round(float(y), 6)) for x, y in coords]
    if len(pts) > 1 and pts[0] == pts[-1]:
        pts = pts[:-1]
    dedup: list[tuple[float, float]] = []
    for pt in pts:
        if not dedup or pt != dedup[-1]:
            dedup.append(pt)
    if len(dedup) > 1 and dedup[0] == dedup[-1]:
        dedup.pop()
    out: list[tuple[float, float]] = []
    n = len(dedup)
    for i in range(n):
        a = dedup[i - 1]
        b = dedup[i]
        c = dedup[(i + 1) % n]
        cross = (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0])
        if abs(cross) > 1e-9:
            out.append(b)
    if len(out) < 3:
        return None
    return out


def normalize_region(geom) -> MultiPolygon:
    """Normalise a region into a MultiPolygon with cleaned rings."""
    polys_in: list[Polygon]
    if geom.is_empty:
        return MultiPolygon([])
    if isinstance(geom, Polygon):
        polys_in = [geom]
    elif isinstance(geom, MultiPolygon):
        polys_in = list(geom.geoms)
    else:
        polys_in = [g for g in geom.geoms if isinstance(g, Polygon)]
    out: list[Polygon] = []
    for poly in polys_in:
        shell = _clean_ring(poly.exterior.coords)
        if shell is None:
            continue
        holes = []
        for ring in poly.interiors:
            cleaned = _clean_ring(ring.coords)
            if cleaned is not None:
                holes.append(cleaned)
        out.append(Polygon(shell, holes))
    return MultiPolygon(out)


# ---------------------------------------------------------------------------
# Silhouettes
# ---------------------------------------------------------------------------

def _arc_segments(radius: float) -> int:
    """Segments for a full circle so that the chord error stays < 0.05 mm."""
    if radius <= C.CHORD_TOL_MM:
        return 8
    theta = 2.0 * math.acos(max(-1.0, 1.0 - C.CHORD_TOL_MM / radius))
    return max(16, int(math.ceil(2.0 * math.pi / theta)))


def circle_poly(radius: float, cx: float = 0.0, cy: float = 0.0) -> Polygon:
    n = _arc_segments(radius)
    pts = [
        (
            round(cx + radius * math.cos(2.0 * math.pi * i / n), 6),
            round(cy + radius * math.sin(2.0 * math.pi * i / n), 6),
        )
        for i in range(n)
    ]
    return Polygon(pts)


def rounded_square(side: float, fillet: float) -> Polygon:
    half = side / 2.0
    if fillet <= 0.0:
        return Polygon(
            [(-half, -half), (half, -half), (half, half), (-half, half)]
        )
    f = min(fillet, half)
    n = max(4, _arc_segments(f) // 4)
    centers = [(half - f, half - f), (-(half - f), half - f),
               (-(half - f), -(half - f)), (half - f, -(half - f))]
    start_angles = [0.0, math.pi / 2.0, math.pi, 3.0 * math.pi / 2.0]
    pts: list[tuple[float, float]] = []
    for (cx, cy), a0 in zip(centers, start_angles):
        for i in range(n + 1):
            a = a0 + (math.pi / 2.0) * i / n
            pts.append((round(cx + f * math.cos(a), 6), round(cy + f * math.sin(a), 6)))
    return Polygon(pts)


def silhouette(params: Params) -> Polygon:
    """EFFECTIVE outer silhouette: xy-comp applied per side to the outer
    contour only (decisions §2). Everything else (QR pitch, product floors)
    uses the nominal size."""
    if params.shape == "circle":
        return circle_poly(params.size / 2.0 + params.xy_comp)
    side = params.size + 2.0 * params.xy_comp
    fillet = max(0.0, params.fillet + params.xy_comp) if params.fillet > 0 else 0.0
    return rounded_square(side, fillet)


def nominal_silhouette(params: Params) -> Polygon:
    if params.shape == "circle":
        return circle_poly(params.size / 2.0)
    return rounded_square(params.size, params.fillet)


# ---------------------------------------------------------------------------
# Face artwork
# ---------------------------------------------------------------------------

@dataclass
class FaceModel:
    name: str                     # 'front' | 'back'
    content: str                  # none|logo|qr|qr_logo
    art: MultiPolygon | None      # positioned artwork, front orientation
    window: Polygon | None        # content window (metrics complement bound)
    qr: QrSymbol | None
    mirrored: bool = False        # True for the back face (decode raster flip)
    # Logo component alone (qr_logo faces carry both): metrics thresholds
    # differ per component — QR modules keep the §3.2 floor even in inlay
    # (§3.6), logo strokes climb to 4x nozzle in inlay.
    logo_art: MultiPolygon | None = None


def _auto_logo_margin(content_size: float) -> float:
    """Engine choice (documented in engine/README.md): auto margin for
    logo-only faces = 5 % of the face content size, at least 1.0 mm."""
    return max(1.0, 0.05 * content_size)


def build_face(
    params: Params,
    name: str,
    content: str,
    window,
    content_size: float,
    logo_geom: MultiPolygon | None,
    warnings: list[str],
) -> FaceModel:
    if content == "none":
        return FaceModel(name=name, content=content, art=None, window=None, qr=None)

    art: MultiPolygon | None = None
    qr: QrSymbol | None = None
    logo_part: MultiPolygon | None = None

    if content in ("qr", "qr_logo"):
        data = params.qr_data_front if name == "front" else params.qr_data_back
        qr = build_qr_symbol(
            data=data,
            ec=params.qr_ec,
            shape=params.shape,
            content_size=content_size,
            nozzle=params.nozzle_mm,
            margin=params.margin,
            warnings=warnings,
        )
        art = qr.modules
        if content == "qr_logo":
            assert logo_geom is not None
            box_side = C.QR_LOGO_BOX_FRACTION * qr.span_mm
            target = Polygon(
                [(-box_side / 2, -box_side / 2), (box_side / 2, -box_side / 2),
                 (box_side / 2, box_side / 2), (-box_side / 2, box_side / 2)]
            )
            logo = fit_into(logo_geom, target, params.logo_rotate)
            channel = C.QR_LOGO_CHANNEL_PASSES * params.nozzle_mm
            qr = carve_logo_window(qr, logo, channel)
            art = unary_union([qr.modules, logo])
            logo_part = logo
    else:  # logo
        assert logo_geom is not None
        margin = params.margin if params.margin is not None else _auto_logo_margin(content_size)
        target = window.buffer(-margin)
        if target.is_empty:
            raise UserParameterError(
                f"Il margine di {margin:.2f} mm non lascia spazio al logo sulla "
                f"faccia {'frontale' if name == 'front' else 'posteriore'}: "
                "riduci il margine o aumenta la dimensione del pezzo."
            )
        art = fit_into(logo_geom, target, params.logo_rotate)
        logo_part = art

    art = normalize_region(art)
    if not window.buffer(1e-6).covers(art):
        raise UserParameterError(
            "La grafica non entra nella faccia "
            f"{'frontale' if name == 'front' else 'posteriore'}: aumenta la "
            "dimensione del pezzo, riduci lo smusso o il margine imposto."
        )
    return FaceModel(name=name, content=content, art=art, window=window, qr=qr,
                     mirrored=(name == "back"), logo_art=logo_part)


# ---------------------------------------------------------------------------
# Slab specification (compositional — no overlay in the meshing path)
# ---------------------------------------------------------------------------

@dataclass
class SliceSpec:
    z0: float
    z1: float
    has_base: bool
    carves: list[MultiPolygon] = field(default_factory=list)  # strictly inside base
    adds: list[MultiPolygon] = field(default_factory=list)    # strictly inside base plan


@dataclass
class Model:
    grid: LayerGrid
    silhouette: Polygon
    base_slices: list[SliceSpec] = field(default_factory=list)
    accent_slices: list[SliceSpec] = field(default_factory=list)
    analytic_base_mm3: float = 0.0
    analytic_accent_mm3: float = 0.0
    top_z: float = 0.0
    thickness_eff: float = 0.0
    depth_eff: float = 0.0
    recess_eff: float | None = None
    pause_z: float | None = None
    pause_layer: int | None = None
    capacity_ml: float | None = None
    faces: dict[str, FaceModel] = field(default_factory=dict)
    bicolor_layers: int | None = None

    @property
    def analytic_full_mm3(self) -> float:
        return self.analytic_base_mm3 + self.analytic_accent_mm3


def _region_area(mp: MultiPolygon) -> float:
    return float(mp.area)


def _slice_area(sil: Polygon, spec: SliceSpec) -> float:
    area = sil.area if spec.has_base else 0.0
    area -= sum(_region_area(c) for c in spec.carves)
    area += sum(_region_area(a) for a in spec.adds)
    return area


def _snap_span(value: float, layer: float, label: str, warnings: list[str]) -> float:
    """Snap a Z span to a whole number of layers, warning when the user's
    value had to be adjusted (spec §8.3: 'se una quota non ci cade, adegua
    e riportalo')."""
    n = max(1, tol_round_int(value, layer))
    snapped = round(n * layer, 9)
    if abs(snapped - value) > C.TOL:
        warnings.append(
            f"{label} adeguata al reticolo dei layer: {value:.3f} mm -> {snapped:.3f} mm."
        )
    return snapped


def build_model(params: Params, warnings: list[str]) -> Model:
    prof = C.PRINTERS[params.printer]["nozzles"][params.nozzle]
    layer = params.layer_height if params.layer_height is not None else prof["layer_default"]
    first_layer = prof["first_layer"]
    grid = LayerGrid(first_layer=first_layer, layer_height=layer)

    # --- thickness on the lattice: top = FL + n*L ---------------------------
    n_top = max(1, tol_round_int(params.thickness - first_layer, layer))
    thickness = round(first_layer + n_top * layer, 9)
    if abs(thickness - params.thickness) > C.TOL:
        warnings.append(
            f"Spessore adeguato al reticolo dei layer: {params.thickness:.3f} mm "
            f"-> {thickness:.3f} mm."
        )

    has_content = params.front != "none" or params.back != "none"
    depth = _snap_span(params.depth, layer, "Profondità grafica", warnings) \
        if has_content else 0.0
    recess = None
    if params.base_profile == "rimmed":
        recess = _snap_span(params.recess_depth, layer, "Profondità incavo", warnings)
        if params.mode == "relief" and has_content and not depth + C.TOL < recess:
            raise UserParameterError(
                f"In rilievo su profilo con bordo l'altezza della grafica "
                f"({depth:.2f} mm) deve restare sotto il bordo: usa una "
                f"profondità < {recess:.2f} mm o aumenta l'incavo."
            )

    sil_mp = normalize_region(silhouette(params))
    if len(sil_mp.geoms) != 1:
        raise UserParameterError("La silhouette del pezzo non è valida con questi parametri.")
    sil = sil_mp.geoms[0]
    nom_sil = nominal_silhouette(params)

    carve_front = depth if (params.front != "none" and params.mode in ("engrave", "inlay")) else 0.0
    carve_back = depth if (params.back != "none" and params.mode in ("engrave", "inlay")) else 0.0
    relief_front = depth if (params.front != "none" and params.mode == "relief") else 0.0
    relief_back = depth if (params.back != "none" and params.mode == "relief") else 0.0
    if relief_back > 0.0:
        warnings.append(
            "Rilievo sulla faccia posteriore: il pezzo appoggia sul piatto solo "
            "sulla grafica in rilievo, adesione e planarità ne risentono. "
            "Valuta 'engrave' o 'inlay' per il retro."
        )

    # --- rimmed geometry -----------------------------------------------------
    inner: MultiPolygon | None = None
    capacity_ml = None
    if params.base_profile == "rimmed":
        inner_raw = sil.buffer(-params.rim_width, quad_segs=32)
        inner = normalize_region(inner_raw)
        if inner.is_empty:
            raise UserParameterError(
                f"Il bordo di {params.rim_width:.1f} mm consuma l'intera faccia: "
                "riduci la larghezza del bordo o aumenta la dimensione."
            )
        # continuity: the recess field must be ONE region strictly inside the
        # silhouette, so the rim ring around it is a single continuous loop
        if len(inner.geoms) != 1 or inner.geoms[0].interiors:
            raise UserParameterError(
                "Il bordo antigoccia non risulta continuo con questi parametri: "
                "riduci la larghezza del bordo o semplifica la forma."
            )
        capacity_ml = inner.area * recess / 1000.0

    # --- face content windows ------------------------------------------------
    if params.base_profile == "rimmed":
        front_window = inner.geoms[0]
        front_content_size = params.size - 2.0 * params.rim_width
    else:
        front_window = nom_sil if nom_sil.within(sil.buffer(1e-6)) else sil
        front_content_size = params.size
    back_window = nom_sil if nom_sil.within(sil.buffer(1e-6)) else sil
    back_content_size = params.size

    logo_geom = load_logo(params.logo, warnings) if params.logo else None
    if logo_geom is None and any(
        c in ("logo", "qr_logo") for c in (params.front, params.back)
    ):
        raise UserParameterError(
            "Le facce con logo richiedono --logo: carica un PNG o un SVG."
        )
    if logo_geom is not None and not any(
        c in ("logo", "qr_logo") for c in (params.front, params.back)
    ):
        warnings.append("--logo fornito ma nessuna faccia lo usa: file ignorato.")

    front = build_face(params, "front", params.front, front_window,
                       front_content_size, logo_geom, warnings)
    back = build_face(params, "back", params.back, back_window,
                      back_content_size, logo_geom, warnings)

    front_art = front.art
    back_art = None
    if back.art is not None:
        # back graphics mirrored: readable when the piece is flipped (§2.3)
        back_art = normalize_region(
            affinity.scale(back.art, xfact=-1.0, yfact=1.0, origin=(0, 0))
        )

    # --- Z quotas ------------------------------------------------------------
    base_z0 = relief_back  # back relief lifts the body; piece still rests at Z=0
    top = round(base_z0 + thickness, 9)
    recess_floor = round(top - recess, 9) if recess is not None else None

    # NFC pocket, top-anchored (validated reference: 58.8x3.0, L=0.10,
    # FL=0.20 -> pocket 1.0..2.0, PAUSE_Z=2.0, PAUSE_LAYER=19 of 29).
    pocket_region = None
    pocket_floor = pocket_top = None
    pause_z = pause_layer = None
    if params.nfc:
        axial_wall = max(C.NFC_AXIAL_WALL_MIN_MM, C.NFC_AXIAL_WALL_MIN_LAYERS * layer)
        pocket_h = round(
            tol_ceil(params.tag_thickness + C.NFC_AXIAL_CLEARANCE_MM, layer) * layer, 9
        )
        ceiling = top - (recess or 0.0) - carve_front
        pocket_top = grid.snap_down(ceiling - axial_wall)
        pocket_floor = round(pocket_top - pocket_h, 9)
        floor_wall = pocket_floor - (base_z0 + carve_back)
        if floor_wall + C.TOL < axial_wall or pocket_floor < first_layer - C.TOL:
            t_min = (params.tag_thickness + C.NFC_AXIAL_CLEARANCE_MM
                     + 2.0 * axial_wall + carve_front + carve_back + (recess or 0.0))
            t_min_grid = round(first_layer + tol_ceil(t_min - first_layer, layer) * layer, 9)
            raise UserParameterError(
                f"Spessore insufficiente per la tasca NFC sul reticolo dei layer: "
                f"servono almeno {t_min_grid:.2f} mm "
                f"(tag {params.tag_thickness:.2f} + gioco {C.NFC_AXIAL_CLEARANCE_MM:.2f} "
                f"+ 2 pareti da {axial_wall:.2f} + incisioni "
                f"{carve_front + carve_back:.2f}"
                + (f" + incavo {recess:.2f}" if recess else "")
                + f"). Imposta --thickness ad almeno {t_min_grid:.2f}."
            )
        pocket_r = params.tag / 2.0 + C.NFC_RADIAL_CLEARANCE_MM
        pocket_region = normalize_region(circle_poly(pocket_r))
        pause_z = pocket_top
        pause_layer = grid.layer_of(pocket_top)

    # --- feature intervals ----------------------------------------------------
    carve_ivals: list[tuple[float, float, MultiPolygon]] = []
    add_ivals: list[tuple[float, float, MultiPolygon]] = []
    accent_ivals: list[tuple[float, float, MultiPolygon]] = []

    if carve_back > 0.0 and back_art is not None:
        carve_ivals.append((base_z0, round(base_z0 + carve_back, 9), back_art))
        if params.mode == "inlay":
            accent_ivals.append((base_z0, round(base_z0 + carve_back, 9), back_art))
    if relief_back > 0.0 and back_art is not None:
        add_ivals.append((0.0, base_z0, back_art))
    if pocket_region is not None:
        carve_ivals.append((pocket_floor, pocket_top, pocket_region))
    if recess is not None:
        carve_ivals.append((recess_floor, top, inner))
    front_floor = recess_floor if recess is not None else top
    if carve_front > 0.0 and front_art is not None:
        carve_ivals.append((round(front_floor - carve_front, 9), front_floor, front_art))
        if params.mode == "inlay":
            accent_ivals.append((round(front_floor - carve_front, 9), front_floor, front_art))
    if relief_front > 0.0 and front_art is not None:
        add_ivals.append((front_floor, round(front_floor + relief_front, 9), front_art))

    # --- compose slabs ---------------------------------------------------------
    zs = {0.0, base_z0, top}
    for z0, z1, _g in carve_ivals + add_ivals:
        zs.add(round(z0, 9))
        zs.add(round(z1, 9))
    breakz = sorted(zs)
    base_slices: list[SliceSpec] = []
    analytic_base = 0.0
    for z0, z1 in zip(breakz[:-1], breakz[1:]):
        if z1 - z0 <= C.TOL:
            continue
        mid = (z0 + z1) / 2.0
        has_base = base_z0 - C.TOL < mid < top + C.TOL
        carves = [g for a, b, g in carve_ivals if a - C.TOL < mid < b + C.TOL]
        adds = [g for a, b, g in add_ivals if a - C.TOL < mid < b + C.TOL]
        if not has_base and not adds:
            continue
        spec = SliceSpec(z0=z0, z1=z1, has_base=has_base,
                         carves=carves if has_base else [],
                         adds=adds)
        base_slices.append(spec)
        analytic_base += _slice_area(sil, spec) * (z1 - z0)

    accent_slices: list[SliceSpec] = []
    analytic_accent = 0.0
    for z0, z1, g in accent_ivals:
        accent_slices.append(SliceSpec(z0=z0, z1=z1, has_base=False, adds=[g]))
        analytic_accent += _region_area(g) * (z1 - z0)

    top_z = top + (relief_front if recess is None else 0.0)

    model = Model(
        grid=grid,
        silhouette=sil,
        base_slices=base_slices,
        accent_slices=accent_slices,
        analytic_base_mm3=analytic_base,
        analytic_accent_mm3=analytic_accent,
        top_z=top_z,
        thickness_eff=thickness,
        depth_eff=depth,
        recess_eff=recess,
        pause_z=pause_z,
        pause_layer=pause_layer,
        capacity_ml=capacity_ml,
        faces={"front": front, "back": back},
    )
    if params.mode == "inlay":
        model.bicolor_layers = tol_round_int(depth, layer)
    return model
