package com.insapos.light

import android.content.Context
import android.content.SharedPreferences

class SessionManager(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences("insapos_lite_session", Context.MODE_PRIVATE)

    companion object {
        private const val KEY_LAST_URL = "last_url"
        private const val KEY_USE_HTTP = "use_http"
        private const val KEY_SERVER_DOMAIN = "server_domain"
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

    fun getBaseUrl(): String {
        val protocol = if (useHttp) "http" else "https"
        return "$protocol://$serverDomain"
    }

    fun getPosUrl(): String = "${getBaseUrl()}/pos/cashier"
}
