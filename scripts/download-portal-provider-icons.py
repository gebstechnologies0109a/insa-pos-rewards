#!/usr/bin/env python3
"""Download provider icons from DaFox portal telcom_icons (passive, one request per icon)."""
from __future__ import annotations

import re
import shutil
import time
import urllib.request
from pathlib import Path

BASE = "https://portal.dafoxtech.com/assets/telcom_icons"
WEB_OUT = Path(r"c:\laragon\www\ePay Plus\public\images\providers")
ANDROID_OUT = Path(r"c:\laragon\www\ePay Plus\ePayPlus\app\src\main\res\drawable")

# epay provider code -> portal asset basename (without hash suffix; we use full hashed URLs)
PORTAL_ICONS: dict[str, tuple[str, str]] = {
    # code: (slug for ic_provider_{slug}, full URL path after telcom_icons/)
    "GLOBE": ("globe", f"{BASE}/globe-logo-26c9fc836b4a87605b32b8ca8672ba3e0732e461a49edb4e3ea8b5c4e725afce.png"),
    "SMART": ("smart", f"{BASE}/smart-logo-7d22e4088add990232d75516036fc66e8cf4d02754ce15c1e02266b3cb3ea6d7.png"),
    "TNT": ("tnt", f"{BASE}/tnt-logo-67507613190f4d3b1b36bde325015e959a5df7e2596d8a1e33464b936eb0bcee.png"),
    "SUN": ("sun", f"{BASE}/sun-logo-80edb17b42a645914003e3aed8941185be2ee1b9ced371d7b318628b1e8f17a2.png"),
    "TM": ("tm", f"{BASE}/tm-logo-a3de97165c107c4676f8148af2b98b2003d464406833debcea7d1a80ad89e041.png"),
    "DITO": ("dito", f"{BASE}/dito-logo-21f04a4fdc08c433cc43e7407282391bd4a6564849ef685447cda7e0675be126.png"),
    "CIGNAL": ("cignal", f"{BASE}/signal-logo-faeae18db305b6535dbb35e19338a38050bebf0b4e8bf8940be839facf8b197e.png"),
    "GSAT": ("gsat", f"{BASE}/gsat-logo-c595284865e9d3977bbcb9730c1dfaf4f891e3cea5aba0a84552def3af2054a2.png"),
    "GOMO": ("gomo", f"{BASE}/gomo-f661cbd4f57200c4a38ea9845e69e8183ca5eb489e08d8c8c476e864756e80e0.png"),
    "SMARTBRO": ("smartbro", f"{BASE}/smart-logo-7d22e4088add990232d75516036fc66e8cf4d02754ce15c1e02266b3cb3ea6d7.png"),
    "CHERRYPREPAID": ("cherryprepaid", f"{BASE}/cherry-logo-524e4040bbe538a6909d13d1a9e4f5849fb3e4de835a23b0b5d1703be5969ef2.png"),
    "GAMEPIN": ("gamepin", f"{BASE}/game-logo-2a941a1a956cc2f31eebf16d0952d18f8ada081aecf01215472b9cb391907483.png"),
    "KURYENTELOAD": ("kuryenteload", f"{BASE}/meralco-logo-33421826728844c1c8604effc9db9860ae4d2ecb2ca04eea3a79aae28cfda224.png"),
    "MERALCO": ("meralco", f"{BASE}/meralco-logo-33421826728844c1c8604effc9db9860ae4d2ecb2ca04eea3a79aae28cfda224.png"),
    "GCASH": ("gcash", f"{BASE}/gcash-logo-2bf9ce6f5707a2ad8d4ce691531b6d4f5f9c6aeced088941d0203ebab28a8488.png"),
    "MAYA": ("maya", f"{BASE}/maya-logo-0bd0597564b7ecbbfee6fe25d25282b5a55abba478b9429a3377f665f765a7a6.png"),
    "PALAWANPAY": ("palawanpay", f"{BASE}/palawanpay-77463193a508a426234e0e2a28f7f896ae9d61c0a85d32c80a19c7866afc800d.png"),
    "COINS": ("coinsph", f"{BASE}/coinsph-logo-5912c07ca7ca9f9aa3c24df52726e3c200aa841c5309296fc3710e1708c59a1d.png"),
    "COINSPH": ("coinsph", f"{BASE}/coinsph-logo-5912c07ca7ca9f9aa3c24df52726e3c200aa841c5309296fc3710e1708c59a1d.png"),
    "RFID_ECARD": ("rfid_ecard", f"{BASE}/rfid_ecard-499f6727ca95bf965a490264734072392f00b06b97b5fd751c6a6b96100f9e9e.png"),
    "EASYTRIP": ("easytrip", f"{BASE}/easytrip-2b40e7ac6453137dd26900f0779dadc19272931591d76bc4e38f1aedffc2fd25.png"),
    "DIBZ_PAY": ("dibz_pay", f"{BASE}/dibz_pay-6d380b3e4c8641c756a7db790800cd073c9fe7f97c165d2ae02d535dc8675869.png"),
    "MAYBANK": ("maybank", f"{BASE}/maybank-3ef48071e72288b87a49cb0be09100b8cd54bccbeeb49613678f5fdc42458047.png"),
    "PDAX": ("pdax", f"{BASE}/pdax-e9340c5b6d3285d7c641cc65a43a48c2454bf4888c75af7617f5a6deada7ca0f.png"),
    "HELLOMONEY": ("hellomoney", f"{BASE}/hellomoney-51c395cce744f55c70751ce2eb4d30b9a30ef0618bfa652235a76eedc5887349.png"),
    "PRICELOCQ": ("pricelocq", f"{BASE}/pricelocq-999569ff217401588c3d14b900ebf154b612d5d9cc2657ac6f41d9224eb3bb2b.png"),
    "DISKARTECH": ("diskartech", f"{BASE}/diskartech-32437803a2e7e037a13e287a8fec16d5a292a9ba339bda872e015d5cf9f88251.png"),
    "BUX": ("bux", f"{BASE}/bux-2ae20d33866960ded3f720338544379a859e86706a5044c13ca9a1c3018d34bc.png"),
    "NATIONLINK": ("nationlink", f"{BASE}/nationlink-77ca6df1ab6e985a5ad6b6a58cd89a0e083479d80fc238affd64c1c21267256d.png"),
    "XENDIT": ("xendit", f"{BASE}/xendit-7cd2b06d1df72615a66904e28bb87159963fdafb80a06ba02657966e2b50d7ca.png"),
    "PERAHUB": ("perahub", f"{BASE}/perahub-a956cd893fa8f479ac0c06dfec3ade585037ab40317579050cff4acd803d4052.png"),
    "ALLEASY": ("alleasy", f"{BASE}/alleasy-cf8475aa576f0315d11bdc42d90578f416b9cfab3a4a7c68a747256d50a3ed8a.png"),
    "JOJOPAY": ("jojopay", f"{BASE}/jojopay-878b0d8f943cb2b65a9f1d0fc635f0f68ecb206d172b442d7b43e7f68c2e5d02.png"),
    "ECPAY_WALLET": ("ecpay_wallet", f"{BASE}/ecpay_wallet-78277455480aab498104be9b3f5d4dc9f1a7b29822b6dea00f84b7f6518c3b4b.png"),
    "MAXIM": ("maxim", f"{BASE}/maxim-02a933b2bcc015bc94284a1b1a3799767da2f1cd87a0b9a602f54e122c396cfa.png"),
    "ALING_PURING": ("aling_puring_credits", f"{BASE}/aling_puring_credits-4798014c34415187aeaa9a364269a81cd640fcc99a166b6ab2a4013b01aac590.png"),
    "NETBANK": ("netbank", f"{BASE}/netbank-93ab4adac830770ba35594dc0b7390180414ab27250f186985920ccbd033da47.png"),
    "BIZMOTO": ("bizmoto", f"{BASE}/bizmoto-9aa7c4e5ecf8c91bf8de172772f19f1e1a35ed9dfa8a931056e1c8a16350aafc.png"),
    "LANDBANK_PREPAID": ("landbank_prepaid_card", f"{BASE}/landbank_prepaid_card-3189b66f0fda4bae3dea40a4c5c0cfca1652862e986e76e062e8e2819c872cf6.png"),
    "TOKTOKWALLET": ("toktokwallet", f"{BASE}/toktokwallet-bdf940425c6531c8de0bc1a77579e68d023cebfb11d7a1a6da107172ca4464ac.png"),
    "ISAKAY_PH": ("isakay_ph", f"{BASE}/isakay_ph-393860346b9f81e9f97ef9bd4d04d76c70bf449022331da6c78b430bedee90f7.png"),
    "CCLEX_RFID": ("cclex_rfid", f"{BASE}/cclex_rfid-2ae4be1d3a0f9883384458f4ebc425511ea8f830d92ae424ca5b04c3ee03ef6c.png"),
    "BUCOR_PADALA": ("bucor_padala", f"{BASE}/bucor_padala-6bd65163db1929c5fc41f8ad938dfe341240514d66d6adeb402cbcc37c4aeae9.png"),
    "ICASH": ("icash", f"{BASE}/icash-e59273eb7abee961ba6c4f988435220068805575fd8b605f2acb5628c2ba74c9.png"),
    "REPAYPH": ("repayph", f"{BASE}/repayph-4aa9d6af36860417698a47e9add67724ed31be769a604c904ebda986156cf5dc.png"),
    "VYBE": ("vybe", f"{BASE}/vybe-9c4a27b699be83cedaec21b2d41c4c2a8ea8584c75109a914d60bd5057f1ee5a.png"),
    "GLOBE_BILL": ("globe_bill", f"{BASE}/globe-logo-26c9fc836b4a87605b32b8ca8672ba3e0732e461a49edb4e3ea8b5c4e725afce.png"),
    "SMART_BILL": ("smart_bill", f"{BASE}/smart-logo-7d22e4088add990232d75516036fc66e8cf4d02754ce15c1e02266b3cb3ea6d7.png"),
    "SUN_BILL": ("sun_bill", f"{BASE}/sun-logo-80edb17b42a645914003e3aed8941185be2ee1b9ced371d7b318628b1e8f17a2.png"),
}

