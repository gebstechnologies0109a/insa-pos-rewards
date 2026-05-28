package com.insapos.v2.sync

import android.util.Log
import com.insapos.v2.db.OfflineDatabase
import com.insapos.v2.network.models.PullPayload
import org.json.JSONArray
import org.json.JSONObject
import java.time.Instant

/**
 * Merges server pull deltas into SQLite with updated_at comparison inside a transaction.
 */
class LocalSyncMerger(private val db: OfflineDatabase) {

    companion object {
        private const val TAG = "LocalSyncMerger"
    }

    fun merge(pull: PullPayload): MergeResult {
        val json = pull.toJson()
        return mergeJson(json)
    }

    fun mergeJson(pull: JSONObject): MergeResult {
        var products = 0
        var categories = 0
        var customers = 0
        var batches = 0
        var alerts = 0

        val writable = db.writableDatabase
        writable.beginTransaction()
        try {
            pull.optJSONArray("products")?.let {
                products = db.upsertProducts(it)
            }
            pull.optJSONArray("categories")?.let {
                categories = db.upsertCategories(it)
            }
            pull.optJSONArray("customers")?.let {
                customers = db.upsertCustomers(it)
            }
            pull.optJSONArray("inventory_batches")?.let {
                batches = db.upsertInventoryBatches(it)
            }
            pull.optJSONArray("expiry_alerts")?.let {
                alerts = db.upsertExpiryAlerts(it)
            }
            when (val settings = pull.opt("settings")) {
                is JSONObject -> mergeSettings(settings)
                is JSONArray -> { /* legacy list — skip */ }
            }

            val ts = pull.optString("server_timestamp", pull.optString("pulled_at", ""))
            if (ts.isNotBlank()) {
                db.setSetting("inventory_last_sync", ts)
                db.setSetting("last_pull_at", ts)
            }

            writable.setTransactionSuccessful()
        } finally {
            writable.endTransaction()
        }

        Log.i(TAG, "Merged pull: products=$products categories=$categories customers=$customers batches=$batches alerts=$alerts")
        return MergeResult(products, categories, customers, batches, alerts)
    }

    private fun mergeSettings(settings: JSONObject) {
        val keys = settings.keys()
        while (keys.hasNext()) {
            val key = keys.next()
            val value = settings.opt(key)
            db.setSetting("pos_$key", value?.toString() ?: "")
        }
        db.setSetting("settings_merged_at", Instant.now().toString())
    }

    data class MergeResult(
        val products: Int,
        val categories: Int,
        val customers: Int,
        val batches: Int,
        val alerts: Int,
    )
}
