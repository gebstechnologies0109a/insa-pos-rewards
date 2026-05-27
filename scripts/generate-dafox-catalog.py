#!/usr/bin/env python3
"""Build docs/DAFOX_PROVIDER_CATALOG.md from portal scrape artifacts."""
from __future__ import annotations

import re
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(r"c:\laragon\www\ePay Plus")
OUT = ROOT / "docs" / "DAFOX_PROVIDER_CATALOG.md"
SNAPSHOT = Path(r"C:\Users\Admin\.cursor\browser-logs\snapshot-2026-05-27T06-51-32-322Z-67bniz.log")
PORTAL_BASE = "https://portal.dafoxtech.com/assets/telcom_icons"

ELOAD = [
    ("GLOBE", "Globe", "Prepaid Load", "prepaid"),
    ("SMART", "Smart", "Prepaid Load", "prepaid"),
    ("TNT", "Talk N Text", "Prepaid Load", "prepaid"),
    ("SUN", "Sun Cellular", "Prepaid Load", "prepaid"),
    ("TM", "TM", "Prepaid Load", "prepaid"),
    ("DITO", "DITO", "Prepaid Load", "prepaid"),
    ("CIGNAL", "Cignal", "Prepaid Load", "prepaid"),
    ("GSAT", "GSAT", "Prepaid Load", "prepaid"),
    ("GOMO", "GOMO", "Prepaid Load", "prepaid"),
    ("SMARTBRO", "Smart Bro", "Prepaid Load", "prepaid"),
    ("CHERRYPREPAID", "Cherry Prepaid", "Prepaid Load", "prepaid"),
    ("GAMEPIN", "Game Pin", "Prepaid Load", "prepaid"),
    ("KURYENTELOAD", "Kuryente Load", "Prepaid Load", "prepaid"),
]

ECASH = [
    ("GCASH", "GCash", "E-Wallet", "prepaid"),
    ("MAYA", "Maya", "E-Wallet", "prepaid"),
    ("PALAWANPAY", "PalawanPay", "E-Wallet", "prepaid"),
    ("COINSPH", "Coins.ph", "E-Wallet", "prepaid"),
    ("GRABPAY", "GrabPay", "E-Wallet", "prepaid"),
    ("SHOPEEPAY", "ShopeePay", "E-Wallet", "prepaid"),
    ("LAZADA", "Lazada Wallet", "E-Wallet", "prepaid"),
    ("DIBZ_PAY", "DIBZ Pay", "E-Wallet", "prepaid"),
    ("MAYBANK", "Maybank", "Bank", "prepaid"),
    ("PDAX", "PDAX", "E-Wallet", "prepaid"),
    ("HELLOMONEY", "HelloMoney", "E-Wallet", "prepaid"),
    ("PRICELOCQ", "Pricelocq", "E-Wallet", "prepaid"),
    ("DISKARTECH", "Diskartech", "E-Wallet", "prepaid"),
    ("BUX", "Bux", "E-Wallet", "prepaid"),
    ("NATIONLINK", "Nationlink", "E-Wallet", "prepaid"),
    ("XENDIT", "Xendit", "Payment Gateway", "prepaid"),
    ("PERAHUB", "Perahub", "E-Wallet", "prepaid"),
    ("ALLEASY", "AllEasy", "E-Wallet", "prepaid"),
    ("JOJOPAY", "JojoPay", "E-Wallet", "prepaid"),
    ("ECPAY_WALLET", "ECPay Wallet", "E-Wallet", "prepaid"),
    ("MAXIM", "Maxim", "Transportation", "prepaid"),
    ("ALING_PURING", "Aling Puring Credits", "E-Wallet", "prepaid"),
    ("NETBANK", "Netbank", "Bank", "prepaid"),
    ("BIZMOTO", "Bizmoto", "E-Wallet", "prepaid"),
    ("TOKTOKWALLET", "TokTok Wallet", "E-Wallet", "prepaid"),
    ("ICASH", "iCash", "E-Wallet", "prepaid"),
    ("REPAYPH", "RepayPH", "E-Wallet", "prepaid"),
    ("VYBE", "Vybe", "E-Wallet", "prepaid"),
    ("GCASH_PERA_OUTLET", "GCash Pera Outlet", "E-Wallet", "prepaid"),
]

RFID = [
    ("EASYTRIP", "EasyTrip", "RFID Services", "prepaid"),
    ("AUTOSWEEP", "Autosweep", "RFID Services", "prepaid"),
    ("TAPNGO", "Tap&Go", "RFID Services", "prepaid"),
    ("CONNECT", "Connect RFID", "RFID Services", "prepaid"),
    ("ETC", "ETC RFID", "RFID Services", "prepaid"),
    ("CCLEX_RFID", "CCLEX RFID", "RFID Services", "prepaid"),
    ("RFID_ECARD", "RFID eCard", "RFID Services", "prepaid"),
]

