package com.epayplus.v2

import android.app.Application
import com.epayplus.v2.service.DeviceCommandService
import com.epayplus.v2.service.DeviceHeartbeatService
import com.epayplus.v2.service.OfflineQueueService
import dagger.hilt.android.HiltAndroidApp
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltAndroidApp
class EPayPlusApp : Application() {

    @Inject lateinit var tokenManager: com.epayplus.v2.data.local.TokenManager

    override fun onCreate() {
        super.onCreate()
        instance = this

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val setupComplete = tokenManager.isSetupComplete.first()
                if (setupComplete) {
                    DeviceHeartbeatService.schedule(this@EPayPlusApp)
                    DeviceCommandService.schedule(this@EPayPlusApp)
                    OfflineQueueService.schedule(this@EPayPlusApp)
                }
            } catch (_: Exception) {}
        }
    }

    companion object {
        lateinit var instance: EPayPlusApp
            private set
    }
}

