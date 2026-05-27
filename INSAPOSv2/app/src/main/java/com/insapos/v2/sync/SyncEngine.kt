package com.insapos.v2.sync

import android.content.Context
import android.util.Log
import com.insapos.v2.ConnectivityMonitor
import com.insapos.v2.SessionManager
import com.insapos.v2.db.OfflineDatabase
import kotlinx.coroutines.*
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

class SyncEngine(
    private val context: Context,
    private val db: OfflineDatabase,
    private val session: SessionManager,
    private val connectivity: ConnectivityMonitor
) {
    companion object {
        private const val TAG = "SyncEngine"
        private const val SYNC_INTERVAL_MS = 45_000L
        private const val PULL_INTERVAL_MS = 300_000L
        private const val STARTUP_PULL_DELAY_MS = 90_000L
    }

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var pushJob: Job? = null
    private var pullJob: Job? = null

    var onSyncStatusChanged: ((SyncStatus) -> Unit)? = null

    @Volatile
    var lastSyncStatus: SyncStatus = SyncStatus.IDLE
        private set

    fun start() {
        startPushLoop()
        startPullLoop()
        Log.i(TAG, "Sync engine started")
    }

    fun stop() {
        pushJob?.cancel()
        pullJob?.cancel()
        scope.cancel()
        Log.i(TAG, "Sync engine stopped")
    }

    fun syncNow() {
        scope.launch {
            pushTransactions()
            pullData()
        }
    }

    private fun startPushLoop() {
        pushJob = scope.launch {
            while (isActive) {
                if (connectivity.isConnected()) {
                    pushTransactions()
                    pushSyncQueue()
                }
                delay(SYNC_INTERVAL_MS)
            }
        }
    }

    private fun startPullLoop() {
        pullJob = scope.launch {
            delay(STARTUP_PULL_DELAY_MS)
            while (isActive) {
                if (connectivity.isConnected()) {
                    pullData()
                }
                delay(PULL_INTERVAL_MS)
            }
        }
    }

    private suspend fun pushTransactions() {
        val unsynced = db.getUnsyncedTransactions()
        if (unsynced.length() == 0) return

        updateStatus(SyncStatus.PUSHING)
        Log.i(TAG, "Pushing ${unsynced.length()} unsynced transactions")

        for (i in 0 until unsynced.length()) {
            val txn = unsynced.getJSONObject(i)
            try {
                val response = httpPost(
                    "${session.getBaseUrl()}/api/pos/sync/push-transaction",
                    txn
                )
                if (response != null && response.optBoolean("ok")) {
                    val serverId = response.optInt("server_id", 0)
                    db.markTransactionSynced(txn.getString("local_id"), serverId)
                    Log.i(TAG, "Synced transaction: ${txn.getString("local_id")} -> server #$serverId")
                } else {
                    Log.w(TAG, "Push failed for ${txn.getString("local_id")}: ${response?.optString("error")}")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Push error: ${e.message}")
            }
        }

        val remaining = db.getUnsyncedCount()
        db.logSync("push", "transactions", unsynced.length() - remaining, "completed")
        updateStatus(if (remaining > 0) SyncStatus.PARTIAL else SyncStatus.IDLE)
    }

    private suspend fun pushSyncQueue() {
        val items = db.getPendingSyncItems()
        if (items.length() == 0) return

        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            try {
                val payload = JSONObject(item.getString("payload"))
                val action = item.getString("action")
                val endpoint = "${session.getBaseUrl()}/api/pos/sync/$action"

                val response = httpPost(endpoint, payload)
                if (response != null && response.optBoolean("ok")) {
                    db.markSyncItemDone(item.getLong("id"))
                } else {
                    db.markSyncItemFailed(
                        item.getLong("id"),
                        response?.optString("error") ?: "Unknown error"
                    )
                }
            } catch (e: Exception) {
                db.markSyncItemFailed(item.getLong("id"), e.message ?: "Exception")
            }
        }
    }

    private suspend fun pullData() {
        updateStatus(SyncStatus.PULLING)
        Log.i(TAG, "Pulling data from server")

        try {
            val lastSync = db.getSetting("last_pull_at") ?: ""
            val params = if (lastSync.isNotBlank()) "?since=$lastSync" else ""

            val productsJson = httpGet("${session.getBaseUrl()}/api/pos/sync/pull-products$params")
            if (productsJson != null) {
                val products = productsJson.optJSONArray("products") ?: JSONArray()
                if (products.length() > 0) {
                    val count = db.upsertProducts(products)
                    db.logSync("pull", "products", count, "completed")
                    Log.i(TAG, "Pulled $count products")
                }
            }

            val customersJson = httpGet("${session.getBaseUrl()}/api/pos/sync/pull-customers$params")
            if (customersJson != null) {
                val customers = customersJson.optJSONArray("customers") ?: JSONArray()
                if (customers.length() > 0) {
                    val count = db.upsertCustomers(customers)
                    db.logSync("pull", "customers", count, "completed")
                    Log.i(TAG, "Pulled $count customers")
                }
            }

            db.setSetting("last_pull_at", now())
            updateStatus(SyncStatus.IDLE)
        } catch (e: Exception) {
            Log.e(TAG, "Pull failed: ${e.message}")
            db.logSync("pull", "all", 0, "failed", e.message)
            updateStatus(SyncStatus.ERROR)
        }
    }

    // --- HTTP helpers ---

    private fun httpPost(urlStr: String, body: JSONObject): JSONObject? {
        return try {
            val conn = URL(urlStr).openConnection() as HttpURLConnection
            conn.requestMethod = "POST"
            conn.setRequestProperty("Content-Type", "application/json")
            conn.setRequestProperty("Accept", "application/json")
            conn.connectTimeout = 15000
            conn.readTimeout = 15000
            conn.doOutput = true

            OutputStreamWriter(conn.outputStream).use { it.write(body.toString()) }

            val code = conn.responseCode
            val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            val responseText = BufferedReader(InputStreamReader(stream)).use { it.readText() }
            conn.disconnect()

            if (code in 200..299) JSONObject(responseText) else {
                Log.w(TAG, "HTTP $code: $responseText")
                null
            }
        } catch (e: Exception) {
            Log.e(TAG, "httpPost error: ${e.message}")
            null
        }
    }

    private fun httpGet(urlStr: String): JSONObject? {
        return try {
            val conn = URL(urlStr).openConnection() as HttpURLConnection
            conn.requestMethod = "GET"
            conn.setRequestProperty("Accept", "application/json")
            conn.connectTimeout = 15000
            conn.readTimeout = 15000

            val code = conn.responseCode
            val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            val responseText = BufferedReader(InputStreamReader(stream)).use { it.readText() }
            conn.disconnect()

            if (code in 200..299) JSONObject(responseText) else null
        } catch (e: Exception) {
            Log.e(TAG, "httpGet error: ${e.message}")
            null
        }
    }

    private fun updateStatus(status: SyncStatus) {
        lastSyncStatus = status
        onSyncStatusChanged?.invoke(status)
    }

    private fun now(): String {
        return java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", java.util.Locale.US)
            .apply { timeZone = java.util.TimeZone.getTimeZone("UTC") }
            .format(java.util.Date())
    }

    enum class SyncStatus {
        IDLE, PUSHING, PULLING, PARTIAL, ERROR
    }
}
