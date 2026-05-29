package com.insapos.v2.printers

import android.content.Context
import android.util.Log
import java.io.File

/**
 * Reflection-based Sunmi built-in printer access (no compile-time SDK dependency).
 */
object SunmiPrinterHelper {
    private const val TAG = "SunmiPrinterHelper"
    private const val WOYOU_PACKAGE = "woyou.aidlservice.jiuiv5"

    fun isSunmiDevice(): Boolean =
        android.os.Build.MANUFACTURER.lowercase().contains("sunmi")

    fun hasWoyouService(context: Context): Boolean = try {
        context.packageManager.getPackageInfo(WOYOU_PACKAGE, 0)
        true
    } catch (_: Exception) {
        false
    }

    fun findPrintService(context: Context): Any? {
        findInnerPrinterService(context)?.let { return it }
        findSunmiPrinterService()?.let { return it }
        return null
    }

    fun sendRaw(service: Any, data: ByteArray): Boolean {
        val methods = listOf(
            arrayOf("sendRAWData", ByteArray::class.java, Any::class.java),
            arrayOf("sendRAWData", ByteArray::class.java),
            arrayOf("sendRawData", ByteArray::class.java, Any::class.java),
            arrayOf("sendRawData", ByteArray::class.java),
            arrayOf("printRawData", ByteArray::class.java),
        )
        for (spec in methods) {
            try {
                val name = spec[0] as String
                val params = spec.drop(1).map { it as Class<*> }.toTypedArray()
                val method = service.javaClass.getMethod(name, *params)
                if (params.size == 2) {
                    method.invoke(service, data, null)
                } else {
                    method.invoke(service, data)
                }
                return true
            } catch (_: Exception) {
                /* try next */
            }
        }
        Log.w(TAG, "No compatible Sunmi send method on ${service.javaClass.name}")
        return false
    }

    private fun findInnerPrinterService(context: Context): Any? {
        return try {
            val managerClass = Class.forName("com.sunmi.peripheral.printer.InnerPrinterManager")
            val manager = managerClass.getMethod("getInstance", Context::class.java).invoke(null, context)
                ?: managerClass.getMethod("getInstance").invoke(null)
            for (methodName in listOf("getService", "getInnerPrinterService", "getPrinterService")) {
                try {
                    val service = managerClass.getMethod(methodName).invoke(manager)
                    if (service != null) {
                        Log.i(TAG, "Sunmi InnerPrinterManager.$methodName available")
                        return service
                    }
                } catch (_: Exception) {
                    /* next */
                }
            }
            null
        } catch (e: Exception) {
            Log.d(TAG, "InnerPrinterManager unavailable: ${e.message}")
            null
        }
    }

    private fun findSunmiPrinterService(): Any? {
        return try {
            val clazz = Class.forName("com.sunmi.peripheral.printer.SunmiPrinterService")
            clazz.getDeclaredMethod("getInstance").invoke(null)
        } catch (_: Exception) {
            null
        }
    }

    fun genericDeviceNodes(): List<String> = listOf(
        "/dev/usb/lp0",
        "/dev/usb/lp1",
        "/dev/ttyUSB0",
        "/dev/ttyS0",
        "/dev/ttyS1",
        "/dev/ttyS2",
        "/dev/ttyS3",
    ).filter { File(it).exists() }
}
