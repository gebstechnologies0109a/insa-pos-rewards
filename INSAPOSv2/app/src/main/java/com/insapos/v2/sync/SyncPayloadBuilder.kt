package com.insapos.v2.sync

import com.insapos.v2.SessionManager
import com.insapos.v2.db.OfflineDatabase
import com.insapos.v2.network.models.SyncPayload
import com.insapos.v2.network.models.SyncPayloadItem
import org.json.JSONArray
import org.json.JSONObject

class SyncPayloadBuilder(
    private val db: OfflineDatabase,
    private val session: SessionManager,
) {

    fun buildFromTransaction(txn: JSONObject): SyncPayload? {
        val localId = txn.optString("local_id", "").ifBlank { return null }
        val branchId = session.branchId ?: txn.optInt("branch_id", 0).takeIf { it > 0 } ?: return null
        val items = mapItems(parseItems(txn))
        if (items.isEmpty()) return null

        val cashierId = txn.optInt("cashier_id", 0).takeIf { it > 0 }
            ?: session.cashierId
            ?: db.getSetting("cashier_id")?.toIntOrNull()
            ?: return null

        return SyncPayload(
            localId = localId,
            branchId = branchId,
            cashierId = cashierId,
            paymentMethod = txn.optString("payment_method", "cash"),
            amountTendered = txn.optDouble("amount_tendered", txn.optDouble("total", 0.0)),
            items = items,
            shiftId = txn.optInt("shift_id", 0).takeIf { it > 0 },
            memberId = txn.optInt("member_id", 0).takeIf { it > 0 },
            createdAt = txn.optString("created_at", null),
        )
    }

    fun buildPushEnvelope(): JSONObject {
        val sales = JSONArray()
        val unsynced = db.getUnsyncedTransactions()
        for (i in 0 until unsynced.length()) {
            val txn = unsynced.getJSONObject(i)
            buildFromTransaction(txn)?.toJson()?.let { sales.put(it) }
        }
        return JSONObject().apply {
            put("sales", sales)
            put("schema_version", 1)
        }
    }

    private fun parseItems(txn: JSONObject): JSONArray {
        val raw = txn.optString("items_json", "")
        if (raw.isNotBlank()) {
            try { return JSONArray(raw) } catch (_: Exception) { }
        }
        return txn.optJSONArray("items") ?: JSONArray()
    }

    private fun mapItems(items: JSONArray): List<SyncPayloadItem> {
        val out = mutableListOf<SyncPayloadItem>()
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            out.add(
                SyncPayloadItem(
                    productId = item.optInt("product_id", item.optInt("id", 0)),
                    productName = item.optString("product_name", item.optString("name", "Item")),
                    qty = item.optDouble("qty", item.optDouble("quantity", 1.0)),
                    price = item.optDouble("price", 0.0),
                    sku = item.optString("sku", null),
                    barcode = item.optString("barcode", null),
                    discount = item.optDouble("discount", 0.0),
                )
            )
        }
        return out
    }
}
