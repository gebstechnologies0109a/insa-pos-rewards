package com.insapos.v2.printers

import android.util.Log
import com.insapos.v2.posengine.ReceiptTemplate

/**
 * Prints a [ReceiptTemplate] via [PrinterManager] (native hardware — not INSABuddy JS shim).
 */
class ReceiptPrinter(private val printerManager: PrinterManager) {

    companion object {
        private const val TAG = "ReceiptPrinter"
    }

    fun printReceipt(template: ReceiptTemplate): Boolean {
        val text = template.toPlainText()
        val (printer, err) = printerManager.ensureActivePrinter(null, null)
        if (printer == null) {
            Log.w(TAG, "Print skipped: ${err ?: "no printer"}")
            return false
        }
        if (!printer.isConnected() && !printer.connect()) {
            Log.w(TAG, "Printer reconnect failed")
            return false
        }
        return printer.printText(text)
    }

    fun printText(text: String): Boolean = printerManager.printText(text)
}
