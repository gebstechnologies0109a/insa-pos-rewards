package com.insapos.v2

import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.KeyEvent

/**
 * Detects barcode scanner HID input by recognizing rapid keystroke bursts.
 *
 * Design principle: NEVER consume the first character of a keystroke sequence.
 * Only consume subsequent rapid-fire characters (< scannerSpeedThreshold apart)
 * that are clearly from a scanner, plus the trailing ENTER/TAB that terminates
 * a scanner barcode. Normal keyboard typing always passes through to the WebView.
 */
class HidScannerDriver(private val onBarcode: (String) -> Unit) {

    companion object {
        private const val TAG = "HidScanner"
    }

    private val buffer = StringBuilder()
    private val handler = Handler(Looper.getMainLooper())
    private var lastBarcode: String = ""
    private var lastKeyTime: Long = 0
    private val flushDelay = 120L
    /** Scanners burst faster than human typing in a search field (~80ms+ between keys). */
    private val scannerSpeedThreshold = 45L

    private val flushRunnable = Runnable {
        val code = buffer.toString()
        buffer.clear()
        if (code.length >= 3) {
            lastBarcode = code
            Log.d(TAG, "Flush detected barcode: $code")
            onBarcode(lastBarcode)
        }
    }

    fun handleKeyEvent(event: KeyEvent): Boolean {
        if (event.action != KeyEvent.ACTION_DOWN) return false

        val keyCode = event.keyCode
        val ch = event.unicodeChar.toChar()

        if (keyCode == KeyEvent.KEYCODE_ENTER || keyCode == KeyEvent.KEYCODE_TAB) {
            handler.removeCallbacks(flushRunnable)
            if (buffer.length >= 3) {
                lastBarcode = buffer.toString()
                buffer.clear()
                Log.i(TAG, "Scanner barcode: $lastBarcode")
                onBarcode(lastBarcode)
                return true
            }
            buffer.clear()
            return false
        }

        if (ch.isLetterOrDigit() || ch == '-' || ch == '_' || ch == '.' || ch == '/') {
            val now = System.currentTimeMillis()
            val gap = now - lastKeyTime

            if (buffer.isNotEmpty() && gap < scannerSpeedThreshold) {
                handler.removeCallbacks(flushRunnable)
                buffer.append(ch)
                lastKeyTime = now
                handler.postDelayed(flushRunnable, flushDelay)
                return true
            }

            handler.removeCallbacks(flushRunnable)
            buffer.clear()
            lastKeyTime = now
            return false
        }

        return false
    }

    fun getLastBarcode(): String = lastBarcode
}
