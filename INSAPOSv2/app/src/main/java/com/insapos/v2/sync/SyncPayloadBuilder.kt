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
        val enriched = enrichTransaction(txn)
        val localId = enriched.optString("local_id", "").ifBlank { return null }
        val branchId = session.branchId
            ?: enriched.optInt("branch_id", 0).takeIf { it > 0 }
            ?: return null
        val items = mapItems(parseItems(enriched))
        if (items.isEmpty()) return null

        val cashierId = enriched.optInt("cashier_id", 0).takeIf { it > 0 }
            ?: session.cashierId
            ?: db.getSetting("cashier_id")?.toIntOrNull()
            ?: resolveCashierIdFromQueue(localId)
            ?: 1

        val itemDiscountSum = items.sumOf { it.discount }
        val discountTotal = enriched.optDouble(
            "discount_total",
            enriched.optDouble("discount", 0.0),
        )
        val orderDiscount = enriched.optDouble("order_discount", -1.0).takeIf { it >= 0 }
            ?: (discountTotal - itemDiscountSum).coerceAtLeast(0.0)

        val subtotal = enriched.optDouble("subtotal", -1.0).takeIf { it >= 0 }
            ?: items.sumOf { it.qty * it.price }

        val total = enriched.optDouble("total", -1.0).takeIf { it >= 0 }
            ?: (subtotal - discountTotal).coerceAtLeast(0.0)

        return SyncPayload(
            localId = localId,
            branchId = branchId,
            cashierId = cashierId,
            paymentMethod = enriched.optString("payment_method", "cash"),
            amountTendered = enriched.optDouble("amount_tendered", total),
            items = items,
            subtotal = subtotal,
            discountTotal = discountTotal,
            orderDiscount = orderDiscount,
            total = total,
            shiftId = enriched.optInt("shift_id", 0).takeIf { it > 0 },
            memberId = enriched.optInt("member_id", enriched.optInt("customer_id", 0)).takeIf { it > 0 },
            createdAt = enriched.optString("created_at", null),
        )
    }

    /** Merge missing branch/cashier/shift from sync_queue or pos_sales for legacy rows. */
    fun enrichTransaction(txn: JSONObject): JSONObject {
        val localId = txn.optString("local_id", "")
        if (localId.isBlank()) return txn

        val out = JSONObject(txn.toString())
        var needsQueue = out.optInt("branch_id", 0) <= 0 ||
            out.optInt("cashier_id", 0) <= 0 ||
            out.optInt("shift_id", 0) <= 0

        if (needsQueue) {
            db.getSyncQueuePayloadForLocalId(localId)?.let { queue ->
                mergeContext(out, queue)
            }
            needsQueue = out.optInt("branch_id", 0) <= 0 ||
                out.optInt("cashier_id", 0) <= 0 ||
                out.optInt("shift_id", 0) <= 0
        }

        if (needsQueue) {
            db.getPosSaleContext(localId)?.let { pos ->
                mergeContext(out, pos)
            }
        }

        return out
    }

    private fun resolveCashierIdFromQueue(localId: String): Int? =
        db.getSyncQueuePayloadForLocalId(localId)
            ?.optInt("cashier_id", 0)
            ?.takeIf { it > 0 }

    private fun mergeContext(target: JSONObject, source: JSONObject) {
        if (target.optInt("branch_id", 0) <= 0) {
            source.optInt("branch_id", 0).takeIf { it > 0 }?.let { target.put("branch_id", it) }
        }
        if (target.optInt("cashier_id", 0) <= 0) {
            source.optInt("cashier_id", 0).takeIf { it > 0 }?.let { target.put("cashier_id", it) }
        }
        if (target.optInt("shift_id", 0) <= 0) {
            source.optInt("shift_id", 0).takeIf { it > 0 }?.let { target.put("shift_id", it) }
        }
        if (target.optInt("member_id", 0) <= 0) {
            source.optInt("member_id", 0).takeIf { it > 0 }?.let { target.put("member_id", it) }
        }
    }

    /** Queue / legacy rows store line items in `items_json` instead of `items`. */
    fun normalizeRawPayload(raw: JSONObject): JSONObject? {
        return buildFromTransaction(raw)?.toJson()
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
