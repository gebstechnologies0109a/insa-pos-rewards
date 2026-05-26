package com.epayplus.v2.util

import android.content.Context
import android.telephony.SmsManager
import android.telephony.SubscriptionManager
import android.util.Log

class SmsHelper(private val context: Context) {

    fun sendSms(
        destinationNumber: String,
        message: String,
        simSlot: Int = 0
    ): Result<Unit> {
        return try {
            val smsManager = getSmsManager(simSlot)
            if (message.length > 160) {
                val parts = smsManager.divideMessage(message)
                smsManager.sendMultipartTextMessage(destinationNumber, null, parts, null, null)
            } else {
                smsManager.sendTextMessage(destinationNumber, null, message, null, null)
            }
            Log.d("SmsHelper", "SMS sent to $destinationNumber: $message")
            Result.success(Unit)
        } catch (e: Exception) {
            Log.e("SmsHelper", "Failed to send SMS: ${e.message}")
            Result.failure(e)
        }
    }

    fun buildEloadSms(keyword: String, phoneNumber: String, amount: Double): String {
        return "$keyword $phoneNumber ${amount.toInt()}"
    }

    fun buildEloadSmsFormatted(format: String, phoneNumber: String, amount: String): String {
        return format
            .replace("{number}", phoneNumber)
            .replace("{amount}", amount)
            .replace("{phone}", phoneNumber)
    }

    @Suppress("DEPRECATION")
    private fun getSmsManager(simSlot: Int): SmsManager {
        return try {
            val subscriptionManager = context.getSystemService(Context.TELEPHONY_SUBSCRIPTION_SERVICE) as SubscriptionManager
            val subscriptionInfoList = subscriptionManager.activeSubscriptionInfoList
            if (subscriptionInfoList != null && subscriptionInfoList.size > simSlot) {
                val subscriptionId = subscriptionInfoList[simSlot].subscriptionId
                SmsManager.getSmsManagerForSubscriptionId(subscriptionId)
            } else {
                SmsManager.getDefault()
            }
        } catch (e: SecurityException) {
            SmsManager.getDefault()
        }
    }
}
