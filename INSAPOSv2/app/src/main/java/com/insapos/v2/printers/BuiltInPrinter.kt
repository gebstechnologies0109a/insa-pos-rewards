package com.insapos.v2.printers

import android.content.Context
import android.util.Log
import java.io.FileOutputStream
import java.lang.reflect.Method
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit
import java.util.concurrent.TimeoutException

class BuiltInPrinter(private val context: Context) : Printer {

    companion object {
        private const val TAG = "BuiltInPrinter"
        private const val GENERIC_WRITE_TIMEOUT_MS = 3000L
        private val genericWriteExecutor = Executors.newCachedThreadPool { r ->
            Thread(r, "builtin-printer-write").apply { isDaemon = true }
        }

        fun isAvailable(context: Context): Boolean {
            return try {
                findSunmiService(context) != null
                    || findIminService(context) != null
                    || SunmiPrinterHelper.genericDeviceNodes().isNotEmpty()
                    || isLikelyBuiltInPosTerminal()
            } catch (_: Exception) {
                false
            }
        }

        private fun isLikelyBuiltInPosTerminal(): Boolean {
            val manufacturer = android.os.Build.MANUFACTURER.lowercase()
            val model = android.os.Build.MODEL.lowercase()
            return manufacturer.contains("sunmi")
                || manufacturer.contains("imin")
                || manufacturer.contains("newland")
                || manufacturer.contains("rockchip")
                || (manufacturer.contains("generic") && model.contains("pos"))
        }

        private fun findSunmiService(context: Context): Any? =
            SunmiPrinterHelper.findPrintService(context)
                ?: if (SunmiPrinterHelper.hasWoyouService(context)) Unit else null

        private fun findIminService(context: Context): Any? {
            return try {
                val clazz = Class.forName("com.imin.printerlib.IminPrintUtils")
                val getInstance: Method = clazz.getDeclaredMethod("getInstance", Context::class.java)
                getInstance.invoke(null, context)
            } catch (_: Exception) {
                null
            }
        }
    }

    override val type = PrinterType.BUILTIN
    override val name: String get() = detectDeviceBrand()

    private var printerService: Any? = null
    private var brand: String = "unknown"
    private var genericDevicePath: String? = null
    private var connected = false

    override fun connect(): Boolean {
        return try {
            printerService = SunmiPrinterHelper.findPrintService(context)
            if (printerService != null) {
                brand = "sunmi"
                connected = true
                Log.i(TAG, "Sunmi printer service connected")
                return true
            }

            if (SunmiPrinterHelper.hasWoyouService(context)) {
                brand = "sunmi"
                connected = true
                Log.i(TAG, "Sunmi woyou print service package present")
                return true
            }

            printerService = findIminService(context)
            if (printerService != null) {
                brand = "imin"
                connected = true
                Log.i(TAG, "iMin printer service connected")
                return true
            }

            val node = SunmiPrinterHelper.genericDeviceNodes().firstOrNull()
            if (node != null) {
                brand = "generic"
                genericDevicePath = node
                connected = true
                Log.i(TAG, "Generic built-in printer detected at $node")
                return true
            }

            if (isLikelyBuiltInPosTerminal()) {
                brand = "generic"
                genericDevicePath = "/dev/usb/lp0"
                connected = true
                Log.i(TAG, "Built-in POS terminal — attempting generic print path")
                return true
            }

            connected = false
            Log.w(TAG, "No built-in printer found")
            false
        } catch (e: Exception) {
            connected = false
            Log.e(TAG, "Connection failed: ${e.message}")
            false
        }
    }

    override fun disconnect() {
        printerService = null
        genericDevicePath = null
        connected = false
    }

    override fun isConnected(): Boolean = connected

    override fun send(data: ByteArray): Boolean {
        if (!isConnected() && !connect()) return false
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
        printerService?.let { service ->
            if (SunmiPrinterHelper.sendRaw(service, data)) return true
        }
        val service = SunmiPrinterHelper.findPrintService(context)
        if (service != null) {
            printerService = service
            if (SunmiPrinterHelper.sendRaw(service, data)) return true
        }
        return sendGeneric(data)
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
        val paths = buildList {
            genericDevicePath?.let { add(it) }
            addAll(SunmiPrinterHelper.genericDeviceNodes())
            add("/dev/usb/lp0")
        }.distinct()
        for (path in paths) {
            val ok = writeGenericPathWithTimeout(path, data)
            if (ok) {
                genericDevicePath = path
                Log.i(TAG, "Generic print succeeded on $path")
                return true
            }
            Log.d(TAG, "Generic send failed or timed out on $path")
        }
        Log.e(TAG, "Generic send failed on all device nodes")
        return false
    }

    override fun getStatus(): PrinterStatus {
        return PrinterStatus(connected = isConnected(), type = type, name = name)
    }

    private fun writeGenericPathWithTimeout(path: String, data: ByteArray): Boolean {
        val future = genericWriteExecutor.submit<Boolean> {
            try {
                FileOutputStream(path).use { stream ->
                    stream.write(data)
                    stream.flush()
                }
                true
            } catch (_: Exception) {
                false
            }
        }
        return try {
            future.get(GENERIC_WRITE_TIMEOUT_MS, TimeUnit.MILLISECONDS)
        } catch (_: TimeoutException) {
            future.cancel(true)
            false
        } catch (_: Exception) {
            false
        }
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
