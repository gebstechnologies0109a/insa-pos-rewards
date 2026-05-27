package com.insapos.v2

import android.content.Context
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbManager
import android.view.InputDevice
import org.json.JSONArray
import org.json.JSONObject

/**
 * Discovers keyboards, mice, and barcode scanners attached to the device.
 */
object HardwareDetector {

    data class IoDevice(
        val id: String,
        val name: String,
        val type: String,
        val vendorId: Int? = null,
        val productId: Int? = null,
        val connected: Boolean = true
    )

    fun detectKeyboards(context: Context): List<IoDevice> {
        val scanners = detectBarcodeScanners(context).map { it.id }.toSet()
        return inputDevices()
            .filter { dev ->
                dev.isKeyboard && !dev.isVirtual && dev.id.toString() !in scanners && !looksLikeScannerName(dev.name)
            }
            .map { toIoDevice(it, "keyboard") }
            .distinctBy { it.id }
    }

    fun detectMice(context: Context): List<IoDevice> {
        return inputDevices()
            .filter { dev -> dev.isMouse && !dev.isVirtual }
            .map { toIoDevice(it, "mouse") }
            .distinctBy { it.id }
    }

    fun detectBarcodeScanners(context: Context): List<IoDevice> {
        val fromInput = inputDevices()
            .filter { dev ->
                !dev.isVirtual && (
                    looksLikeScannerName(dev.name) ||
                    (dev.isKeyboard && dev.isExternal && !dev.isBuiltInKeyboard)
                )
            }
            .map { toIoDevice(it, "scanner") }

        val fromUsb = usbScannerDevices(context)

        return (fromInput + fromUsb)
            .distinctBy { it.id }
            .sortedBy { it.name.lowercase() }
    }

    fun scanAll(context: Context): JSONObject {
        val keyboards = detectKeyboards(context)
        val mice = detectMice(context)
        val scanners = detectBarcodeScanners(context)
        return JSONObject().apply {
            put("ok", true)
            put("keyboards", devicesToJson(keyboards))
            put("mice", devicesToJson(mice))
            put("scanners", devicesToJson(scanners))
        }
    }

    private fun inputDevices(): List<InputDevice> {
        return InputDevice.getDeviceIds().toList()
            .mapNotNull { id -> InputDevice.getDevice(id) }
    }

    private val InputDevice.isKeyboard: Boolean
        get() = (sources and InputDevice.SOURCE_KEYBOARD) == InputDevice.SOURCE_KEYBOARD

    private val InputDevice.isMouse: Boolean
        get() = (sources and InputDevice.SOURCE_MOUSE) != 0 ||
            (sources and InputDevice.SOURCE_MOUSE_RELATIVE) != 0

    private val InputDevice.isExternal: Boolean
        get() = !isVirtual && (sources and InputDevice.SOURCE_KEYBOARD) != 0

    private val InputDevice.isBuiltInKeyboard: Boolean
        get() {
            val n = name?.lowercase() ?: return true
            return n.contains("built-in") || n.contains("builtin") || n.contains("virtual")
        }

    private fun looksLikeScannerName(name: String?): Boolean {
        if (name.isNullOrBlank()) return false
        val n = name.lowercase()
        val hints = listOf(
            "scanner", "barcode", "bar code", "qr", "symbol", "honeywell",
            "zebra", "datalogic", "newland", "socket", "unitech", "code",
            "scan", "hid keyboard", "usb scanner"
        )
        return hints.any { n.contains(it) }
    }

    private fun usbScannerDevices(context: Context): List<IoDevice> {
        val result = mutableListOf<IoDevice>()
        try {
            val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager ?: return result
            for (device in usbManager.deviceList.values) {
                var isHid = false
                var isScanner = false
                for (i in 0 until device.interfaceCount) {
                    val iface = device.getInterface(i)
                    if (iface.interfaceClass == UsbConstants.USB_CLASS_HID) {
                        isHid = true
                        if (iface.interfaceProtocol != 1 && iface.interfaceProtocol != 2) {
                            isScanner = true
                        }
                    }
                }
                if (isHid && (isScanner || looksLikeScannerName(device.deviceName))) {
                    val id = "usb:${device.vendorId}:${device.productId}:${device.deviceName.hashCode()}"
                    result.add(
                        IoDevice(
                            id = id,
                            name = device.deviceName ?: "USB Scanner",
                            type = "scanner",
                            vendorId = device.vendorId,
                            productId = device.productId
                        )
                    )
                }
            }
        } catch (_: Exception) {
        }
        return result
    }

    private fun toIoDevice(device: InputDevice, type: String): IoDevice {
        return IoDevice(
            id = device.id.toString(),
            name = device.name?.ifBlank { "Device ${device.id}" } ?: "Device ${device.id}",
            type = type,
            connected = true
        )
    }

    private fun devicesToJson(devices: List<IoDevice>): JSONArray {
        val arr = JSONArray()
        for (d in devices) {
            arr.put(JSONObject().apply {
                put("id", d.id)
                put("name", d.name)
                put("type", d.type)
                put("connected", d.connected)
                if (d.vendorId != null) put("vendor_id", d.vendorId)
                if (d.productId != null) put("product_id", d.productId)
            })
        }
        return arr
    }
}
