package com.insapos.v2

import android.content.Context
import android.os.Build
import android.provider.Settings
import org.json.JSONObject

object DeviceInfo {

    fun toJson(context: Context): JSONObject {
        val deviceId = Settings.Secure.getString(
            context.contentResolver,
            Settings.Secure.ANDROID_ID
        )
        val hw = try { HardwareDetector.detect(context).optJSONObject("summary") } catch (_: Exception) { null }
        return JSONObject().apply {
            put("app", "INSAPOSv2")
            put("version", BuildConfig.VERSION_NAME)
            put("versionCode", BuildConfig.VERSION_CODE)
            put("deviceId", deviceId)
            put("manufacturer", Build.MANUFACTURER)
            put("model", Build.MODEL)
            put("brand", Build.BRAND)
            put("androidVersion", Build.VERSION.RELEASE)
            put("sdkInt", Build.VERSION.SDK_INT)
            put("product", Build.PRODUCT)
            put("hardware", Build.HARDWARE)
            put("display", Build.DISPLAY)
            put("isDebug", BuildConfig.DEBUG)
            put("hasPhysicalKeyboard", hw?.optBoolean("hasPhysicalKeyboard", false) ?: false)
            put("hasMouse", hw?.optBoolean("hasMouse", false) ?: false)
            put("hasBarcodeScanner", hw?.optBoolean("hasBarcodeScanner", false) ?: false)
            put("hasUsbPrinter", hw?.optBoolean("hasUsbPrinter", false) ?: false)
        }
    }

    fun toJsonString(context: Context): String = toJson(context).toString()
}
