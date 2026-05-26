package com.epayplus.v2.data.local.dao

import androidx.room.*
import com.epayplus.v2.data.local.entity.AccountEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface AccountDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(account: AccountEntity): Long

    @Update
    suspend fun update(account: AccountEntity)

    @Query("SELECT * FROM accounts LIMIT 1")
    fun getAccount(): Flow<AccountEntity?>

    @Query("SELECT * FROM accounts LIMIT 1")
    suspend fun getAccountSync(): AccountEntity?

    @Query("UPDATE accounts SET balance = :balance WHERE id = :id")
    suspend fun updateBalance(id: Long, balance: Double)

    @Query("UPDATE accounts SET pin = :pin WHERE id = :id")
    suspend fun updatePin(id: Long, pin: String)

    @Query("UPDATE accounts SET isKioskMode = :enabled, kioskPin = :pin WHERE id = :id")
    suspend fun updateKioskMode(id: Long, enabled: Boolean, pin: String)

    @Query("UPDATE accounts SET printerAddress = :address, printerType = :type WHERE id = :id")
    suspend fun updatePrinterConfig(id: Long, address: String, type: String)

    @Query("UPDATE accounts SET lastLoginAt = :timestamp WHERE id = :id")
    suspend fun updateLastLogin(id: Long, timestamp: Long = System.currentTimeMillis())
}
