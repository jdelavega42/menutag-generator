#!/usr/bin/env python3
"""MenuTag geometric engine — CLI entry point (contract docs/contracts/03).

Laravel orchestrates, Python computes. Run with the project virtualenv:
engine/.venv/bin/python3 engine/menutag.py --shape square --size 58.8 ...
"""

from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from mtengine.cli import main  # noqa: E402

if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
