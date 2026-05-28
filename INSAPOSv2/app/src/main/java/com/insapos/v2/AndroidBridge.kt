package com.insapos.v2

import android.util.Log
import android.webkit.JavascriptInterface
import org.json.JSONObject

class AndroidBridge(private val activity: MainActivity) {

    companion object {
        private const val TAG = "INSAPOSv3Bridge"
        const val BRIDGE_NAME = "INSAPOS"
    }

    private val session by lazy { SessionManager(activity) }

    @JavascriptInterface
    fun getDeviceInfo(): String {
        return DeviceInfo.toJsonString(activity)
    }

    @JavascriptInterface
    fun notifySuperAdminStatus(isSuperAdmin: Boolean) {
        activity.runOnUiThread { activity.setSuperAdminFromWeb(isSuperAdmin) }
    }

    @JavascriptInterface
    fun openPosMode() {
        activity.runOnUiThread { activity.openPosMode() }
    }

    @JavascriptInterface
    fun openSuperAdminPanel() {
        activity.runOnUiThread { activity.openSuperAdminPanel() }
    }

    @JavascriptInterface
    fun getAppVersion(): String {
        return BuildConfig.VERSION_NAME
    }

    @JavascriptInterface
    fun getDeviceFingerprint(): String {
        return DeviceFingerprint.get(activity)
    }

    @JavascriptInterface
    fun getTerminalId(): String {
        return session.terminalSessionId ?: ""
    }

    @JavascriptInterface
    fun setTerminalSessionId(sessionId: String?) {
        session.terminalSessionId = sessionId?.takeIf { it.isNotBlank() }
    }

    @JavascriptInterface
    fun setBranchId(branchId: Int) {
        if (branchId > 0) {
            session.branchId = branchId
            activity.runOnUiThread { activity.onBranchIdSetFromWeb(branchId) }
        }
    }

    @JavascriptInterface
    fun isDebug(): Boolean {
        return BuildConfig.DEBUG
    }

    @JavascriptInterface
    fun printReceipt(data: String): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}/print"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.requestMethod = "POST"
            conn.setRequestProperty("Content-Type", "application/json")
            conn.doOutput = true
            conn.connectTimeout = 5000
            conn.readTimeout = 10000
            conn.outputStream.bufferedWriter().use { it.write(data) }
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            Log.e(TAG, "printReceipt failed", e)
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    @JavascriptInterface
    fun openDrawer(): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}/drawer/open"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.requestMethod = "POST"
            conn.connectTimeout = 3000
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            Log.e(TAG, "openDrawer failed", e)
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    @JavascriptInterface
    fun scanBarcode(): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}/scan"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.requestMethod = "GET"
            conn.connectTimeout = 30000
            conn.readTimeout = 30000
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            Log.e(TAG, "scanBarcode failed", e)
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    @JavascriptInterface
    fun getPrinterStatus(): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}/printer/status"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.requestMethod = "GET"
            conn.connectTimeout = 3000
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    @JavascriptInterface
    fun getServicePort(): Int = PosLocalServer.PORT

    @JavascriptInterface
    fun isOfflineCapable(): Boolean = true

    @JavascriptInterface
    fun getOfflineStats(): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}/offline/stats"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.connectTimeout = 3000
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    @JavascriptInterface
    fun getSyncStatus(): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}/offline/sync/status"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.connectTimeout = 3000
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    @JavascriptInterface
    fun triggerSync(): String = prefetchCatalog()

    /** Full catalog + stock + customers download into native SQLite (after setBranchId). */
    @JavascriptInterface
    fun prefetchCatalog(): String {
        return try {
            val branchId = session.branchId
            if (branchId != null && branchId > 0) {
                activity.runOnUiThread { activity.onBranchIdSetFromWeb(branchId) }
            } else {
                val url = "http://127.0.0.1:${PosLocalServer.PORT}/offline/sync/now"
                val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
                conn.requestMethod = "POST"
                conn.connectTimeout = 3000
                conn.inputStream.bufferedReader().use { it.readText() }
                conn.disconnect()
            }
            JSONObject().put("ok", true).put("triggered", true).put("full", true).toString()
        } catch (e: Exception) {
            Log.e(TAG, "prefetchCatalog failed", e)
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    @JavascriptInterface
    fun setScanInputFocused(focused: Boolean) {
        activity.notifyScanInputFocused(focused)
    }

    @JavascriptInterface
    fun setCashierId(cashierId: Int) {
        if (cashierId > 0) session.cashierId = cashierId
    }

    @JavascriptInterface
    fun getLocalProducts(query: String?): String = localGet("/local/products" + if (!query.isNullOrBlank()) "?q=${java.net.URLEncoder.encode(query, "UTF-8")}" else "")

    @JavascriptInterface
    fun getLocalInventory(): String = localGet("/local/inventory")

    @JavascriptInterface
    fun getLocalCustomers(): String = localGet("/local/customers")

    @JavascriptInterface
    fun createLocalSale(jsonPayload: String): String = localPost("/local/sale", jsonPayload)

    @JavascriptInterface
    fun openLocalShift(jsonPayload: String): String = localPost("/local/shift/open", jsonPayload)

    @JavascriptInterface
    fun closeLocalShift(jsonPayload: String): String = localPost("/local/shift/close", jsonPayload)

    @JavascriptInterface
    fun getLocalShiftStatus(): String = localGet("/local/shift/status")

    @JavascriptInterface
    fun getLocalReceipt(localId: String): String = localGet("/local/receipt?local_id=${java.net.URLEncoder.encode(localId, "UTF-8")}")

    @JavascriptInterface
    fun triggerLocalSync(): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}/local/sync/now"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.requestMethod = "POST"
            conn.connectTimeout = 5000
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    private fun localGet(path: String): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}$path"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.requestMethod = "GET"
            conn.connectTimeout = 5000
            conn.readTimeout = 15000
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            Log.e(TAG, "localGet $path failed", e)
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    private fun localPost(path: String, body: String): String {
        return try {
            val url = "http://127.0.0.1:${PosLocalServer.PORT}$path"
            val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
            conn.requestMethod = "POST"
            conn.setRequestProperty("Content-Type", "application/json")
            conn.doOutput = true
            conn.connectTimeout = 5000
            conn.readTimeout = 15000
            conn.outputStream.bufferedWriter().use { it.write(body) }
            val response = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()
            response
        } catch (e: Exception) {
            Log.e(TAG, "localPost $path failed", e)
            JSONObject().put("ok", false).put("error", e.message).toString()
        }
    }

    @JavascriptInterface
    fun log(level: String, message: String) {
        when (level.lowercase()) {
            "error" -> Log.e(TAG, message)
            "warn" -> Log.w(TAG, message)
            "debug" -> Log.d(TAG, message)
            else -> Log.i(TAG, message)
        }
    }
}
