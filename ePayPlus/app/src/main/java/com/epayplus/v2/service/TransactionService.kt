package com.epayplus.v2.service

import android.app.Service
import android.content.Intent
import android.os.IBinder
import android.telephony.SmsManager
import com.epayplus.v2.data.repository.AccountRepository
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.*
import javax.inject.Inject

@AndroidEntryPoint
class TransactionService : Service() {

    @Inject lateinit var transactionRepository: TransactionRepository
    @Inject lateinit var accountRepository: AccountRepository

    private val serviceScope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val action = intent?.getStringExtra("action") ?: return START_NOT_STICKY

        when (action) {
            "SEND_SMS" -> {
                val number = intent.getStringExtra("number") ?: ""
                val message = intent.getStringExtra("message") ?: ""
                val transactionId = intent.getLongExtra("transactionId", 0)
                sendSmsLoad(number, message, transactionId)
            }
            "SYNC" -> syncTransactions()
        }

        return START_NOT_STICKY
    }

    private fun sendSmsLoad(number: String, message: String, transactionId: Long) {
        serviceScope.launch {
            try {
                @Suppress("DEPRECATION")
                val smsManager = SmsManager.getDefault()
                smsManager.sendTextMessage(number, null, message, null, null)
                transactionRepository.updateTransactionStatus(transactionId, "SUCCESS", "SMS sent")
            } catch (e: Exception) {
                transactionRepository.updateTransactionStatus(transactionId, "FAILED", e.message ?: "SMS failed")
            }
        }
    }

    private fun syncTransactions() {
        serviceScope.launch {
            val account = accountRepository.getAccountSync() ?: return@launch
            transactionRepository.syncPendingTransactions(account.apiKey)
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onDestroy() {
        super.onDestroy()
        serviceScope.cancel()
    }
}
