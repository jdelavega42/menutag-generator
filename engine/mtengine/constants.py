"""Domain constants for the MenuTag engine.

SOURCE OF TRUTH: config/product.php and config/printers.php in the Laravel
application (contract docs/contracts/05-configurazione.md). The values below
are a documented REPLICA of that single table: any change there must be
mirrored here (parity is covered by the WS-6 boundary tests). No domain
constant may live anywhere else in engine/ code.
"""

from __future__ import annotations

# ---------------------------------------------------------------------------
# Numerical policy (contract 03 §3 / spec §8.3)
# ---------------------------------------------------------------------------
# Explicit tolerance on EVERY floating-point division and comparison used for
# layer-grid math and threshold checks: floor(1.2 / 0.4) must yield 3, not 2.
TOL = 1e-9
# 2D coordinates are quantised to this grid before meshing so that shared
# boundary vertices between slabs match bit-exactly (watertight by design).
COORD_QUANT_DECIMALS = 6
# Chord-error budget for arc discretisation and logo contour simplification
# (spec §8.6). NEVER applied to QR module outlines.
CHORD_TOL_MM = 0.05
# Mesh volume vs analytic volume acceptance (spec §8.2).
VOLUME_TOL_MM3 = 1e-3
# Indicative triangle budget (spec §8.6) — a safeguard, not a target.
TRIANGLE_SOFT_CAP = 200_000

# ---------------------------------------------------------------------------
# Product minimums / maximums — config('product.*')  (2 EUR coin reference)
# ---------------------------------------------------------------------------
SIZE_MIN_MM = 25.75
SIZE_MAX_MM = 200.0
THICKNESS_MIN_MM = 2.20
THICKNESS_MAX_MM = 20.0

# ---------------------------------------------------------------------------
# QR — config('product.qr')
# ---------------------------------------------------------------------------
QR_MIN_PITCH_MM = 1.2          # product policy: independent from the nozzle
QR_FLOOR_VERSION = 6           # v6 = 41 modules -> product floors below
QR_FLOOR_SQUARE_MM = 58.8      # min_pitch * (41 + 8)
QR_FLOOR_CIRCLE_MM = 79.2      # min_pitch * (41 * sqrt(2) + 8), rounded to 0.1
QR_QUIET_ZONE_MODULES = 4
QR_DEFAULT_EC = "H"
QR_MODULE_DILATION_MM = 0.005  # anti non-manifold dilation of every module
QR_LOGO_CHANNEL_PASSES = 1.2   # light channel between logo and modules, in passes
QR_DEMO_URL = "https://menu.example.it/demo"
# Engine choice (documented, not a product constant): side of the central logo
# box on qr_logo faces, as a fraction of the symbol span. At EC H the erased
# modules stay well below the ~30 % correction capacity; the geometric decode
# check (§8.2) is the final arbiter.
QR_LOGO_BOX_FRACTION = 0.24

# Byte-mode character capacity per ISO/IEC 18004, version 1..20 per EC level.
# Capacity in CHARACTERS (mode+count header already subtracted). Identical to
# config('product.qr.byte_capacity'); parity tested against segno at the
# 64/65-byte boundary (v7 -> v8).
QR_BYTE_CAPACITY: dict[str, dict[int, int]] = {
    "H": {1: 7, 2: 14, 3: 24, 4: 34, 5: 44, 6: 58, 7: 64, 8: 84, 9: 98,
          10: 119, 11: 137, 12: 155, 13: 177, 14: 194, 15: 220, 16: 250,
          17: 280, 18: 310, 19: 338, 20: 382},
    "Q": {1: 11, 2: 20, 3: 32, 4: 46, 5: 60, 6: 74, 7: 86, 8: 108, 9: 130,
          10: 151, 11: 177, 12: 203, 13: 241, 14: 258, 15: 292, 16: 322,
          17: 364, 18: 394, 19: 442, 20: 482},
    "M": {1: 14, 2: 26, 3: 42, 4: 62, 5: 84, 6: 106, 7: 122, 8: 152, 9: 180,
          10: 213, 11: 251, 12: 287, 13: 331, 14: 362, 15: 412, 16: 450,
          17: 504, 18: 560, 19: 624, 20: 666},
    "L": {1: 17, 2: 32, 3: 53, 4: 78, 5: 106, 6: 134, 7: 154, 8: 192, 9: 230,
          10: 271, 11: 321, 12: 367, 13: 425, 14: 458, 15: 520, 16: 586,
          17: 644, 18: 718, 19: 792, 20: 858},
}

