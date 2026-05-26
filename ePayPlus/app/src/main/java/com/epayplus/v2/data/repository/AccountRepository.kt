package com.epayplus.v2.data.repository

import com.epayplus.v2.data.local.dao.AccountDao
import com.epayplus.v2.data.local.entity.AccountEntity
import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.domain.model.LoginRequest
import kotlinx.coroutines.flow.Flow
import javax.inject.Inject

class AccountRepository @Inject constructor(
    private val accountDao: AccountDao,
    private val apiService: EPayApiService
) {
    fun getAccount(): Flow<AccountEntity?> = accountDao.getAccount()

    suspend fun getAccountSync(): AccountEntity? = accountDao.getAccountSync()

    suspend fun login(accountId: String, pin: String, deviceId: String): Result<AccountEntity> {
        return try {
            val response = apiService.login(LoginRequest(accountId, pin, deviceId))
            if (response.isSuccessful && response.body()?.success == true) {
                val accountInfo = response.body()!!.account!!
                val token = response.body()!!.token!!
                val entity = AccountEntity(
                    accountId = accountInfo.id,
                    businessName = accountInfo.businessName,
                    ownerName = accountInfo.ownerName,
                    mobileNumber = accountInfo.mobileNumber,
                    email = accountInfo.email,
                    balance = accountInfo.balance,
                    pin = pin,
                    apiKey = token
                )
                accountDao.insert(entity)
                Result.success(entity)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Login failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun refreshBalance(): Result<Double> {
        return try {
            val account = accountDao.getAccountSync() ?: return Result.failure(Exception("No account"))
            val response = apiService.getBalance("Bearer ${account.apiKey}")
            if (response.isSuccessful && response.body()?.success == true) {
                val balance = response.body()!!.balance
                accountDao.updateBalance(account.id, balance)
                Result.success(balance)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to get balance"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateKioskMode(enabled: Boolean, pin: String) {
        val account = accountDao.getAccountSync() ?: return
        accountDao.updateKioskMode(account.id, enabled, pin)
    }

    suspend fun updatePrinterConfig(address: String, type: String) {
        val account = accountDao.getAccountSync() ?: return
        accountDao.updatePrinterConfig(account.id, address, type)
    }

    suspend fun changePin(currentPin: String, newPin: String): Result<Unit> {
        return try {
            val account = accountDao.getAccountSync() ?: return Result.failure(Exception("No account"))
            if (account.pin != currentPin) return Result.failure(Exception("Incorrect current PIN"))
            accountDao.updatePin(account.id, newPin)
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun saveAccount(account: AccountEntity) {
        accountDao.insert(account)
    }
}
