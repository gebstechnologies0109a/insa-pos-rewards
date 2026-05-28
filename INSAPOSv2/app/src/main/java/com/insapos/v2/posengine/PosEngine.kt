package com.insapos.v2.posengine

import com.insapos.v2.db.OfflineDatabase
import org.json.JSONArray
import org.json.JSONObject

/**
 * Facade for local POS operations — all reads/writes go through SQLite.
 */
class PosEngine(private val db: OfflineDatabase) {

    val inventory = PosInventoryManager(db)
    val shifts = PosShiftManager(db)
    private val sales = PosSaleProcessor(db, inventory)

    fun getProducts(query: String? = null): JSONObject {
        val products = if (!query.isNullOrBlank()) db.searchProducts(query) else db.getProducts()
        return JSONObject().apply {
            put("ok", true)
            put("products", products)
            put("count", products.length())
        }
    }

    fun getCustomers(): JSONObject {
        val customers = db.getCustomers()
        return JSONObject().apply {
            put("ok", true)
            put("customers", customers)
            put("count", customers.length())
        }
    }

    fun getInventory(): JSONObject {
        val inv = inventory.getInventory()
        return JSONObject().apply {
            put("ok", true)
            put("inventory", inv)
            put("count", inv.length())
        }
    }

    fun createSale(payload: JSONObject): JSONObject = sales.createSale(payload)

    fun getReceipt(localId: String): JSONObject? = sales.getReceipt(localId)

    fun getShiftStatus(): JSONObject {
        val shift = shifts.getStatus()
        return JSONObject().apply {
            put("ok", true)
            put("shift", shift ?: JSONObject.NULL)
            put("active", shift != null)
        }
    }

    fun openShift(cashierId: Int, branchId: Int, openingCash: Double): JSONObject =
        shifts.openShift(cashierId, branchId, openingCash)

    fun closeShift(closingCash: Double): JSONObject = shifts.closeShift(closingCash)

    fun getSyncStatus(unsynced: Int, queueCount: Int, engineStatus: String): JSONObject =
        JSONObject().apply {
            put("ok", true)
            put("status", engineStatus)
            put("unsynced_count", unsynced)
            put("sync_queue_count", queueCount)
            put("products", db.getProducts().length())
            put("customers", db.getCustomers().length())
        }

    fun getStats(): JSONObject = db.getOfflineStats().apply { put("ok", true) }
}
