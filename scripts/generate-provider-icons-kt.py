#!/usr/bin/env python3
"""Generate ProviderIcons.kt from drawable ic_provider_* files."""
from pathlib import Path

DRAWABLE = Path(r"c:\laragon\www\ePay Plus\ePayPlus\app\src\main\res\drawable")
OUT = Path(r"c:\laragon\www\ePay Plus\ePayPlus\app\src\main\java\com\epayplus\v2\ui\components\ProviderIcons.kt")

slugs = sorted({f.stem.replace("ic_provider_", "") for f in DRAWABLE.glob("ic_provider_*")})

ALIASES: dict[str, str] = {
    "GLOBE": "globe",
    "GLOBE5": "globe",
    "GLOBE10": "globe",
    "GLOBE_LOAD": "globe",
    "GLOBE_BILL": "globe_bill",
    "GLOBE_POSTPAID": "globe_bill",
    "GLOBE_AT_HOME": "globe_bill",
    "SMART": "smart",
    "SMART5": "smart",
    "SMART_LOAD": "smart",
    "SMART_BILL": "smart_bill",
    "SMART_BRO": "smartbro",
    "SMARTBRO": "smartbro",
    "TNT": "tnt",
    "TNT5": "tnt",
    "TALK_N_TEXT": "tnt",
    "DITO": "dito",
    "DITO5": "dito",
    "DITO_BILLS": "dito",
    "SUN": "sun",
    "SUN_BILL": "sun",
    "SUN_CELLULAR": "sun",
    "TM": "tm",
    "TM5": "tm",
    "GCASH": "gcash",
    "GCASH_CASHIN": "gcash",
    "MAYA": "maya",
    "MAYA_CASHIN": "maya",
    "PAYMAYA": "maya",
    "MERALCO": "meralco",
    "MER": "meralco",
    "KURYENTELOAD": "kuryenteload",
    "PLDT": "pldt",
    "MAYNILAD": "maynilad",
    "MANILA_WATER": "manila_water",
    "MWATER": "manila_water",
    "CONVERGE": "converge",
    "SKY": "sky",
    "SKY_CABLE": "sky",
    "CIGNAL": "cignal",
    "COINS": "coinsph",
    "COINSPH": "coinsph",
    "SSS": "sss",
    "PAGIBIG": "pagibig",
    "PHILHEALTH": "philhealth",
    "BPI": "bpi",
    "BPI_LOAN": "bpi",
    "BPI_CC": "bpi",
    "BDO": "bdo",
    "VECO": "veco",
    "HOME_CREDIT": "home_credit",
    "HCREDIT": "home_credit",
    "GRABPAY": "grabpay",
    "SHOPEEPAY": "shopeepay",
    "LAZADA": "lazada",
    "GOMO": "gomo",
    "GSAT": "gsat",
    "CHERRYPREPAID": "cherryprepaid",
    "GAMEPIN": "gamepin",
    "PALAWANPAY": "palawanpay",
    "RFID_ECARD": "rfid_ecard",
    "EASYTRIP": "easytrip",
    "AUTOSWEEP": "autosweep",
    "TAPNGO": "tapngo",
    "TAP_N_GO": "tapngo",
    "CONNECT": "connect",
    "ETC": "etc",
    "OTHER": "etc",
    "NBI": "nbi",
    "DIBZ_PAY": "dibz_pay",
    "MAYBANK": "maybank",
    "PDAX": "pdax",
    "HELLOMONEY": "hellomoney",
    "PRICELOCQ": "pricelocq",
    "DISKARTECH": "diskartech",
    "BUX": "bux",
    "NATIONLINK": "nationlink",
    "XENDIT": "xendit",
    "PERAHUB": "perahub",
    "ALLEASY": "alleasy",
    "JOJOPAY": "jojopay",
    "ECPAY_WALLET": "ecpay_wallet",
    "MAXIM": "maxim",
    "ALING_PURING": "aling_puring_credits",
    "ALING_PURING_CREDITS": "aling_puring_credits",
    "NETBANK": "netbank",
    "BIZMOTO": "bizmoto",
    "TOKTOKWALLET": "toktokwallet",
    "CCLEX_RFID": "cclex_rfid",
    "BUCOR_PADALA": "bucor_padala",
    "ICASH": "icash",
    "REPAYPH": "repayph",
    "VYBE": "vybe",
}

