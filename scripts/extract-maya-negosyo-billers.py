#!/usr/bin/env python3
"""Extract Maya Negosyo biller-like tokens from APK DEX (offline)."""
from __future__ import annotations

import re
import sys
import zipfile
from pathlib import Path

APK = Path(r"C:\Users\Admin\Downloads\maya-negosyo-app.apk")
URLS = Path(r"C:\Users\Admin\Downloads\maya-negosyo-urls.txt")


def from_apk() -> set[str]:
    slugs: set[str] = set()
    pat = re.compile(rb"images/billers/([A-Za-z0-9_]+)/")
    with zipfile.ZipFile(APK) as z:
        for name in z.namelist():
            if not name.endswith((".dex", ".json", ".xml")):
                continue
            try:
                data = z.read(name)
            except Exception:
                continue
            for m in pat.finditer(data):
                slugs.add(m.group(1).decode("ascii", "ignore"))
            # billerCode / biller_id style
            for m in re.finditer(rb'biller(?:Code|Id|_id)?["\']?\s*[:=]\s*["\']([A-Za-z0-9_]+)', data, re.I):
                slugs.add(m.group(1).decode("ascii", "ignore"))
    return slugs


def from_urls() -> set[str]:
    if not URLS.exists():
        return set()
    text = URLS.read_text(encoding="utf-8", errors="ignore")
    slugs = set(re.findall(r"images/billers/([A-Za-z0-9_]+)/", text))
    return slugs


def main() -> None:
    apk_slugs = from_apk()
    url_slugs = from_urls()
    all_slugs = sorted(apk_slugs | url_slugs)
    print(f"APK slugs: {len(apk_slugs)}")
    print(f"URL slugs: {len(url_slugs)}")
    print(f"Combined: {len(all_slugs)}")
    for s in all_slugs:
        print(s)


if __name__ == "__main__":
    main()
