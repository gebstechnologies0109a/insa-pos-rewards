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

    fun searchTransactions(query: String): Flow<List<TransactionEntity>> =
        transactionDao.searchTransactions("%$query%")

    fun getTodaySales(): Flow<Double> {
        return transactionDao.getTodaySales(getTodayStartMillis())
    }

    fun getTodayTransactionCount(): Flow<Int> {
        return transactionDao.getTodayTransactionCount(getTodayStartMillis())
    }

    fun getRecentSalesSummaries() = transactionDao.getRecentSalesSummaries()

    suspend fun getTransactionById(id: Long): TransactionEntity? =
        transactionDao.getTransactionById(id)

    suspend fun processEload(
        providerCode: String,
        productCode: String,
        mobileNumber: String,
        amount: Double,
        providerName: String,
        productName: String
    ): Result<TransactionEntity> {
        val refNumber = generateReferenceNumber()
        val transaction = TransactionEntity(
            type = "ELOAD",
            provider = providerName,
            product = productName,
            amount = amount,
            fee = 0.0,
            targetNumber = mobileNumber,
            referenceNumber = refNumber,
            status = "PENDING",
            paymentMethod = "WALLET"
        )
        val localId = transactionDao.insert(transaction)

        return try {
            val response = apiService.processEload(
                EloadRequest(
                    providerCode = providerCode,
                    productCode = productCode,
                    mobileNumber = mobileNumber,
                    amount = amount,
                    referenceId = refNumber
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val serverRef = response.body()?.referenceNumber ?: refNumber
                transactionDao.updateStatus(localId, "SUCCESS")
                transactionDao.updateReferenceNumber(localId, serverRef)
                val updated = transactionDao.getTransactionById(localId)!!
                Result.success(updated)
            } else {
                val msg = response.body()?.message ?: "Transaction failed"
                transactionDao.updateStatus(localId, "FAILED")
                transactionDao.updateRemarks(localId, msg)
                Result.failure(Exception(msg))
            }
        } catch (e: Exception) {
            transactionDao.updateStatus(localId, "FAILED")
            transactionDao.updateRemarks(localId, e.localizedMessage ?: "Network error")
            Result.failure(e)
        }
    }

    suspend fun processBillPayment(
        providerCode: String,
        productCode: String,
        accountNumber: String,
        amount: Double,
        providerName: String
    ): Result<TransactionEntity> {
        val refNumber = generateReferenceNumber()
        val transaction = TransactionEntity(
            type = "BILLS",
            provider = providerName,
            product = "Bill Payment",
            amount = amount,
            fee = 0.0,
            targetNumber = accountNumber,
            referenceNumber = refNumber,
            status = "PENDING",
            paymentMethod = "WALLET"
        )
        val localId = transactionDao.insert(transaction)

        return try {
            val response = apiService.processBillPayment(
                BillPaymentRequest(
                    providerCode = providerCode,
                    productCode = productCode,
                    accountNumber = accountNumber,
                    amount = amount,
                    referenceId = refNumber
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val serverRef = response.body()?.referenceNumber ?: refNumber
                transactionDao.updateStatus(localId, "SUCCESS")
                transactionDao.updateReferenceNumber(localId, serverRef)
                Result.success(transactionDao.getTransactionById(localId)!!)
            } else {
                val msg = response.body()?.message ?: "Transaction failed"
                transactionDao.updateStatus(localId, "FAILED")
                transactionDao.updateRemarks(localId, msg)
                Result.failure(Exception(msg))
            }
        } catch (e: Exception) {
            transactionDao.updateStatus(localId, "FAILED")
            transactionDao.updateRemarks(localId, e.localizedMessage ?: "Network error")
            Result.failure(e)
        }
    }

    suspend fun processEcash(
        providerCode: String,
        accountNumber: String,
        amount: Double,
        providerName: String
    ): Result<TransactionEntity> {
        val refNumber = generateReferenceNumber()
        val transaction = TransactionEntity(
            type = "ECASH",
            provider = providerName,
            product = "Cash-In",
            amount = amount,
            fee = 0.0,
            targetNumber = accountNumber,
            referenceNumber = refNumber,
            status = "PENDING",
            paymentMethod = "WALLET"
        )
        val localId = transactionDao.insert(transaction)

        return try {
            val response = apiService.processEcash(
                EcashRequest(
                    providerCode = providerCode,
                    accountNumber = accountNumber,
                    amount = amount,
                    referenceId = refNumber
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val serverRef = response.body()?.referenceNumber ?: refNumber
                transactionDao.updateStatus(localId, "SUCCESS")
                transactionDao.updateReferenceNumber(localId, serverRef)
                Result.success(transactionDao.getTransactionById(localId)!!)
            } else {
                val msg = response.body()?.message ?: "Transaction failed"
                transactionDao.updateStatus(localId, "FAILED")
                transactionDao.updateRemarks(localId, msg)
                Result.failure(Exception(msg))
            }
        } catch (e: Exception) {
            transactionDao.updateStatus(localId, "FAILED")
            transactionDao.updateRemarks(localId, e.localizedMessage ?: "Network error")
            Result.failure(e)
        }
    }

    suspend fun processRfid(
        providerCode: String,
        accountNumber: String,
        amount: Double,
        providerName: String,
        tagId: String? = null
    ): Result<TransactionEntity> {
        val refNumber = generateReferenceNumber()
        val transaction = TransactionEntity(
            type = "RFID",
            provider = providerName,
            product = "RFID Reload",
            amount = amount,
            fee = 0.0,
            targetNumber = accountNumber,
            referenceNumber = refNumber,
            status = "PENDING",
            paymentMethod = "WALLET",
            remarks = tagId?.let { "tag:$it" } ?: ""
        )
        val localId = transactionDao.insert(transaction)

        return try {
            val response = apiService.processRfid(
                RfidRequest(
                    providerCode = providerCode,
                    accountNumber = accountNumber,
                    amount = amount,
                    referenceId = refNumber,
                    tagId = tagId
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val serverRef = response.body()?.referenceNumber ?: refNumber
                transactionDao.updateStatus(localId, "SUCCESS")
                transactionDao.updateReferenceNumber(localId, serverRef)
                Result.success(transactionDao.getTransactionById(localId)!!)
            } else {
                val msg = response.body()?.message ?: "Transaction failed"
                transactionDao.updateStatus(localId, "FAILED")
                transactionDao.updateRemarks(localId, msg)
                Result.failure(Exception(msg))
            }
        } catch (e: Exception) {
            transactionDao.updateStatus(localId, "FAILED")
            transactionDao.updateRemarks(localId, e.localizedMessage ?: "Network error")
            Result.failure(e)
        }
    }

    suspend fun syncPendingTransactions(): Result<Int> {
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
            val response = apiService.syncTransactions(syncRequests)
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
