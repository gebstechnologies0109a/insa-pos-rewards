package com.insapos.v2.sync

import android.content.Context
import android.util.Log
import com.insapos.v2.BuildConfig
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
import java.net.URLEncoder

class SyncEngine(
    private val context: Context,
    private val db: OfflineDatabase,
    private val session: SessionManager,
    private val connectivity: ConnectivityMonitor,
    private val cookieProvider: () -> String? = { null }
) {
    companion object {
        private const val TAG = "SyncEngine"
        private const val PUSH_INTERVAL_ACTIVE_MS = 12_000L
        private const val PUSH_INTERVAL_IDLE_MS = 15_000L
        private const val PUSH_FAIL_BACKOFF_BASE_MS = 5_000L
        private const val PUSH_FAIL_BACKOFF_MAX_MS = 300_000L
        private const val MAX_PUSH_PER_CYCLE = 25
        private const val MAX_BATCH_PUSH = 25
        private const val PULL_INTERVAL_MS = 300_000L
        private const val STARTUP_PULL_DELAY_MS = 8_000L
        private const val KEY_CATALOG_LAST_SYNC = "catalog_last_sync"
        private const val KEY_CATALOG_SYNCED_AT = "catalog_synced_at"
    }

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var pushJob: Job? = null
    private var pullJob: Job? = null
    private var consecutivePushFailures = 0
    private var lastConflict: JSONObject? = null

    var onSyncStatusChanged: ((SyncStatus) -> Unit)? = null
    var onDownloadProgress: ((DownloadProgress) -> Unit)? = null
    var onConflict: ((JSONObject) -> Unit)? = null

    @Volatile
    var lastSyncStatus: SyncStatus = SyncStatus.IDLE
        private set

    fun getStatusJson(): JSONObject {
        return JSONObject().apply {
            put("status", lastSyncStatus.name)
            put("unsynced_count", db.getUnsyncedCount())
            put("sync_queue_count", db.getSyncQueueCount())
            put("consecutive_failures", consecutivePushFailures)
            put("last_conflict", lastConflict ?: JSONObject.NULL)
            put("online", connectivity.isConnected())
        }
    }

    data class DownloadProgress(
        val phase: String,
        val percent: Int,
        val message: String
    )

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
        syncNowIncremental()
    }

    /** Full catalog download — manual refresh or first login only. */
    fun syncNowFull() {
        scope.launch {
            emitDownloadProgress("products", 5, "Downloading products…")
            pushTransactions()
            pushSyncQueue()
            pullData(fullSync = true, forceCatalog = true)
            emitDownloadProgress("done", 100, "Store data ready")
        }
    }

    /** Push queued sales + delta inventory only (routine / startup). */
    fun syncNowIncremental() {
        scope.launch {
            pushTransactions()
            pushSyncQueue()
            pullData(fullSync = false, forceCatalog = false)
        }
    }

    private fun startPushLoop() {
        pushJob = scope.launch {
            while (isActive) {
                var hadWork = false
                if (connectivity.isConnected()) {
                    hadWork = pushTransactions() || pushSyncQueue()
                }
                val delayMs = when {
                    consecutivePushFailures >= 1 -> {
                        minOf(
                            PUSH_FAIL_BACKOFF_BASE_MS * (1 shl minOf(consecutivePushFailures, 6)),
                            PUSH_FAIL_BACKOFF_MAX_MS
                        )
                    }
                    hadWork -> PUSH_INTERVAL_ACTIVE_MS
                    else -> PUSH_INTERVAL_IDLE_MS
                }
                delay(delayMs)
            }
        }
    }

    private fun startPullLoop() {
        pullJob = scope.launch {
            delay(STARTUP_PULL_DELAY_MS)
            while (isActive) {
                if (connectivity.isConnected()) {
                    pullData(fullSync = false, forceCatalog = false)
                }
                delay(PULL_INTERVAL_MS)
            }
        }
    }

    private suspend fun pushTransactions(): Boolean {
        val unsynced = db.getUnsyncedTransactions()
        if (unsynced.length() == 0) return false

        updateStatus(SyncStatus.PUSHING)
        if (BuildConfig.DEBUG) {
            Log.i(TAG, "Pushing ${unsynced.length()} unsynced transactions")
        }

        var synced = 0
        var failures = 0
        val batchSize = minOf(unsynced.length(), MAX_PUSH_PER_CYCLE)
        for (i in 0 until batchSize) {
            val txn = unsynced.getJSONObject(i)
            val localId = txn.optString("local_id", "")
            val payload = resolvePushBody(txn)
                ?: db.getSyncQueuePayloadForLocalId(localId)?.let { resolvePushBody(it) }
            if (payload == null) {
                Log.w(TAG, "Skipping push for $localId — could not build payload (missing branch/items?)")
                continue
            }
            try {
                val response = httpPost(
                    "${session.getBaseUrl()}/api/pos/sync/push",
                    payload
                )
                if (response != null && response.optBoolean("success")) {
                    val serverId = response.optInt("server_id", response.optJSONObject("sale")?.optInt("id", 0) ?: 0)
                    db.markTransactionSynced(txn.getString("local_id"), serverId)
                    synced++
                    Log.i(TAG, "Synced transaction: ${txn.getString("local_id")} -> server #$serverId")
                } else if (response != null && SyncConflictResolver.hasBlockingConflicts(response)) {
                    lastConflict = response
                    onConflict?.invoke(response)
                    failures++
                    Log.w(TAG, "Conflict for ${txn.optString("local_id")}")
                } else {
                    failures++
                    Log.w(TAG, "Push failed for ${txn.optString("local_id")}: ${response?.optString("message")}")
                }
            } catch (e: Exception) {
                failures++
                Log.e(TAG, "Push error: ${e.message}")
            }
        }

        consecutivePushFailures = if (failures > 0 && synced == 0) {
            consecutivePushFailures + failures
        } else if (synced > 0) {
            0
        } else {
            consecutivePushFailures
        }

        val remaining = db.getUnsyncedCount()
        db.logSync("push", "transactions", synced, "completed")
        updateStatus(if (remaining > 0) SyncStatus.PARTIAL else SyncStatus.IDLE)
        return synced > 0
    }

    private suspend fun pushSyncQueue(): Boolean {
        val items = db.getPendingSyncItems()
        if (items.length() == 0) return false

        var processed = 0
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            try {
                val payload = JSONObject(item.getString("payload"))
                val action = item.getString("action")
                val endpoint = when (action) {
                    "push-transaction", "transaction_push" ->
                        "${session.getBaseUrl()}/api/pos/sync/push"
                    else -> "${session.getBaseUrl()}/api/pos/sync/$action"
                }

                val body = if (action == "push-transaction" || action == "transaction_push") {
                    resolvePushBody(payload)
                } else {
                    payload
                }
                if (body == null) {
                    val recordId = item.optString("record_id", payload.optString("local_id", ""))
                    Log.w(TAG, "Skipping sync_queue #${item.getLong("id")} ($recordId) — could not build payload")
                    continue
                }

                val response = httpPost(endpoint, body)
                if (response != null && (response.optBoolean("success") || response.optBoolean("ok"))) {
                    val localId = body.optString("local_id", item.optString("record_id", ""))
                    if (localId.isNotBlank()) {
                        val serverId = response.optInt(
                            "server_id",
                            response.optJSONObject("sale")?.optInt("id", 0) ?: 0
                        )
                        db.markTransactionSynced(localId, serverId)
                    }
                    db.markSyncItemDone(item.getLong("id"))
                    processed++
                } else if (response != null && SyncConflictResolver.hasBlockingConflicts(response)) {
                    lastConflict = response
                    onConflict?.invoke(response)
                    db.markSyncItemFailed(
                        item.getLong("id"),
                        response.optString("message", "Price conflicts detected. Please review.")
                    )
                } else {
                    val err = response?.optString("message")
                        ?: response?.optString("error")
                        ?: "Unknown error"
                    db.markSyncItemFailed(item.getLong("id"), err)
                }
            } catch (e: Exception) {
                db.markSyncItemFailed(item.getLong("id"), e.message ?: "Exception")
            }
        }
        return processed > 0
    }

    private suspend fun pullData(fullSync: Boolean, forceCatalog: Boolean = fullSync) {
        if (!connectivity.isConnected()) {
            updateStatus(SyncStatus.IDLE)
            return
        }

        updateStatus(SyncStatus.PULLING)
        if (BuildConfig.DEBUG) {
            Log.i(TAG, "Pulling data (fullSync=$fullSync forceCatalog=$forceCatalog)")
        }

        try {
            val branchId = session.branchId
            if (branchId == null) {
                Log.w(TAG, "Skipping pull — branch_id not set (call INSAPOS.setBranchId from cashier)")
                updateStatus(SyncStatus.IDLE)
                return
            }

            val needsCatalog = forceCatalog || fullSync || isCatalogStale(branchId)
            if (needsCatalog) {
                emitDownloadProgress("products", 15, "Downloading products…")
                pullProductCatalog(branchId)
                val syncedAt = now()
                db.setSetting(KEY_CATALOG_LAST_SYNC, syncedAt)
                db.setSetting(KEY_CATALOG_SYNCED_AT, syncedAt)
                emitDownloadProgress("customers", 80, "Downloading customers…")
                pullCustomers()
            } else if (BuildConfig.DEBUG) {
                Log.d(TAG, "Skipping product catalog pull — already synced for branch $branchId")
            }

            emitDownloadProgress("inventory", 55, "Downloading stock levels…")
            pullInventory(branchId, fullSync)

            markCacheReady(branchId)

            updateStatus(SyncStatus.IDLE)
        } catch (e: Exception) {
            Log.e(TAG, "Pull failed: ${e.message}")
            db.logSync("pull", "all", 0, "failed", e.message)
            updateStatus(SyncStatus.ERROR)
        }
    }

    private suspend fun pullProductCatalog(branchId: Int) {
        val url = "${session.getBaseUrl()}/api/pos/products/all?branch_id=" +
            URLEncoder.encode(branchId.toString(), "UTF-8")
        val json = httpGet(url) ?: return
        val products = json.optJSONArray("products") ?: JSONArray()
        if (products.length() > 0) {
            val count = db.upsertProducts(products)
            db.logSync("pull", "products", count, "completed")
            Log.i(TAG, "Pulled $count products from catalog")
        }
        val categories = json.optJSONArray("categories") ?: JSONArray()
        if (categories.length() > 0) {
            db.upsertCategories(categories)
        }
    }

    private suspend fun pullInventory(branchId: Int, fullSync: Boolean) {
        val lastSync = if (fullSync) "" else {
            db.getSetting("inventory_last_sync") ?: db.getSetting("last_pull_at") ?: ""
        }
        val params = buildString {
            append("?branch_id=").append(URLEncoder.encode(branchId.toString(), "UTF-8"))
            if (lastSync.isNotBlank()) {
                append("&since=").append(URLEncoder.encode(lastSync, "UTF-8"))
            }
        }

        val productsJson = httpGet("${session.getBaseUrl()}/api/pos/sync/pull$params")
        if (productsJson != null && productsJson.optBoolean("success", true)) {
            val result = merger.mergeJson(productsJson)
            db.logSync("pull", "inventory", result.products + result.batches, "completed")
            Log.i(TAG, "Merged pull: products=${result.products} batches=${result.batches}")
        }
    }

    private suspend fun pullCustomers() {
        val customersJson = httpGet("${session.getBaseUrl()}/api/pos/customers/all")
        if (customersJson != null) {
            val customers = customersJson.optJSONArray("customers") ?: JSONArray()
            if (customers.length() > 0) {
                val count = db.upsertCustomers(customers)
                db.logSync("pull", "customers", count, "completed")
                Log.i(TAG, "Pulled $count customers")
            }
        }
    }

    private val payloadBuilder by lazy { SyncPayloadBuilder(db, session) }
    private val merger by lazy { LocalSyncMerger(db) }

    private fun buildPushPayload(txn: JSONObject): JSONObject? =
        payloadBuilder.buildFromTransaction(txn)?.toJson()

    private fun resolvePushBody(raw: JSONObject): JSONObject? {
        val enriched = payloadBuilder.enrichTransaction(raw)
        return buildPushPayload(enriched) ?: payloadBuilder.normalizeRawPayload(enriched)
    }

    private fun parseItems(txn: JSONObject): JSONArray {
        val raw = txn.optString("items_json", "")
        if (raw.isNotBlank()) {
            try {
                return JSONArray(raw)
            } catch (_: Exception) {
            }
        }
        return txn.optJSONArray("items") ?: JSONArray()
    }

    private fun mapItemsForPush(items: JSONArray): JSONArray {
        val out = JSONArray()
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            out.put(JSONObject().apply {
                put("product_id", item.optInt("product_id", item.optInt("id", 0)))
                put("product_name", item.optString("product_name", item.optString("name", "Item")))
                put("sku", if (item.isNull("sku")) JSONObject.NULL else item.getString("sku"))
                put("barcode", if (item.isNull("barcode")) JSONObject.NULL else item.getString("barcode"))
                put("qty", item.optDouble("qty", item.optDouble("quantity", 1.0)))
                put("price", item.optDouble("price", 0.0))
                put("discount", item.optDouble("discount", 0.0))
            })
        }
        return out
    }

    private fun applyRequestHeaders(conn: HttpURLConnection) {
        conn.setRequestProperty("Accept", "application/json")
        cookieProvider()?.let { cookies ->
            if (cookies.isNotBlank()) {
                conn.setRequestProperty("Cookie", cookies)
            }
        }
    }

    private fun httpPost(urlStr: String, body: JSONObject): JSONObject? {
        return try {
            val conn = URL(urlStr).openConnection() as HttpURLConnection
            conn.requestMethod = "POST"
            conn.setRequestProperty("Content-Type", "application/json")
            applyRequestHeaders(conn)
            conn.connectTimeout = 15000
            conn.readTimeout = 15000
            conn.doOutput = true

            OutputStreamWriter(conn.outputStream).use { it.write(body.toString()) }

            readJsonResponse(conn)
        } catch (e: Exception) {
            Log.e(TAG, "httpPost error: ${e.message ?: e.javaClass.simpleName}", e)
            null
        }
    }

    private fun httpGet(urlStr: String): JSONObject? {
        return try {
            val conn = URL(urlStr).openConnection() as HttpURLConnection
            conn.requestMethod = "GET"
            applyRequestHeaders(conn)
            conn.connectTimeout = 15000
            conn.readTimeout = 30000

            readJsonResponse(conn)
        } catch (e: Exception) {
            Log.e(TAG, "httpGet error: ${e.message}")
            null
        }
    }

    private fun readJsonResponse(conn: HttpURLConnection): JSONObject? {
        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
        val responseText = BufferedReader(InputStreamReader(stream)).use { it.readText() }
        conn.disconnect()

        return try {
            val json = JSONObject(responseText)
            if (code in 200..299) {
                json
            } else {
                Log.w(TAG, "HTTP $code: $responseText")
                json.put("_http_status", code)
                json
            }
        } catch (_: Exception) {
            Log.w(TAG, "HTTP $code (non-JSON): $responseText")
            null
        }
    }

    /** True when catalog was never synced for this branch (first login / branch change). */
    private fun isCatalogStale(branchId: Int): Boolean {
        if (db.getProducts().length() == 0) return true
        val readyBranch = db.getSetting("cache_ready_branch_id")?.toIntOrNull()
        if (readyBranch != branchId) return true
        val syncedAt = db.getSetting(KEY_CATALOG_SYNCED_AT)
            ?: db.getSetting(KEY_CATALOG_LAST_SYNC)
            ?: db.getSetting("cache_ready_at")
        return syncedAt.isNullOrBlank()
    }

    private fun markCacheReady(branchId: Int) {
        val count = db.getProducts().length()
        if (count > 0) {
            db.setSetting("cache_ready", "1")
            db.setSetting("cache_ready_branch_id", branchId.toString())
            db.setSetting("cache_ready_at", now())
            if (db.getSetting(KEY_CATALOG_LAST_SYNC).isNullOrBlank()) {
                db.setSetting(KEY_CATALOG_LAST_SYNC, now())
            }
            Log.i(TAG, "Offline cache ready ($count products)")
        }
    }

    private fun emitDownloadProgress(phase: String, percent: Int, message: String) {
        onDownloadProgress?.invoke(DownloadProgress(phase, percent, message))
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
