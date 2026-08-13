"""Parametric validation: the engine re-verifies V1..V12 of the DTO contract
(docs/contracts/02-dto-parametri.md) with the SAME formulas and the SAME
constants as the PHP side — defence in depth. Every violation is a user error:
exit 2 with an Italian, human-readable message that says how to get back
within the limits (contract 03 §5).
"""

from __future__ import annotations

import math
import os

from . import constants as C
from .errors import UserParameterError
from .params import Params
from .qrsym import byte_mode_version, modules_for_version, size_min_qr


def _fail(message: str) -> None:
    raise UserParameterError(message)


def resolved_layer(params: Params) -> tuple[float, float]:
    """(layer_height, first_layer) resolved from the printer profile."""
    printer = C.PRINTERS.get(params.printer)
    if printer is None:
        _fail(
            f"Stampante '{params.printer}' sconosciuta: i profili disponibili "
            f"sono {', '.join(sorted(C.PRINTERS))}. Usa --printer a1mini."
        )
    prof = printer["nozzles"].get(params.nozzle)
    if prof is None:
        _fail(
            f"Ugello {params.nozzle} non previsto dal profilo "
            f"{printer['name']}: valori ammessi 0.2 e 0.4."
        )
    layer = params.layer_height if params.layer_height is not None else prof["layer_default"]
    return layer, prof["first_layer"]


def engraved_sum(params: Params) -> float:
    if params.mode not in ("engrave", "inlay"):
        return 0.0
    return sum(
        params.depth for content in (params.front, params.back) if content != "none"
    )


