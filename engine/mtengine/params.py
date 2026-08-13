"""CLI contract of the engine (contract docs/contracts/03-motore-cli.md §3).

argparse errors are user errors: they exit 2 with an Italian message on
stderr, like every other parametric error.
"""

from __future__ import annotations

import argparse
import sys
from dataclasses import dataclass, field

from . import constants as C
from .errors import EXIT_USER


class ItalianArgumentParser(argparse.ArgumentParser):
    """argparse with Italian, exit-2 error reporting (contract 03 §5)."""

    def error(self, message: str) -> None:  # type: ignore[override]
        sys.stderr.write(
            "Parametri non validi: "
            f"{message}\n"
            "Controlla la sintassi dei parametri: esegui con --help per "
            "l'elenco completo delle opzioni e dei valori ammessi.\n"
        )
        sys.exit(EXIT_USER)


@dataclass
class Params:
    shape: str
    size: float
    fillet: float
    thickness: float
    base_profile: str
    rim_width: float
    recess_depth: float
    front: str
    back: str
    mode: str
    depth: float
    margin: float | None          # None == auto
    logo: str | None
    logo_rotate: float
    qr_data_front: str | None
    qr_data_back: str | None
    qr_ec: str
    nfc: bool
    tag: int
    tag_thickness: float
    nozzle: str
    layer_height: float | None
    printer: str
    material: str
    plate: int
    xy_comp: float
    out: str
    out_accent: str | None
    # whether the rimmed-only flags were explicitly present on the CLI
    rim_width_given: bool = False
    recess_given: bool = False
    # filled by validation
    warnings: list[str] = field(default_factory=list)

    @property
    def nozzle_mm(self) -> float:
        return float(self.nozzle)

    @property
    def effective_size(self) -> float:
        """size + 2 * xy_comp — used ONLY by the NFC pocket checks (V8):

        it measures the piece that actually leaves the printer. Product
        limits (V1) and QR floors (V5) use the NOMINAL size (decisions §2).
        """
        return self.size + 2.0 * self.xy_comp

    def faces(self) -> list[tuple[str, str]]:
        return [("front", self.front), ("back", self.back)]

    def engraved_depth_sum(self) -> float:
        """Sum of the engraved depths (mode engrave|inlay) over faces with content."""
        if self.mode not in ("engrave", "inlay"):
            return 0.0
        return sum(self.depth for _, content in self.faces() if content != "none")

    def has_qr(self, content: str) -> bool:
        return content in ("qr", "qr_logo")

    def has_logo(self, content: str) -> bool:
        return content in ("logo", "qr_logo")


def parse_args(argv: list[str]) -> Params:
    p = ItalianArgumentParser(
        prog="menutag.py",
        description="Motore geometrico MenuTag: genera STL binari per Bambu Lab A1 mini.",
    )
    p.add_argument("--shape", required=True, choices=["circle", "square"])
    p.add_argument("--size", required=True, type=float)
    p.add_argument("--fillet", type=float, default=0.0)
    p.add_argument("--thickness", type=float, default=4.0)
    p.add_argument("--base-profile", choices=["flat", "rimmed"], default="flat")
    p.add_argument("--rim-width", type=float, default=None)
    p.add_argument("--recess-depth", type=float, default=None)
    p.add_argument("--front", choices=["none", "logo", "qr", "qr_logo"], default="none")
    p.add_argument("--back", choices=["none", "logo", "qr", "qr_logo"], default="none")
    p.add_argument("--mode", choices=["engrave", "relief", "inlay"], default="engrave")
    p.add_argument("--depth", type=float, default=0.8)
    p.add_argument("--margin", default="auto")
    p.add_argument("--logo", default=None)
    p.add_argument("--logo-rotate", type=float, default=0.0)
    p.add_argument("--qr-data", default=None)
    p.add_argument("--qr-data-front", default=None)
    p.add_argument("--qr-data-back", default=None)
    p.add_argument("--qr-ec", choices=["L", "M", "Q", "H"], default="H")
    p.add_argument("--nfc", action="store_true")
    p.add_argument("--tag", type=int, choices=[22, 25], default=25)
    p.add_argument("--tag-thickness", type=float, default=C.NFC_TAG_THICKNESS_DEFAULT_MM)
    p.add_argument("--nozzle", choices=["0.2", "0.4"], default="0.4")
    p.add_argument("--layer-height", type=float, default=None)
    p.add_argument("--printer", default=C.DEFAULT_PRINTER)
    p.add_argument("--material", choices=["pla-matte", "petg"], default="pla-matte")
    p.add_argument("--plate", type=int, default=1)
    p.add_argument("--xy-comp", type=float, default=0.0)
    p.add_argument("--out", required=True)
    p.add_argument("--out-accent", default=None)
    ns = p.parse_args(argv)

    margin: float | None
    if isinstance(ns.margin, str) and ns.margin.strip().lower() == "auto":
        margin = None
    else:
        try:
            margin = float(ns.margin)
        except ValueError:
            p.error("--margin accetta un numero in millimetri oppure 'auto'")
            raise SystemExit(EXIT_USER)  # unreachable, keeps type-checkers happy

    # --qr-data is the shortcut for both faces; the per-face flags prevail.
    qr_front = ns.qr_data_front if ns.qr_data_front is not None else ns.qr_data
    qr_back = ns.qr_data_back if ns.qr_data_back is not None else ns.qr_data

    return Params(
        shape=ns.shape, size=ns.size, fillet=ns.fillet, thickness=ns.thickness,
        base_profile=ns.base_profile,
        rim_width=ns.rim_width if ns.rim_width is not None else 5.0,
        recess_depth=ns.recess_depth if ns.recess_depth is not None else 1.2,
        front=ns.front, back=ns.back, mode=ns.mode, depth=ns.depth,
        margin=margin, logo=ns.logo, logo_rotate=ns.logo_rotate,
        qr_data_front=qr_front, qr_data_back=qr_back, qr_ec=ns.qr_ec,
        nfc=ns.nfc, tag=ns.tag, tag_thickness=ns.tag_thickness,
        nozzle=ns.nozzle, layer_height=ns.layer_height, printer=ns.printer,
        material=ns.material, plate=ns.plate, xy_comp=ns.xy_comp,
        out=ns.out, out_accent=ns.out_accent,
        rim_width_given=ns.rim_width is not None,
        recess_given=ns.recess_depth is not None,
    )
