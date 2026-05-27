package com.insapos.v2

import android.content.Context
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbManager
import android.os.Build
import android.provider.Settings
import org.json.JSONArray
import org.json.JSONObject

object DeviceInfo {

    fun toJson(context: Context): JSONObject {
        val deviceId = Settings.Secure.getString(
            context.contentResolver,
            Settings.Secure.ANDROID_ID
        )
        val fingerprint = DeviceFingerprint.get(context)
        return JSONObject().apply {
            put("app", "INSAPOSv2")
            put("version", BuildConfig.VERSION_NAME)
            put("versionCode", BuildConfig.VERSION_CODE)
            put("deviceId", deviceId)
            put("device_fingerprint", fingerprint)
            put("fingerprint", fingerprint)
            put("manufacturer", Build.MANUFACTURER)
            put("model", Build.MODEL)
            put("brand", Build.BRAND)
            put("androidVersion", Build.VERSION.RELEASE)
            put("sdkInt", Build.VERSION.SDK_INT)
            put("product", Build.PRODUCT)
            put("hardware", Build.HARDWARE)
            put("display", Build.DISPLAY)
            put("isDebug", BuildConfig.DEBUG)
            put("peripherals", getPeripherals(context))
        }
    }

    private fun getPeripherals(context: Context): JSONObject {
        val peripherals = JSONObject()
        val usbDevices = JSONArray()
        var hasKeyboard = false
        var hasMouse = false
        var hasScanner = false
        var hasUsbPrinter = false

        try {
            val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager
            usbManager?.deviceList?.values?.forEach { device ->
                val info = JSONObject().apply {
                    put("name", device.deviceName)
                    put("vendorId", device.vendorId)
                    put("productId", device.productId)
                    put("class", device.deviceClass)
                }
                usbDevices.put(info)

                for (i in 0 until device.interfaceCount) {
                    when (device.getInterface(i).interfaceClass) {
                        UsbConstants.USB_CLASS_HID -> {
                            when (device.getInterface(i).interfaceProtocol) {
                                1 -> hasKeyboard = true
                                2 -> hasMouse = true
                                else -> hasScanner = true
                            }
                        }
                        UsbConstants.USB_CLASS_PRINTER -> hasUsbPrinter = true
                    }
                }
            }
        } catch (_: Exception) {}

        peripherals.put("usb_devices", usbDevices)
        peripherals.put("keyboard", hasKeyboard)
        peripherals.put("mouse", hasMouse)
        peripherals.put("scanner", hasScanner)
        peripherals.put("usb_printer", hasUsbPrinter)
        return peripherals
    }

    fun toJsonString(context: Context): String = toJson(context).toString()
}
