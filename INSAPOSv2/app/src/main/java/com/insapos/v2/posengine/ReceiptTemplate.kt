package com.insapos.v2.posengine

data class ReceiptItem(
    val name: String,
    val qty: Double,
    val price: Double,
    val discount: Double = 0.0,
) {
    val lineTotal: Double get() = qty * price
}

data class ReceiptTemplate(
    val storeName: String,
    val branchName: String,
    val cashierName: String,
    val localId: String,
    val items: List<ReceiptItem>,
    val subtotal: Double,
    val discount: Double,
    val total: Double,
    val amountTendered: Double,
    val change: Double,
    val paymentMethod: String,
    val soldAt: String,
) {
    fun toPlainText(): String {
        val div = "================================"
        val lines = mutableListOf<String>()
        lines.add(div)
        lines.add(storeName.centered(32))
        if (branchName.isNotBlank()) lines.add(branchName.centered(32))
        lines.add(div)
        if (cashierName.isNotBlank()) lines.add("Cashier: $cashierName")
        lines.add("Date: $soldAt")
        lines.add("Sale: $localId")
        lines.add(div)
        for (item in items) {
            lines.add(item.name.take(32))
            lines.add("  ${item.qty} x ${fmt(item.price)} = ${fmt(item.lineTotal)}")
        }
        lines.add(div)
        lines.add("Subtotal:".padEnd(20) + fmt(subtotal).padStart(12))
        lines.add("Discount:".padEnd(20) + fmt(discount).padStart(12))
        lines.add("TOTAL:".padEnd(20) + fmt(total).padStart(12))
        lines.add("Tendered:".padEnd(20) + fmt(amountTendered).padStart(12))
        lines.add("Change:".padEnd(20) + fmt(change).padStart(12))
        lines.add("Pay: $paymentMethod")
        lines.add(div)
        lines.add("Thank you!")
        lines.add("")
        return lines.joinToString("\n")
    }

    private fun fmt(v: Double): String = String.format(java.util.Locale.US, "%.2f", v)

    private fun String.centered(width: Int): String {
        if (length >= width) return this
        val pad = (width - length) / 2
        return " ".repeat(pad) + this
    }
}
