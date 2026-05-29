package com.insapos.v2.sync

import android.database.sqlite.SQLiteDatabase
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

        db.runInTransaction { sqlite ->
            pull.optJSONArray("products")?.let { arr ->
                products = db.upsertProductsInTransaction(sqlite, arr, 0, arr.length())
            }
            pull.optJSONArray("categories")?.let { arr ->
                categories = db.upsertCategoriesInTransaction(sqlite, arr)
            }
            pull.optJSONArray("customers")?.let { arr ->
                customers = db.upsertCustomersInTransaction(sqlite, arr, 0, arr.length())
            }
            pull.optJSONArray("inventory_batches")?.let { arr ->
                batches = db.upsertInventoryBatchesInTransaction(sqlite, arr)
            }
            pull.optJSONArray("expiry_alerts")?.let { arr ->
                alerts = db.upsertExpiryAlertsInTransaction(sqlite, arr)
            }
            when (val settings = pull.opt("settings")) {
                is JSONObject -> mergeSettingsInTransaction(sqlite, settings)
                is JSONArray -> { /* legacy list — skip */ }
            }

            val ts = pull.optString("server_timestamp", pull.optString("pulled_at", ""))
            if (ts.isNotBlank()) {
                db.setSettingInTransaction(sqlite, "inventory_last_sync", ts)
                db.setSettingInTransaction(sqlite, "last_pull_at", ts)
            }
            true
        }

        Log.i(TAG, "Merged pull: products=$products categories=$categories customers=$customers batches=$batches alerts=$alerts")
        return MergeResult(products, categories, customers, batches, alerts)
    }

    private fun mergeSettingsInTransaction(sqlite: SQLiteDatabase, settings: JSONObject) {
        val keys = settings.keys()
        while (keys.hasNext()) {
            val key = keys.next()
            val value = settings.opt(key)
            db.setSettingInTransaction(sqlite, "pos_$key", value?.toString() ?: "")
        }
        db.setSettingInTransaction(sqlite, "settings_merged_at", Instant.now().toString())
    }

    data class MergeResult(
        val products: Int,
        val categories: Int,
        val customers: Int,
        val batches: Int,
        val alerts: Int,
    )
}