def validate(params: Params, warnings: list[str]) -> None:
    layer, _first = resolved_layer(params)
    has_content = params.front != "none" or params.back != "none"

    # V1 — product minimums/maximums, on the NOMINAL size (decisions §2)
    if not (C.SIZE_MIN_MM - C.TOL <= params.size <= C.SIZE_MAX_MM + C.TOL):
        _fail(
            f"Dimensione {params.size:.2f} mm fuori dai limiti di prodotto: "
            f"ammessa tra {C.SIZE_MIN_MM:.2f} mm (moneta da 2 euro) e "
            f"{C.SIZE_MAX_MM:.0f} mm. Imposta --size in quell'intervallo."
        )
    if not (C.THICKNESS_MIN_MM - C.TOL <= params.thickness <= C.THICKNESS_MAX_MM + C.TOL):
        _fail(
            f"Spessore {params.thickness:.2f} mm fuori dai limiti: ammesso tra "
            f"{C.THICKNESS_MIN_MM:.2f} e {C.THICKNESS_MAX_MM:.0f} mm. "
            "Correggi --thickness."
        )

    # V2 — fillet
    if params.fillet > C.TOL and params.shape != "square":
        _fail("Lo smusso degli angoli (--fillet) vale solo per la forma quadrata: "
              "rimuovilo o passa a --shape square.")
    if params.fillet < -C.TOL or params.fillet > params.size / 2.0 + C.TOL:
        _fail(
            f"Smusso {params.fillet:.2f} mm non valido: deve stare tra 0 e "
            f"metà lato ({params.size / 2.0:.2f} mm)."
        )

    # V3 — rimmed profile
    if params.base_profile != "rimmed" and (params.rim_width_given or params.recess_given):
        _fail("--rim-width e --recess-depth valgono solo con --base-profile rimmed.")
    if params.base_profile == "rimmed":
        min_rim = C.RIM_MIN_PASSES * params.nozzle_mm
        if params.rim_width + C.TOL < min_rim:
            _fail(
                f"Bordo antigoccia di {params.rim_width:.2f} mm troppo stretto: "
                f"servono almeno {C.RIM_MIN_PASSES} passate dell'ugello "
                f"({min_rim:.2f} mm con ugello {params.nozzle}). Aumenta --rim-width."
            )
        if params.recess_depth <= C.TOL:
            _fail("La profondità dell'incavo (--recess-depth) deve essere positiva.")

    # V4 — QR/logo coherence
    for face, content, data in (
        ("frontale", params.front, params.qr_data_front),
        ("posteriore", params.back, params.qr_data_back),
    ):
        if content in ("qr", "qr_logo") and not data:
            _fail(
                f"La faccia {face} è impostata su QR ma manca il contenuto: "
                f"passa --qr-data-{'front' if face == 'frontale' else 'back'} "
                "con l'indirizzo del menù."
            )
        if content not in ("qr", "qr_logo") and data:
            _fail(
                f"Contenuto QR fornito per la faccia {face} che però è "
                f"'{content}': rimuovi il dato o imposta la faccia su qr/qr_logo."
            )
    needs_logo = any(c in ("logo", "qr_logo") for c in (params.front, params.back))
    if needs_logo:
        if not params.logo:
            _fail("Le facce con logo richiedono --logo con un file PNG o SVG.")
        if not os.path.isfile(params.logo):
            _fail(
                f"File logo non trovato: {params.logo}. Controlla il percorso "
                "(deve essere assoluto e leggibile dal motore)."
            )

    # V5 — QR floor depending on shape and URL (NOMINAL size)
    for content, data in ((params.front, params.qr_data_front),
                          (params.back, params.qr_data_back)):
        if content not in ("qr", "qr_logo") or not data:
            continue
        min_size = size_min_qr(data, params.qr_ec, params.shape)
        if params.size + C.TOL < min_size:
            other_shape = "circle" if params.shape == "square" else "square"
            other_min = size_min_qr(data, params.qr_ec, other_shape)
            n = modules_for_version(byte_mode_version(data, params.qr_ec))
            side_txt = "di lato" if params.shape == "square" else "di diametro"
            other_txt = "di diametro" if params.shape == "square" else "di lato"
            _fail(
                f"Con questo indirizzo ({len(data.encode('utf-8'))} byte, QR da "
                f"{n} moduli a correzione {params.qr_ec}) il QR richiede almeno "
                f"{min_size:.1f} mm {side_txt}, oppure {other_min:.1f} mm "
                f"{other_txt}. Imposta --size ad almeno {min_size:.1f} "
                "o accorcia l'URL."
            )

    # V9 — graphic depth (checked before the budgets that consume it)
    if has_content and not (C.DEPTH_MIN_MM - C.TOL <= params.depth <= C.DEPTH_MAX_MM + C.TOL):
        _fail(
            f"Profondità grafica {params.depth:.2f} mm fuori range: ammessa tra "
            f"{C.DEPTH_MIN_MM:.1f} e {C.DEPTH_MAX_MM:.1f} mm. Correggi --depth."
        )
    if (params.base_profile == "rimmed" and params.mode == "relief" and has_content
            and not params.depth + C.TOL < params.recess_depth):
        _fail(
            f"In rilievo con bordo antigoccia la grafica ({params.depth:.2f} mm) "
            f"deve restare sotto il bordo: usa --depth < {params.recess_depth:.2f} "
            "o aumenta --recess-depth."
        )

    # V6 — thickness budget (residual core)
    carved = engraved_sum(params)
    recess = params.recess_depth if params.base_profile == "rimmed" else 0.0
    core = params.thickness - carved - recess
    core_min = max(C.CORE_MIN_MM, C.CORE_MIN_LAYERS * layer)
    if core + C.TOL < core_min:
        _fail(
            f"Nucleo residuo di {core:.2f} mm insufficiente: con incisioni per "
            f"{carved:.2f} mm"
            + (f" e incavo di {recess:.2f} mm" if recess else "")
            + f" servono almeno {core_min:.2f} mm di nucleo "
            f"({C.CORE_MIN_MM:.2f} mm e {C.CORE_MIN_LAYERS} layer). "
            f"Porta --thickness ad almeno {carved + recess + core_min:.2f} mm "
            "o riduci le profondità."
        )

    # V7 — minimum thickness with NFC (computed, never a constant — §3.3)
    if params.nfc:
        axial_wall = max(C.NFC_AXIAL_WALL_MIN_MM, C.NFC_AXIAL_WALL_MIN_LAYERS * layer)
        t_min = (params.tag_thickness + C.NFC_AXIAL_CLEARANCE_MM
                 + 2.0 * axial_wall + carved + recess)
        if params.thickness + C.TOL < t_min:
            _fail(
                f"Spessore {params.thickness:.2f} mm insufficiente per la tasca "
                f"NFC: il minimo calcolato è {t_min:.2f} mm "
                f"(tag {params.tag_thickness:.2f} + gioco assiale "
                f"{C.NFC_AXIAL_CLEARANCE_MM:.2f} + 2 pareti da {axial_wall:.2f}"
                + (f" + incisioni {carved:.2f}" if carved else "")
                + (f" + incavo {recess:.2f}" if recess else "")
                + f"). Imposta --thickness ad almeno {t_min:.2f} mm."
            )

        # V8 — minimum footprint, on the EFFECTIVE size (decisions §2)
        plan_min = (params.tag + 2.0 * C.NFC_RADIAL_CLEARANCE_MM
                    + 2.0 * C.NFC_RADIAL_WALL_MIN_MM)
        if params.effective_size + C.TOL < plan_min:
            _fail(
                f"Pianta insufficiente per il tag NFC da {params.tag} mm: con "
                f"gioco radiale {C.NFC_RADIAL_CLEARANCE_MM:.2f} e parete minima "
                f"{C.NFC_RADIAL_WALL_MIN_MM:.2f} servono {plan_min:.1f} mm "
                f"effettivi (dimensione effettiva attuale: "
                f"{params.effective_size:.2f} mm con compensazione XY "
                f"{params.xy_comp:+.2f}). Aumenta --size"
                + (" o scegli il tag da 22 mm." if params.tag == 25 else ".")
            )

    # V10 — QR with central logo forces EC H
    if any(c == "qr_logo" for c in (params.front, params.back)) and params.qr_ec != "H":
        _fail(
            "Il QR con logo centrale richiede la correzione d'errore H "
            "(il logo consuma capacità di correzione): imposta --qr-ec H."
        )

    # V11 — layer height within the nozzle range
    prof = C.PRINTERS[params.printer]["nozzles"][params.nozzle]
    if not (prof["layer_min"] - C.TOL <= layer <= prof["layer_max"] + C.TOL):
        _fail(
            f"Altezza layer {layer:.2f} mm fuori range per l'ugello "
            f"{params.nozzle}: ammessa tra {prof['layer_min']:.2f} e "
            f"{prof['layer_max']:.2f} mm. Correggi --layer-height."
        )

    # V12 — plate, XY compensation, tag thickness
    if not (1 <= params.plate <= C.PLATE_MAX_PIECES):
        _fail(
            f"Numero pezzi per piastra {params.plate} non valido: ammesso tra 1 "
            f"e {C.PLATE_MAX_PIECES}. Correggi --plate."
        )
    lo, hi = C.XY_COMP_RANGE_MM
    if not (lo - C.TOL <= params.xy_comp <= hi + C.TOL):
        _fail(
            f"Compensazione XY {params.xy_comp:+.2f} mm fuori range: ammessa tra "
            f"{lo:+.2f} e {hi:+.2f} mm per lato. Correggi --xy-comp."
        )
    tlo, thi = C.NFC_TAG_THICKNESS_RANGE_MM
    if not (tlo - C.TOL <= params.tag_thickness <= thi + C.TOL):
        _fail(
            f"Spessore tag {params.tag_thickness:.2f} mm fuori range: ammesso "
            f"tra {tlo:.2f} e {thi:.2f} mm. Correggi --tag-thickness."
        )

    # mode-specific output coherence (contract 03 §3)
    if params.mode == "inlay":
        if not params.out_accent:
            _fail("Con --mode inlay è obbligatorio --out-accent per l'STL "
                  "della parte a colore secondario.")
        if not has_content:
            _fail("Con --mode inlay serve almeno una faccia con grafica "
                  "(--front o --back diversi da none).")


def validate_plate_bbox(params: Params, bbox_x: float, bbox_y: float,
                        warnings: list[str]) -> None:
    """Bed check on the WHOLE plate bounding box: blocking above 180 mm,
    warning above 175 mm (printer profile, §8.2). A parametric user error per
    the outcome partition (decisions §3)."""
    printer = C.PRINTERS[params.printer]
    bed = printer["bed_mm"]
    warn = printer["bed_warn_mm"]
    if bbox_x > bed["x"] + C.TOL or bbox_y > bed["y"] + C.TOL:
        _fail(
            f"La piastra da {params.plate} pezzi misura {bbox_x:.1f} x "
            f"{bbox_y:.1f} mm e supera il piano di {printer['name']} "
            f"({bed['x']:.0f} x {bed['y']:.0f} mm). Riduci --plate o la "
            "dimensione del pezzo. (Il massimo di prodotto resta 200 mm, ma il "
            "piano disponibile della stampante scelta è più piccolo.)"
        )
    if bbox_x > warn + C.TOL or bbox_y > warn + C.TOL:
        warnings.append(
            f"La piastra misura {bbox_x:.1f} x {bbox_y:.1f} mm: oltre "
            f"{warn:.0f} mm resta poco margine per brim e skirt."
        )
