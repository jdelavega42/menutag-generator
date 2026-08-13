"""Slab stack -> watertight triangle mesh.

No CSG and no boolean overlays: sections and caps are ASSEMBLED from the same
normalised feature rings (base silhouette, carve regions, addition regions),
triangulated with manifold3d's exact polygon triangulator (hole-aware, uses
ONLY the input vertices — no Steiner points, every boundary edge covered
exactly once). Vertical walls are quad strips along the same rings, so
every shared boundary vertex is keyed on identical coordinates and the mesh
is watertight by construction; trimesh then re-verifies every §8.2 invariant.
"""

from __future__ import annotations

import math

import manifold3d
import numpy as np
import trimesh
from shapely.geometry import MultiPolygon, Polygon
from shapely.geometry.polygon import orient
from shapely.prepared import prep
from shapely.strtree import STRtree

from . import constants as C
from .errors import IntegrityError
from .solid import SliceSpec

_KEY_DECIMALS = 6


# ---------------------------------------------------------------------------
# Compositional region assembly (nesting by containment, never by overlay)
# ---------------------------------------------------------------------------

def _region_polys(mp) -> list[Polygon]:
    if mp is None or mp.is_empty:
        return []
    if isinstance(mp, Polygon):
        return [mp]
    return [g for g in mp.geoms if isinstance(g, Polygon) and not g.is_empty]


def assemble_region(
    base_polys: list[Polygon],
    carve_regions: list[MultiPolygon],
    add_regions: list[MultiPolygon],
) -> list[Polygon]:
    """Region = union(base) - union(carves) + union(adds), assuming every
    carve polygon lies strictly inside a base polygon's filled area, carve
    polygons are mutually disjoint (possibly nested through interior rings)
    and adds are disjoint from carves. Output polygons reuse the input ring
    coordinates verbatim."""
    carve_polys = [p for mp in carve_regions for p in _region_polys(mp)]
    result: list[Polygon] = []

    depth: dict[int, int] = {}
    if carve_polys:
        shells = [Polygon(p.exterior) for p in carve_polys]
        tree = STRtree(shells)
        prepared = [prep(s) for s in shells]
        for i, poly in enumerate(carve_polys):
            pt = poly.representative_point()
            d = 0
            for j in tree.query(pt):
                j = int(j)
                if j != i and prepared[j].contains(pt):
                    d += 1
            depth[i] = d

    for bp in base_polys:
        prepared_bp = prep(bp)
        top_level = [
            carve_polys[i] for i in range(len(carve_polys))
            if depth[i] == 0 and prepared_bp.contains(carve_polys[i].representative_point())
        ]
        result.append(
            Polygon(bp.exterior, [*[r for r in bp.interiors],
                                  *[c.exterior for c in top_level]])
        )

    for i, cpoly in enumerate(carve_polys):
        d = depth[i]
        for ring in cpoly.interiors:
            island = Polygon(ring)
            prepared_island = prep(island)
            children = [
                carve_polys[j] for j in range(len(carve_polys))
                if depth[j] == d + 1
                and prepared_island.contains(carve_polys[j].representative_point())
            ]
            result.append(Polygon(ring, [c.exterior for c in children]))

    for mp in add_regions:
        result.extend(_region_polys(mp))
    return [p for p in result if not p.is_empty]


def section_polys(sil: Polygon, spec: SliceSpec) -> list[Polygon]:
    base = [sil] if spec.has_base else []
    return assemble_region(base, spec.carves, spec.adds)


# ---------------------------------------------------------------------------
# Mesh assembly
# ---------------------------------------------------------------------------

class _MeshBuilder:
    def __init__(self) -> None:
        self._index: dict[tuple[float, float, float], int] = {}
        self.vertices: list[tuple[float, float, float]] = []
        self.faces: list[tuple[int, int, int]] = []

    def vid(self, x: float, y: float, z: float) -> int:
        key = (round(x, _KEY_DECIMALS), round(y, _KEY_DECIMALS), round(z, _KEY_DECIMALS))
        idx = self._index.get(key)
        if idx is None:
            idx = len(self.vertices)
            self._index[key] = idx
            self.vertices.append(key)
        return idx

    def add_tri(self, a: int, b: int, c: int) -> None:
        if a == b or b == c or a == c:
            return  # repeated-vertex triangle contributes no edges
        self.faces.append((a, b, c))


