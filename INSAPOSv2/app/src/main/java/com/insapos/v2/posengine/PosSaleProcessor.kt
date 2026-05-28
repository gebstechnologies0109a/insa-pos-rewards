package com.insapos.v2.posengine

import com.insapos.v2.db.OfflineDatabase
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

class PosSaleProcessor(
    private val db: OfflineDatabase,
    private val inventory: PosInventoryManager,
    private val receiptGenerator: PosReceiptGenerator = PosReceiptGenerator,
) {

    fun createSale(payload: JSONObject): JSONObject {
        val localId = payload.optString("local_id", "").ifBlank { UUID.randomUUID().toString() }
        val items = parseItems(payload)
        if (items.length() == 0) {
            return JSONObject().apply {
                put("ok", false)
                put("error", "No items in sale")
            }
        }

        val stockErrors = inventory.deductStock(items)
        if (stockErrors.isNotEmpty()) {
            return JSONObject().apply {
                put("ok", false)
                put("error", stockErrors.joinToString("; "))
                put("stock_errors", JSONArray(stockErrors))
            }
        }

        val subtotal = sumSubtotal(items)
        val discount = payload.optDouble("discount_total", payload.optDouble("discount", 0.0))
        val total = payload.optDouble("total", subtotal - discount)
        val tendered = payload.optDouble("amount_tendered", total)
        val change = payload.optDouble("change_due", payload.optDouble("change_amount", (tendered - total).coerceAtLeast(0.0)))

        val txn = JSONObject().apply {
            put("local_id", localId)
            put("branch_id", payload.optInt("branch_id", 0))
            put("shift_id", payload.optInt("shift_id", 0))
            put("cashier_id", payload.optInt("cashier_id", 0))
            put("member_id", payload.optInt("member_id", 0))
            put("items_json", items.toString())
            put("items", items)
            put("subtotal", subtotal)
            put("discount", discount)
            put("discount_total", discount)
            put("tax", payload.optDouble("tax", 0.0))
            put("total", total)
            put("payment_method", payload.optString("payment_method", "cash"))
            put("amount_tendered", tendered)
            put("change_amount", change)
            put("change_due", change)
            put("cashier_name", payload.optString("cashier_name", ""))
            put("notes", payload.optString("notes", ""))
            put("created_at", payload.optString("created_at", now()))
            put("status", "completed")
        }

        db.savePosSale(txn)
        db.saveTransaction(txn)
        db.enqueueSyncAction("push-transaction", "transactions_local", localId, txn)

        val receipt = receiptGenerator.generate(
            txn,
            storeName = payload.optString("store_name", "INSA POS"),
            branchName = payload.optString("branch_name", ""),
            cashier = payload.optString("cashier_name", ""),
        )
        db.saveReceipt(localId, receipt.optString("json", txn.toString()), receipt.optString("text", ""), receipt.optString("html", ""))

        return JSONObject().apply {
            put("ok", true)
            put("local_id", localId)
            put("sale", txn)
            put("receipt", receipt)
            put("offline", true)
        }
    }

    fun getReceipt(localId: String): JSONObject? {
        val receipt = db.getReceipt(localId) ?: return null
        return JSONObject().apply {
            put("ok", true)
            put("local_id", localId)
            put("receipt", receipt)
        }
    }

    private fun parseItems(payload: JSONObject): JSONArray {
        payload.optJSONArray("items")?.let { if (it.length() > 0) return it }
        val raw = payload.optString("items_json", "")
        if (raw.isNotBlank()) {
            try { return JSONArray(raw) } catch (_: Exception) { }
        }
        return JSONArray()
    }

    private fun sumSubtotal(items: JSONArray): Double {
        var sum = 0.0
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            val qty = item.optDouble("qty", item.optDouble("quantity", 1.0))
            val price = item.optDouble("price", 0.0)
            sum += qty * price
        }
        return sum
    }

    private fun now(): String =
        java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", java.util.Locale.US)
            .apply { timeZone = java.util.TimeZone.getTimeZone("UTC") }
            .format(java.util.Date())
}
