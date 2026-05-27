package com.epayplus.v2.util

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import com.epayplus.v2.R

/**
 * Fallback helpers for INSA POS outside the in-app WebView embed.
 * Primary POS entry is [com.epayplus.v2.ui.screens.InsaPosEmbeddedScreen].
 */
object InsaPosLauncher {

    const val INSA_CASHIER_URL = "https://insapos.diybizrewards.com/pos/cashier"

    private val INSAPOS_PACKAGES = listOf(
        "com.insapos.v2",
        "com.insapos.light",
        "com.insapos.posapp",
    )

    fun launchInsaApp(context: Context): Boolean {
        val pm = context.packageManager
        for (pkg in INSAPOS_PACKAGES) {
            val intent = pm.getLaunchIntentForPackage(pkg)?.apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            if (intent != null) {
                context.startActivity(intent)
                return true
            }
        }
        return false
    }

    fun openWebCashier(context: Context): Boolean {
        return try {
            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(INSA_CASHIER_URL)).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            context.startActivity(intent)
            true
        } catch (_: Exception) {
            false
        }
    }

    /** Prefer standalone INSA APK; otherwise toast and run [onFallback]. */
    fun launchOrToast(context: Context, onFallback: (() -> Unit)? = null): Boolean {
        if (launchInsaApp(context)) return true
        Toast.makeText(context, R.string.insa_pos_not_installed, Toast.LENGTH_LONG).show()
        onFallback?.invoke()
        return false
    }
}
