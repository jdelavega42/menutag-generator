"""Engine orchestrator: parse -> validate -> build -> mesh -> verify -> export
-> report. Outcome partition (contract 03 §5, decisions §3):

- parametric user error   -> exit 2, Italian message on stderr
- mesh integrity failure  -> exit 3, logged (stderr), never shown to users
- artwork printability    -> exit 0 + PRINTABILITY=ok|warn|blocked + WARNING=
"""

from __future__ import annotations

import math
import os
import sys
import traceback

import trimesh

from . import constants as C
from .errors import EXIT_INTERNAL, EXIT_OK, EXIT_USER, IntegrityError, UserParameterError
from .meshing import mesh_from_slices, plate_offsets, replicate, validate_mesh
from .metrics import face_metrics, printability
from .params import Params, parse_args
from .qrsym import rasterize_and_decode, size_min_qr
from .solid import Model, build_model
from .validation import resolved_layer, validate, validate_plate_bbox


def _fmt(x: float, decimals: int = 4) -> str:
    s = f"{x:.{decimals}f}".rstrip("0")
    if s.endswith("."):
        s += "0"
    if s in ("-0.0", "-0"):
        s = "0.0"
    return s


def _size_min_functional(params: Params) -> float:
    """Functional minimum for the CURRENT content; the product minimum when
    there is no content (contract 03 §4)."""
    result = C.SIZE_MIN_MM
    for content, data in ((params.front, params.qr_data_front),
                          (params.back, params.qr_data_back)):
        if content in ("qr", "qr_logo") and data:
            result = max(result, size_min_qr(data, params.qr_ec, params.shape))
    if params.nfc:
        plan_min = (params.tag + 2.0 * C.NFC_RADIAL_CLEARANCE_MM
                    + 2.0 * C.NFC_RADIAL_WALL_MIN_MM)
        # V8 works on the effective size: translate back to the nominal one
        result = max(result, plan_min - 2.0 * params.xy_comp)
    return round(result, 2)


