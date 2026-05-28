package com.insapos.v2.printers

import android.util.Log
import com.insapos.v2.posengine.ReceiptTemplate

/**
 * Prints a [ReceiptTemplate] via [PrinterManager] (native hardware — not INSABuddy JS shim).
 */
class ReceiptPrinter(
    private val printerManager: PrinterManager,
    private val printerSettings: PrinterSettings? = null,
) {

    companion object {
        private const val TAG = "ReceiptPrinter"
    }

    fun printReceipt(template: ReceiptTemplate): Boolean {
        val layout = printerSettings?.layout() ?: PrinterConfig.resolve(null, null)
        val text = template.toPlainText(layout)
        val (printer, err) = printerManager.ensureActivePrinter(null, null)
        if (printer == null) {
            Log.w(TAG, "Print skipped: ${err ?: "no printer"}")
            return false
        }
        if (!printer.isConnected() && !printer.connect()) {
            Log.w(TAG, "Printer reconnect failed")
            return false
        }
        return printer.printText(text, layout)
    }

    fun printText(text: String): Boolean {
        val layout = printerSettings?.layout() ?: PrinterConfig.resolve(null, null)
        return printerManager.printText(text, layout)
    }
}
