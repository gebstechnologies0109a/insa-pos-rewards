#!/usr/bin/env python3
"""Write docs/PROVIDER_ICONS.md from on-disk assets."""
from pathlib import Path

WEB = Path(r"c:\laragon\www\ePay Plus\public\images\providers")
ANDROID = Path(r"c:\laragon\www\ePay Plus\ePayPlus\app\src\main\res\drawable")
PORTAL_BASE = "https://portal.dafoxtech.com/assets/telcom_icons"

slugs = sorted({
    f.stem.replace("ic_provider_", "")
    for d in (WEB, ANDROID)
    for f in d.glob("ic_provider_*")
})

lines = [
    "# Provider icon mapping",
    "",
    "Sources: **portal** (catalog at `/promos`, CDN requires session — URLs recorded), **apk** (DaFoxTechTablet.apk via aapt2).",
    "",
    f"**Total unique slugs:** {len(slugs)}",
    "",
    "| Code (examples) | Slug | Source | Android drawable | Web path |",
    "|-----------------|------|--------|------------------|----------|",
]

code_examples = {
    "globe": "GLOBE",
    "smart": "SMART",
    "meralco": "MERALCO",
    "gcash": "GCASH",
    "easytrip": "EASYTRIP",
    "pagibig": "PAGIBIG",
    "philhealth": "PHILHEALTH (no asset — Material fallback)",
}

for slug in slugs:
    web_file = next((f for f in WEB.glob(f"ic_provider_{slug}.*")), None)
    android_file = next((f for f in ANDROID.glob(f"ic_provider_{slug}.*")), None)
    src = "apk" if android_file else "portal"
    if web_file and android_file:
        src = "apk+portal"
    code = code_examples.get(slug, slug.upper())
    android_name = android_file.name if android_file else "—"
    web_path = f"/images/providers/{web_file.name}" if web_file else "—"
    lines.append(f"| {code} | {slug} | {src} | {android_name} | {web_path} |")

lines.extend([
    "",
    "## Material icon fallback",
    "",
    "- **PHILHEALTH** — not in DaFox APK or portal telcom_icons",
    "- Unknown biller codes from Maya with no matching slug",
    "- Quick Services tiles (LOAD, Bills, Cash-in, RFID categories) — unchanged custom vectors",
])

Path(r"c:\laragon\www\ePay Plus\docs\PROVIDER_ICONS.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
print(f"Wrote {len(slugs)} rows")
