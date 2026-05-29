package com.insapos.v2

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

/**
 * Ensures INSAPOS opens after boot when OEM launcher routing or standard HOME
 * selection does not already bring the app to the foreground.
 */
class BootReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action ?: return
        if (action != Intent.ACTION_BOOT_COMPLETED &&
            action != "android.intent.action.QUICKBOOT_POWERON"
        ) {
            return
        }

        Log.i(TAG, "Boot completed — launching MainActivity")
        val launch = Intent(context, MainActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
        }
        context.startActivity(launch)
    }

    companion object {
        private const val TAG = "INSAPOSBootReceiver"
    }
}
