"""Logo import: SVG stays vector-to-vector, PNG is raster + contour tracing
(spec §2.3 — rasterising an SVG throws away the best input the user can give;
tracing is the correct route ONLY for PNG).

Libraries: svgelements (SVG parsing, curves flattened under the 0.05 mm chord
budget), OpenCV (PNG thresholding + findContours). Contour simplification with
chord error < 0.05 mm is ALLOWED here (spec §8.6: logo contours, plate arcs)
and forbidden on QR modules.
"""

from __future__ import annotations

import math
import os

import cv2
import numpy as np
from shapely import affinity
from shapely.geometry import LineString, MultiPolygon, Polygon
from shapely.ops import polygonize, unary_union

from . import constants as C
from .errors import UserParameterError


def _as_multipolygon(geom) -> MultiPolygon:
    if geom.is_empty:
        return MultiPolygon([])
    if isinstance(geom, Polygon):
        return MultiPolygon([geom])
    if isinstance(geom, MultiPolygon):
        return geom
    polys = [g for g in getattr(geom, "geoms", []) if isinstance(g, Polygon)]
    return MultiPolygon(polys)


# ---------------------------------------------------------------------------
# PNG: raster + contour tracing (OpenCV), two-level hierarchy -> holes
# ---------------------------------------------------------------------------

