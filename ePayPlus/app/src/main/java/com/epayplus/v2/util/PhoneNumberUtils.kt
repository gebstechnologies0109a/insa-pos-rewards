package com.epayplus.v2.util

object PhoneNumberUtils {

    /** Digits-only input for display (09…). */
    fun sanitizeInput(input: String): String {
        return input.filter { it.isDigit() }.take(13)
    }

    /**
     * Normalize to Philippine local format 09XXXXXXXXX for API login.
     * Returns null if the number cannot be normalized to a valid mobile.
     */
    fun normalizeForApi(input: String): String? {
        var digits = input.filter { it.isDigit() }
        if (digits.startsWith("63") && digits.length >= 12) {
            digits = "0" + digits.drop(2)
        } else if (digits.startsWith("9") && digits.length == 10) {
            digits = "0$digits"
        }
        return if (digits.matches(Regex("^09\\d{9}$"))) digits else null
    }

    fun isValidForLogin(input: String): Boolean = normalizeForApi(input) != null
}
