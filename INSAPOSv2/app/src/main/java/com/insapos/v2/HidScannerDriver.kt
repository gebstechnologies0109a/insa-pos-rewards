package com.insapos.v2

import android.os.Handler
import android.os.Looper
import android.view.KeyEvent

class HidScannerDriver(private val onBarcode: (String) -> Unit) {

    private val buffer = StringBuilder()
    private val handler = Handler(Looper.getMainLooper())
    private var lastBarcode: String = ""
    private var lastKeyTime: Long = 0
    private val flushDelay = 80L
    private val scannerSpeedThreshold = 60L

    private val flushRunnable = Runnable {
        val code = buffer.toString()
        buffer.clear()
        if (code.length >= 3) {
            lastBarcode = code
            onBarcode(lastBarcode)
        }
    }

    fun handleKeyEvent(event: KeyEvent): Boolean {
        if (event.action != KeyEvent.ACTION_DOWN) return false
        val ch = event.unicodeChar.toChar()

        if (event.keyCode == KeyEvent.KEYCODE_ENTER ||
            event.keyCode == KeyEvent.KEYCODE_TAB
        ) {
            handler.removeCallbacks(flushRunnable)
            if (buffer.length >= 3) {
                lastBarcode = buffer.toString()
                buffer.clear()
                onBarcode(lastBarcode)
                return true
            }
            buffer.clear()
            return false
        }

        if (ch.isLetterOrDigit() || ch == '-' || ch == '_' || ch == '.' || ch == '/') {
            val now = System.currentTimeMillis()
            if (buffer.isEmpty() || (now - lastKeyTime) < scannerSpeedThreshold) {
                handler.removeCallbacks(flushRunnable)
                buffer.append(ch)
                lastKeyTime = now
                handler.postDelayed(flushRunnable, flushDelay)
                return true
            } else {
                handler.removeCallbacks(flushRunnable)
                buffer.clear()
                buffer.append(ch)
                lastKeyTime = now
                handler.postDelayed(flushRunnable, flushDelay)
                return false
            }
        }

        return false
    }

    fun getLastBarcode(): String = lastBarcode
}