def run(argv: list[str]) -> int:
    params = parse_args(argv)
    warnings: list[str] = []

    validate(params, warnings)
    layer, first_layer = resolved_layer(params)

    model: Model = build_model(params, warnings)

    # --- plate bbox check BEFORE meshing (parametric, exit 2) ----------------
    minx, miny, maxx, maxy = model.silhouette.bounds
    piece_x, piece_y = maxx - minx, maxy - miny
    spacing = C.PRINTERS[params.printer]["plate_spacing_mm"]
    offsets = plate_offsets(params.plate, piece_x, piece_y, spacing)
    xs = [o[0] for o in offsets]
    ys = [o[1] for o in offsets]
    plate_x = (max(xs) - min(xs)) + piece_x
    plate_y = (max(ys) - min(ys)) + piece_y
    validate_plate_bbox(params, plate_x, plate_y, warnings)

    # --- meshing + §8.2 integrity gate ---------------------------------------
    base_mesh = mesh_from_slices(model.silhouette, model.base_slices)
    delta_base = validate_mesh(base_mesh, model.analytic_base_mm3, "corpo base")
    if len(base_mesh.faces) * params.plate > C.TRIANGLE_SOFT_CAP:
        warnings.append(
            f"La piastra supera il tetto indicativo di {C.TRIANGLE_SOFT_CAP} "
            "triangoli: file STL voluminoso ma valido."
        )

    accent_mesh = None
    delta_accent = 0.0
    if params.mode == "inlay":
        accent_mesh = mesh_from_slices(model.silhouette, model.accent_slices)
        delta_accent = validate_mesh(accent_mesh, model.analytic_accent_mm3, "accento")
        delta_sum = abs(
            (base_mesh.volume + accent_mesh.volume) - model.analytic_full_mm3
        )
        if delta_sum >= C.VOLUME_TOL_MM3:
            raise IntegrityError(
                f"inlay: somma volumi base+accento fuori dal solido pieno di "
                f"{delta_sum:.6f} mm3 (complanarità/contatto non esatti)"
            )

    # --- printability metrics + geometric QR decode (§8.2, §8.4) -------------
    has_art = any(f.art is not None for f in model.faces.values())
    qr_present = False
    qr_all_decoded: bool | None = None
    feature_min = None
    void_min = None
    feature_loss = 0.0
    void_loss = 0.0
    residue_pct = 0.0
    residue_width = 0.0
    qr_face = None
    for face in model.faces.values():
        if face.art is None:
            continue
        # Per-component thresholds (§3.6): QR modules keep the §3.2 product
        # floor (2x nozzle) even in inlay; logo strokes climb to 4x in inlay.
        logo_mult = C.DETAIL_INLAY_X if params.mode == "inlay" else C.DETAIL_LEGIBLE_X
        parts = []
        if face.qr is not None:
            parts.append((face.qr.modules, C.DETAIL_LEGIBLE_X * params.nozzle_mm))
        if face.logo_art is not None:
            parts.append((face.logo_art, logo_mult * params.nozzle_mm))
        if not parts:
            parts = [(face.art, logo_mult * params.nozzle_mm)]
        fm = face_metrics(face.art, face.window, params.nozzle_mm, parts)
        if fm.feature_min_mm is not None:
            feature_min = fm.feature_min_mm if feature_min is None else min(feature_min, fm.feature_min_mm)
        if fm.void_min_mm is not None:
            void_min = fm.void_min_mm if void_min is None else min(void_min, fm.void_min_mm)
        feature_loss = max(feature_loss, fm.feature_loss_pct)
        void_loss = max(void_loss, fm.void_loss_pct)
        if fm.residue_pct >= residue_pct:
            residue_pct = fm.residue_pct
            residue_width = fm.residue_width_mm
        if face.qr is not None:
            qr_present = True
            if qr_face is None or face.name == "front":
                qr_face = face
            ok = rasterize_and_decode(
                face.art, face.qr.data, face.qr.pitch_mm, mirrored=face.mirrored
            )
            qr_all_decoded = ok if qr_all_decoded is None else (qr_all_decoded and ok)

    # Warnings only above a perceptible floor: sub-0.05% rounding dust would
    # print as "0.0%" and dilute the real alerts.
    WARN_FLOOR_PCT = 0.05
    if residue_pct >= WARN_FLOOR_PCT:
        warnings.append(
            "Dopo il primo perimetro restano fasce più strette di una passata: "
            "stampare con almeno 2 perimetri e NON attivare 'only one wall' "
            "su questa faccia."
        )
    # Decisions §3: the 2 % / 10 % thresholds govern the whole §8.4 at-risk
    # area — morphological opening on solid and complement AND the residue
    # left after the first perimeter.
    loss_at_risk = max(feature_loss, void_loss, residue_pct)
    if feature_loss >= WARN_FLOOR_PCT:
        warnings.append(
            f"Apertura morfologica sul pieno: {_fmt(feature_loss, 2)}% dell'area "
            "sotto la soglia di leggibilità con l'ugello scelto."
        )
    if void_loss >= WARN_FLOOR_PCT:
        warnings.append(
            f"Apertura morfologica sul complemento: vuoti stretti pari al "
            f"{_fmt(void_loss, 2)}% dell'area della grafica rischiano di chiudersi."
        )
    if qr_all_decoded is False:
        warnings.append(
            "La decodifica del QR dalla geometria prodotta è FALLITA: il "
            "simbolo stampato potrebbe non essere scansionabile."
        )
    printability_value = printability(loss_at_risk, qr_all_decoded) if has_art else "ok"

    # --- plate replication + export ------------------------------------------
    plate_base = replicate(base_mesh, offsets)
    analytic_total = model.analytic_base_mm3 * params.plate
    plate_accent = None
    if accent_mesh is not None:
        plate_accent = replicate(accent_mesh, offsets)

    # center plate bbox on XY origin, piece(s) resting at Z=0 (§8.2)
    bmin, bmax = plate_base.bounds
    shift = (-(bmin[0] + bmax[0]) / 2.0, -(bmin[1] + bmax[1]) / 2.0, 0.0)
    plate_base.apply_translation(shift)
    if plate_accent is not None:
        plate_accent.apply_translation(shift)
    bmin, bmax = plate_base.bounds
    if plate_accent is not None:
        amin, amax = plate_accent.bounds
        bmin = [min(bmin[i], amin[i]) for i in range(3)]
        bmax = [max(bmax[i], amax[i]) for i in range(3)]
    bbox = (bmax[0] - bmin[0], bmax[1] - bmin[1], bmax[2] - bmin[2])

    plate_base.export(params.out)  # trimesh writes BINARY STL for .stl
    if plate_accent is not None and params.out_accent:
        plate_accent.export(params.out_accent)

    # --- §8.2 re-verified on the file as re-read, not only in memory ---------
    reread = trimesh.load(params.out, force="mesh")
    if not (reread.is_watertight and reread.is_winding_consistent and reread.volume > 0):
        raise IntegrityError("l'STL riletto da file non è watertight/manifold")
    delta_total = abs(float(reread.volume) - analytic_total)
    if delta_total >= C.VOLUME_TOL_MM3 * params.plate:
        raise IntegrityError(
            f"volume dell'STL riletto fuori tolleranza: scarto {delta_total:.6f} mm3"
        )
    if params.mode == "inlay" and params.out_accent:
        reread_acc = trimesh.load(params.out_accent, force="mesh")
        if not (reread_acc.is_watertight and reread_acc.is_winding_consistent
                and reread_acc.volume > 0):
            raise IntegrityError("l'STL accento riletto da file non è watertight/manifold")

    file_size_kb = max(1, math.ceil(os.path.getsize(params.out) / 1024))
    density = C.MATERIAL_DENSITY_G_CM3[params.material]
    total_volume = float(plate_base.volume) + (
        float(plate_accent.volume) if plate_accent is not None else 0.0
    )
    weight_g = total_volume / 1000.0 * density
    volume_delta = max(delta_base, delta_accent, delta_total / params.plate)

    # --- stdout report (contract 03 §4, key order of the contract table) -----
    lines: list[str] = []
    emit = lines.append
    emit("OK=1")
    emit(f"TRIANGLES={len(plate_base.faces)}")
    emit(f"VOLUME_MM3={_fmt(float(plate_base.volume))}")
    emit(f"WEIGHT_G={_fmt(weight_g, 2)}")
    emit(f"NOZZLE={params.nozzle}")
    emit(f"LAYER_HEIGHT={_fmt(layer)}")
    emit(f"FIRST_LAYER={_fmt(first_layer)}")
    if params.nfc and model.pause_z is not None:
        emit(f"PAUSE_Z={_fmt(model.pause_z)}")
        emit(f"PAUSE_LAYER={model.pause_layer}")
    emit(f"BBOX_X={_fmt(bbox[0], 2)}")
    emit(f"BBOX_Y={_fmt(bbox[1], 2)}")
    emit(f"BBOX_Z={_fmt(bbox[2], 2)}")
    emit(f"FILE_SIZE_KB={file_size_kb}")
    if qr_present and qr_face is not None and qr_face.qr is not None:
        emit(f"QR_VERSION={qr_face.qr.version}")
        emit(f"QR_EC={qr_face.qr.ec}")
        emit(f"QR_MODULES={qr_face.qr.n_modules}")
        emit(f"QR_PITCH_MM={_fmt(qr_face.qr.pitch_mm)}")
        emit(f"QR_DECODED={'yes' if qr_all_decoded else 'no'}")
    if has_art:
        if feature_min is not None:
            emit(f"FEATURE_MIN_MM={_fmt(feature_min, 3)}")
        emit(f"FEATURE_LOSS_PCT={_fmt(feature_loss, 3)}")
        if void_min is not None:
            emit(f"VOID_MIN_MM={_fmt(void_min, 3)}")
        emit(f"PERIMETER_RESIDUE_PCT={_fmt(residue_pct, 3)}")
        emit(f"PERIMETER_RESIDUE_WIDTH_MM={_fmt(residue_width, 3)}")
    emit(f"VOLUME_DELTA_MM3={_fmt(volume_delta, 6)}")
    emit(f"SIZE_MIN_FUNCTIONAL_MM={_fmt(_size_min_functional(params), 2)}")
    emit(f"RENDER_MODE={params.mode}")
    if params.mode == "inlay" and plate_accent is not None:
        emit(f"ACCENT_TRIANGLES={len(plate_accent.faces)}")
        emit(f"ACCENT_VOLUME_MM3={_fmt(float(plate_accent.volume))}")
        emit(f"BICOLOR_LAYERS={model.bicolor_layers}")
    if params.base_profile == "rimmed":
        emit(f"RIM_WIDTH={_fmt(params.rim_width)}")
        emit(f"RECESS_DEPTH={_fmt(model.recess_eff)}")
        emit(f"CAPACITY_ML={_fmt(model.capacity_ml, 2)}")
    emit(f"MATERIAL={params.material}")
    emit(f"PLATE={params.plate}")
    emit(f"XY_COMP_MM={_fmt(params.xy_comp, 2)}")
    emit(f"PRINTABILITY={printability_value}")
    for warning in warnings:
        emit(f"WARNING={warning.replace(chr(10), ' ')}")

    sys.stdout.write("\n".join(lines) + "\n")
    return EXIT_OK


def main(argv: list[str]) -> int:
    try:
        return run(argv)
    except UserParameterError as exc:
        sys.stderr.write(str(exc).rstrip() + "\n")
        return EXIT_USER
    except IntegrityError as exc:
        sys.stderr.write(f"Errore interno di integrità della mesh: {exc}\n")
        return EXIT_INTERNAL
    except SystemExit:
        raise
    except Exception:  # noqa: BLE001 — internal: logged by the caller, never shown
        sys.stderr.write("Errore interno del motore:\n")
        traceback.print_exc(file=sys.stderr)
        return EXIT_INTERNAL
