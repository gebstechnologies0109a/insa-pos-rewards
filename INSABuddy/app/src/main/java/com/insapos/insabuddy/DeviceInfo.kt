package com.insapos.insabuddy

import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.BatteryManager
import android.os.Build
import org.json.JSONObject

class DeviceInfo(private val context: Context) {

    fun toJson(): JSONObject {
        return JSONObject().apply {
            put("model", "${Build.MANUFACTURER} ${Build.MODEL}")
            put("android_version", Build.VERSION.RELEASE)
            put("sdk_level", Build.VERSION.SDK_INT)
            put("battery", getBatteryLevel())
            put("battery_charging", isBatteryCharging())
            put("network_type", getNetworkType())
            put("app_version", BuildConfig.VERSION_NAME)
            put("app_version_code", BuildConfig.VERSION_CODE)
            put("device_id", Build.SERIAL.takeIf { it != Build.UNKNOWN } ?: Build.FINGERPRINT)
        }
    }

    private fun getBatteryLevel(): Int {
        val batteryStatus = context.registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
        val level = batteryStatus?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
        val scale = batteryStatus?.getIntExtra(BatteryManager.EXTRA_SCALE, 100) ?: 100
        return if (level >= 0) (level * 100 / scale) else -1
    }

    private fun isBatteryCharging(): Boolean {
        val batteryStatus = context.registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
        val status = batteryStatus?.getIntExtra(BatteryManager.EXTRA_STATUS, -1) ?: -1
        return status == BatteryManager.BATTERY_STATUS_CHARGING ||
               status == BatteryManager.BATTERY_STATUS_FULL
    }

    @Suppress("DEPRECATION")
    private fun getNetworkType(): String {
        val cm = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val network = cm.activeNetwork ?: return "none"
            val capabilities = cm.getNetworkCapabilities(network) ?: return "none"
            return when {
                capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "wifi"
                capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> "cellular"
                capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) -> "ethernet"
                else -> "other"
            }
        }

        val info = cm.activeNetworkInfo
        return when {
            info == null || !info.isConnected -> "none"
            info.type == ConnectivityManager.TYPE_WIFI -> "wifi"
            info.type == ConnectivityManager.TYPE_MOBILE -> "cellular"
            info.type == ConnectivityManager.TYPE_ETHERNET -> "ethernet"
            else -> "other"
        }
    }
}
