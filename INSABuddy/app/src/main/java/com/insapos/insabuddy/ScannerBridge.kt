package com.insapos.insabuddy

import android.app.Activity
import android.content.Intent
import android.util.Log
import com.google.zxing.integration.android.IntentIntegrator
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

class ScannerBridge {

    companion object {
        private const val TAG = "ScannerBridge"
        private const val SCAN_TIMEOUT_SECONDS = 30L
    }

    var lastResult: String? = null
        private set
    var lastFormat: String? = null
        private set

    private var continuousMode = false
    private var scanLatch: CountDownLatch? = null
    var onScanResult: ((String, String) -> Unit)? = null

    private var activityRef: Activity? = null

    fun setActivity(activity: Activity?) {
        activityRef = activity
    }

    /**
     * Request a single scan. Blocks until result is available or timeout.
     * Returns the scanned value, or null on timeout/cancel.
     */
    fun requestScan(): String? {
        val activity = activityRef ?: run {
            Log.e(TAG, "No activity attached for scanning")
            return null
        }

        lastResult = null
        lastFormat = null
        scanLatch = CountDownLatch(1)

        activity.runOnUiThread {
            IntentIntegrator(activity)
                .setDesiredBarcodeFormats(IntentIntegrator.ALL_CODE_TYPES)
                .setPrompt("Scan barcode or QR code")
                .setBeepEnabled(true)
                .setOrientationLocked(false)
                .initiateScan()
        }

        // Block until scan completes or timeout
        scanLatch?.await(SCAN_TIMEOUT_SECONDS, TimeUnit.SECONDS)
        return lastResult
    }

    fun handleScanResult(requestCode: Int, resultCode: Int, data: Intent?): Boolean {
        val result = IntentIntegrator.parseActivityResult(requestCode, resultCode, data)
            ?: return false

        if (result.contents != null) {
            lastResult = result.contents
            lastFormat = result.formatName
            Log.i(TAG, "Scanned: ${result.contents} (${result.formatName})")
            onScanResult?.invoke(result.contents, result.formatName ?: "")
        }

        scanLatch?.countDown()
        return true
    }

    fun setContinuousMode(enabled: Boolean) {
        continuousMode = enabled
        Log.i(TAG, "Continuous mode: $enabled")
    }

    fun isContinuousMode(): Boolean = continuousMode
}
