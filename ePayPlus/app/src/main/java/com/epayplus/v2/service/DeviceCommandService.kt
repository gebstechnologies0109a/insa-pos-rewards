package com.epayplus.v2.service

import android.content.Context
import android.content.Intent
import androidx.hilt.work.HiltWorker
import androidx.work.*
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.data.remote.EPayApiService
import dagger.assisted.Assisted
import dagger.assisted.AssistedInject
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.util.concurrent.TimeUnit

@HiltWorker
class DeviceCommandService @AssistedInject constructor(
    @Assisted private val appContext: Context,
    @Assisted params: WorkerParameters,
    private val apiService: EPayApiService,
    private val tokenManager: TokenManager
) : CoroutineWorker(appContext, params) {

    companion object {
        const val WORK_NAME = "device_commands"

        fun schedule(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val request = PeriodicWorkRequestBuilder<DeviceCommandService>(
                1, TimeUnit.MINUTES
            )
                .setConstraints(constraints)
                .build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                WORK_NAME,
                ExistingPeriodicWorkPolicy.KEEP,
                request
            )
        }
    }

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        try {
            val deviceId = tokenManager.getDeviceId() ?: return@withContext Result.retry()

            val response = apiService.getDeviceCommands(deviceId)
            if (!response.isSuccessful) return@withContext Result.retry()

            val commands = response.body()?.commands ?: return@withContext Result.success()

            for (cmd in commands) {
                val result = executeCommand(cmd.command, cmd.params)
                try {
                    apiService.acknowledgeCommand(
                        mapOf(
                            "command_id" to cmd.id.toString(),
                            "status" to if (result) "acknowledged" else "failed",
                            "result" to if (result) "OK" else "Failed to execute"
                        )
                    )
                } catch (_: Exception) { }
            }

            Result.success()
        } catch (e: Exception) {
            Result.retry()
        }
    }

    private fun executeCommand(command: String, params: Map<String, Any>?): Boolean {
        return when (command) {
            "restart_app" -> {
                val intent = appContext.packageManager.getLaunchIntentForPackage(appContext.packageName)
                intent?.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_NEW_TASK)
                appContext.startActivity(intent)
                true
            }
            "enable_kiosk" -> {
                val intent = Intent(appContext, com.epayplus.v2.ui.KioskActivity::class.java)
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                appContext.startActivity(intent)
                true
            }
            "disable_kiosk" -> {
                val intent = Intent(appContext, KioskService::class.java)
                intent.action = KioskService.ACTION_STOP
                appContext.startService(intent)
                true
            }
            "clear_cache" -> {
                try {
                    appContext.cacheDir.deleteRecursively()
                    true
                } catch (_: Exception) { false }
            }
            "sync_products" -> {
                OfflineQueueService.triggerNow(appContext)
                true
            }
            "lock_device" -> {
                val km = com.epayplus.v2.util.KioskManager(appContext)
                km.lockDevice()
                true
            }
            "update_config" -> {
                true
            }
            else -> false
        }
    }
}