for slug in slugs:
    ALIASES.setdefault(slug.upper(), slug)

res_cases = "\n".join(f'        "{s}" -> R.drawable.ic_provider_{s}' for s in slugs)
alias_cases = "\n".join(
    f'        "{code}" -> "{slug}"'
    for code, slug in sorted(ALIASES.items())
    if slug in slugs
)

kt = f'''package com.epayplus.v2.ui.components

import androidx.annotation.DrawableRes
import com.epayplus.v2.R

/**
 * Maps provider codes / names to local drawable assets (portal + DaFox APK).
 * Prefer API logo_url when present — see [ProviderIcon].
 */
object ProviderIcons {{

    fun resolve(providerCode: String?, providerName: String? = null): Int? {{
        val code = providerCode?.trim().orEmpty()
        val name = providerName?.trim().orEmpty()
        if (code.isNotEmpty()) {{
            providerIconRes(code)?.let {{ return it }}
        }}
        if (name.isNotEmpty()) {{
            providerIconRes(name)?.let {{ return it }}
            matchByName(name)?.let {{ return it }}
        }}
        return null
    }}

    @DrawableRes
    fun providerIconRes(key: String): Int? {{
        val normalized = normalize(key)
        slugForCode(normalized)?.let {{ slug -> drawableForSlug(slug)?.let {{ return it }} }}
        partialSlug(normalized)?.let {{ slug -> drawableForSlug(slug)?.let {{ return it }} }}
        return null
    }}

    @DrawableRes
    private fun drawableForSlug(slug: String): Int? = when (slug) {{
{res_cases}
        else -> null
    }}

    private fun slugForCode(normalized: String): String? = when (normalized) {{
{alias_cases}
        else -> null
    }}

    @DrawableRes
    private fun matchByName(name: String): Int? {{
        val n = name.lowercase()
        val hints = listOf(
            "meralco" to "meralco",
            "maynilad" to "maynilad",
            "manila water" to "manila_water",
            "globe" to if ("bill" in n || "postpaid" in n) "globe_bill" else "globe",
            "smart" to if ("bro" in n || "bill" in n) "smart_bill" else "smart",
            "talk n text" to "tnt",
            "dito" to "dito",
            "gcash" to "gcash",
            "maya" to "maya",
            "paymaya" to "maya",
            "pldt" to "pldt",
            "converge" to "converge",
            "cignal" to "cignal",
            "coins" to "coinsph",
            "sss" to "sss",
            "pag-ibig" to "pagibig",
            "pagibig" to "pagibig",
            "philhealth" to "philhealth",
            "easytrip" to "easytrip",
            "autosweep" to "autosweep",
            "grab" to "grabpay",
            "shopee" to "shopeepay",
            "home credit" to "home_credit",
            "veco" to "veco",
            "bpi" to "bpi",
        )
        for ((needle, slug)) in hints {{
            if (needle in n || n == needle.replace(" ", "")) {{
                drawableForSlug(slug)?.let {{ return it }}
            }}
        }}
        return null
    }}

    private fun partialSlug(normalized: String): String? {{
        val rules = listOf(
            "MERALCO" to "meralco",
            "GLOBE" to if (normalized.contains("BILL")) "globe_bill" else "globe",
            "SMART" to if (normalized.contains("BRO") || normalized.contains("BILL")) "smart_bill" else "smart",
            "MAYNILAD" to "maynilad",
            "PLDT" to "pldt",
            "GCASH" to "gcash",
            "MAYA" to "maya",
            "DITO" to "dito",
            "CIGNAL" to "cignal",
            "CONVERGE" to "converge",
            "EASYTRIP" to "easytrip",
            "PAGIBIG" to "pagibig",
            "PHILHEALTH" to "philhealth",
        )
        for ((needle, slug) in rules) {{
            if (needle in normalized) return slug
        }}
        return null
    }}

    private fun normalize(key: String): String =
        key.trim().uppercase()
            .replace(" ", "_")
            .replace("-", "_")
            .replace(".", "")
            .replace("&", "AND")
}}
'''
OUT.write_text(kt, encoding="utf-8")
print(f"Wrote {OUT} ({len(slugs)} slugs, {len(ALIASES)} aliases)")
