package com.insapos.v2.posengine

import com.insapos.v2.printers.PrinterConfig

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
    fun toPlainText(layout: PrinterConfig.Layout = PrinterConfig.resolve(null, null)): String {
        val w = layout.charWidth
        val div = PrinterConfig.divider(w)
        val lines = mutableListOf<String>()
        lines.add(div)
        for (line in PrinterConfig.wrapText(storeName, w)) {
            lines.add(PrinterConfig.centered(line, w))
        }
        if (branchName.isNotBlank()) {
            for (line in PrinterConfig.wrapText(branchName, w)) {
                lines.add(PrinterConfig.centered(line, w))
            }
        }
        lines.add(div)
        if (cashierName.isNotBlank()) lines.add("Cashier: $cashierName".take(w))
        lines.add("Date: $soldAt".take(w))
        lines.add("Sale: $localId".take(w))
        lines.add(div)
        for (item in items) {
            for (line in PrinterConfig.wrapText(item.name, w)) {
                lines.add(line)
            }
            lines.add("  ${item.qty} x ${fmt(item.price)} = ${fmt(item.lineTotal)}".take(w))
        }
        lines.add(div)
        lines.add(PrinterConfig.moneyLine("Subtotal:", fmt(subtotal), w))
        lines.add(PrinterConfig.moneyLine("Discount:", fmt(discount), w))
        lines.add(PrinterConfig.moneyLine("TOTAL:", fmt(total), w))
        lines.add(PrinterConfig.moneyLine("Tendered:", fmt(amountTendered), w))
        lines.add(PrinterConfig.moneyLine("Change:", fmt(change), w))
        lines.add("Pay: $paymentMethod".take(w))
        lines.add(div)
        lines.add("Thank you!")
        lines.add("")
        return lines.joinToString("\n")
    }

    private fun fmt(v: Double): String = String.format(java.util.Locale.US, "%.2f", v)
}
