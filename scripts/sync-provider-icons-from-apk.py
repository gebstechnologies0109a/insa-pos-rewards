#!/usr/bin/env python3
"""Copy extracted ic_* drawables to ic_provider_{slug} for Android + web."""
from __future__ import annotations

import shutil
from pathlib import Path

DRAWABLE = Path(r"c:\laragon\www\ePay Plus\ePayPlus\app\src\main\res\drawable")
WEB = Path(r"c:\laragon\www\ePay Plus\public\images\providers")

# legacy ic_* base -> provider slug
LEGACY_TO_SLUG = {
    "ic_globe": "globe",
    "ic_globe_bill": "globe_bill",
    "ic_smart": "smart",
    "ic_smart_bill": "smart_bill",
    "ic_tnt": "tnt",
    "ic_dito": "dito",
    "ic_sun": "sun",
    "ic_sun": "sun",
    "ic_tm": "tm",
    "ic_gcash": "gcash",
    "ic_maya": "maya",
    "ic_meralco": "meralco",
    "ic_pldt": "pldt",
    "ic_maynilad": "maynilad",
    "ic_manila_water": "manila_water",
    "ic_converge": "converge",
    "ic_sky": "sky",
    "ic_cignal": "cignal",
    "ic_coins": "coinsph",
    "ic_sss": "sss",
    "ic_bpi": "bpi",
    "ic_veco": "veco",
    "ic_home_credit": "home_credit",
}


def main() -> None:
    WEB.mkdir(parents=True, exist_ok=True)
    n = 0
    for legacy, slug in LEGACY_TO_SLUG.items():
        for ext in (".webp", ".png"):
            src = DRAWABLE / f"{legacy}{ext}"
            if not src.exists():
                continue
            dest_android = DRAWABLE / f"ic_provider_{slug}{ext}"
            dest_web = WEB / f"ic_provider_{slug}{ext}"
            shutil.copy2(src, dest_android)
            shutil.copy2(src, dest_web)
            n += 1
            print(f"{legacy}{ext} -> ic_provider_{slug}{ext}")
            break
    print(f"Synced {n} provider icon files")


if __name__ == "__main__":
    main()