BILL_GROUPS = {
    "Telecommunications": [
        ("PLDT", "PLDT", "postpaid"),
        ("SMART_BILL", "Smart Postpaid", "postpaid"),
        ("GLOBE_BILL", "Globe Postpaid", "postpaid"),
        ("SUN_BILL", "Sun Postpaid", "postpaid"),
        ("DITO_BILL", "DITO Postpaid", "postpaid"),
        ("INNOVE", "Innove (Globelines)", "postpaid"),
        ("BAYANTEL", "Bayantel", "postpaid"),
    ],
    "Electricity": [("MERALCO", "Meralco", "postpaid"), ("VECO", "VECO", "postpaid")],
    "Water": [("MAYNILAD", "Maynilad", "postpaid"), ("MANILA_WATER", "Manila Water", "postpaid")],
    "Internet/Cable": [
        ("SKY", "Sky Cable", "postpaid"),
        ("CONVERGE", "Converge ICT", "postpaid"),
        ("CIGNAL", "Cignal TV", "postpaid"),
    ],
    "Government": [
        ("SSS", "SSS", "postpaid"),
        ("PAGIBIG", "Pag-IBIG", "postpaid"),
        ("NBI", "NBI Clearance", "postpaid"),
        ("PHILHEALTH", "PhilHealth", "postpaid"),
    ],
}


def slug(code: str) -> str:
    return code.lower().replace(" ", "_")


def icon_url(code: str) -> str:
    s = slug(code)
    web = ROOT / "public" / "images" / "providers"
    for ext in ("webp", "png"):
        if (web / f"ic_provider_{s}.{ext}").exists():
            return f"/images/providers/ic_provider_{s}.{ext}"
    return f"{PORTAL_BASE}/{s}-*.png (portal CDN; session required)"


def parse_portal_billers() -> list[str]:
    if not SNAPSHOT.exists():
        return []
    text = SNAPSHOT.read_text(encoding="utf-8", errors="replace")
    names = re.findall(r"- role: option\n\s+name: (.+)\n", text)
    return names


def table_rows(service: str, rows: list[tuple]) -> list[str]:
    lines = []
    for row in rows:
        if len(row) == 4:
            code, name, category, billing = row
        else:
            code, name, billing = row
            category = service
        lines.append(
            f"| {name} | `{code}` | {category} | {billing} | {service} | {icon_url(code)} |"
        )
    return lines


def main() -> None:
    portal_billers = parse_portal_billers()
    ts = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")

    lines = [
        "# DaFox Portal Provider Catalog",
        "",
        f"Scanned **portal.dafoxtech.com** on {ts}.",
        "",
        "Sources: `/promos` (eLoad/eCash/RFID), `/bill_fees` (bills dropdown).",
        "Icons: local `public/images/providers/` when present; else portal CDN (`/assets/telcom_icons/`).",
        "",
        "## Summary counts",
        "",
        f"| Service | Seeded (ePayPlus) | Portal billers (full list) |",
        f"|---------|-------------------|----------------------------|",
        f"| E-Load (prepaid) | {len(ELOAD)} | — |",
        f"| Cash-in / E-Wallet | {len(ECASH)} | — |",
        f"| RFID | {len(RFID)} | — |",
        f"| Bills (seeded subset) | {sum(len(v) for v in BILL_GROUPS.values())} | {len(portal_billers)} |",
        "",
        "## 1. E-Load (prepaid) — `/promos`",
        "",
        "| Name | Code | Category | Billing | Type | Icon |",
        "|------|------|----------|---------|------|------|",
    ]
    lines.extend(table_rows("ELOAD", [(c, n, cat, b) for c, n, cat, b in ELOAD]))

    lines.extend([
        "",
        "## 2. Cash-in — `/promos`",
        "",
        "| Name | Code | Category | Billing | Type | Icon |",
        "|------|------|----------|---------|------|------|",
    ])
    lines.extend(table_rows("ECASH", [(c, n, cat, b) for c, n, cat, b in ECASH]))

    lines.extend([
        "",
        "## 3. RFID — `/promos`",
        "",
        "| Name | Code | Category | Billing | Type | Icon |",
        "|------|------|----------|---------|------|------|",
    ])
    lines.extend(table_rows("RFID", [(c, n, cat, b) for c, n, cat, b in RFID]))

    for cat, billers in BILL_GROUPS.items():
        lines.extend([
            "",
            f"## Bills → {cat} (postpaid) — `/bill_fees`",
            "",
            "| Name | Code | Category | Billing | Type | Icon |",
            "|------|------|----------|---------|------|------|",
        ])
        lines.extend(table_rows("BILLS", [(c, n, cat, b) for c, n, b in billers]))

    if portal_billers:
        lines.extend([
            "",
            "## Appendix: Portal `/bill_fees` biller names (full dropdown)",
            "",
            f"Total: **{len(portal_billers)}** billers. Group headers on portal: Telecommunications, Water Utility, Cable and Internet, Electric Utility, Government, Insurance, Loans, Credit Cards, Real Estate, Education, and others.",
            "",
            "<details><summary>Full biller list</summary>",
            "",
        ])
        for name in portal_billers:
            lines.append(f"- {name}")
        lines.extend(["", "</details>", ""])

    OUT.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"Wrote {OUT} ({len(portal_billers)} portal billers)")


if __name__ == "__main__":
    main()
