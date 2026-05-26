package com.epayplus.v2.data.repository

import android.provider.Settings
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.data.local.dao.AccountDao
import com.epayplus.v2.data.local.entity.AccountEntity
import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.domain.model.ChangePinRequest
import com.epayplus.v2.domain.model.LoginRequest
import kotlinx.coroutines.flow.Flow
import javax.inject.Inject

class AccountRepository @Inject constructor(
    private val accountDao: AccountDao,
    private val apiService: EPayApiService,
    private val tokenManager: TokenManager
) {
    fun getAccount(): Flow<AccountEntity?> = accountDao.getAccount()

    suspend fun getAccountSync(): AccountEntity? = accountDao.getAccountSync()

    suspend fun login(accountId: String, pin: String, deviceId: String): Result<AccountEntity> {
        return try {
            val response = apiService.login(LoginRequest(accountId, pin, deviceId))
            if (response.isSuccessful && response.body()?.success == true) {
                val body = response.body()!!
                val accountInfo = body.account!!
                val token = body.token!!

                tokenManager.saveSession(
                    token = token,
                    accountId = accountInfo.id,
                    businessName = accountInfo.businessName,
                    ownerName = accountInfo.ownerName
                )

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
                val errorMsg = response.body()?.message
                    ?: response.errorBody()?.string()
                    ?: "Login failed. Please check your credentials."
                Result.failure(Exception(errorMsg))
            }
        } catch (e: Exception) {
            Result.failure(Exception("Connection error: ${e.localizedMessage ?: "Unable to reach server"}"))
        }
    }

    suspend fun refreshBalance(): Result<Double> {
        return try {
            val response = apiService.getBalance()
            if (response.isSuccessful && response.body()?.success == true) {
                val balance = response.body()!!.balance
                val account = accountDao.getAccountSync()
                account?.let { accountDao.updateBalance(it.id, balance) }
                Result.success(balance)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to get balance"))
            }
        } catch (e: Exception) {
            val account = accountDao.getAccountSync()
            if (account != null) {
                Result.success(account.balance)
            } else {
                Result.failure(e)
            }
        }
    }

    suspend fun logout() {
        tokenManager.clearSession()
        accountDao.getAccountSync()?.let { accountDao.delete(it) }
    }

    suspend fun changePin(currentPin: String, newPin: String): Result<Unit> {
        return try {
            val response = apiService.changePin(ChangePinRequest(currentPin, newPin))
            if (response.isSuccessful && response.body()?.success == true) {
                val account = accountDao.getAccountSync()
                account?.let { accountDao.updatePin(it.id, newPin) }
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to change PIN"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun saveAccount(account: AccountEntity) {
        accountDao.insert(account)
    }

    fun isLoggedIn(): Flow<Boolean> = tokenManager.isLoggedIn
}
