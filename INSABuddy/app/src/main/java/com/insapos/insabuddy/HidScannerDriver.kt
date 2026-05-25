package com.insapos.insabuddy

import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.KeyEvent

/**
 * Intercepts keyboard input from USB and Bluetooth HID barcode scanners.
 * HID scanners send rapid key events followed by ENTER.
 * This driver buffers characters and emits a barcode on ENTER
 * (or after a timeout if keys stop arriving).
 */
class HidScannerDriver {

    companion object {
        private const val TAG = "HidScannerDriver"
        private const val INPUT_TIMEOUT_MS = 150L
        private const val MIN_BARCODE_LENGTH = 3
    }

    private val buffer = StringBuilder()
    private var lastKeyTime = 0L
    private val handler = Handler(Looper.getMainLooper())
    private var timeoutRunnable: Runnable? = null

    var onBarcodeScanned: ((String) -> Unit)? = null
    var lastBarcode: String? = null
        private set
    var isListening = true

    /**
     * Call this from Activity.dispatchKeyEvent(). Returns true if the event
     * was consumed (i.e., it looks like scanner input).
     */
    fun handleKeyEvent(event: KeyEvent): Boolean {
        if (!isListening) return false

        // Only process key-down events with printable characters or ENTER
        if (event.action != KeyEvent.ACTION_DOWN) return false

        val keyCode = event.keyCode
        val now = System.currentTimeMillis()

        // If there's a long gap, this is probably manual typing — reset
        if (buffer.isNotEmpty() && (now - lastKeyTime) > INPUT_TIMEOUT_MS * 3) {
            buffer.clear()
        }

        lastKeyTime = now

        when {
            keyCode == KeyEvent.KEYCODE_ENTER -> {
                cancelTimeout()
                emitBarcode()
                return true
            }
            keyCode == KeyEvent.KEYCODE_TAB -> {
                // Some scanners use TAB as suffix
                cancelTimeout()
                emitBarcode()
                return true
            }
            isPrintableKey(keyCode) -> {
                val char = keyEventToChar(event)
                if (char != null) {
                    buffer.append(char)
                    resetTimeout()
                    return buffer.length > 1
                }
            }
        }

        return false
    }

    private fun emitBarcode() {
        val code = buffer.toString().trim()
        buffer.clear()

        if (code.length >= MIN_BARCODE_LENGTH) {
            Log.i(TAG, "Barcode scanned: $code")
            lastBarcode = code
            onBarcodeScanned?.invoke(code)
        }
    }

    private fun resetTimeout() {
        cancelTimeout()
        timeoutRunnable = Runnable {
            if (buffer.isNotEmpty()) {
                emitBarcode()
            }
        }
        handler.postDelayed(timeoutRunnable!!, INPUT_TIMEOUT_MS)
    }

    private fun cancelTimeout() {
        timeoutRunnable?.let { handler.removeCallbacks(it) }
        timeoutRunnable = null
    }

    private fun isPrintableKey(keyCode: Int): Boolean {
        return keyCode in KeyEvent.KEYCODE_0..KeyEvent.KEYCODE_9 ||
               keyCode in KeyEvent.KEYCODE_A..KeyEvent.KEYCODE_Z ||
               keyCode == KeyEvent.KEYCODE_MINUS ||
               keyCode == KeyEvent.KEYCODE_PERIOD ||
               keyCode == KeyEvent.KEYCODE_SLASH ||
               keyCode == KeyEvent.KEYCODE_SPACE ||
               keyCode == KeyEvent.KEYCODE_PLUS ||
               keyCode == KeyEvent.KEYCODE_STAR ||
               keyCode == KeyEvent.KEYCODE_POUND
    }

    private fun keyEventToChar(event: KeyEvent): Char? {
        val unicodeChar = event.unicodeChar
        if (unicodeChar != 0) return unicodeChar.toChar()

        return when (event.keyCode) {
            in KeyEvent.KEYCODE_0..KeyEvent.KEYCODE_9 -> ('0' + (event.keyCode - KeyEvent.KEYCODE_0))
            in KeyEvent.KEYCODE_A..KeyEvent.KEYCODE_Z -> {
                val base = 'a' + (event.keyCode - KeyEvent.KEYCODE_A)
                if (event.isShiftPressed) base.uppercaseChar() else base
            }
            KeyEvent.KEYCODE_MINUS -> '-'
            KeyEvent.KEYCODE_PERIOD -> '.'
            KeyEvent.KEYCODE_SLASH -> '/'
            KeyEvent.KEYCODE_SPACE -> ' '
            KeyEvent.KEYCODE_PLUS -> '+'
            KeyEvent.KEYCODE_STAR -> '*'
            KeyEvent.KEYCODE_POUND -> '#'
            else -> null
        }
    }
}
