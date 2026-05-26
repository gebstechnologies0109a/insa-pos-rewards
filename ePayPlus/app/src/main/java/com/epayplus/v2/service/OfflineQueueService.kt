package com.epayplus.v2.service

import android.content.Context
import androidx.hilt.work.HiltWorker
import androidx.work.*
import com.epayplus.v2.data.local.EPayDatabase
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.data.remote.EPayApiService
import dagger.assisted.Assisted
import dagger.assisted.AssistedInject
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.util.concurrent.TimeUnit

@HiltWorker
class OfflineQueueService @AssistedInject constructor(
    @Assisted context: Context,
    @Assisted params: WorkerParameters,
    private val database: EPayDatabase,
    private val apiService: EPayApiService,
    private val tokenManager: TokenManager
) : CoroutineWorker(context, params) {

    companion object {
        const val WORK_NAME = "offline_sync"

        fun schedule(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val request = PeriodicWorkRequestBuilder<OfflineQueueService>(
                5, TimeUnit.MINUTES
            )
                .setConstraints(constraints)
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
                .build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                WORK_NAME,
                ExistingPeriodicWorkPolicy.KEEP,
                request
            )
        }

        fun triggerNow(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val request = OneTimeWorkRequestBuilder<OfflineQueueService>()
                .setConstraints(constraints)
                .build()

            WorkManager.getInstance(context).enqueue(request)
        }
    }

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        try {
            val deviceId = tokenManager.getDeviceId() ?: return@withContext Result.retry()

            val pendingTransactions = database.transactionDao().getPendingSync()
            if (pendingTransactions.isEmpty()) return@withContext Result.success()

            val syncPayload = pendingTransactions.map { tx ->
                mapOf(
                    "local_id" to tx.id.toString(),
                    "type" to tx.type,
                    "provider_code" to tx.provider,
                    "product_code" to tx.product,
                    "target_number" to tx.targetNumber,
                    "amount" to tx.amount.toString(),
                    "reference_number" to tx.referenceNumber,
                    "status" to tx.status,
                    "created_at" to tx.createdAt.toString()
                )
            }

            val response = apiService.syncOfflineTransactions(
                mapOf(
                    "device_id" to deviceId,
                    "transactions" to syncPayload
                )
            )

            if (response.isSuccessful) {
                pendingTransactions.forEach { tx ->
                    database.transactionDao().markSynced(tx.id)
                }
                Result.success()
            } else {
                Result.retry()
            }
        } catch (e: Exception) {
            Result.retry()
        }
    }
}
