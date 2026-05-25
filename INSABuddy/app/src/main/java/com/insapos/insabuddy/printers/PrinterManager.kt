package com.insapos.insabuddy.printers

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.content.Context
import android.content.SharedPreferences
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbManager
import android.util.Log

class PrinterManager(private val context: Context) {

    companion object {
        private const val TAG = "PrinterManager"
        private const val PREFS_NAME = "insabuddy_printers"
        private const val KEY_PRINTER_TYPE = "printer_type"
        private const val KEY_PRINTER_ADDRESS = "printer_address"
    }

    var currentPrinter: Printer? = null
        private set

    private val prefs: SharedPreferences =
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    val onPrinterChanged: MutableList<(PrinterStatus?) -> Unit> = mutableListOf()

    fun initialize() {
        restoreSavedPrinter()
    }

    fun print(data: ByteArray): Boolean {
        val printer = currentPrinter
        if (printer == null) {
            Log.w(TAG, "No printer selected")
            return false
        }
        return printer.send(data)
    }

    fun printText(text: String): Boolean {
        val printer = currentPrinter
        if (printer == null) {
            Log.w(TAG, "No printer selected")
            return false
        }
        return printer.printText(text)
    }

    fun openDrawer() {
        val pulse = byteArrayOf(0x1B, 0x70, 0x00, 0x19, 0xFA.toByte())
        currentPrinter?.send(pulse)
    }

    fun getStatus(): PrinterStatus {
        return currentPrinter?.getStatus() ?: PrinterStatus(
            connected = false,
            type = "none",
            name = "No printer"
        )
    }

    fun selectPrinter(printer: Printer): Boolean {
        currentPrinter?.disconnect()
        val connected = printer.connect()
        if (connected) {
            currentPrinter = printer
            savePrinter(printer)
            notifyChange(printer.getStatus())
        }
        return connected
    }

    @SuppressLint("MissingPermission")
    fun scanBluetoothPrinters(): List<BluetoothPrinter> {
        val printers = mutableListOf<BluetoothPrinter>()
        try {
            val btManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
            val adapter = btManager?.adapter ?: BluetoothAdapter.getDefaultAdapter()
            if (adapter == null || !adapter.isEnabled) return printers

            adapter.bondedDevices?.forEach { device ->
                val majorClass = device.bluetoothClass?.majorDeviceClass ?: 0
                val deviceName = device.name?.lowercase() ?: ""
                if (majorClass == 0x0600 ||
                    deviceName.contains("printer") ||
                    deviceName.contains("pos") ||
                    deviceName.contains("thermal") ||
                    deviceName.contains("receipt") ||
                    deviceName.contains("esc")
                ) {
                    printers.add(BluetoothPrinter(device))
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Bluetooth scan failed: ${e.message}")
        }
        return printers
    }

    /**
     * Returns ALL paired Bluetooth devices so the user can select printers
     * that don't advertise a printer device class or known name pattern.
     */
    @SuppressLint("MissingPermission")
    fun scanAllBluetoothDevices(): List<BluetoothPrinter> {
        val printers = mutableListOf<BluetoothPrinter>()
        try {
            val btManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
            val adapter = btManager?.adapter ?: BluetoothAdapter.getDefaultAdapter()
            if (adapter == null || !adapter.isEnabled) return printers

            adapter.bondedDevices?.forEach { device ->
                printers.add(BluetoothPrinter(device))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Bluetooth scan all failed: ${e.message}")
        }
        return printers
    }

    fun scanUsbPrinters(): List<UsbPrinter> {
        val printers = mutableListOf<UsbPrinter>()
        try {
            val usbManager = context.getSystemService(Context.USB_SERVICE) as UsbManager
            usbManager.deviceList.values.forEach { device ->
                for (i in 0 until device.interfaceCount) {
                    if (device.getInterface(i).interfaceClass == UsbConstants.USB_CLASS_PRINTER) {
                        printers.add(UsbPrinter(context, device))
                        break
                    }
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "USB scan failed: ${e.message}")
        }
        return printers
    }

    fun scanBuiltInPrinter(): BuiltInPrinter? {
        return if (BuiltInPrinter.isAvailable(context)) {
            BuiltInPrinter(context)
        } else null
    }

    fun scanAll(): List<Printer> {
        val all = mutableListOf<Printer>()
        scanBuiltInPrinter()?.let { all.add(it) }
        // Include all paired Bluetooth devices (not just printer-class ones)
        // so users can connect to printers that don't advertise the right class
        val btDevices = scanAllBluetoothDevices()
        all.addAll(btDevices)
        all.addAll(scanUsbPrinters())
        return all
    }

    fun reconnect(): Boolean {
        val printer = currentPrinter ?: return false
        return if (!printer.isConnected()) printer.connect() else true
    }

    fun disconnect() {
        currentPrinter?.disconnect()
        currentPrinter = null
        notifyChange(null)
    }

    private fun savePrinter(printer: Printer) {
        prefs.edit()
            .putString(KEY_PRINTER_TYPE, printer.type)
            .putString(KEY_PRINTER_ADDRESS, printer.name)
            .apply()
    }

    private fun restoreSavedPrinter() {
        val savedType = prefs.getString(KEY_PRINTER_TYPE, null) ?: return
        val savedAddress = prefs.getString(KEY_PRINTER_ADDRESS, null) ?: return

        try {
            when (savedType) {
                "bluetooth" -> {
                    scanBluetoothPrinters().find { it.name == savedAddress }?.let {
                        if (it.connect()) {
                            currentPrinter = it
                            notifyChange(it.getStatus())
                        }
                    }
                }
                "network" -> {
                    val parts = savedAddress.split(":")
                    if (parts.size == 2) {
                        val printer = NetworkPrinter(parts[0], parts[1].toIntOrNull() ?: 9100)
                        if (printer.connect()) {
                            currentPrinter = printer
                            notifyChange(printer.getStatus())
                        }
                    }
                }
                "builtin" -> {
                    scanBuiltInPrinter()?.let {
                        if (it.connect()) {
                            currentPrinter = it
                            notifyChange(it.getStatus())
                        }
                    }
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Restore printer failed: ${e.message}")
        }
    }

    private fun notifyChange(status: PrinterStatus?) {
        onPrinterChanged.forEach { it(status) }
    }
}
