package com.insapos.insabuddy.printers

import android.content.Context
import android.util.Log
import java.lang.reflect.Method

/**
 * Supports built-in thermal printers on Android POS terminals:
 * Sunmi, iMin, Newland, and other manufacturers that expose
 * a print service via system APIs.
 */
class BuiltInPrinter(private val context: Context) : Printer {

    companion object {
        private const val TAG = "BuiltInPrinter"

        private val SUNMI_SERVICE = "woyou.aidlservice.jiuiv5.IWoyouService"
        private val IMIN_SERVICE = "com.imin.printerlib.IminPrintUtils"

        fun isAvailable(context: Context): Boolean {
            return getSunmiPrintService(context) != null || getIminPrintService(context) != null
        }

        private fun getSunmiPrintService(context: Context): Any? {
            return try {
                val clazz = Class.forName("com.sunmi.peripheral.printer.SunmiPrinterService")
                clazz.getDeclaredMethod("getInstance").invoke(null)
            } catch (_: Exception) {
                null
            }
        }

        private fun getIminPrintService(context: Context): Any? {
            return try {
                val clazz = Class.forName("com.imin.printerlib.IminPrintUtils")
                val getInstance: Method = clazz.getDeclaredMethod("getInstance", Context::class.java)
                getInstance.invoke(null, context)
            } catch (_: Exception) {
                null
            }
        }
    }

    override val type = "builtin"
    override val name: String get() = detectDeviceBrand()

    private var printerService: Any? = null
    private var brand: String = "unknown"

    override fun connect(): Boolean {
        return try {
            printerService = getSunmiPrintService(context)
            if (printerService != null) {
                brand = "sunmi"
                Log.i(TAG, "Sunmi printer service connected")
                return true
            }

            printerService = getIminPrintService(context)
            if (printerService != null) {
                brand = "imin"
                Log.i(TAG, "iMin printer service connected")
                return true
            }

            // Fallback: try generic Android print service via /dev/usb/lp0
            if (java.io.File("/dev/usb/lp0").exists()) {
                brand = "generic"
                Log.i(TAG, "Generic built-in printer detected at /dev/usb/lp0")
                return true
            }

            Log.w(TAG, "No built-in printer found")
            false
        } catch (e: Exception) {
            Log.e(TAG, "Connection failed: ${e.message}")
            false
        }
    }

    override fun disconnect() {
        printerService = null
    }

    override fun isConnected(): Boolean = printerService != null

    override fun send(data: ByteArray): Boolean {
        if (!isConnected()) {
            if (!connect()) return false
        }
        return try {
            when (brand) {
                "sunmi" -> sendSunmi(data)
                "imin" -> sendImin(data)
                "generic" -> sendGeneric(data)
                else -> false
            }
        } catch (e: Exception) {
            Log.e(TAG, "Send failed: ${e.message}")
            false
        }
    }

    private fun sendSunmi(data: ByteArray): Boolean {
        return try {
            val service = printerService ?: return false
            val sendMethod = service.javaClass.getMethod("sendRAWData", ByteArray::class.java, Any::class.java)
            sendMethod.invoke(service, data, null)
            true
        } catch (e: Exception) {
            Log.e(TAG, "Sunmi send failed: ${e.message}")
            false
        }
    }

    private fun sendImin(data: ByteArray): Boolean {
        return try {
            val service = printerService ?: return false
            val sendMethod = service.javaClass.getMethod("sendRAWData", ByteArray::class.java)
            sendMethod.invoke(service, data)
            true
        } catch (e: Exception) {
            Log.e(TAG, "iMin send failed: ${e.message}")
            false
        }
    }

    private fun sendGeneric(data: ByteArray): Boolean {
        return try {
            java.io.FileOutputStream("/dev/usb/lp0").use { it.write(data) }
            true
        } catch (e: Exception) {
            Log.e(TAG, "Generic send failed: ${e.message}")
            false
        }
    }

    override fun getStatus(): PrinterStatus {
        return PrinterStatus(
            connected = isConnected(),
            type = type,
            name = name
        )
    }

    private fun detectDeviceBrand(): String {
        val manufacturer = android.os.Build.MANUFACTURER.lowercase()
        return when {
            manufacturer.contains("sunmi") -> "Sunmi Built-in Printer"
            manufacturer.contains("imin") -> "iMin Built-in Printer"
            manufacturer.contains("newland") -> "Newland Built-in Printer"
            else -> "Built-in Printer ($manufacturer)"
        }
    }
}
