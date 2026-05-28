package com.insapos.v2

import android.util.Log
import android.webkit.JavascriptInterface
import com.insapos.v2.db.OfflineDatabase
import org.json.JSONObject
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit
import java.util.concurrent.TimeoutException

class AndroidBridge(private val activity: MainActivity) {

    companion object {
        private const val TAG = "INSAPOSv3Bridge"
        const val BRIDGE_NAME = "INSAPOS"
        private const val LOCAL_HTTP_TIMEOUT_SEC = 12L
    }

    private val session by lazy { SessionManager(activity) }
    private val httpExecutor = Executors.newSingleThreadExecutor { r ->
        Thread(r, "insapos-bridge-http").apply { isDaemon = true }
    }

    private fun activityAlive(): Boolean =
        !activity.isFinishing && !activity.isDestroyed

    private fun unavailable(): String =
        JSONObject().put("ok", false).put("error", "Activity not available").toString()

    private fun safeBridge(block: () -> String): String {
        return try {
            if (!activityAlive()) return unavailable()
            block()
        } catch (t: Throwable) {
            Log.e(TAG, "Bridge error", t)
            JSONObject().put("ok", false).put("error", t.message ?: "error").toString()
        }
    }

    @JavascriptInterface
    fun getDeviceInfo(): String = safeBridge {
        DeviceInfo.toJsonString(activity)
    }

    @JavascriptInterface
    fun notifySuperAdminStatus(isSuperAdmin: Boolean) {
        if (!activityAlive()) return
        activity.runOnUiThread {
            if (activityAlive()) activity.setSuperAdminFromWeb(isSuperAdmin)
        }
    }

    @JavascriptInterface
    fun openPosMode() {
        if (!activityAlive()) return
        activity.runOnUiThread {
            if (activityAlive()) activity.openPosMode()
        }
    }

    @JavascriptInterface
    fun openSuperAdminPanel() {
        if (!activityAlive()) return
        activity.runOnUiThread {
            if (activityAlive()) activity.openSuperAdminPanel()
        }
    }

    @JavascriptInterface
    fun getAppVersion(): String = BuildConfig.VERSION_NAME

    @JavascriptInterface
    fun getDeviceFingerprint(): String = safeBridge {
        DeviceFingerprint.get(activity)
    }

    @JavascriptInterface
    fun getTerminalId(): String = session.terminalSessionId ?: ""

    @JavascriptInterface
    fun setTerminalSessionId(sessionId: String?) {
        session.terminalSessionId = sessionId?.takeIf { it.isNotBlank() }
    }

    @JavascriptInterface
    fun setBranchId(branchId: Int) {
        if (branchId > 0) {
            session.branchId = branchId
            if (!activityAlive()) return
            activity.onBranchIdSetFromWeb(branchId)
        }
    }

    @JavascriptInterface
    fun isDebug(): Boolean = BuildConfig.DEBUG

    @JavascriptInterface
    fun printReceipt(data: String): String = safeBridge { httpPostJson("/print", data) }

    @JavascriptInterface
    fun openDrawer(): String = safeBridge { httpPostJson("/drawer/open", null) }

    @JavascriptInterface
    fun scanBarcode(): String = safeBridge { httpGet("/scan", 30_000, 30_000) }

    @JavascriptInterface
    fun getPrinterStatus(): String = safeBridge { httpGet("/printer/status", 3000, 3000) }

    @JavascriptInterface
    fun getServicePort(): Int = PosLocalServer.PORT

    @JavascriptInterface
    fun isOfflineCapable(): Boolean = true

    @JavascriptInterface
    fun getOfflineStats(): String = safeBridge { httpGet("/offline/stats", 3000, 3000) }

    @JavascriptInterface
    fun getSyncStatus(): String = safeBridge { httpGet("/offline/sync/status", 3000, 3000) }

    @JavascriptInterface
    fun triggerSync(): String = prefetchCatalog()

    @JavascriptInterface
    fun prefetchCatalog(): String = safeBridge {
        val branchId = session.branchId
        if (branchId != null && branchId > 0) {
            activity.onBranchIdSetFromWeb(branchId)
        } else {
            httpPostJson("/local/sync/now", null)
        }
        JSONObject().put("ok", true).put("triggered", true).toString()
    }

    @JavascriptInterface
    fun setScanInputFocused(focused: Boolean) {
        if (!activityAlive()) return
        activity.notifyScanInputFocused(focused)
        if (focused) {
            activity.posService?.syncEngine?.suppressCatalogPull()
        }
    }

