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

    fun getBaseUrl(): String {
        val protocol = if (useHttp) "http" else "https"
        return "$protocol://$serverDomain"
    }

    fun getPosUrl(): String = "${getBaseUrl()}/pos"

    fun clear() {
        prefs.edit().clear().apply()
    }
}
