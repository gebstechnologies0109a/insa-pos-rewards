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

    fun getProducts(
        query: String? = null,
        limit: Int = OfflineDatabase.DEFAULT_PRODUCT_PAGE_SIZE,
        offset: Int = 0,
    ): JSONObject {
        val total = db.getProductCount()
        val products = if (!query.isNullOrBlank()) {
            db.searchProducts(query)
        } else {
            db.getProductsPage(offset, limit)
        }
        return JSONObject().apply {
            put("ok", true)
            put("products", products)
            put("count", products.length())
            put("total", total)
            put("offset", offset)
            put("limit", limit)
            put("has_more", offset + products.length() < total)
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
        val enriched = shift?.let { s ->
            val totals = db.aggregateShiftSales(s)
            JSONObject(s.toString()).apply {
                put("total_sales", totals.optDouble("total_sales", 0.0))
                put("transaction_count", totals.optInt("transaction_count", 0))
                put("discount_total", totals.optDouble("discount_total", 0.0))
                put("void_total", totals.optDouble("void_total", 0.0))
            }
        }
        return JSONObject().apply {
            put("ok", true)
            put("shift", enriched ?: JSONObject.NULL)
            put("active", shift != null)
        }
    }

    fun getShiftSalesTotal(): JSONObject {
        val shift = shifts.getStatus()
            ?: return JSONObject().apply {
                put("ok", false)
                put("error", "No active shift")
            }
        val totals = db.aggregateShiftSales(shift)
        return JSONObject().apply {
            put("ok", true)
            put("total_sales", totals.optDouble("total_sales", 0.0))
            put("transaction_count", totals.optInt("transaction_count", 0))
            put("discount_total", totals.optDouble("discount_total", 0.0))
            put("void_total", totals.optDouble("void_total", 0.0))
        }
    }

    fun getLocalXReading(cashierId: Int): JSONObject {
        val totals = db.getCashierTodayReadingStats(cashierId)
        return JSONObject().apply {
            put("ok", true)
            put("reading", JSONObject().apply {
                put("total_sales", totals.optDouble("total_sales", 0.0))
                put("transaction_count", totals.optInt("transaction_count", 0))
                put("discount_total", totals.optDouble("discount_total", 0.0))
                put("void_total", totals.optDouble("void_total", 0.0))
                put("generated_at", java.text.SimpleDateFormat(
                    "yyyy-MM-dd HH:mm:ss",
                    java.util.Locale.US,
                ).apply {
                    timeZone = java.util.TimeZone.getDefault()
                }.format(java.util.Date()))
                put("payment_breakdown", JSONObject())
                put("source", "local")
            })
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
            put("products", db.getProductCount())
            put("customers", db.getCustomerCount())
        }

    fun getStats(): JSONObject = db.getOfflineStats().apply { put("ok", true) }
}
