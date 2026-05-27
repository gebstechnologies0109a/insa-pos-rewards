package com.epayplus.v2.ui.components

import androidx.annotation.DrawableRes
import com.epayplus.v2.R

/**
 * Maps provider codes / names to local drawable assets (portal + DaFox APK).
 * Prefer API logo_url when present — see [ProviderIcon].
 */
object ProviderIcons {

    fun resolve(providerCode: String?, providerName: String? = null): Int? {
        val code = providerCode?.trim().orEmpty()
        val name = providerName?.trim().orEmpty()
        if (code.isNotEmpty()) {
            providerIconRes(code)?.let { return it }
        }
        if (name.isNotEmpty()) {
            providerIconRes(name)?.let { return it }
            matchByName(name)?.let { return it }
        }
        return null
    }

    @DrawableRes
    fun providerIconRes(key: String): Int? {
        val normalized = normalize(key)
        slugForCode(normalized)?.let { slug -> drawableForSlug(slug)?.let { return it } }
        partialSlug(normalized)?.let { slug -> drawableForSlug(slug)?.let { return it } }
        return null
    }

    @DrawableRes
    private fun drawableForSlug(slug: String): Int? = when (slug) {
        "aling_puring_credits" -> R.drawable.ic_provider_aling_puring_credits
        "alleasy" -> R.drawable.ic_provider_alleasy
        "autosweep" -> R.drawable.ic_provider_autosweep
        "bdo" -> R.drawable.ic_provider_bdo
        "bizmoto" -> R.drawable.ic_provider_bizmoto
        "bpi" -> R.drawable.ic_provider_bpi
        "bucor_padala" -> R.drawable.ic_provider_bucor_padala
        "bux" -> R.drawable.ic_provider_bux
        "cclex_rfid" -> R.drawable.ic_provider_cclex_rfid
        "cherryprepaid" -> R.drawable.ic_provider_cherryprepaid
        "cignal" -> R.drawable.ic_provider_cignal
        "coinsph" -> R.drawable.ic_provider_coinsph
        "connect" -> R.drawable.ic_provider_connect
        "converge" -> R.drawable.ic_provider_converge
        "dibz_pay" -> R.drawable.ic_provider_dibz_pay
        "diskartech" -> R.drawable.ic_provider_diskartech
        "dito" -> R.drawable.ic_provider_dito
        "easytrip" -> R.drawable.ic_provider_easytrip
        "ecpay_wallet" -> R.drawable.ic_provider_ecpay_wallet
        "etc" -> R.drawable.ic_provider_etc
        "gamepin" -> R.drawable.ic_provider_gamepin
        "gcash" -> R.drawable.ic_provider_gcash
        "globe" -> R.drawable.ic_provider_globe
        "globe_bill" -> R.drawable.ic_provider_globe_bill
        "gomo" -> R.drawable.ic_provider_gomo
        "grabpay" -> R.drawable.ic_provider_grabpay
        "gsat" -> R.drawable.ic_provider_gsat
        "hellomoney" -> R.drawable.ic_provider_hellomoney
        "home_credit" -> R.drawable.ic_provider_home_credit
        "icash" -> R.drawable.ic_provider_icash
        "jojopay" -> R.drawable.ic_provider_jojopay
        "kuryenteload" -> R.drawable.ic_provider_kuryenteload
        "lazada" -> R.drawable.ic_provider_lazada
        "manila_water" -> R.drawable.ic_provider_manila_water
        "maxim" -> R.drawable.ic_provider_maxim
        "maya" -> R.drawable.ic_provider_maya
        "maybank" -> R.drawable.ic_provider_maybank
        "maynilad" -> R.drawable.ic_provider_maynilad
        "meralco" -> R.drawable.ic_provider_meralco
        "nationlink" -> R.drawable.ic_provider_nationlink
        "nbi" -> R.drawable.ic_provider_nbi
        "netbank" -> R.drawable.ic_provider_netbank
        "pagibig" -> R.drawable.ic_provider_pagibig
        "palawanpay" -> R.drawable.ic_provider_palawanpay
        "pdax" -> R.drawable.ic_provider_pdax
        "perahub" -> R.drawable.ic_provider_perahub
        "pldt" -> R.drawable.ic_provider_pldt
        "pricelocq" -> R.drawable.ic_provider_pricelocq
        "repayph" -> R.drawable.ic_provider_repayph
        "rfid_ecard" -> R.drawable.ic_provider_rfid_ecard
        "shopeepay" -> R.drawable.ic_provider_shopeepay
        "sky" -> R.drawable.ic_provider_sky
        "smart" -> R.drawable.ic_provider_smart
        "smart_bill" -> R.drawable.ic_provider_smart_bill
        "smartbro" -> R.drawable.ic_provider_smartbro
        "sss" -> R.drawable.ic_provider_sss
        "sun" -> R.drawable.ic_provider_sun
        "tapngo" -> R.drawable.ic_provider_tapngo
        "tm" -> R.drawable.ic_provider_tm
        "tnt" -> R.drawable.ic_provider_tnt
        "toktokwallet" -> R.drawable.ic_provider_toktokwallet
        "veco" -> R.drawable.ic_provider_veco
        "vybe" -> R.drawable.ic_provider_vybe
        "xendit" -> R.drawable.ic_provider_xendit
        else -> null
    }

