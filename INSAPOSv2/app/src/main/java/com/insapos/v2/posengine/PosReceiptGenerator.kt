package com.insapos.v2.posengine

import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

object PosReceiptGenerator {

    fun generate(sale: JSONObject, storeName: String = "INSA POS", branchName: String = "", cashier: String = ""): JSONObject {
        val items = sale.optJSONArray("items") ?: sale.optJSONArray("items_json")?.let {
            try { org.json.JSONArray(it.toString()) } catch (_: Exception) { null }
        } ?: org.json.JSONArray()

        val lines = mutableListOf<String>()
        val div = "================================"
        lines.add(div)
        lines.add(storeName.centered(32))
        if (branchName.isNotBlank()) lines.add(branchName.centered(32))
        lines.add(div)
        if (cashier.isNotBlank()) lines.add("Cashier: $cashier")
        lines.add("Date: ${formatNow()}")
        lines.add("Sale: ${sale.optString("local_id", "LOCAL")}")
        lines.add(div)

        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            val name = item.optString("product_name", item.optString("name", "Item"))
            val qty = item.optDouble("qty", item.optDouble("quantity", 1.0))
            val price = item.optDouble("price", 0.0)
            val lineTotal = qty * price
            lines.add(name.take(32))
            lines.add("  ${qty} x ${fmt(price)} = ${fmt(lineTotal)}")
        }

        lines.add(div)
        lines.add("Subtotal:".padEnd(20) + fmt(sale.optDouble("subtotal", 0.0)).padStart(12))
        lines.add("Discount:".padEnd(20) + fmt(sale.optDouble("discount", sale.optDouble("discount_total", 0.0))).padStart(12))
        lines.add("TOTAL:".padEnd(20) + fmt(sale.optDouble("total", 0.0)).padStart(12))
        lines.add("Tendered:".padEnd(20) + fmt(sale.optDouble("amount_tendered", 0.0)).padStart(12))
        lines.add("Change:".padEnd(20) + fmt(sale.optDouble("change_amount", sale.optDouble("change_due", 0.0))).padStart(12))
        lines.add(div)
        lines.add("Thank you!")
        lines.add("")

        val text = lines.joinToString("\n")
        return JSONObject().apply {
            put("text", text)
            put("json", sale.toString())
            put("html", "<pre>$text</pre>")
        }
    }

    private fun fmt(v: Double): String = String.format(Locale.US, "%.2f", v)

    private fun formatNow(): String =
        SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())

    private fun String.centered(width: Int): String {
        if (length >= width) return this
        val pad = (width - length) / 2
        return " ".repeat(pad) + this
    }
}
