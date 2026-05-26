package com.epayplus.v2.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.service.DeviceHeartbeatService
import com.epayplus.v2.service.EPayService
import com.epayplus.v2.service.KioskService
import com.epayplus.v2.service.OfflineQueueService
import com.epayplus.v2.service.DeviceCommandService
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject

@AndroidEntryPoint
class BootReceiver : BroadcastReceiver() {

    @Inject lateinit var tokenManager: TokenManager

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED &&
            intent.action != "android.intent.action.QUICKBOOT_POWERON") return

        // Start foreground service
        val serviceIntent = Intent(context, EPayService::class.java)
        context.startForegroundService(serviceIntent)

        // Schedule background workers
        DeviceHeartbeatService.schedule(context)
        OfflineQueueService.schedule(context)
        DeviceCommandService.schedule(context)

        // Launch kiosk mode if configured
        val mode = tokenManager.getDeviceModeSync()
        if (mode == "kiosk") {
            val kioskIntent = Intent(context, com.epayplus.v2.ui.KioskActivity::class.java).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            context.startActivity(kioskIntent)
        }
    }
}
