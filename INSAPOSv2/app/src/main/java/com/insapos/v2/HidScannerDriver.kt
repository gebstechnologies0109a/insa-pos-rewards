package com.insapos.v2

import android.os.Handler
import android.os.Looper
import android.view.KeyEvent

class HidScannerDriver(private val onBarcode: (String) -> Unit) {

    private val buffer = StringBuilder()
    private val handler = Handler(Looper.getMainLooper())
    private var lastBarcode: String = ""
    private val flushDelay = 80L

    private val flushRunnable = Runnable {
        if (buffer.isNotEmpty()) {
            lastBarcode = buffer.toString()
            onBarcode(lastBarcode)
            buffer.clear()
        }
    }

    fun handleKeyEvent(event: KeyEvent): Boolean {
        if (event.action != KeyEvent.ACTION_DOWN) return false
        val ch = event.unicodeChar.toChar()

        if (event.keyCode == KeyEvent.KEYCODE_ENTER ||
            event.keyCode == KeyEvent.KEYCODE_TAB
        ) {
            handler.removeCallbacks(flushRunnable)
            if (buffer.isNotEmpty()) {
                lastBarcode = buffer.toString()
                onBarcode(lastBarcode)
                buffer.clear()
            }
            return true
        }

        if (ch.isLetterOrDigit() || ch == '-' || ch == '_' || ch == '.' || ch == '/') {
            handler.removeCallbacks(flushRunnable)
            buffer.append(ch)
            handler.postDelayed(flushRunnable, flushDelay)
            return true
        }

        return false
    }

    fun getLastBarcode(): String = lastBarcode
}
