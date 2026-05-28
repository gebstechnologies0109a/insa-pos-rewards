package com.insapos.v2.posengine

import com.insapos.v2.db.OfflineDatabase
import org.json.JSONArray
import org.json.JSONObject

/**
 * Local inventory reads and stock deductions against SQLite cache.
 */
class PosInventoryManager(private val db: OfflineDatabase) {

    fun getInventory(): JSONArray = db.getInventorySummary()

    fun getProductStock(productId: Int): Double = db.getProductStock(productId)

    fun deductStock(items: JSONArray): List<String> {
        val errors = mutableListOf<String>()
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            val productId = item.optInt("product_id", item.optInt("id", 0))
            val qty = item.optDouble("qty", item.optDouble("quantity", 0.0))
            if (productId <= 0 || qty <= 0) continue

            val available = db.getProductStock(productId)
            if (available < qty) {
                val name = item.optString("product_name", item.optString("name", "Product #$productId"))
                errors.add("Insufficient stock for $name (have $available, need $qty)")
                continue
            }
            db.adjustProductStock(productId, -qty)
            db.recordStockMovement(
                productId = productId,
                qty = -qty,
                movementType = "sale",
                reference = item.optString("local_id", ""),
            )
        }
        return errors
    }
}
