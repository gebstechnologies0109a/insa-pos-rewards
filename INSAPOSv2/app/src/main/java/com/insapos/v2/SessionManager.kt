package com.insapos.v2

import android.content.Context
import android.content.SharedPreferences

class SessionManager(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences("insapos_v2_session", Context.MODE_PRIVATE)

    companion object {
        private const val KEY_LAST_URL = "last_url"
        private const val KEY_USE_HTTP = "use_http"
        private const val KEY_SERVER_DOMAIN = "server_domain"
        private const val KEY_SELECTED_PRINTER = "selected_printer"
        private const val KEY_FIRST_LAUNCH = "first_launch"
        private const val KEY_BRANCH_ID = "branch_id"
        private const val KEY_TERMINAL_SESSION_ID = "terminal_session_id"
        private const val KEY_CASHIER_ID = "cashier_id"
        private const val KEY_LICENSE_VALID_UNTIL = "license_valid_until"
    }

    var lastUrl: String?
        get() = prefs.getString(KEY_LAST_URL, null)
        set(value) = prefs.edit().putString(KEY_LAST_URL, value).apply()

    var useHttp: Boolean
        get() = prefs.getBoolean(KEY_USE_HTTP, false)
        set(value) = prefs.edit().putBoolean(KEY_USE_HTTP, value).apply()

    var serverDomain: String
        get() = prefs.getString(KEY_SERVER_DOMAIN, "insapos.diybizrewards.com")!!
        set(value) = prefs.edit().putString(KEY_SERVER_DOMAIN, value).apply()

    var selectedPrinter: String?
        get() = prefs.getString(KEY_SELECTED_PRINTER, null)
        set(value) = prefs.edit().putString(KEY_SELECTED_PRINTER, value).apply()

    var isFirstLaunch: Boolean
        get() = prefs.getBoolean(KEY_FIRST_LAUNCH, true)
        set(value) = prefs.edit().putBoolean(KEY_FIRST_LAUNCH, value).apply()

    var branchId: Int?
        get() {
            val v = prefs.getInt(KEY_BRANCH_ID, -1)
            return if (v > 0) v else null
        }
        set(value) = prefs.edit().apply {
            if (value != null && value > 0) putInt(KEY_BRANCH_ID, value) else remove(KEY_BRANCH_ID)
        }.apply()

    var terminalSessionId: String?
        get() = prefs.getString(KEY_TERMINAL_SESSION_ID, null)
        set(value) = prefs.edit().putString(KEY_TERMINAL_SESSION_ID, value).apply()

    var cashierId: Int?
        get() {
            val v = prefs.getInt(KEY_CASHIER_ID, -1)
            return if (v > 0) v else null
        }
        set(value) = prefs.edit().apply {
            if (value != null && value > 0) putInt(KEY_CASHIER_ID, value) else remove(KEY_CASHIER_ID)
        }.apply()

    var licenseValidUntil: Long
        get() = prefs.getLong(KEY_LICENSE_VALID_UNTIL, 0L)
        set(value) = prefs.edit().putLong(KEY_LICENSE_VALID_UNTIL, value).apply()

    fun isLicenseCachedValid(): Boolean {
        val until = licenseValidUntil
        return until > System.currentTimeMillis()
    }

    fun getBaseUrl(): String {
        val protocol = if (useHttp) "http" else "https"
        return "$protocol://$serverDomain"
    }

    fun getPosUrl(): String = "${getBaseUrl()}/pos/cashier"

    fun clear() {
        prefs.edit().clear().apply()
    }
}
