package com.epayplus.v2.service

import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.BatteryManager
import android.os.Environment
import android.os.StatFs
import androidx.hilt.work.HiltWorker
import androidx.work.*
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.data.remote.EPayApiService
import dagger.assisted.Assisted
import dagger.assisted.AssistedInject
import java.util.concurrent.TimeUnit

@HiltWorker
class DeviceHeartbeatService @AssistedInject constructor(
    @Assisted context: Context,
    @Assisted params: WorkerParameters,
    private val apiService: EPayApiService,
    private val tokenManager: TokenManager
) : CoroutineWorker(context, params) {

    companion object {
        const val WORK_NAME = "device_heartbeat"
        private const val INTERVAL_MINUTES = 3L

        fun schedule(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val request = PeriodicWorkRequestBuilder<DeviceHeartbeatService>(
                INTERVAL_MINUTES, TimeUnit.MINUTES
            )
                .setConstraints(constraints)
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 1, TimeUnit.MINUTES)
                .build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                WORK_NAME,
                ExistingPeriodicWorkPolicy.KEEP,
                request
            )
        }

        fun cancel(context: Context) {
            WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
        }
    }

    override suspend fun doWork(): Result {
        return try {
            val deviceId = tokenManager.getDeviceId() ?: return Result.retry()
            val batteryLevel = getBatteryLevel()
            val freeStorage = getFreeStorageMb()

            val response = apiService.sendHeartbeat(
                mapOf(
                    "device_id" to deviceId,
                    "battery_level" to batteryLevel.toString(),
                    "free_storage_mb" to freeStorage.toString(),
                    "uptime_seconds" to (android.os.SystemClock.elapsedRealtime() / 1000).toString(),
                    "app_version" to "3.0"
                )
            )

            if (response.isSuccessful) Result.success() else Result.retry()
        } catch (e: Exception) {
            Result.retry()
        }
    }

    private fun getBatteryLevel(): Int {
        val batteryIntent = applicationContext.registerReceiver(
            null, IntentFilter(Intent.ACTION_BATTERY_CHANGED)
        )
        val level = batteryIntent?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
        val scale = batteryIntent?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1
        return if (level >= 0 && scale > 0) (level * 100 / scale) else -1
    }

    private fun getFreeStorageMb(): Long {
        val stat = StatFs(Environment.getDataDirectory().path)
        return stat.availableBlocksLong * stat.blockSizeLong / (1024 * 1024)
    }
}
