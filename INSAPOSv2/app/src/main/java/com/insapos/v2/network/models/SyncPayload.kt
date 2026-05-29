package com.insapos.v2.network.models

import org.json.JSONArray
import org.json.JSONObject

data class SyncPayload(
    val localId: String,
    val branchId: Int,
    val cashierId: Int,
    val paymentMethod: String,
    val amountTendered: Double,
    val items: List<SyncPayloadItem>,
    val subtotal: Double? = null,
    val discountTotal: Double? = null,
    val orderDiscount: Double? = null,
    val total: Double? = null,
    val shiftId: Int? = null,
    val memberId: Int? = null,
    val createdAt: String? = null,
    val deviceFingerprint: String? = null,
) {
    fun toJson(): JSONObject = JSONObject().apply {
        put("local_id", localId)
        put("branch_id", branchId)
        put("cashier_id", cashierId)
        put("payment_method", paymentMethod)
        put("amount_tendered", amountTendered)
        subtotal?.let { put("subtotal", it) }
        discountTotal?.let { put("discount_total", it) }
        orderDiscount?.let { put("order_discount", it) }
        total?.let { put("total", it) }
        shiftId?.let { put("shift_id", it) }
        memberId?.let { put("member_id", it) }
        createdAt?.let { put("created_at", it) }
        deviceFingerprint?.let { put("device_fingerprint", it) }
        put("items", JSONArray().apply {
            items.forEach { put(it.toJson()) }
        })
    }
}

data class SyncPayloadItem(
    val productId: Int,
    val productName: String,
    val qty: Double,
    val price: Double,
    val sku: String? = null,
    val barcode: String? = null,
    val discount: Double = 0.0,
) {
    fun toJson(): JSONObject = JSONObject().apply {
        put("product_id", productId)
        put("product_name", productName)
        put("qty", qty)
        put("price", price)
        put("discount", discount)
        put("sku", sku ?: JSONObject.NULL)
        put("barcode", barcode ?: JSONObject.NULL)
    }
}
