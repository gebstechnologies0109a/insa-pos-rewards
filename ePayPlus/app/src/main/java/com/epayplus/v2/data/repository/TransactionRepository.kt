package com.epayplus.v2.data.repository

import com.epayplus.v2.data.local.dao.TransactionDao
import com.epayplus.v2.data.local.entity.TransactionEntity
import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.domain.model.*
import kotlinx.coroutines.flow.Flow
import java.util.UUID
import javax.inject.Inject

class TransactionRepository @Inject constructor(
    private val transactionDao: TransactionDao,
    private val apiService: EPayApiService
) {
    fun getAllTransactions(): Flow<List<TransactionEntity>> = transactionDao.getAllTransactions()

    fun getTransactionsByType(type: String): Flow<List<TransactionEntity>> =
        transactionDao.getTransactionsByType(type)

    fun getTodaySales(): Flow<Double> {
        val todayStart = getTodayStartMillis()
        return transactionDao.getTodaySales(todayStart)
    }

    fun getTodayTransactionCount(): Flow<Int> {
        val todayStart = getTodayStartMillis()
        return transactionDao.getTodayTransactionCount(todayStart)
    }

    fun getRecentSalesSummaries() = transactionDao.getRecentSalesSummaries()

    suspend fun createTransaction(
        type: String,
        provider: String,
        product: String,
        amount: Double,
        fee: Double,
        targetNumber: String,
        paymentMethod: String = "WALLET"
    ): TransactionEntity {
        val transaction = TransactionEntity(
            type = type,
            provider = provider,
            product = product,
            amount = amount,
            fee = fee,
            targetNumber = targetNumber,
            referenceNumber = generateReferenceNumber(),
            status = "PENDING",
            paymentMethod = paymentMethod
        )
        val id = transactionDao.insert(transaction)
        return transaction.copy(id = id)
    }

    suspend fun updateTransactionStatus(id: Long, status: String, remarks: String = "") {
        val transaction = transactionDao.getTransactionById(id) ?: return
        transactionDao.update(
            transaction.copy(
                status = status,
                remarks = remarks,
                completedAt = if (status != "PENDING") System.currentTimeMillis() else null
            )
        )
    }

    suspend fun syncPendingTransactions(token: String): Result<Int> {
        return try {
            val unsynced = transactionDao.getUnsyncedTransactions()
            if (unsynced.isEmpty()) return Result.success(0)

            val syncRequests = unsynced.map {
                SyncTransactionRequest(
                    localId = it.id,
                    type = it.type,
                    referenceNumber = it.referenceNumber,
                    amount = it.amount,
                    status = it.status,
                    createdAt = it.createdAt
                )
            }
            val response = apiService.syncTransactions("Bearer $token", syncRequests)
            if (response.isSuccessful && response.body()?.success == true) {
                transactionDao.markAsSynced(unsynced.map { it.id })
                Result.success(response.body()?.syncedCount ?: 0)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Sync failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    private fun generateReferenceNumber(): String {
        val timestamp = System.currentTimeMillis().toString().takeLast(10)
        val random = UUID.randomUUID().toString().take(6).uppercase()
        return "EP$timestamp$random"
    }

    private fun getTodayStartMillis(): Long {
        val calendar = java.util.Calendar.getInstance()
        calendar.set(java.util.Calendar.HOUR_OF_DAY, 0)
        calendar.set(java.util.Calendar.MINUTE, 0)
        calendar.set(java.util.Calendar.SECOND, 0)
        calendar.set(java.util.Calendar.MILLISECOND, 0)
        return calendar.timeInMillis
    }
}
