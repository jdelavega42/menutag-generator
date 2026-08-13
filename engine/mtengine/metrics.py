"""Printability metrics (spec §8.4): morphological opening on the SOLID and
on its COMPLEMENT, residue after the first perimeter with the median band
width. Artwork issues never abort the run: they surface as PRINTABILITY=
ok|warn|blocked plus WARNING lines (decisions §3).
"""

from __future__ import annotations

import statistics
from dataclasses import dataclass

from shapely.geometry import MultiPolygon, Polygon

from . import constants as C

# Buffers cannot resolve the 1e-9 comparison tolerance, so the "strictly
# narrower than the threshold" semantics uses a 1e-5 mm shave — far below any
# physically meaningful width. Width measurements ignore slivers thinner than
# 0.05 mm (the same chord budget of §8.6): the diagonal-corner pinches created
# by the 0.005 mm module dilation are real but irrelevant to the metric.
_EDGE_EPS = 1e-5
_MEASURE_RES = 0.05


def _opening(geom, width: float):
    """Morphological opening with a SQUARE structuring element (mitre joins):
    a disc element would count the corner rounding of every QR module as lost
    area (~2 % on a perfectly clean symbol), which is not what the §8.4 metric
    measures — feature width, not corner sharpness."""
    r = max(0.0, width / 2.0 - _EDGE_EPS)
    if r == 0.0 or geom.is_empty:
        return geom
    return geom.buffer(-r, join_style="mitre", mitre_limit=4.0).buffer(
        r, join_style="mitre", mitre_limit=4.0
    )


def area_loss_pct(geom, width: float) -> float:
    if geom is None or geom.is_empty or geom.area <= 0.0:
        return 0.0
    kept = _opening(geom, width).area
    return max(0.0, (geom.area - kept) / geom.area * 100.0)


def min_feature_width(geom, hi: float = 8.0) -> float | None:
    """Smallest structure width present in `geom`, by bisection on the
    morphological opening (resolution 0.05 mm)."""
    if geom is None or geom.is_empty:
        return None
    g = _opening(geom, _MEASURE_RES)
    if g.is_empty:
        return _MEASURE_RES
    area = g.area
    eps = max(1e-3, 1e-4 * area)

    def lost(width: float) -> float:
        return area - _opening(g, width).area

    if lost(hi) <= eps:
        return hi  # nothing thinner than the search ceiling
    lo = _MEASURE_RES
    if lost(lo) > eps:
        return lo
    for _ in range(18):
        mid = (lo + hi) / 2.0
        if lost(mid) > eps:
            hi = mid
        else:
            lo = mid
    return round(hi, 3)


@dataclass
class FaceMetrics:
    feature_min_mm: float | None
    feature_loss_pct: float
    void_min_mm: float | None
    void_loss_pct: float
    residue_pct: float
    residue_width_mm: float


def _components(geom) -> list[Polygon]:
    if geom.is_empty:
        return []
    if isinstance(geom, Polygon):
        return [geom]
    return [g for g in geom.geoms if isinstance(g, Polygon) and not g.is_empty]


def perimeter_residue(art, nozzle: float) -> tuple[float, float]:
    """Residue after ONE perimeter line (erosion by the extrusion width):
    the share of artwork area left in bands strictly narrower than one pass,
    plus the median band width (ribbon estimate w = 2A/P)."""
    if art is None or art.is_empty or art.area <= 0.0:
        return 0.0, 0.0
    residue = art.buffer(-(nozzle - _EDGE_EPS), join_style="mitre", mitre_limit=4.0)
    if residue.is_empty:
        return 0.0, 0.0
    thin = residue.difference(_opening(residue, nozzle))
    pct = max(0.0, thin.area / art.area * 100.0)
    source = thin if not thin.is_empty else residue
    widths = []
    for comp in _components(source):
        per = comp.length
        if per > 0:
            widths.append(2.0 * comp.area / per)
    width = statistics.median(widths) if widths else 0.0
    return pct, round(width, 3)


def face_metrics(art, window, nozzle: float, parts) -> FaceMetrics:
    """Metrics for one face. `parts` is a list of (geometry, blocking
    threshold) pairs, one per artwork component: QR modules keep the §3.2
    product floor (2x nozzle) even in inlay (§3.6), while logo strokes climb
    to 4x nozzle in inlay. Voids and perimeter residue are measured on the
    whole face art; voids must host 2 base passes (2x nozzle)."""
    void_threshold = 2.0 * nozzle
    complement = window.difference(art) if window is not None else None
    residue_pct, residue_w = perimeter_residue(art, nozzle)

    feature_min: float | None = None
    lost_area = 0.0
    total_area = 0.0
    for part, threshold in parts:
        if part is None or part.is_empty:
            continue
        width = min_feature_width(part)
        if width is not None:
            feature_min = width if feature_min is None else min(feature_min, width)
        lost_area += part.area * area_loss_pct(part, threshold) / 100.0
        total_area += part.area
    feature_loss = (lost_area / total_area * 100.0) if total_area > 0.0 else 0.0

    return FaceMetrics(
        feature_min_mm=feature_min,
        feature_loss_pct=round(feature_loss, 3),
        void_min_mm=min_feature_width(complement) if complement is not None else None,
        void_loss_pct=round(
            (max(0.0, (complement.area - _opening(complement, void_threshold).area))
             / art.area * 100.0) if (complement is not None and art.area > 0) else 0.0,
            3,
        ),
        residue_pct=round(residue_pct, 3),
        residue_width_mm=residue_w,
    )


def printability(loss_pct: float, qr_all_decoded: bool | None) -> str:
    """Decisions §3: warn above 2 % of at-risk area, blocked above 10 % or
    when the QR does not decode."""
    if qr_all_decoded is False:
        return "blocked"
    if loss_pct > C.LOSS_BLOCK_PCT:
        return "blocked"
    if loss_pct > C.LOSS_WARN_PCT:
        return "warn"
    return "ok"