# Legacy ic_* slug aliases from APK extract (slug -> legacy drawable base without ext)
APK_LEGACY_SLUG = {
    "pldt": "ic_pldt",
    "maynilad": "ic_maynilad",
    "manila_water": "ic_manila_water",
    "converge": "ic_converge",
    "sky": "ic_sky",
    "sss": "ic_sss",
    "pagibig": "ic_pagibig",
    "philhealth": "ic_philhealth",
    "bpi": "ic_bpi",
    "bdo": "ic_bdo",
    "veco": "ic_veco",
    "home_credit": "ic_home_credit",
    "grabpay": "ic_grabpay",
    "shopeepay": "ic_shopeepay",
    "lazada": "ic_lazada",
}


def download(url: str, dest: Path) -> bool:
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "ePayPlus-icon-sync/1.0"})
        with urllib.request.urlopen(req, timeout=30) as resp:
            dest.write_bytes(resp.read())
        return True
    except Exception as exc:
        print(f"  FAIL {dest.name}: {exc}")
        return False


def main() -> None:
    WEB_OUT.mkdir(parents=True, exist_ok=True)
    ANDROID_OUT.mkdir(parents=True, exist_ok=True)
    seen_slugs: set[str] = set()
    manifest: list[str] = []
    ok = 0

    for code, (slug, url) in PORTAL_ICONS.items():
        if slug in seen_slugs:
            continue
        seen_slugs.add(slug)
        web_path = WEB_OUT / f"ic_provider_{slug}.png"
        android_path = ANDROID_OUT / f"ic_provider_{slug}.png"
        time.sleep(0.15)
        if download(url, web_path):
            shutil.copy2(web_path, android_path)
            manifest.append(f"| {code} | {slug} | portal | {url} | ic_provider_{slug}.png | /images/providers/ic_provider_{slug}.png |")
            ok += 1
            print(f"OK {slug}")

    # Copy legacy APK drawables to ic_provider_* when present
    for slug, legacy in APK_LEGACY_SLUG.items():
        if slug in seen_slugs:
            continue
        for ext in (".webp", ".png"):
            src = ANDROID_OUT / f"{legacy}{ext}"
            if src.exists():
                web_path = WEB_OUT / f"ic_provider_{slug}.png"
                android_path = ANDROID_OUT / f"ic_provider_{slug}.png"
                if ext == ".webp":
                    shutil.copy2(src, ANDROID_OUT / f"ic_provider_{slug}.webp")
                    shutil.copy2(src, web_path)  # web can serve webp as png name is ok
                else:
                    shutil.copy2(src, android_path)
                    shutil.copy2(src, web_path)
                manifest.append(f"| {slug.upper()} | {slug} | apk ({legacy}) | — | ic_provider_{slug}{ext} | /images/providers/ic_provider_{slug}.png |")
                seen_slugs.add(slug)
                ok += 1
                print(f"APK {slug} <- {legacy}")
                break

    print(f"\nDownloaded/copied {ok} unique provider icons ({len(seen_slugs)} slugs)")
    doc = Path(r"c:\laragon\www\ePay Plus\docs\PROVIDER_ICONS.md")
    doc.parent.mkdir(parents=True, exist_ok=True)
    header = "# Provider icon mapping\n\n| Name/Code | Slug | Source | Portal URL | Android drawable | Web path |\n|-----------|------|--------|------------|------------------|----------|\n"
    doc.write_text(header + "\n".join(manifest) + "\n", encoding="utf-8")
    print(f"Wrote {doc}")


if __name__ == "__main__":
    main()
