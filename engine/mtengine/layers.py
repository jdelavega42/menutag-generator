"""Layer-grid arithmetic with the explicit 1e-9 tolerance (contract 03 §3).

Every critical height must satisfy (z - first_layer) / layer_height == integer
within TOL: engraving floor of each face, NFC pocket floor and ceiling, recess
floor, top of the piece. floor(1.2 / 0.4) MUST give 3.
"""

from __future__ import annotations

import math
from dataclasses import dataclass

from .constants import TOL


def tol_floor(a: float, b: float) -> int:
    """floor(a / b) with the explicit 1e-9 tolerance on the quotient."""
    return int(math.floor(a / b + TOL))


def tol_ceil(a: float, b: float) -> int:
    """ceil(a / b) with the explicit 1e-9 tolerance on the quotient."""
    return int(math.ceil(a / b - TOL))


def tol_round_int(a: float, b: float) -> int:
    """round(a / b) to the nearest integer."""
    return int(round(a / b))


def is_multiple(a: float, b: float) -> bool:
    """True when a is an integer multiple of b within TOL."""
    q = a / b
    return abs(q - round(q)) <= TOL * max(1.0, abs(q))


@dataclass(frozen=True)
class LayerGrid:
    """The Z lattice of the print: first layer FL, then steps of L."""

    first_layer: float
    layer_height: float

    def on_grid(self, z: float) -> bool:
        return is_multiple(z - self.first_layer, self.layer_height)

    def snap_down(self, z: float) -> float:
        n = tol_floor(z - self.first_layer, self.layer_height)
        return self.first_layer + n * self.layer_height

    def snap_nearest(self, z: float) -> float:
        n = tol_round_int(z - self.first_layer, self.layer_height)
        return self.first_layer + n * self.layer_height

    def layer_of(self, z: float) -> int:
        """1-based layer index whose TOP is at height z (z must be on grid)."""
        return 1 + tol_round_int(z - self.first_layer, self.layer_height)

    def layer_count(self, top: float) -> int:
        return self.layer_of(top)
