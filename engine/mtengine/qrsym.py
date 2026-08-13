"""QR symbol handling: version prediction, forced byte-mode encoding, sizing,
analytic module rectangles and geometric decode (spec §2.3, §3.2, §3.4, §8.2).

Library choice: segno — it lets us FORCE version, error-correction level and
mask, in byte mode without segmentation, and read them back from the produced
symbol; the geometric re-read of §8.2 is done with OpenCV's QRCodeDetector on
a rasterisation of the exported face geometry.
"""

from __future__ import annotations

import math
from dataclasses import dataclass

import cv2
import numpy as np
import segno
from shapely.geometry import MultiPolygon, Polygon, box
from shapely.ops import unary_union

from . import constants as C
from .errors import IntegrityError, UserParameterError
from .layers import tol_floor


def byte_mode_version(data: str, ec: str) -> int:
    """Minimal byte-mode version for `data` at EC level `ec`.

    Same rule and same ISO table as the PHP side (config product.qr.byte_capacity):
    capacity is in characters of the UTF-8 encoded payload, no segmentation.
    """
    payload_len = len(data.encode("utf-8"))
    for version in sorted(C.QR_BYTE_CAPACITY[ec]):
        if payload_len <= C.QR_BYTE_CAPACITY[ec][version]:
            return version
    raise UserParameterError(
        f"Il contenuto del QR è troppo lungo ({payload_len} byte): con correzione "
        f"{ec} il massimo gestito è {C.QR_BYTE_CAPACITY[ec][20]} byte (versione 20). "
        "Accorcia l'URL, ad esempio con un dominio più corto o un percorso breve."
    )


def modules_for_version(version: int) -> int:
    return 17 + 4 * version


def size_min_qr(data: str, ec: str, shape: str) -> float:
    """Product minimum size for a QR face: max(shape floor, pitch floor formula).

    Uses the NOMINAL size and the product minimum pitch of 1.2 mm
    (policy, independent from the nozzle — decisions §4).
    """
    n = modules_for_version(byte_mode_version(data, ec))
    if shape == "square":
        raw = C.QR_MIN_PITCH_MM * (n + 8)
        floor = C.QR_FLOOR_SQUARE_MM
    else:
        raw = C.QR_MIN_PITCH_MM * (n * math.sqrt(2.0) + 8)
        floor = C.QR_FLOOR_CIRCLE_MM
    # round UP to 0.1 mm like the published floors
    raw = math.ceil(raw * 10.0 - C.TOL) / 10.0
    return max(raw, floor)


@dataclass
class QrSymbol:
    data: str
    version: int
    ec: str
    mask: int
    n_modules: int
    pitch_mm: float
    span_mm: float               # n_modules * pitch
    matrix: list[list[bool]]
    modules: MultiPolygon        # dilated analytic rectangles, centered at (0,0)
    logo_shape: MultiPolygon | None = None
    dropped_modules: int = 0


def _auto_pitch(content_size: float, n: int, shape: str) -> float:
    """Available pitch from the auto-margin formulas of spec §3.4."""
    if shape == "square":
        return content_size / (n + 8)
    # circle: solve qr_side*sqrt(2) + 8*qr_side/n = diameter, pitch = qr_side/n
    return content_size / (n * math.sqrt(2.0) + 8)


def _fixed_margin_pitch(content_size: float, margin: float, n: int, shape: str) -> float:
    if shape == "square":
        return (content_size - 2.0 * margin) / n
    # circle: the symbol square is inscribed on the diagonal
    return (content_size - 2.0 * margin) / (n * math.sqrt(2.0))