def _ring_coords(ring) -> list[tuple[float, float]]:
    pts = list(ring.coords)
    if len(pts) > 1 and pts[0] == pts[-1]:
        pts = pts[:-1]
    return [(float(x), float(y)) for x, y in pts]


def _walls(mb: _MeshBuilder, polys: list[Polygon], z0: float, z1: float) -> None:
    for poly in polys:
        poly = orient(poly, sign=1.0)  # exterior CCW, holes CW
        for ring in [poly.exterior, *poly.interiors]:
            pts = _ring_coords(ring)
            n = len(pts)
            for i in range(n):
                ax, ay = pts[i]
                bx, by = pts[(i + 1) % n]
                if ax == bx and ay == by:
                    continue
                a0 = mb.vid(ax, ay, z0)
                b0 = mb.vid(bx, by, z0)
                a1 = mb.vid(ax, ay, z1)
                b1 = mb.vid(bx, by, z1)
                # CCW travel keeps the solid on the left -> outward normals
                mb.add_tri(a0, b0, b1)
                mb.add_tri(a0, b1, a1)


def _cap(mb: _MeshBuilder, polys: list[Polygon], z: float, up: bool) -> None:
    """Triangulate horizontal caps with manifold3d's exact polygon
    triangulator: it references ONLY the input vertices (no Steiner points)
    and matches every boundary edge exactly, so cap edges pair with the wall
    strips built from the same rings."""
    for poly in polys:
        poly = orient(poly, sign=1.0)  # exterior CCW, holes CW (manifold API)
        rings = [_ring_coords(poly.exterior)] + [_ring_coords(r) for r in poly.interiors]
        verts: list[tuple[float, float]] = []
        loops: list[np.ndarray] = []
        for ring in rings:
            loops.append(np.asarray(ring, dtype=np.float64))
            verts.extend(ring)
        tris = manifold3d.triangulate(loops, C.TOL)
        for i, j, k in np.asarray(tris, dtype=np.int64):
            (x0, y0), (x1, y1), (x2, y2) = verts[i], verts[j], verts[k]
            cross = (x1 - x0) * (y2 - y0) - (y1 - y0) * (x2 - x0)
            ccw = cross > 0.0
            if cross != 0.0 and ccw != up:
                (x1, y1), (x2, y2) = (x2, y2), (x1, y1)
            elif cross == 0.0 and not up:
                # zero-area guard (not expected with oriented rings): flip to
                # keep the winding rule uniform for down caps
                (x1, y1), (x2, y2) = (x2, y2), (x1, y1)
            mb.add_tri(mb.vid(x0, y0, z), mb.vid(x1, y1, z), mb.vid(x2, y2, z))


def _features_key(regions: list[MultiPolygon]) -> set[int]:
    return {id(r) for r in regions}


