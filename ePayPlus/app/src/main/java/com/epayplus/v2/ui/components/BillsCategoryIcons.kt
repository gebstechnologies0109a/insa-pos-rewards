package com.epayplus.v2.ui.components

import androidx.annotation.DrawableRes
import androidx.compose.ui.graphics.Color
import com.epayplus.v2.R

/**
 * Maps bill payment service category names/codes to original vector icons and accent colors.
 * Category labels come from product seed / API (`ProductEntity.category`).
 */
object BillsCategoryIcons {

    @DrawableRes
    fun categoryIconRes(categoryCode: String, categoryName: String): Int {
        return when (normalize(categoryCode).ifBlank { normalize(categoryName) }) {
            "ELECTRICITY", "ELECTRIC", "POWER", "UTILITIES_ELECTRIC" -> R.drawable.ic_bills_electricity
            "WATER", "UTILITIES_WATER" -> R.drawable.ic_bills_water
            "INTERNET_CABLE", "INTERNET", "CABLE", "CABLE_TV", "BROADBAND",
            "INTERNETCABLE", "INTERNET/CABLE" -> R.drawable.ic_bills_cable
            "TELECOMMUNICATIONS", "TELECOM", "MOBILE", "PHONE", "POSTPAID" -> R.drawable.ic_bills_telecom
            "GOVERNMENT", "GOV", "TAX", "PUBLIC" -> R.drawable.ic_bills_government
            "INSURANCE", "INSURE" -> R.drawable.ic_bills_insurance
            "LOANS", "LOAN", "LENDING", "FINANCE" -> R.drawable.ic_bills_loans
            "CREDIT_CARDS", "CREDITCARD", "CREDIT_CARD", "CREDIT CARDS" -> R.drawable.ic_bills_credit_cards
            "REAL_ESTATE", "REALESTATE", "REAL ESTATE", "HOUSING", "PROPERTY" -> R.drawable.ic_bills_real_estate
            "SCHOOLS", "SCHOOL", "EDUCATION", "TUITION" -> R.drawable.ic_bills_education
            "OTHERS", "OTHER", "MISC", "MISCELLANEOUS" -> R.drawable.ic_bills_others
            else -> matchByDisplayName(categoryName.ifBlank { categoryCode })
        }
    }

    fun categoryColor(categoryCode: String, categoryName: String): Color {
        return when (normalize(categoryCode).ifBlank { normalize(categoryName) }) {
            "ELECTRICITY", "ELECTRIC", "POWER", "UTILITIES_ELECTRIC" -> Color(0xFFFFA726)
            "WATER", "UTILITIES_WATER" -> Color(0xFF42A5F5)
            "INTERNET_CABLE", "INTERNET", "CABLE", "CABLE_TV", "BROADBAND",
            "INTERNETCABLE", "INTERNET/CABLE" -> Color(0xFF7E57C2)
            "TELECOMMUNICATIONS", "TELECOM", "MOBILE", "PHONE", "POSTPAID" -> Color(0xFF66BB6A)
            "GOVERNMENT", "GOV", "TAX", "PUBLIC" -> Color(0xFF5C6BC0)
            "INSURANCE", "INSURE" -> Color(0xFFEF5350)
            "LOANS", "LOAN", "LENDING", "FINANCE" -> Color(0xFF26A69A)
            "CREDIT_CARDS", "CREDITCARD", "CREDIT_CARD", "CREDIT CARDS" -> Color(0xFFEC407A)
            "REAL_ESTATE", "REALESTATE", "REAL ESTATE", "HOUSING", "PROPERTY" -> Color(0xFF8D6E63)
            "SCHOOLS", "SCHOOL", "EDUCATION", "TUITION" -> Color(0xFF29B6F6)
            "OTHERS", "OTHER", "MISC", "MISCELLANEOUS" -> Color(0xFF78909C)
            else -> colorByDisplayName(categoryName.ifBlank { categoryCode })
        }
    }

    @DrawableRes
    private fun matchByDisplayName(name: String): Int = when {
        name.equals("Electricity", ignoreCase = true) -> R.drawable.ic_bills_electricity
        name.equals("Water", ignoreCase = true) -> R.drawable.ic_bills_water
        name.equals("Internet/Cable", ignoreCase = true) -> R.drawable.ic_bills_cable
        name.contains("cable", ignoreCase = true) || name.contains("internet", ignoreCase = true) ->
            R.drawable.ic_bills_cable
        name.equals("Telecommunications", ignoreCase = true) -> R.drawable.ic_bills_telecom
        name.contains("telecom", ignoreCase = true) || name.contains("mobile", ignoreCase = true) ->
            R.drawable.ic_bills_telecom
        name.equals("Government", ignoreCase = true) -> R.drawable.ic_bills_government
        name.equals("Insurance", ignoreCase = true) -> R.drawable.ic_bills_insurance
        name.equals("Loans", ignoreCase = true) -> R.drawable.ic_bills_loans
        name.equals("Credit Cards", ignoreCase = true) -> R.drawable.ic_bills_credit_cards
        name.equals("Real Estate", ignoreCase = true) -> R.drawable.ic_bills_real_estate
        name.equals("Schools", ignoreCase = true) -> R.drawable.ic_bills_education
        name.contains("school", ignoreCase = true) || name.contains("education", ignoreCase = true) ->
            R.drawable.ic_bills_education
        else -> R.drawable.ic_bills_others
    }

    private fun colorByDisplayName(name: String): Color = when {
        name.equals("Electricity", ignoreCase = true) -> Color(0xFFFFA726)
        name.equals("Water", ignoreCase = true) -> Color(0xFF42A5F5)
        name.equals("Internet/Cable", ignoreCase = true) -> Color(0xFF7E57C2)
        name.equals("Telecommunications", ignoreCase = true) -> Color(0xFF66BB6A)
        name.equals("Government", ignoreCase = true) -> Color(0xFF5C6BC0)
        name.equals("Insurance", ignoreCase = true) -> Color(0xFFEF5350)
        name.equals("Loans", ignoreCase = true) -> Color(0xFF26A69A)
        name.equals("Credit Cards", ignoreCase = true) -> Color(0xFFEC407A)
        name.equals("Real Estate", ignoreCase = true) -> Color(0xFF8D6E63)
        name.equals("Schools", ignoreCase = true) -> Color(0xFF29B6F6)
        else -> Color(0xFF78909C)
    }

    private fun normalize(value: String): String =
        value.trim()
            .uppercase()
            .replace("/", "_")
            .replace(" ", "_")
            .replace("-", "_")
}