def build_qr_symbol(
    data: str,
    ec: str,
    shape: str,
    content_size: float,
    nozzle: float,
    margin: float | None,
    warnings: list[str],
) -> QrSymbol:
    """Encode `data` at the minimal byte-mode version, size the module pitch on
    the extrusion-width lattice (explicit 1e-9 tolerance) and produce the
    dilated analytic module rectangles centered at the face origin."""
    version = byte_mode_version(data, ec)
    n = modules_for_version(version)

    raw_pitch = (
        _auto_pitch(content_size, n, shape)
        if margin is None
        else _fixed_margin_pitch(content_size, margin, n, shape)
    )
    # Align DOWN to an integer multiple of the extrusion width (= nozzle),
    # with the explicit tolerance: floor(1.2/0.4) must give 3 (spec §8.3).
    passes = tol_floor(raw_pitch, nozzle)
    pitch = round(passes * nozzle, 9)
    if pitch + C.TOL < C.QR_MIN_PITCH_MM:
        raise UserParameterError(
            f"Con questi parametri il passo del modulo QR risulterebbe {pitch:.2f} mm, "
            f"sotto il minimo di prodotto di {C.QR_MIN_PITCH_MM:.1f} mm. "
            "Aumenta la dimensione del pezzo o riduci il margine imposto "
            "(oppure lascia il margine in 'auto')."
        )
    if margin is not None and margin + C.TOL < C.QR_QUIET_ZONE_MODULES * pitch:
        warnings.append(
            f"Il margine imposto di {margin:.2f} mm è sotto la quiet zone di 4 moduli "
            f"({C.QR_QUIET_ZONE_MODULES * pitch:.2f} mm): la scansione può risentirne. "
            "Consigliato il margine 'auto'."
        )

    # Byte mode FORCED, no segmentation, EC never boosted, version forced.
    qr = segno.make(
        data, error=ec.lower(), mode="byte", version=version, boost_error=False
    )
    # Re-read version / EC / mask from the produced symbol (spec §2.3).
    if int(qr.version) != version or str(qr.error).upper() != ec.upper():
        raise IntegrityError(
            f"il simbolo QR riletto non corrisponde: atteso v{version}/{ec}, "
            f"ottenuto v{qr.version}/{qr.error}"
        )
    matrix = [[bool(v) for v in row] for row in qr.matrix]
    if len(matrix) != n:
        raise IntegrityError(f"matrice QR di {len(matrix)} moduli, attesi {n}")

    span = n * pitch
    half = span / 2.0
    d = C.QR_MODULE_DILATION_MM  # analytic dilation: rectangles, never rasters
    rects: list[Polygon] = []
    for r, row in enumerate(matrix):
        for c_, dark in enumerate(row):
            if not dark:
                continue
            x0 = -half + c_ * pitch - d
            x1 = -half + (c_ + 1) * pitch + d
            y1 = half - r * pitch + d
            y0 = half - (r + 1) * pitch - d
            rects.append(box(round(x0, 6), round(y0, 6), round(x1, 6), round(y1, 6)))
    merged = unary_union(rects)
    if isinstance(merged, Polygon):
        merged = MultiPolygon([merged])

    return QrSymbol(
        data=data, version=version, ec=str(qr.error).upper(), mask=int(qr.mask),
        n_modules=n, pitch_mm=pitch, span_mm=span, matrix=matrix, modules=merged,
    )


def carve_logo_window(sym: QrSymbol, logo: MultiPolygon, channel_mm: float) -> QrSymbol:
    """Drop every module that intersects the logo dilated by the light guard
    channel (>= 1.2 passes, spec §8.3). Whole modules are removed — no partial
    slivers below the legibility threshold."""
    guard = logo.buffer(channel_mm, quad_segs=8)
    kept: list[Polygon] = []
    dropped = 0
    # Work on the raw rectangles (rebuilt from the matrix): dropping WHOLE
    # modules keeps every survivor analytic — no partial slivers.
    half = sym.span_mm / 2.0
    d = C.QR_MODULE_DILATION_MM
    for r, row in enumerate(sym.matrix):
        for c_, dark in enumerate(row):
            if not dark:
                continue
            x0 = -half + c_ * sym.pitch_mm - d
            x1 = -half + (c_ + 1) * sym.pitch_mm + d
            y1 = half - r * sym.pitch_mm + d
            y0 = half - (r + 1) * sym.pitch_mm - d
            rect = box(round(x0, 6), round(y0, 6), round(x1, 6), round(y1, 6))
            if rect.intersects(guard):
                dropped += 1
            else:
                kept.append(rect)
    merged = unary_union(kept)
    if isinstance(merged, Polygon):
        merged = MultiPolygon([merged])
    sym.modules = merged
    sym.logo_shape = logo
    sym.dropped_modules = dropped
    return sym


def rasterize_and_decode(
    artwork: MultiPolygon,
    expected: str,
    pitch_mm: float,
    mirrored: bool,
) -> bool:
    """§8.2: decode the QR from the produced geometry by rasterising the face.

    Dark = artwork (modules + optional central logo). The back face was
    mirrored in geometry, so the raster is flipped horizontally to read it
    the way a phone would (looking at the back of the piece).
    """
    px_per_mm = max(4.0, 12.0 / pitch_mm)  # >= 12 px per module
    minx, miny, maxx, maxy = artwork.bounds
    quiet = 4.0 * pitch_mm
    w = int(math.ceil((maxx - minx + 2 * quiet) * px_per_mm))
    h = int(math.ceil((maxy - miny + 2 * quiet) * px_per_mm))
    img = np.full((h, w), 255, np.uint8)

    def to_px(coords: np.ndarray) -> np.ndarray:
        xs = (coords[:, 0] - minx + quiet) * px_per_mm
        ys = (maxy + quiet - coords[:, 1]) * px_per_mm  # flip Y: image rows go down
        return np.stack([xs, ys], axis=1).astype(np.int32)

    polys = sorted(artwork.geoms, key=lambda p: p.area, reverse=True)
    for poly in polys:
        cv2.fillPoly(img, [to_px(np.asarray(poly.exterior.coords))], 0)
        for ring in poly.interiors:
            cv2.fillPoly(img, [to_px(np.asarray(ring.coords))], 255)

    if mirrored:
        img = cv2.flip(img, 1)

    detector = cv2.QRCodeDetector()
    decoded, _, _ = detector.detectAndDecode(img)
    if decoded == expected:
        return True
    # second chance: some detectors prefer a slight blur of hard edges
    blurred = cv2.GaussianBlur(img, (3, 3), 0)
    decoded, _, _ = detector.detectAndDecode(blurred)
    return decoded == expected
