package com.epayplus.v2.util

import android.content.Context
import android.os.Build
import android.webkit.JavascriptInterface
import com.epayplus.v2.BuildConfig
import org.json.JSONObject

/**
 * Minimal [window.INSAPOS] bridge for INSA cashier embedded in ePay Plus.
 *
 * Auth note: ePay Plus API bearer token is NOT shared with INSA web session cookies.
 * The user signs in on the INSA host inside the WebView (separate Laravel session).
 * Printer/barcode offline features require the standalone INSA POS app (local service on 127.0.0.1).
 */
class InsaPosWebBridge(@Suppress("UNUSED_PARAMETER") context: Context) {

    companion object {
        const val BRIDGE_NAME = "INSAPOS"
    }

    @JavascriptInterface
    fun getDeviceInfo(): String = deviceInfoJson()

    @JavascriptInterface
    fun getAppVersion(): String = BuildConfig.VERSION_NAME

    @JavascriptInterface
    fun isDebug(): Boolean = BuildConfig.DEBUG

    @JavascriptInterface
    fun getServicePort(): Int = 0

    @JavascriptInterface
    fun isOfflineCapable(): Boolean = false

    @JavascriptInterface
    fun isLite(): Boolean = true

    @JavascriptInterface
    fun log(level: String, message: String) {
        android.util.Log.i("ePayInsaEmbed", "[$level] $message")
    }

    fun deviceInfoJson(): String {
        return JSONObject()
            .put("app", "ePayPlus")
            .put("version", BuildConfig.VERSION_NAME)
            .put("platform", "android")
            .put("model", Build.MODEL)
            .put("manufacturer", Build.MANUFACTURER)
            .put("sdk", Build.VERSION.SDK_INT)
            .put("androidVersion", Build.VERSION.RELEASE)
            .put("embedded", true)
            .put("lite", true)
            .toString()
    }

    fun injectReadyScript(): String {
        val deviceJson = deviceInfoJson().replace("\\", "\\\\").replace("'", "\\'")
        return """
            (function() {
                window.INSAPOS_DEVICE = JSON.parse('$deviceJson');
                window.INSAPOS_SERVICE_PORT = 0;
                window.INSAPOS_OFFLINE_CAPABLE = false;
                window.INSAPOS_ONLINE = navigator.onLine;
                if (window.onINSAPOSReady) window.onINSAPOSReady(window.INSAPOS_DEVICE);
                document.dispatchEvent(new CustomEvent('insapos:ready', { detail: window.INSAPOS_DEVICE }));
            })();
        """.trimIndent()
    }
}
