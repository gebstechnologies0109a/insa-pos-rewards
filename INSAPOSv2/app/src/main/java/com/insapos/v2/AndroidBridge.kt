package com.insapos.v2

import android.content.Context
import android.util.Log
import android.webkit.JavascriptInterface
import org.json.JSONObject

class AndroidBridge(private val context: Context) {

    companion object {
        private const val TAG = "INSAPOSv2Bridge"
        const val BRIDGE_NAME = "INSAPOS"
    }

    @JavascriptInterface
    fun getDeviceInfo(): String {
        return DeviceInfo.toJsonString(context)
    }

    @JavascriptInterface
    fun getAppVersion(): String {
        return BuildConfig.VERSION_NAME
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
    fun log(level: String, message: String) {
        when (level.lowercase()) {
            "error" -> Log.e(TAG, message)
            "warn" -> Log.w(TAG, message)
            "debug" -> Log.d(TAG, message)
            else -> Log.i(TAG, message)
        }
    }
}
