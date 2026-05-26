package com.epayplus.v2.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import android.util.Log
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import javax.inject.Inject

@AndroidEntryPoint
class SmsReceiver : BroadcastReceiver() {

    @Inject lateinit var transactionRepository: TransactionRepository

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) return

        val messages = Telephony.Sms.Intents.getMessagesFromIntent(intent)
        messages.forEach { sms ->
            val sender = sms.displayOriginatingAddress
            val body = sms.messageBody

            Log.d("SmsReceiver", "SMS from $sender: $body")

            CoroutineScope(Dispatchers.IO).launch {
                processSmsResponse(sender, body)
            }
        }
    }

    private suspend fun processSmsResponse(sender: String, body: String) {
        // Parse SMS responses from network providers
        // Pattern matching for transaction confirmations
        val successPatterns = listOf(
            Regex("(?i)success.*ref.*?(\\w+)"),
            Regex("(?i)load.*sent.*?(09\\d{9})"),
            Regex("(?i)confirmed.*ref.*?(\\d+)")
        )

        val failPatterns = listOf(
            Regex("(?i)failed|error|insufficient|invalid"),
            Regex("(?i)unable.*process"),
            Regex("(?i)transaction.*declined")
        )

        for (pattern in successPatterns) {
            val match = pattern.find(body)
            if (match != null) {
                val refNumber = match.groupValues.getOrNull(1) ?: ""
                val transaction = transactionRepository.run {
                    // Update matching pending transaction
                }
                return
            }
        }

        for (pattern in failPatterns) {
            if (pattern.containsMatchIn(body)) {
                // Mark transaction as failed
                return
            }
        }
    }
}
