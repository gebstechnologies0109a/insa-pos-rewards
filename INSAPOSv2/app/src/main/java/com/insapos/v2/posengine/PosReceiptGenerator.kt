package com.insapos.v2.posengine

import org.json.JSONArray
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

object PosReceiptGenerator {

    fun generate(sale: JSONObject, storeName: String = "INSA POS", branchName: String = "", cashier: String = ""): JSONObject {
        val items = parseItems(sale)
        val receiptItems = mutableListOf<ReceiptItem>()
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            receiptItems.add(
                ReceiptItem(
                    name = item.optString("product_name", item.optString("name", "Item")),
                    qty = item.optDouble("qty", item.optDouble("quantity", 1.0)),
                    price = item.optDouble("price", 0.0),
                    discount = item.optDouble("discount", 0.0),
                )
            )
        }

        val subtotal = sale.optDouble("subtotal", receiptItems.sumOf { it.lineTotal })
        val discount = sale.optDouble("discount", sale.optDouble("discount_total", 0.0))
        val total = sale.optDouble("total", subtotal - discount)
        val tendered = sale.optDouble("amount_tendered", total)
        val change = sale.optDouble("change_amount", sale.optDouble("change_due", (tendered - total).coerceAtLeast(0.0)))

        val template = ReceiptTemplate(
            storeName = storeName,
            branchName = branchName,
            cashierName = cashier,
            localId = sale.optString("local_id", "LOCAL"),
            items = receiptItems,
            subtotal = subtotal,
            discount = discount,
            total = total,
            amountTendered = tendered,
            change = change,
            paymentMethod = sale.optString("payment_method", "cash"),
            soldAt = sale.optString("created_at", formatNow()),
        )

        val text = template.toPlainText()
        return JSONObject().apply {
            put("text", text)
            put("json", sale.toString())
            put("html", "<pre>$text</pre>")
            put("template", JSONObject().put("local_id", template.localId))
        }
    }

    private fun parseItems(sale: JSONObject): JSONArray {
        sale.optJSONArray("items")?.let { if (it.length() > 0) return it }
        val raw = sale.optString("items_json", "")
        if (raw.isNotBlank()) {
            try { return JSONArray(raw) } catch (_: Exception) { }
        }
        return JSONArray()
    }

    private fun formatNow(): String =
        SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())
}