    @JavascriptInterface
    fun setCashierId(cashierId: Int) {
        if (cashierId > 0) session.cashierId = cashierId
    }

    @JavascriptInterface
    fun getLocalProducts(query: String?): String =
        getLocalProductsPage(query, 0, OfflineDatabase.DEFAULT_PRODUCT_PAGE_SIZE)

    @JavascriptInterface
    fun getLocalProductsPage(query: String?, offset: Int, limit: Int): String = safeBridge {
        val safeLimit = limit.coerceIn(1, OfflineDatabase.MAX_PRODUCT_PAGE_SIZE)
        val safeOffset = offset.coerceAtLeast(0)
        val base = "/local/products?limit=$safeLimit&offset=$safeOffset"
        val path = if (!query.isNullOrBlank()) {
            "$base&q=${java.net.URLEncoder.encode(query, "UTF-8")}"
        } else {
            base
        }
        httpGet(path, 5000, 15_000)
    }

    @JavascriptInterface
    fun getLocalInventory(): String = safeBridge { httpGet("/local/inventory", 5000, 15_000) }

    @JavascriptInterface
    fun getLocalCustomers(): String = safeBridge { httpGet("/local/customers", 5000, 15_000) }

    @JavascriptInterface
    fun createLocalSale(jsonPayload: String): String = safeBridge { httpPostJson("/local/sale", jsonPayload) }

    @JavascriptInterface
    fun openLocalShift(jsonPayload: String): String = safeBridge { httpPostJson("/local/shift/open", jsonPayload) }

    @JavascriptInterface
    fun closeLocalShift(jsonPayload: String): String = safeBridge { httpPostJson("/local/shift/close", jsonPayload) }

    @JavascriptInterface
    fun getLocalShiftStatus(): String = safeBridge { httpGet("/local/shift/status", 5000, 15_000) }

    @JavascriptInterface
    fun getLocalReceipt(localId: String): String = safeBridge {
        httpGet(
            "/local/receipt?local_id=${java.net.URLEncoder.encode(localId, "UTF-8")}",
            5000,
            15_000
        )
    }

    @JavascriptInterface
    fun triggerLocalSync(): String = safeBridge { httpPostJson("/local/sync/now", null) }

    @JavascriptInterface
    fun log(level: String, message: String) {
        when (level.lowercase()) {
            "error" -> Log.e(TAG, message)
            "warn" -> Log.w(TAG, message)
            "debug" -> Log.d(TAG, message)
            else -> Log.i(TAG, message)
        }
    }

    private fun httpGet(path: String, connectMs: Int, readMs: Int): String =
        runOnHttpThread { httpGetBlocking(path, connectMs, readMs) }

    private fun httpPostJson(path: String, body: String?): String =
        runOnHttpThread { httpPostJsonBlocking(path, body) }

    private fun runOnHttpThread(block: () -> String): String {
        val future = httpExecutor.submit(block)
        return try {
            future.get(LOCAL_HTTP_TIMEOUT_SEC, TimeUnit.SECONDS)
        } catch (e: TimeoutException) {
            future.cancel(true)
            Log.w(TAG, "Local HTTP timed out")
            JSONObject().put("ok", false).put("error", "timeout").toString()
        } catch (e: Exception) {
            Log.w(TAG, "Local HTTP failed: ${e.message}")
            JSONObject().put("ok", false).put("error", e.message ?: "error").toString()
        }
    }

    private fun httpGetBlocking(path: String, connectMs: Int, readMs: Int): String {
        val url = "http://127.0.0.1:${PosLocalServer.PORT}$path"
        val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
        conn.requestMethod = "GET"
        conn.connectTimeout = connectMs
        conn.readTimeout = readMs
        return conn.inputStream.bufferedReader().use { it.readText() }.also { conn.disconnect() }
    }

    private fun httpPostJsonBlocking(path: String, body: String?): String {
        val url = "http://127.0.0.1:${PosLocalServer.PORT}$path"
        val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
        conn.requestMethod = "POST"
        conn.setRequestProperty("Content-Type", "application/json")
        conn.connectTimeout = 5000
        conn.readTimeout = 15_000
        if (body != null) {
            conn.doOutput = true
            conn.outputStream.bufferedWriter().use { it.write(body) }
        }
        return conn.inputStream.bufferedReader().use { it.readText() }.also { conn.disconnect() }
    }
}