def mesh_from_slices(sil: Polygon, slices: list[SliceSpec]) -> trimesh.Trimesh:
    """Build a watertight mesh from contiguous slabs (compositional caps)."""
    if not slices:
        raise IntegrityError("stack di sezioni vuoto: nessuna geometria da esportare")
    ordered = sorted(slices, key=lambda s: s.z0)
    mb = _MeshBuilder()

    sections = [section_polys(sil, s) for s in ordered]
    for spec, polys in zip(ordered, sections):
        _walls(mb, polys, spec.z0, spec.z1)

    # transitions: (z, below index or None, above index or None)
    events: list[tuple[float, int | None, int | None]] = []
    events.append((ordered[0].z0, None, 0))
    for i in range(len(ordered) - 1):
        if abs(ordered[i].z1 - ordered[i + 1].z0) <= C.TOL:
            events.append((ordered[i].z1, i, i + 1))
        else:  # gap (not expected, handled for safety)
            events.append((ordered[i].z1, i, None))
            events.append((ordered[i + 1].z0, None, i + 1))
    events.append((ordered[-1].z1, len(ordered) - 1, None))

    for z, bi, ai in events:
        below = ordered[bi] if bi is not None else None
        above = ordered[ai] if ai is not None else None
        if below is None and above is not None:
            # bottom of the stack (or of a floating slab): full section, down
            _cap(mb, sections[ai], z, up=False)
            continue
        if above is None and below is not None:
            _cap(mb, sections[bi], z, up=True)
            continue
        assert below is not None and above is not None
        if below.has_base and above.has_base:
            bc, ac = _features_key(below.carves), _features_key(above.carves)
            ba, aa = _features_key(below.adds), _features_key(above.adds)
            # appearing carve: solid below, empty above -> UP cap on the carve
            # region, minus any addition still occupying part of it above
            for carve in above.carves:
                if id(carve) not in bc:
                    _cap(mb, assemble_region(_region_polys(carve), [], []) if not above.adds
                         else assemble_region(_region_polys(carve), above.adds, []), z, up=True)
            # disappearing carve: empty below, solid above -> DOWN cap
            for carve in below.carves:
                if id(carve) not in ac:
                    _cap(mb, assemble_region(_region_polys(carve), below.adds, []), z, up=False)
            # disappearing addition inside the base span -> UP cap on its top
            for add in below.adds:
                if id(add) not in aa:
                    _cap(mb, _region_polys(add), z, up=True)
            # appearing addition over solid base: no horizontal face (the
            # solid continues upward through the addition footprint)
            del ba  # documented: intentionally unused beyond the note above
        elif not below.has_base and above.has_base:
            # base begins: its bottom face, minus regions already solid below
            _cap(mb, assemble_region([sil], above.carves + below.adds, []), z, up=False)
            # additions below not covered by the base cannot exist (adds ⊂ base plan)
        elif below.has_base and not above.has_base:
            # base ends: its top face, minus regions still solid above
            _cap(mb, assemble_region([sil], below.carves + above.adds, []), z, up=True)
        else:
            # floating slab to floating slab (not used by the current stacks)
            _cap(mb, sections[bi], z, up=True)
            _cap(mb, sections[ai], z, up=False)

    faces = _repair_degenerate(np.asarray(mb.vertices, dtype=np.float64),
                               [list(f) for f in mb.faces])
    mesh = trimesh.Trimesh(
        vertices=np.asarray(mb.vertices, dtype=np.float64),
        faces=np.asarray(faces, dtype=np.int64),
        process=False,
    )
    return mesh


def _tri_area2(verts: np.ndarray, tri: list[int]) -> float:
    a, b, c = verts[tri[0]], verts[tri[1]], verts[tri[2]]
    return float(np.linalg.norm(np.cross(b - a, c - a)))


