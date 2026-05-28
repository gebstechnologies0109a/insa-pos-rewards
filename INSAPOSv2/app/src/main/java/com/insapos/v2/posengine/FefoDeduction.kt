package com.insapos.v2.posengine

import com.insapos.v2.db.OfflineDatabase
import org.json.JSONArray
import org.json.JSONObject

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
        val dbWritable = db.writableDatabase
        dbWritable.beginTransaction()
        try {
            for (i in 0 until batches.length()) {
                if (remaining <= 0) break
                val batch = batches.getJSONObject(i)
                val available = batch.optDouble("qty", 0.0)
                if (available <= 0) continue
                val take = minOf(available, remaining)
                db.deductBatchQty(batch.getLong("id"), take)
                db.recordStockMovement(productId, -take, "sale", reference)
                remaining -= take
            }
            if (remaining > 0.0001) {
                dbWritable.endTransaction()
                return "Insufficient batch stock (short ${remaining})"
            }
            db.adjustProductStock(productId, -qty)
            dbWritable.setTransactionSuccessful()
            return null
        } finally {
            dbWritable.endTransaction()
        }
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