# ---------------------------------------------------------------------------
# NFC pocket — config('product.nfc')
# ---------------------------------------------------------------------------
# Declared axial-clearance choice (spec §3.3): the pocket is 0.20 mm deeper
# than the tag, so the closing layer crosses 0.20 mm of air. Cost: one
# irregular, invisible internal layer. The rejected alternative — the nozzle
# slamming on a tag thicker than declared — costs the whole print.
NFC_RADIAL_CLEARANCE_MM = 0.20
NFC_AXIAL_CLEARANCE_MM = 0.20
NFC_RADIAL_WALL_MIN_MM = 1.50
NFC_AXIAL_WALL_MIN_MM = 0.40
NFC_AXIAL_WALL_MIN_LAYERS = 2
NFC_TAG_THICKNESS_DEFAULT_MM = 0.80
NFC_TAG_THICKNESS_RANGE_MM = (0.30, 1.60)
NFC_TAG_DIAMETERS = (22, 25)

# ---------------------------------------------------------------------------
# Graphics — config('product.graphics')
# ---------------------------------------------------------------------------
DEPTH_MIN_MM = 0.2
DEPTH_MAX_MM = 2.0
CORE_MIN_MM = 1.0
CORE_MIN_LAYERS = 4

# ---------------------------------------------------------------------------
# Detail thresholds (nozzle multipliers) — config('product.detail')
# ---------------------------------------------------------------------------
DETAIL_EXIST_X = 1
DETAIL_LEGIBLE_X = 2       # blocking legibility threshold
DETAIL_FULL_X = 3          # recommended full-quality threshold
DETAIL_INLAY_X = 4         # inlay: base wall + accent fill
DETAIL_INLAY_VOID_PASSES = 2
LOSS_WARN_PCT = 2.0
LOSS_BLOCK_PCT = 10.0

# ---------------------------------------------------------------------------
# Materials — config('product.materials')
# ---------------------------------------------------------------------------
MATERIAL_DENSITY_G_CM3 = {"pla-matte": 1.24, "petg": 1.27}

# ---------------------------------------------------------------------------
# Rimmed profile (spec §3.5) — rim must span >= 3 nozzle passes
# ---------------------------------------------------------------------------
RIM_MIN_PASSES = 3

# ---------------------------------------------------------------------------
# Plate / XY compensation extensions — config('product.plate', 'product.xy_comp_range_mm')
# ---------------------------------------------------------------------------
PLATE_MAX_PIECES = 100
XY_COMP_RANGE_MM = (-0.30, 0.30)

# ---------------------------------------------------------------------------
# Printer profiles — config('printers.php')
# ---------------------------------------------------------------------------
PRINTERS: dict[str, dict] = {
    "a1mini": {
        "name": "Bambu Lab A1 mini",
        "bed_mm": {"x": 180.0, "y": 180.0, "z": 180.0},
        "bed_warn_mm": 175.0,   # margin for brim and skirt
        "plate_spacing_mm": 5.0,
        "nozzles": {
            "0.2": {"layer_min": 0.05, "layer_max": 0.15,
                    "layer_default": 0.10, "first_layer": 0.15},
            "0.4": {"layer_min": 0.08, "layer_max": 0.30,
                    "layer_default": 0.10, "first_layer": 0.20},
        },
    },
}
DEFAULT_PRINTER = "a1mini"
