package com.insapos.v2.posengine

import com.insapos.v2.db.OfflineDatabase
import org.json.JSONArray

/**
 * Local inventory reads and FEFO stock deductions against SQLite cache.
 */
class PosInventoryManager(private val db: OfflineDatabase) {

    private val fefo = FefoDeduction(db)

    fun getInventory(): JSONArray = db.getInventorySummary()

    fun getProductStock(productId: Int): Double = db.getProductStock(productId)

    fun deductStock(items: JSONArray): List<String> = fefo.deductItems(items)
}