    private fun slugForCode(normalized: String): String? = when (normalized) {
        "ALING_PURING" -> "aling_puring_credits"
        "ALING_PURING_CREDITS" -> "aling_puring_credits"
        "ALLEASY" -> "alleasy"
        "AUTOSWEEP" -> "autosweep"
        "BDO" -> "bdo"
        "BIZMOTO" -> "bizmoto"
        "BPI" -> "bpi"
        "BPI_CC" -> "bpi"
        "BPI_LOAN" -> "bpi"
        "BUCOR_PADALA" -> "bucor_padala"
        "BUX" -> "bux"
        "CCLEX_RFID" -> "cclex_rfid"
        "CHERRYPREPAID" -> "cherryprepaid"
        "CIGNAL" -> "cignal"
        "COINS" -> "coinsph"
        "COINSPH" -> "coinsph"
        "CONNECT" -> "connect"
        "CONVERGE" -> "converge"
        "DIBZ_PAY" -> "dibz_pay"
        "DISKARTECH" -> "diskartech"
        "DITO" -> "dito"
        "DITO5" -> "dito"
        "DITO_BILLS" -> "dito"
        "EASYTRIP" -> "easytrip"
        "ECPAY_WALLET" -> "ecpay_wallet"
        "ETC" -> "etc"
        "GAMEPIN" -> "gamepin"
        "GCASH" -> "gcash"
        "GCASH_CASHIN" -> "gcash"
        "GLOBE" -> "globe"
        "GLOBE10" -> "globe"
        "GLOBE5" -> "globe"
        "GLOBE_AT_HOME" -> "globe_bill"
        "GLOBE_BILL" -> "globe_bill"
        "GLOBE_LOAD" -> "globe"
        "GLOBE_POSTPAID" -> "globe_bill"
        "GOMO" -> "gomo"
        "GRABPAY" -> "grabpay"
        "GSAT" -> "gsat"
        "HCREDIT" -> "home_credit"
        "HELLOMONEY" -> "hellomoney"
        "HOME_CREDIT" -> "home_credit"
        "ICASH" -> "icash"
        "JOJOPAY" -> "jojopay"
        "KURYENTELOAD" -> "kuryenteload"
        "LAZADA" -> "lazada"
        "MANILA_WATER" -> "manila_water"
        "MAXIM" -> "maxim"
        "MAYA" -> "maya"
        "MAYA_CASHIN" -> "maya"
        "MAYBANK" -> "maybank"
        "MAYNILAD" -> "maynilad"
        "MER" -> "meralco"
        "MERALCO" -> "meralco"
        "MWATER" -> "manila_water"
        "NATIONLINK" -> "nationlink"
        "NBI" -> "nbi"
        "NETBANK" -> "netbank"
        "OTHER" -> "etc"
        "PAGIBIG" -> "pagibig"
        "PALAWANPAY" -> "palawanpay"
        "PAYMAYA" -> "maya"
        "PDAX" -> "pdax"
        "PERAHUB" -> "perahub"
        "PLDT" -> "pldt"
        "PRICELOCQ" -> "pricelocq"
        "REPAYPH" -> "repayph"
        "RFID_ECARD" -> "rfid_ecard"
        "SHOPEEPAY" -> "shopeepay"
        "SKY" -> "sky"
        "SKY_CABLE" -> "sky"
        "SMART" -> "smart"
        "SMART5" -> "smart"
        "SMARTBRO" -> "smartbro"
        "SMART_BILL" -> "smart_bill"
        "SMART_BRO" -> "smartbro"
        "SMART_LOAD" -> "smart"
        "SSS" -> "sss"
        "SUN" -> "sun"
        "SUN_BILL" -> "sun"
        "SUN_CELLULAR" -> "sun"
        "TALK_N_TEXT" -> "tnt"
        "TAPNGO" -> "tapngo"
        "TAP_N_GO" -> "tapngo"
        "TM" -> "tm"
        "TM5" -> "tm"
        "TNT" -> "tnt"
        "TNT5" -> "tnt"
        "TOKTOKWALLET" -> "toktokwallet"
        "VECO" -> "veco"
        "VYBE" -> "vybe"
        "XENDIT" -> "xendit"
        else -> null
    }

    @DrawableRes
    private fun matchByName(name: String): Int? {
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
        for ((needle, slug) in hints) {
            if (needle in n || n == needle.replace(" ", "")) {
                drawableForSlug(slug)?.let { return it }
            }
        }
        return null
    }

    private fun partialSlug(normalized: String): String? {
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
        for ((needle, slug) in rules) {
            if (needle in normalized) return slug
        }
        return null
    }

    private fun normalize(key: String): String =
        key.trim().uppercase()
            .replace(" ", "_")
            .replace("-", "_")
            .replace(".", "")
            .replace("&", "AND")
}