def _trace_png(path: str, warnings: list[str] | None = None) -> MultiPolygon:
    img = cv2.imread(path, cv2.IMREAD_GRAYSCALE)
    if img is None:
        raise UserParameterError(
            f"Il file logo '{os.path.basename(path)}' non è un PNG leggibile. "
            "Carica un PNG ad alto contrasto (grafica scura su fondo chiaro)."
        )
    _, bw = cv2.threshold(img, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    # Artwork = dark pixels. The background is inferred from the image BORDER
    # (a global mean would misread a dark emblem cropped tight, >50% of the
    # canvas, and silently trace the white ground instead of the drawing).
    dark = bw == 0
    border = np.concatenate([dark[0, :], dark[-1, :], dark[:, 0], dark[:, -1]])
    if border.mean() > 0.5:
        dark = ~dark
        if warnings is not None:
            warnings.append(
                "Il PNG del logo ha il bordo scuro: interpretato come grafica "
                "chiara su fondo scuro e quindi invertito in tracciamento. Se "
                "il risultato non è quello atteso, fornisci una grafica scura "
                "su fondo chiaro."
            )
    mask = np.where(dark, 255, 0).astype(np.uint8)
    contours, hierarchy = cv2.findContours(
        mask, cv2.RETR_CCOMP, cv2.CHAIN_APPROX_SIMPLE
    )
    if hierarchy is None or len(contours) == 0:
        raise UserParameterError(
            "Il PNG del logo non contiene aree tracciabili: serve una grafica "
            "scura su fondo chiaro (o viceversa) con contrasto netto."
        )
    h_img = img.shape[0]
    hier = hierarchy[0]  # rows: [next, prev, first_child, parent]

    def ring(cnt: np.ndarray) -> list[tuple[float, float]] | None:
        pts = cnt.reshape(-1, 2).astype(float)
        if len(pts) < 3:
            return None
        # Flip Y: image rows grow downward, geometry Y grows upward.
        return [(float(x), float(h_img - 1 - y)) for x, y in pts]

    polys: list[Polygon] = []
    for i, cnt in enumerate(contours):
        if hier[i][3] != -1:
            continue  # holes are attached to their parent below
        ext = ring(cnt)
        if ext is None:
            continue
        holes = []
        child = hier[i][2]
        while child != -1:
            hole = ring(contours[child])
            if hole is not None:
                holes.append(hole)
            child = hier[child][0]
        poly = Polygon(ext, holes)
        if not poly.is_valid:
            poly = poly.buffer(0)
        if not poly.is_empty:
            polys.append(poly)
    merged = unary_union(polys)
    return _as_multipolygon(merged)


# ---------------------------------------------------------------------------
# SVG: vector -> vector (svgelements), curves flattened, even-odd fill
# ---------------------------------------------------------------------------

def _trace_svg(path: str) -> MultiPolygon:
    from svgelements import SVG, Path as SvgPath, Shape as SvgShape

    try:
        svg = SVG.parse(path)
    except Exception as exc:  # noqa: BLE001 — parser errors are user errors
        raise UserParameterError(
            f"Il file SVG del logo non è leggibile ({exc}). "
            "Esporta di nuovo il logo come SVG semplice (tracciati pieni)."
        ) from exc

    rings: list[list[tuple[float, float]]] = []
    # Flatten every shape's subpaths; sampling density is relative to the
    # drawing size so the chord error stays under budget after the fit.
    elements = [e for e in svg.elements() if isinstance(e, SvgShape)]
    diag = 1.0
    boxes = [e.bbox() for e in elements]
    boxes = [b for b in boxes if b is not None]
    if boxes:
        xs0 = min(b[0] for b in boxes)
        ys0 = min(b[1] for b in boxes)
        xs1 = max(b[2] for b in boxes)
        ys1 = max(b[3] for b in boxes)
        diag = max(1e-6, math.hypot(xs1 - xs0, ys1 - ys0))
    step = diag / 600.0  # ~600 samples along the diagonal: chord << 0.05 mm

    for element in elements:
        try:
            p = SvgPath(element)
            p.reify()
        except Exception:  # noqa: BLE001
            continue
        for sub in p.as_subpaths():
            sp = SvgPath(sub)
            pts: list[tuple[float, float]] = []
            for seg in sp:
                name = type(seg).__name__
                if name in ("Move",):
                    if seg.end is not None:
                        pts.append((float(seg.end.x), float(seg.end.y)))
                    continue
                if seg.end is None or seg.start is None:
                    continue
                if name in ("Line", "Close"):
                    pts.append((float(seg.end.x), float(seg.end.y)))
                    continue
                try:
                    length = seg.length(error=1e-4)
                except Exception:  # noqa: BLE001
                    length = 0.0
                n = max(4, int(math.ceil(length / max(step, 1e-9))))
                for k in range(1, n + 1):
                    pt = seg.point(k / n)
                    pts.append((float(pt.x), float(pt.y)))
            if len(pts) >= 3:
                rings.append(pts)

    if not rings:
        raise UserParameterError(
            "L'SVG del logo non contiene tracciati chiusi riempibili. "
            "Converti testi e tratti in tracciati pieni prima di caricarlo."
        )

    # Even-odd fill: polygonize the noded ring arrangement, keep faces
    # covered by an odd number of rings.
    ring_polys: list[Polygon] = []
    for r in rings:
        try:
            rp = Polygon(r)
            if not rp.is_valid:
                rp = rp.buffer(0)
            if not rp.is_empty:
                ring_polys.append(rp)
        except Exception:  # noqa: BLE001
            continue
    lines = unary_union([LineString(list(r) + [r[0]]) for r in rings])
    faces = list(polygonize(lines))
    kept: list[Polygon] = []
    for face in faces:
        pt = face.representative_point()
        count = sum(1 for rp in ring_polys if rp.covers(pt))
        if count % 2 == 1:
            kept.append(face)
    merged = unary_union(kept)
    merged = _as_multipolygon(merged)
    if merged.is_empty:
        raise UserParameterError(
            "L'SVG del logo non produce aree piene: controlla che i tracciati "
            "siano chiusi e riempiti."
        )
    # SVG Y axis points down: mirror to geometry Y-up.
    return _as_multipolygon(affinity.scale(merged, xfact=1, yfact=-1, origin=(0, 0)))


def load_logo(path: str, warnings: list[str] | None = None) -> MultiPolygon:
    """Load a logo file into raw (unscaled) filled 2D geometry, Y-up."""
    ext = os.path.splitext(path)[1].lower()
    if ext == ".svg":
        geom = _trace_svg(path)
    elif ext == ".png":
        geom = _trace_png(path, warnings)
    else:
        raise UserParameterError(
            f"Formato logo non supportato: '{ext or 'senza estensione'}'. "
            "Sono accettati PNG e SVG."
        )
    if geom.is_empty or geom.area <= 0:
        raise UserParameterError(
            "Il logo non contiene aree piene tracciabili: usa una grafica "
            "ad alto contrasto (PNG) o tracciati riempiti (SVG)."
        )
    return geom


def fit_into(
    geom: MultiPolygon,
    target,
    rotate_deg: float,
    simplify_tol_mm: float = 0.03,
) -> MultiPolygon:
    """Rotate `geom` by `rotate_deg`, scale it uniformly to fit inside the
    `target` region and center it on the target centroid.

    Simplification (chord error < 0.05 mm) is applied AFTER scaling to mm —
    allowed on logo contours only (spec §8.6).
    """
    g = affinity.rotate(geom, rotate_deg, origin="centroid")
    minx, miny, maxx, maxy = g.bounds
    gw, gh = maxx - minx, maxy - miny
    if gw <= 0 or gh <= 0:
        raise UserParameterError("Il logo ha estensione nulla dopo la rotazione.")
    tminx, tminy, tmaxx, tmaxy = target.bounds
    tw, th = tmaxx - tminx, tmaxy - tminy
    scale = min(tw / gw, th / gh)
    cx, cy = (minx + maxx) / 2.0, (miny + maxy) / 2.0
    tcx, tcy = (tminx + tmaxx) / 2.0, (tminy + tmaxy) / 2.0

    for _ in range(80):
        placed = affinity.scale(g, xfact=scale, yfact=scale, origin=(cx, cy))
        placed = affinity.translate(placed, xoff=tcx - cx, yoff=tcy - cy)
        simplified = placed.simplify(simplify_tol_mm, preserve_topology=True)
        # Morphological closing by the same 0.005 mm used to dilate QR modules
        # (spec §2.3): sub-polygons touching in a single point or along a
        # sliver would otherwise produce a non-manifold wall edge.
        closed = unary_union(simplified).buffer(0.005, quad_segs=4).buffer(
            -0.005, quad_segs=4
        )
        closed = unary_union(closed)
        if not closed.is_empty and target.covers(closed):
            return _as_multipolygon(closed)
        scale *= 0.97
    raise UserParameterError(
        "Il logo non entra nell'area disponibile della faccia: aumenta la "
        "dimensione del pezzo, riduci il margine o riduci lo smusso degli angoli."
    )
