package com.epayplus.v2.data.local.dao

import androidx.room.*
import com.epayplus.v2.data.local.entity.TransactionEntity
import com.epayplus.v2.data.local.entity.SalesSummaryEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface TransactionDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(transaction: TransactionEntity): Long

    @Update
    suspend fun update(transaction: TransactionEntity)

    @Delete
    suspend fun delete(transaction: TransactionEntity)

    @Query("SELECT * FROM transactions ORDER BY createdAt DESC")
    fun getAllTransactions(): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE type = :type ORDER BY createdAt DESC")
    fun getTransactionsByType(type: String): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE status = :status ORDER BY createdAt DESC")
    fun getTransactionsByStatus(status: String): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE createdAt BETWEEN :startTime AND :endTime ORDER BY createdAt DESC")
    fun getTransactionsByDateRange(startTime: Long, endTime: Long): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE provider LIKE :query OR product LIKE :query OR targetNumber LIKE :query OR referenceNumber LIKE :query ORDER BY createdAt DESC")
    fun searchTransactions(query: String): Flow<List<TransactionEntity>>

    @Query("SELECT * FROM transactions WHERE id = :id")
    suspend fun getTransactionById(id: Long): TransactionEntity?

    @Query("SELECT * FROM transactions WHERE referenceNumber = :refNo")
    suspend fun getTransactionByRef(refNo: String): TransactionEntity?

    @Query("SELECT COUNT(*) FROM transactions WHERE createdAt >= :todayStart")
    fun getTodayTransactionCount(todayStart: Long): Flow<Int>

    @Query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS' AND createdAt >= :todayStart")
    fun getTodaySales(todayStart: Long): Flow<Double>

    @Query("UPDATE transactions SET status = :status, completedAt = CASE WHEN :status != 'PENDING' THEN :now ELSE completedAt END WHERE id = :id")
    suspend fun updateStatus(id: Long, status: String, now: Long = System.currentTimeMillis())

    @Query("UPDATE transactions SET referenceNumber = :refNumber WHERE id = :id")
    suspend fun updateReferenceNumber(id: Long, refNumber: String)

    @Query("UPDATE transactions SET remarks = :remarks WHERE id = :id")
    suspend fun updateRemarks(id: Long, remarks: String)

    @Query("SELECT * FROM transactions WHERE syncedToServer = 0")
    suspend fun getUnsyncedTransactions(): List<TransactionEntity>

    @Query("UPDATE transactions SET syncedToServer = 1 WHERE id IN (:ids)")
    suspend fun markAsSynced(ids: List<Long>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSalesSummary(summary: SalesSummaryEntity)

    @Query("SELECT * FROM sales_summary ORDER BY date DESC LIMIT 30")
    fun getRecentSalesSummaries(): Flow<List<SalesSummaryEntity>>

    @Query("SELECT * FROM sales_summary WHERE date = :date")
    suspend fun getSalesSummaryByDate(date: String): SalesSummaryEntity?

    @Query("SELECT * FROM transactions ORDER BY createdAt DESC LIMIT :limit")
    fun getRecentTransactions(limit: Int = 5): Flow<List<TransactionEntity>>
}
