package com.epayplus.v2.service

import android.app.PendingIntent
import android.content.Context
import android.telephony.SmsManager
import com.epayplus.v2.data.local.EPayDatabase
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.data.remote.EPayApiService
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class SmsService @Inject constructor(
    @ApplicationContext private val context: Context,
    private val apiService: EPayApiService,
    private val tokenManager: TokenManager
) {
    suspend fun sendSms(number: String, message: String): Boolean {
        return withContext(Dispatchers.IO) {
            try {
                val smsManager = context.getSystemService(SmsManager::class.java)
                    ?: @Suppress("DEPRECATION") SmsManager.getDefault()

                val parts = smsManager.divideMessage(message)
                if (parts.size == 1) {
                    smsManager.sendTextMessage(number, null, message, null, null)
                } else {
                    smsManager.sendMultipartTextMessage(number, null, parts, null, null)
                }

                reportSmsToServer(number, message, "outgoing", "sent")
                true
            } catch (e: Exception) {
                reportSmsToServer(number, message, "outgoing", "failed")
                false
            }
        }
    }

    suspend fun processIncomingSms(sender: String, body: String) {
        withContext(Dispatchers.IO) {
            reportSmsToServer(sender, body, "incoming", "received")

            val parsed = parseSmsResponse(body)
            if (parsed != null) {
                // Transaction confirmation received — update local DB
            }
        }
    }

    private fun parseSmsResponse(body: String): SmsParseResult? {
        val successPatterns = listOf(
            Regex("(?i)success.*ref.*?(\\d{10,})"),
            Regex("(?i)confirmed.*txn.*?(\\d{10,})"),
            Regex("(?i)loaded.*ref.*no.*?(\\d{8,})")
        )

        val failPatterns = listOf(
            Regex("(?i)failed|declined|insufficient|invalid"),
        )

        for (pattern in successPatterns) {
            val match = pattern.find(body)
            if (match != null) {
                return SmsParseResult(
                    status = "SUCCESS",
                    referenceNumber = match.groupValues.getOrNull(1) ?: ""
                )
            }
        }

        for (pattern in failPatterns) {
            if (pattern.containsMatchIn(body)) {
                return SmsParseResult(status = "FAILED", referenceNumber = "")
            }
        }

        return null
    }

    private suspend fun reportSmsToServer(number: String, message: String, direction: String, status: String) {
        try {
            val deviceId = tokenManager.getDeviceId() ?: return
            apiService.reportSms(
                mapOf(
                    "device_id" to deviceId,
                    "direction" to direction,
                    "number" to number,
                    "message" to message,
                    "status" to status
                )
            )
        } catch (_: Exception) { }
    }

    data class SmsParseResult(
        val status: String,
        val referenceNumber: String
    )
}