def _repair_degenerate(verts: np.ndarray, faces: list[list[int]]) -> list[list[int]]:
    """Remove zero-area triangles by flipping their longest edge against the
    neighbouring face. The exact triangulator can emit a sliver when vertices
    of DIFFERENT rings happen to be collinear; the flip preserves the edge
    pairing (watertightness) and yields two proper triangles."""
    for _ in range(16):
        degenerate = [i for i, f in enumerate(faces) if _tri_area2(verts, f) < 1e-12]
        if not degenerate:
            break
        edge_map: dict[tuple[int, int], list[int]] = {}
        for i, f in enumerate(faces):
            for e in ((f[0], f[1]), (f[1], f[2]), (f[2], f[0])):
                edge_map.setdefault(tuple(sorted(e)), []).append(i)
        fixed_any = False
        for i in degenerate:
            f = faces[i]
            if _tri_area2(verts, f) >= 1e-12:
                continue
            # longest edge spans the other two vertices of the sliver
            edges = [(f[0], f[1], f[2]), (f[1], f[2], f[0]), (f[2], f[0], f[1])]
            a, b, m = max(
                edges, key=lambda e: float(np.linalg.norm(verts[e[1]] - verts[e[0]]))
            )
            key = tuple(sorted((a, b)))
            partners = [j for j in edge_map.get(key, []) if j != i]
            if len(partners) != 1:
                continue
            j = partners[0]
            g = faces[j]
            # edge_map can be stale: an earlier flip in this round may have
            # rewritten faces[j]. Skip and retry next round with a fresh map.
            if a not in g or b not in g:
                continue
            others = [v for v in g if v not in (a, b)]
            if len(others) != 1:
                continue
            x = others[0]
            # orientation of the neighbour determines the two replacements
            if (g[(g.index(a) + 1) % 3]) == b:
                faces[j] = [a, b, x]  # canonicalised below by the split
                faces[i] = [a, m, x]
                faces[j] = [m, b, x]
            else:
                faces[i] = [b, m, x]
                faces[j] = [m, a, x]
            fixed_any = True
        if not fixed_any:
            break
    return faces


def validate_mesh(mesh: trimesh.Trimesh, analytic_mm3: float, label: str) -> float:
    """Mandatory §8.2 integrity gate. Returns |mesh volume - analytic|."""
    if len(mesh.faces) == 0:
        raise IntegrityError(f"{label}: mesh vuota")
    if not mesh.is_watertight:
        raise IntegrityError(f"{label}: mesh non watertight")
    if not mesh.is_winding_consistent:
        raise IntegrityError(f"{label}: winding delle facce incoerente")
    if mesh.volume <= 0.0:
        raise IntegrityError(f"{label}: volume non positivo ({mesh.volume:.6f})")
    areas = mesh.area_faces
    if len(areas) and float(areas.min()) <= 0.0:
        raise IntegrityError(f"{label}: facce degeneri presenti")
    # Prismatic construction: every facet is horizontal or vertical, so the
    # 45-degree downward-overhang rule (§8.3) is satisfied by construction —
    # the only down-facing horizontal ceilings are the NFC pocket (intentional,
    # explicitly excluded by the spec) and engraved back faces.
    nz = np.abs(mesh.face_normals[:, 2])
    if not bool(np.all((nz < 1e-6) | (nz > 1.0 - 1e-6))):
        raise IntegrityError(f"{label}: facce non prismatiche inattese (sbalzi)")
    delta = abs(float(mesh.volume) - analytic_mm3)
    if delta >= C.VOLUME_TOL_MM3:
        raise IntegrityError(
            f"{label}: scarto volume mesh/analitico {delta:.6f} mm3 "
            f">= {C.VOLUME_TOL_MM3} (possibile cavità con normali invertite)"
        )
    return delta


def plate_offsets(n: int, bbox_x: float, bbox_y: float, spacing: float) -> list[tuple[float, float]]:
    """Grid as square as possible, pitch = piece bbox + spacing, centered on
    the origin (decisions §2)."""
    cols = int(math.ceil(math.sqrt(n)))
    rows = int(math.ceil(n / cols))
    px = bbox_x + spacing
    py = bbox_y + spacing
    offsets = []
    for i in range(n):
        r, c = divmod(i, cols)
        offsets.append((c * px, r * py))
    xs = [o[0] for o in offsets]
    ys = [o[1] for o in offsets]
    cx = (min(xs) + max(xs)) / 2.0
    cy = (min(ys) + max(ys)) / 2.0
    return [(round(x - cx, 6), round(y - cy, 6)) for x, y in offsets]


def replicate(mesh: trimesh.Trimesh, offsets: list[tuple[float, float]]) -> trimesh.Trimesh:
    if len(offsets) == 1 and offsets[0] == (0.0, 0.0):
        return mesh
    copies = []
    for dx, dy in offsets:
        m = mesh.copy()
        m.apply_translation((dx, dy, 0.0))
        copies.append(m)
    return trimesh.util.concatenate(copies)
