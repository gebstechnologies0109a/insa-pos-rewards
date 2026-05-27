package com.epayplus.v2.ui.components

import androidx.annotation.DrawableRes
import com.epayplus.v2.R

/**
 * Maps provider codes / names to local drawable assets (extracted reference logos).
 * Falls back to null when no asset exists — UI shows Material icon instead.
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
        val normalized = key.uppercase()
            .replace(" ", "_")
            .replace("-", "_")
            .replace(".", "")

        return when {
            normalized in MERALCO_KEYS -> R.drawable.ic_meralco
            normalized in GLOBE_BILL_KEYS -> R.drawable.ic_globe_bill
            normalized in GLOBE_KEYS -> R.drawable.ic_globe
            normalized in SMART_BILL_KEYS -> R.drawable.ic_smart_bill
            normalized in SMART_KEYS -> R.drawable.ic_smart
            normalized in TNT_KEYS -> R.drawable.ic_tnt
            normalized in DITO_KEYS -> R.drawable.ic_dito
            normalized in SUN_KEYS -> R.drawable.ic_sun
            normalized in TM_KEYS -> R.drawable.ic_tm
            normalized in GCASH_KEYS -> R.drawable.ic_gcash
            normalized in MAYA_KEYS -> R.drawable.ic_maya
            normalized in PLDT_KEYS -> R.drawable.ic_pldt
            normalized in MAYNILAD_KEYS -> R.drawable.ic_maynilad
            normalized in MANILA_WATER_KEYS -> R.drawable.ic_manila_water
            normalized in CONVERGE_KEYS -> R.drawable.ic_converge
            normalized in SKY_KEYS -> R.drawable.ic_sky
            normalized in CIGNAL_KEYS -> R.drawable.ic_cignal
            normalized in COINS_KEYS -> R.drawable.ic_coins
            normalized in SSS_KEYS -> R.drawable.ic_sss
            normalized in PAGIBIG_KEYS -> null // no asset
            normalized in PHILHEALTH_KEYS -> null
            normalized in BPI_KEYS -> R.drawable.ic_bpi
            normalized in BDO_KEYS -> null
            normalized in VECO_KEYS -> R.drawable.ic_veco
            normalized in HOME_CREDIT_KEYS -> R.drawable.ic_home_credit
            else -> partialMatch(normalized)
        }
    }

    @DrawableRes
    private fun matchByName(name: String): Int? {
        val n = name.lowercase()
        return when {
            "meralco" in n -> R.drawable.ic_meralco
            "maynilad" in n -> R.drawable.ic_maynilad
            "manila water" in n -> R.drawable.ic_manila_water
            "globe" in n && ("bill" in n || "postpaid" in n || "broadband" in n) -> R.drawable.ic_globe_bill
            "globe" in n -> R.drawable.ic_globe
            "smart" in n && ("bro" in n || "postpaid" in n || "bill" in n) -> R.drawable.ic_smart_bill
            "smart" in n -> R.drawable.ic_smart
            "talk n text" in n || n == "tnt" -> R.drawable.ic_tnt
            "dito" in n -> R.drawable.ic_dito
            "sun" in n -> R.drawable.ic_sun
            n == "tm" || "touch mobile" in n -> R.drawable.ic_tm
            "gcash" in n -> R.drawable.ic_gcash
            "maya" in n || "paymaya" in n -> R.drawable.ic_maya
            "pldt" in n -> R.drawable.ic_pldt
            "converge" in n -> R.drawable.ic_converge
            "sky" in n -> R.drawable.ic_sky
            "cignal" in n -> R.drawable.ic_cignal
            "coins" in n -> R.drawable.ic_coins
            "sss" in n -> R.drawable.ic_sss
            "bpi" in n -> R.drawable.ic_bpi
            "veco" in n || "visayan electric" in n -> R.drawable.ic_veco
            "home credit" in n -> R.drawable.ic_home_credit
            else -> null
        }
    }

    @DrawableRes
    private fun partialMatch(normalized: String): Int? = when {
        normalized.contains("MERALCO") -> R.drawable.ic_meralco
        normalized.contains("GLOBE") && normalized.contains("BILL") -> R.drawable.ic_globe_bill
        normalized.contains("GLOBE") -> R.drawable.ic_globe
        normalized.contains("SMART") -> R.drawable.ic_smart
        normalized.contains("MAYNILAD") -> R.drawable.ic_maynilad
        normalized.contains("PLDT") -> R.drawable.ic_pldt
        normalized.contains("GCASH") -> R.drawable.ic_gcash
        normalized.contains("MAYA") -> R.drawable.ic_maya
        normalized.contains("DITO") -> R.drawable.ic_dito
        normalized.contains("CIGNAL") -> R.drawable.ic_cignal
        normalized.contains("CONVERGE") -> R.drawable.ic_converge
        else -> null
    }

    private val MERALCO_KEYS = setOf("MERALCO", "MER", "MERALCO_PAY")
    private val GLOBE_KEYS = setOf("GLOBE", "GLOBE5", "GLOBE10", "GLOBE_LOAD")
    private val GLOBE_BILL_KEYS = setOf("GLOBE_BILL", "GLOBE_BILL_PAY", "GLOBE_POSTPAID", "GLOBE_AT_HOME")
    private val SMART_KEYS = setOf("SMART", "SMART5", "SMART10", "SMART_LOAD")
    private val SMART_BILL_KEYS = setOf("SMART_BILL", "SMART_BILL_PAY", "SMART_BRO", "SMARTBRO")
    private val TNT_KEYS = setOf("TNT", "TNT5", "TALK_N_TEXT")
    private val DITO_KEYS = setOf("DITO", "DITO5")
    private val SUN_KEYS = setOf("SUN", "SUN_BILL", "SUN_BILL_PAY", "SUN_CELLULAR")
    private val TM_KEYS = setOf("TM", "TM5")
    private val GCASH_KEYS = setOf("GCASH", "GCASH_CASHIN")
    private val MAYA_KEYS = setOf("MAYA", "MAYA_CASHIN", "PAYMAYA")
    private val PLDT_KEYS = setOf("PLDT", "PLDT_PAY")
    private val MAYNILAD_KEYS = setOf("MAYNILAD", "MAYNILAD_PAY")
    private val MANILA_WATER_KEYS = setOf("MANILA_WATER", "MWATER", "MWATER_PAY")
    private val CONVERGE_KEYS = setOf("CONVERGE", "CONVERGE_PAY")
    private val SKY_KEYS = setOf("SKY", "SKY_PAY", "SKY_CABLE")
    private val CIGNAL_KEYS = setOf("CIGNAL", "CIGNAL_PAY")
    private val COINS_KEYS = setOf("COINS", "COINSPH", "COINS_CASHIN")
    private val SSS_KEYS = setOf("SSS", "SSS_PAY")
    private val PAGIBIG_KEYS = setOf("PAGIBIG", "PAGIBIG_PAY")
    private val PHILHEALTH_KEYS = setOf("PHILHEALTH", "PHILHEALTH_PAY")
    private val BPI_KEYS = setOf("BPI", "BPI_LOAN", "BPI_LOAN_PAY", "BPI_CC", "BPI_CC_PAY")
    private val BDO_KEYS = setOf("BDO", "BDO_LOAN", "BDO_CC")
    private val VECO_KEYS = setOf("VECO", "VECO_PAY")
    private val HOME_CREDIT_KEYS = setOf("HOME_CREDIT", "HCREDIT", "HCREDIT_PAY")
}
