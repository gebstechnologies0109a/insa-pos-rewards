package com.insapos.v2.printers

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
        private const val PREFS_NAME = "insaposv2_printers"
        private const val KEY_PRINTER_TYPE = "printer_type"
        private const val KEY_PRINTER_ADDRESS = "printer_address"
    }

    var currentPrinter: Printer? = null
        private set

    var lastSelectedType: String? = null
        private set
    var lastSelectedName: String? = null
        private set

    private val prefs: SharedPreferences =
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    val onPrinterChanged: MutableList<(PrinterStatus?) -> Unit> = mutableListOf()

    fun initialize() {
        restoreSavedPrinter()
    }

    fun getActivePrinter(): Printer? = currentPrinter

    fun print(data: ByteArray): Boolean {
        val printer = currentPrinter ?: return false
        return printer.send(data)
    }

    fun printText(text: String): Boolean {
        val printer = currentPrinter ?: return false
        return printer.printText(text)
    }

    fun openDrawer() {
        currentPrinter?.openDrawer()
    }

    fun getStatus(): PrinterStatus {
        return currentPrinter?.getStatus() ?: PrinterStatus(
            connected = false, type = "none", name = "No printer"
        )
    }

    fun selectPrinter(printer: Printer): Boolean {
        currentPrinter?.disconnect()
        val connected = printer.connect()
        if (connected) {
            currentPrinter = printer
            lastSelectedType = printer.type
            lastSelectedName = printer.name
            savePrinter(printer)
            notifyChange(printer.getStatus())
        }
        return connected
    }

    fun selectByName(name: String): Boolean {
        val all = scanAll()
        val match = all.find { it.name == name } ?: return false
        return selectPrinter(match)
    }

    fun selectByTypeAndName(type: String, name: String): Boolean {
        val all = scanAll()
        val match = all.find { it.type == type && it.name == name }
            ?: all.find { it.name == name }
            ?: return false
        return selectPrinter(match)
    }

    /**
     * Select by type/name; returns a user-facing error when selection or connect fails.
     */
    fun selectByTypeAndNameWithMessage(type: String, name: String): Pair<Boolean, String?> {
        if (name.isBlank()) return false to "Printer name required"
        val all = scanAll()
        val match = if (type.isNotBlank()) {
            all.find { it.type == type && it.name == name } ?: all.find { it.name == name }
        } else {
            all.find { it.name == name }
        }
        if (match == null) {
            return false to "Printer not found: $name"
        }
        if (match is UsbPrinter && !match.hasUsbPermission()) {
            return false to "USB permission required for ${match.name}"
        }
        if (!selectPrinter(match)) {
            val hint = when (match.type) {
                "usb" -> "Grant USB permission when prompted, then try again"
                "bluetooth" -> "Ensure the printer is paired, powered on, and in range"
                else -> "Check that the printer is available"
            }
            return false to "Could not connect to ${match.name}. $hint"
        }
        return true to null
    }

    /**
     * Ensures a connected printer for printing; optionally re-selects from request body.
     */
    fun ensureActivePrinter(type: String?, name: String?): Pair<Printer?, String?> {
        if (!type.isNullOrBlank() && !name.isNullOrBlank()) {
            val (ok, err) = selectByTypeAndNameWithMessage(type, name)
            if (!ok) return null to err
            return currentPrinter to null
        }
        val active = currentPrinter
        if (active != null) {
            if (active.isConnected()) return active to null
            if (active is UsbPrinter && !active.hasUsbPermission()) {
                return null to "USB permission required for ${active.name}"
            }
            if (active.connect()) return active to null
        }
        val savedType = lastSelectedType
        val savedName = lastSelectedName
        if (!savedName.isNullOrBlank()) {
            val (ok, err) = selectByTypeAndNameWithMessage(savedType ?: "", savedName)
            if (ok) return currentPrinter to null
            return null to (err ?: "Could not reconnect to $savedName")
        }
        return null to "No printer connected — select a printer first"
    }

    fun findUsbPrinterByName(name: String): UsbPrinter? {
        return scanUsbPrinters().find { it.name == name }
    }

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
            Log.e(TAG, "Bluetooth scan failed: ${e.message}")
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
        return if (BuiltInPrinter.isAvailable(context)) BuiltInPrinter(context) else null
    }

    fun scanAll(): List<Printer> {
        val all = mutableListOf<Printer>()
        scanBuiltInPrinter()?.let { all.add(it) }
        all.addAll(scanAllBluetoothDevices())
        all.addAll(scanUsbPrinters())
        return all
    }

    fun reconnect(): Boolean {
        val printer = currentPrinter ?: return false
        return if (!printer.isConnected()) printer.connect() else true
    }

    fun release() {
        currentPrinter?.disconnect()
        currentPrinter = null
    }

    private fun savePrinter(printer: Printer) {
        prefs.edit()
            .putString(KEY_PRINTER_TYPE, printer.type)
            .putString(KEY_PRINTER_ADDRESS, printer.name)
            .apply()
    }

    @SuppressLint("MissingPermission")
    private fun restoreSavedPrinter() {
        val savedType = prefs.getString(KEY_PRINTER_TYPE, null) ?: return
        val savedAddress = prefs.getString(KEY_PRINTER_ADDRESS, null) ?: return

        try {
            when (savedType) {
                "bluetooth" -> {
                    scanAllBluetoothDevices().find { it.name == savedAddress }?.let {
                        if (it.connect()) {
                            currentPrinter = it
                            lastSelectedType = it.type
                            lastSelectedName = it.name
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
                            lastSelectedType = printer.type
                            lastSelectedName = printer.name
                            notifyChange(printer.getStatus())
                        }
                    }
                }
                "builtin" -> {
                    scanBuiltInPrinter()?.let {
                        if (it.connect()) {
                            currentPrinter = it
                            lastSelectedType = it.type
                            lastSelectedName = it.name
                            notifyChange(it.getStatus())
                        }
                    }
                }
                "usb" -> {
                    scanUsbPrinters().find { it.name == savedAddress }?.let {
                        if (it.connect()) {
                            currentPrinter = it
                            lastSelectedType = it.type
                            lastSelectedName = it.name
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
