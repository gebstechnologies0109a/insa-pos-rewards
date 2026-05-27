#!/usr/bin/env python3
"""Extract provider logos from DaFox APK -> ic_provider_{slug} in drawable + web."""
import re
import shutil
import subprocess
import zipfile
from pathlib import Path

APK = Path(r"c:\Users\Admin\Downloads\DaFoxTechTablet.apk")
AAPT2 = Path(r"C:\Users\Admin\Android\Sdk\build-tools\34.0.0\aapt2.exe")
ANDROID_OUT = Path(r"c:\laragon\www\ePay Plus\ePayPlus\app\src\main\res\drawable")
WEB_OUT = Path(r"c:\laragon\www\ePay Plus\public\images\providers")

# ic_provider_{slug} -> DaFox drawable resource name
MAPPING: dict[str, str] = {
    "globe": "globe_logo",
    "globe_bill": "globe_postpaid_biller_logo",
    "smart": "smart_logo",
    "smart_bill": "smart_communications",
    "smartbro": "smartbro_logo",
    "tnt": "tnt_logo",
    "dito": "dito_logo",
    "sun": "sun_logo",
    "sun_bill": "sun_bro",
    "tm": "tm_logo",
    "gcash": "gcash_logo",
    "maya": "maya_logo",
    "meralco": "meralco_prepaid_logo",
    "kuryenteload": "kuryenteload_logo",
    "pldt": "pldt_logo",
    "maynilad": "maynilad_water_services",
    "manila_water": "manila_water",
    "converge": "converge_ict",
    "sky": "sky_logo",
    "cignal": "cignal_logo",
    "coinsph": "coinsph_logo",
    "sss": "sss",
    "pagibig": "pagibig_logo",
    "bpi": "bpi",
    "bdo": "bdo_network_slg",
    "veco": "veco",
    "home_credit": "home_credit",
    "grabpay": "grabpeso",
    "shopeepay": "shopeepay_logo",
    "lazada": "lazada_philippines",
    "gomo": "gomo_logo",
    "gsat": "gsat_logo",
    "cherryprepaid": "cherry_prepaid_logo",
    "gamepin": "gamepin_logo",
    "palawanpay": "palawanpay",
    "rfid_ecard": "rfid_ecard",
    "easytrip": "easytrip_logo",
    "dibz_pay": "dibz_pay",
    "maybank": "maybank",
    "pdax": "pdax",
    "hellomoney": "hellomoney",
    "pricelocq": "pricelocq",
    "diskartech": "diskartech",
    "bux": "bux",
    "nationlink": "nationlink",
    "xendit": "xendit_logo",
    "perahub": "perahub",
    "alleasy": "alleasy_logo",
    "jojopay": "jojopay",
    "ecpay_wallet": "ecpay_wallet",
    "maxim": "maxim",
    "aling_puring_credits": "aling_puring_credits_logo",
    "netbank": "netbank",
    "bizmoto": "bizmoto",
    "toktokwallet": "toktokwallet",
    "cclex_rfid": "cclex_rfid",
    "bucor_padala": "bucor_padala_logo",
    "icash": "icash_logo",
    "repayph": "repayph_logo",
    "vybe": "vybe_logo",
    "autosweep": "biller_default_logo",
    "tapngo": "biller_default_logo",
    "connect": "biller_default_logo",
    "etc": "biller_default_logo",
    "nbi": "biller_default_logo",
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
    ANDROID_OUT.mkdir(parents=True, exist_ok=True)
    WEB_OUT.mkdir(parents=True, exist_ok=True)
    name_to_file = parse_aapt_dump(APK)
    extracted = []
    missing = []
    with zipfile.ZipFile(APK) as zf:
        for slug, dafox_name in MAPPING.items():
            zip_path = name_to_file.get(dafox_name)
            if not zip_path:
                missing.append(f"{slug}:{dafox_name}")
                continue
            ext = Path(zip_path).suffix or ".webp"
            out_name = f"ic_provider_{slug}{ext}"
            dest_android = ANDROID_OUT / out_name
            dest_web = WEB_OUT / out_name
            data = zf.read(zip_path)
            dest_android.write_bytes(data)
            shutil.copy2(dest_android, dest_web)
            # Legacy alias for existing Kotlin refs
            legacy = {
                "globe": "ic_globe",
                "globe_bill": "ic_globe_bill",
                "smart": "ic_smart",
                "smart_bill": "ic_smart_bill",
                "tnt": "ic_tnt",
                "dito": "ic_dito",
                "sun": "ic_sun",
                "tm": "ic_tm",
                "gcash": "ic_gcash",
                "maya": "ic_maya",
                "meralco": "ic_meralco",
                "pldt": "ic_pldt",
                "maynilad": "ic_maynilad",
                "manila_water": "ic_manila_water",
                "converge": "ic_converge",
                "sky": "ic_sky",
                "cignal": "ic_cignal",
                "coinsph": "ic_coins",
                "sss": "ic_sss",
                "bpi": "ic_bpi",
                "veco": "ic_veco",
                "home_credit": "ic_home_credit",
            }.get(slug)
            if legacy:
                (ANDROID_OUT / f"{legacy}{ext}").write_bytes(data)
            extracted.append(f"{out_name} <- {dafox_name}")
    print(f"Extracted {len(extracted)} logos")
    for line in extracted:
        print(" ", line)
    if missing:
        print(f"Missing ({len(missing)}):", ", ".join(missing[:20]))


if __name__ == "__main__":
    main()
