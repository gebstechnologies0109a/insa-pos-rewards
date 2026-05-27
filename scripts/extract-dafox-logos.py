#!/usr/bin/env python3
"""Extract provider logos from DaFox APK using aapt2 resource dump."""
import re
import shutil
import subprocess
import zipfile
from pathlib import Path

APK = Path(r"c:\Users\Admin\Downloads\DaFoxTechTablet.apk")
AAPT2 = Path(r"C:\Users\Admin\Android\Sdk\build-tools\34.0.0\aapt2.exe")
OUT = Path(r"c:\laragon\www\ePay Plus\ePayPlus\app\src\main\res\drawable")

# ePayPlus drawable name -> DaFox drawable resource name (first match wins)
MAPPING = {
    "ic_meralco": "meralco",
    "ic_globe": "globe_logo",
    "ic_globe_bill": "globe_postpaid_biller_logo",
    "ic_smart": "smart_logo",
    "ic_smart_bill": "smart_communications",
    "ic_tnt": "tnt_logo",
    "ic_dito": "dito_logo",
    "ic_sun": "sun_logo",
    "ic_tm": "tm_logo",
    "ic_gcash": "gcash_logo",
    "ic_maya": "maya_logo",
    "ic_pldt": "pldt_logo",
    "ic_maynilad": "maynilad_water_services",
    "ic_manila_water": "manila_water",  # may not exist
    "ic_converge": "converge_ict",
    "ic_sky": "sky_logo",
    "ic_cignal": "cignal_logo",
    "ic_coins": "coinsph_logo",
    "ic_sss": "sss",
    "ic_pagibig": "pagibig",
    "ic_philhealth": "philhealth",
    "ic_bpi": "bpi",
    "ic_bdo": "bdo",
    "ic_veco": "veco",
    "ic_grabpay": "grabpay",
    "ic_shopeepay": "shopeepay",
    "ic_lazada": "lazada",
    "ic_home_credit": "home_credit",
}


def parse_aapt_dump(apk: Path) -> dict[str, str]:
    out = subprocess.check_output(
        [str(AAPT2), "dump", "resources", str(apk)],
        stderr=subprocess.STDOUT,
        text=True,
        errors="ignore",
    )
    name_to_file: dict[str, str] = {}
    current: str | None = None
    for line in out.splitlines():
        m = re.search(r"drawable/([a-z0-9_]+)", line)
        if m and "resource 0x" in line:
            current = m.group(1)
            continue
        if current and "(file)" in line:
            fm = re.search(r"\(file\)\s+(res/\S+)", line)
            if fm:
                name_to_file[current] = fm.group(1)
                current = None
    return name_to_file


def main() -> None:
    if not APK.exists():
        raise SystemExit(f"APK not found: {APK}")
    OUT.mkdir(parents=True, exist_ok=True)
    name_to_file = parse_aapt_dump(APK)
    extracted = []
    missing = []
    with zipfile.ZipFile(APK) as zf:
        for out_name, dafox_name in MAPPING.items():
            zip_path = name_to_file.get(dafox_name)
            if not zip_path:
                missing.append(dafox_name)
                continue
            ext = Path(zip_path).suffix or ".webp"
            dest = OUT / f"{out_name}{ext}"
            data = zf.read(zip_path)
            dest.write_bytes(data)
            extracted.append(f"{out_name}{ext} <- {dafox_name} ({zip_path})")
    print(f"Extracted {len(extracted)} logos to {OUT}")
    for line in extracted:
        print("  ", line)
    if missing:
        print(f"Missing in APK ({len(missing)}):", ", ".join(missing))


if __name__ == "__main__":
    main()
