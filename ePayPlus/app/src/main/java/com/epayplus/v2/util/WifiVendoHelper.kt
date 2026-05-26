package com.epayplus.v2.util

import android.content.Context
import android.net.wifi.WifiManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.net.HttpURLConnection
import java.net.URL

class WifiVendoHelper(private val context: Context) {

    data class WifiVendoConfig(
        val routerIp: String = "192.168.1.1",
        val apiEndpoint: String = "/api/voucher",
        val username: String = "admin",
        val password: String = "admin",
        val ratePerMinute: Double = 1.0,
        val minimumAmount: Double = 5.0
    )

    private var config = WifiVendoConfig()

    fun updateConfig(newConfig: WifiVendoConfig) {
        config = newConfig
    }

    suspend fun generateVoucher(amount: Double): Result<VoucherInfo> = withContext(Dispatchers.IO) {
        try {
            if (amount < config.minimumAmount) {
                return@withContext Result.failure(Exception("Minimum amount is ₱${config.minimumAmount}"))
            }

            val minutes = (amount / config.ratePerMinute).toInt()
            val url = URL("http://${config.routerIp}${config.apiEndpoint}?minutes=$minutes")
            val connection = url.openConnection() as HttpURLConnection
            connection.requestMethod = "POST"
            connection.setRequestProperty("Authorization", "Basic ${
                android.util.Base64.encodeToString(
                    "${config.username}:${config.password}".toByteArray(),
                    android.util.Base64.NO_WRAP
                )
            }")
            connection.connectTimeout = 10000
            connection.readTimeout = 10000

            val responseCode = connection.responseCode
            if (responseCode == HttpURLConnection.HTTP_OK) {
                val response = connection.inputStream.bufferedReader().readText()
                val voucher = VoucherInfo(
                    code = parseVoucherCode(response),
                    minutes = minutes,
                    amount = amount
                )
                Result.success(voucher)
            } else {
                Result.failure(Exception("Failed to generate voucher: HTTP $responseCode"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun calculateMinutes(amount: Double): Int = (amount / config.ratePerMinute).toInt()

    fun isWifiConnected(): Boolean {
        val wifiManager = context.applicationContext.getSystemService(Context.WIFI_SERVICE) as WifiManager
        return wifiManager.isWifiEnabled && wifiManager.connectionInfo.networkId != -1
    }

    private fun parseVoucherCode(response: String): String {
        // Parse voucher code from router API response
        return try {
            val regex = Regex("\"code\"\\s*:\\s*\"([^\"]+)\"")
            regex.find(response)?.groupValues?.get(1) ?: "VOUCHER-${System.currentTimeMillis()}"
        } catch (e: Exception) {
            "VOUCHER-${System.currentTimeMillis()}"
        }
    }

    data class VoucherInfo(
        val code: String,
        val minutes: Int,
        val amount: Double
    )
}
