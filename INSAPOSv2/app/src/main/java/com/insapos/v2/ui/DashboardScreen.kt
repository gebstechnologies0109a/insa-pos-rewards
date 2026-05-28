package com.insapos.v2.ui

import android.webkit.WebView
import com.insapos.v2.MainActivity
import org.json.JSONObject

/**
 * Pushes compact POS dashboard stats into the WebView for the cashier header.
 */
object DashboardScreen {

    fun dispatchDashboardData(activity: MainActivity, webView: WebView, payload: JSONObject) {
        val json = payload.toString()
            .replace("\\", "\\\\")
            .replace("'", "\\'")
        val js = """
            (function() {
                var detail = JSON.parse('$json');
                document.dispatchEvent(new CustomEvent('insapos:dashboardData', { detail: detail }));
                if (window.posAppInstance && window.posAppInstance.applyDashboardData) {
                    window.posAppInstance.applyDashboardData(detail);
                }
            })();
        """.trimIndent()
        webView.post { webView.evaluateJavascript(js, null) }
    }

    fun buildPayload(
        salesToday: Int = 0,
        revenueToday: Double = 0.0,
        pendingSync: Int = 0,
        shiftOpen: Boolean = false,
        productsCached: Int = 0,
    ): JSONObject = JSONObject()
        .put("sales_today", salesToday)
        .put("revenue_today", revenueToday)
        .put("pending_sync", pendingSync)
        .put("shift_open", shiftOpen)
        .put("products_cached", productsCached)
        .put("ts", System.currentTimeMillis())
}
