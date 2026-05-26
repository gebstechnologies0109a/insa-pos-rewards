package com.insapos.v2

import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.InputDevice
import android.view.KeyEvent

/**
 * Detects HID barcode scanner input vs normal keyboard typing.
 *
 * Barcode scanners send characters at 10-40ms intervals — much faster
 * than any human can type.  We use a 60ms gap threshold: sequences with
 * inter-key gaps <= 60ms are treated as scanner input.  The first character
 * of every sequence always passes through to the WebView so that normal
 * keyboard use is never blocked.
 */
class HidScannerDriver(private val onBarcode: (String) -> Unit) {

    companion object {
        private const val TAG = "HidScanner"
        private const val MAX_SCAN_GAP_MS = 60L
        private const val FLUSH_DELAY_MS = 120L
        private const val BUFFER_RESET_MS = 500L
        private const val MIN_BARCODE_LEN = 3
    }

    private val buffer = StringBuilder()
    private val handler = Handler(Looper.getMainLooper())
    private var lastBarcode = ""
    private var lastKeyTime = 0L
    private var inScanMode = false

    private val flushRunnable = Runnable {
        if (buffer.length >= MIN_BARCODE_LEN) {
            lastBarcode = buffer.toString()
            Log.d(TAG, "Flush barcode: $lastBarcode")
            onBarcode(lastBarcode)
        }
        buffer.clear()
        inScanMode = false
    }

    /**
     * @return true if the key was consumed (scanner input),
     *         false if it should be forwarded to the WebView.
     */
    fun handleKeyEvent(event: KeyEvent): Boolean {
        if (event.action != KeyEvent.ACTION_DOWN) return false

        if (isNavOrSystemKey(event.keyCode)) return false

        val now = System.currentTimeMillis()
        val gap = now - lastKeyTime
        lastKeyTime = now

        if (gap > BUFFER_RESET_MS && buffer.isNotEmpty()) {
            buffer.clear()
            inScanMode = false
        }

        if (event.keyCode == KeyEvent.KEYCODE_ENTER ||
            event.keyCode == KeyEvent.KEYCODE_TAB
        ) {
            handler.removeCallbacks(flushRunnable)
            if (buffer.length >= MIN_BARCODE_LEN) {
                lastBarcode = buffer.toString()
                Log.i(TAG, "Barcode: $lastBarcode")
                onBarcode(lastBarcode)
                buffer.clear()
                inScanMode = false
                return true
            }
            buffer.clear()
            inScanMode = false
            return false
        }

        val ch = event.unicodeChar.toChar()
        if (!isValidScanChar(ch)) return false

        if (buffer.isEmpty()) {
            buffer.append(ch)
            handler.removeCallbacks(flushRunnable)
            handler.postDelayed(flushRunnable, FLUSH_DELAY_MS)
            return false
        }

        if (gap <= MAX_SCAN_GAP_MS) {
            handler.removeCallbacks(flushRunnable)
            buffer.append(ch)
            inScanMode = true
            handler.postDelayed(flushRunnable, FLUSH_DELAY_MS)
            return true
        }

        buffer.clear()
        buffer.append(ch)
        inScanMode = false
        handler.removeCallbacks(flushRunnable)
        handler.postDelayed(flushRunnable, FLUSH_DELAY_MS)
        return false
    }

    fun getLastBarcode(): String = lastBarcode

    fun isInScanMode(): Boolean = inScanMode

    private fun isValidScanChar(ch: Char): Boolean =
        ch.isLetterOrDigit() || ch == '-' || ch == '_' || ch == '.' ||
            ch == '/' || ch == '+' || ch == '=' || ch == ':' || ch == ' '

    private fun isNavOrSystemKey(keyCode: Int): Boolean = when (keyCode) {
        KeyEvent.KEYCODE_BACK,
        KeyEvent.KEYCODE_HOME,
        KeyEvent.KEYCODE_MENU,
        KeyEvent.KEYCODE_VOLUME_UP,
        KeyEvent.KEYCODE_VOLUME_DOWN,
        KeyEvent.KEYCODE_POWER,
        KeyEvent.KEYCODE_DPAD_UP,
        KeyEvent.KEYCODE_DPAD_DOWN,
        KeyEvent.KEYCODE_DPAD_LEFT,
        KeyEvent.KEYCODE_DPAD_RIGHT,
        KeyEvent.KEYCODE_ESCAPE,
        KeyEvent.KEYCODE_DEL,
        KeyEvent.KEYCODE_FORWARD_DEL,
        KeyEvent.KEYCODE_SHIFT_LEFT,
        KeyEvent.KEYCODE_SHIFT_RIGHT,
        KeyEvent.KEYCODE_CTRL_LEFT,
        KeyEvent.KEYCODE_CTRL_RIGHT,
        KeyEvent.KEYCODE_ALT_LEFT,
        KeyEvent.KEYCODE_ALT_RIGHT,
        KeyEvent.KEYCODE_CAPS_LOCK,
        KeyEvent.KEYCODE_NUM_LOCK,
        KeyEvent.KEYCODE_SCROLL_LOCK,
        KeyEvent.KEYCODE_FUNCTION,
        KeyEvent.KEYCODE_SYSRQ,
        KeyEvent.KEYCODE_BREAK,
        KeyEvent.KEYCODE_MOVE_HOME,
        KeyEvent.KEYCODE_MOVE_END,
        KeyEvent.KEYCODE_INSERT,
        KeyEvent.KEYCODE_PAGE_UP,
        KeyEvent.KEYCODE_PAGE_DOWN,
        KeyEvent.KEYCODE_F1, KeyEvent.KEYCODE_F2, KeyEvent.KEYCODE_F3,
        KeyEvent.KEYCODE_F4, KeyEvent.KEYCODE_F5, KeyEvent.KEYCODE_F6,
        KeyEvent.KEYCODE_F7, KeyEvent.KEYCODE_F8, KeyEvent.KEYCODE_F9,
        KeyEvent.KEYCODE_F10, KeyEvent.KEYCODE_F11, KeyEvent.KEYCODE_F12 -> true
        else -> false
    }
}
