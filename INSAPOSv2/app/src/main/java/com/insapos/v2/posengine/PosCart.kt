package com.insapos.v2.posengine

import org.json.JSONArray
import org.json.JSONObject

/**
 * In-memory cart for a POS session. Persisted to SQLite cart table on demand.
 */
class PosCart(private val sessionId: String) {

    private val items = mutableListOf<CartItem>()

    data class CartItem(
        val productId: Int,
        val productName: String,
        val barcode: String?,
        val sku: String?,
        val qty: Double,
        val price: Double,
        val discount: Double = 0.0,
    )

    fun clear() {
        items.clear()
    }

    fun addItem(item: CartItem) {
        val existing = items.indexOfFirst { it.productId == item.productId && it.price == item.price }
        if (existing >= 0) {
            val cur = items[existing]
            items[existing] = cur.copy(qty = cur.qty + item.qty)
        } else {
            items.add(item)
        }
    }

    fun removeAt(index: Int) {
        if (index in items.indices) items.removeAt(index)
    }

    fun subtotal(): Double = items.sumOf { it.qty * it.price }

    fun discountTotal(): Double = items.sumOf { it.discount }

    fun total(): Double = subtotal() - discountTotal()

    fun toItemsJson(): JSONArray {
        val arr = JSONArray()
        for (item in items) {
            arr.put(JSONObject().apply {
                put("product_id", item.productId)
                put("product_name", item.productName)
                put("name", item.productName)
                put("barcode", item.barcode ?: JSONObject.NULL)
                put("sku", item.sku ?: JSONObject.NULL)
                put("qty", item.qty)
                put("quantity", item.qty)
                put("price", item.price)
                put("discount", item.discount)
            })
        }
        return arr
    }

    fun toJson(): JSONObject = JSONObject().apply {
        put("session_id", sessionId)
        put("items", toItemsJson())
        put("subtotal", subtotal())
        put("discount", discountTotal())
        put("total", total())
        put("count", items.size)
    }
}
