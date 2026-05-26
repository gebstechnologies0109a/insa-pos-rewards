package com.insapos.v2

import android.os.Handler
import android.os.Looper
import android.view.KeyEvent

class HidScannerDriver(private val onBarcode: (String) -> Unit) {

    private val buffer = StringBuilder()
    private val handler = Handler(Looper.getMainLooper())
    private var lastBarcode: String = ""
    private var lastKeyTime = 0L
    private val flushDelay = 150L
    private val maxGap = 100L
    private val minBarcodeLength = 3

    private val flushRunnable = Runnable {
        if (buffer.length >= minBarcodeLength) {
            lastBarcode = buffer.toString()
            onBarcode(lastBarcode)
        }
        buffer.clear()
    }

    fun handleKeyEvent(event: KeyEvent): Boolean {
        if (event.action != KeyEvent.ACTION_DOWN) return false

        val now = System.currentTimeMillis()
        val gap = now - lastKeyTime
        lastKeyTime = now

        if (gap > 450 && buffer.isNotEmpty()) {
            buffer.clear()
        }

        if (event.keyCode == KeyEvent.KEYCODE_ENTER ||
            event.keyCode == KeyEvent.KEYCODE_TAB
        ) {
            handler.removeCallbacks(flushRunnable)
            if (buffer.length >= minBarcodeLength) {
                lastBarcode = buffer.toString()
                onBarcode(lastBarcode)
                buffer.clear()
                return true
            }
            buffer.clear()
            return false
        }

        val ch = event.unicodeChar.toChar()
        if (ch.isLetterOrDigit() || ch == '-' || ch == '_' || ch == '.' || ch == '/') {
            if (buffer.isEmpty() || gap <= maxGap) {
                handler.removeCallbacks(flushRunnable)
                buffer.append(ch)
                handler.postDelayed(flushRunnable, flushDelay)
                return true
            } else {
                buffer.clear()
                return false
            }
        }

        return false
    }

    fun getLastBarcode(): String = lastBarcode
}
