"""Outcome partition of the engine (contract 03 §5, decisions doc §3).

- UserParameterError  -> exit 2, human-readable Italian message on stderr,
                         shown to the user verbatim, with how to get back
                         within the limits. No STL is produced.
- IntegrityError      -> exit 3 (internal), logged and never exposed.
- Artwork printability issues are NOT exceptions: exit 0 + PRINTABILITY=...
"""

from __future__ import annotations

EXIT_OK = 0
EXIT_USER = 2
EXIT_INTERNAL = 3


class UserParameterError(Exception):
    """Parametric/dimensional user error, verifiable before building geometry."""


class IntegrityError(Exception):
    """Mesh integrity failure (spec §8.2) or any other internal fault."""
