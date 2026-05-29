package com.insapos.v2.posengine

import android.content.ContentValues
import com.insapos.v2.db.OfflineDatabase
import org.json.JSONArray
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.util.UUID

/**
 * FEFO stock deduction against local inventory_batches cache.
 */
class FefoDeduction(private val db: OfflineDatabase) {

    data class Allocation(val batchId: Long, val productId: Int, val qty: Double)

    /**
     * @return error message or null on success
     */
    fun deduct(productId: Int, qty: Double, reference: String): String? {
        if (qty <= 0) return null

        val batches = db.getFefoBatches(productId)
        if (batches.length() == 0) {
            val stock = db.getProductStock(productId)
            return if (stock < qty) "Insufficient stock (have $stock, need $qty)" else run {
                db.adjustProductStock(productId, -qty)
                db.recordStockMovement(productId, -qty, "sale", reference)
                null
            }
        }

        var remaining = qty
        var stockError: String? = null
        val ts = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.US)
            .apply { timeZone = TimeZone.getTimeZone("UTC") }
            .format(Date())
        val committed = db.runInTransaction { sql ->
            for (i in 0 until batches.length()) {
                if (remaining <= 0) break
                val batch = batches.getJSONObject(i)
                val available = batch.optDouble("qty", 0.0)
                if (available <= 0) continue
                val take = minOf(available, remaining)
                sql.execSQL(
                    "UPDATE inventory_batches SET qty = MAX(0, qty - ?) WHERE id = ?",
                    arrayOf(take, batch.getLong("id").toString())
                )
                val cv = ContentValues().apply {
                    put("local_id", UUID.randomUUID().toString())
                    put("product_id", productId)
                    put("qty", -take)
                    put("movement_type", "sale")
                    put("reference", reference)
                    put("synced", 0)
                    put("created_at", ts)
                }
                sql.insert("stock_movements", null, cv)
                remaining -= take
            }
            if (remaining > 0.0001) {
                stockError = "Insufficient batch stock (short $remaining)"
                return@runInTransaction false
            }
            sql.execSQL(
                "UPDATE products SET stock = MAX(0, stock + ?), updated_at = ? WHERE server_id = ? OR id = ?",
                arrayOf(-qty, ts, productId.toString(), productId.toString())
            )
            true
        }
        return if (committed) null else stockError
    }

    fun deductItems(items: JSONArray): List<String> {
        val errors = mutableListOf<String>()
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            val productId = item.optInt("product_id", item.optInt("id", 0))
            val qty = item.optDouble("qty", item.optDouble("quantity", 0.0))
            if (productId <= 0 || qty <= 0) continue
            val name = item.optString("product_name", item.optString("name", "Product #$productId"))
            val err = deduct(productId, qty, item.optString("local_id", ""))
            if (err != null) errors.add("$name: $err")
        }
        return errors
    }
}
