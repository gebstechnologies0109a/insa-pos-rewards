package com.insapos.v2

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.content.Context
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbManager
import android.util.Log
import android.view.InputDevice
import org.json.JSONArray
import org.json.JSONObject

object HardwareDetector {

    private const val TAG = "HardwareDetector"

    fun detect(context: Context): JSONObject {
        val result = JSONObject()
        result.put("keyboards", detectKeyboards())
        result.put("mice", detectMice())
        result.put("barcodeScanners", detectBarcodeScanners())
        result.put("usbDevices", detectUsbDevices(context))
        result.put("bluetoothDevices", detectBluetoothDevices(context))
        result.put("summary", buildSummary(context))
        return result
    }

    fun detectKeyboards(): JSONArray {
        val arr = JSONArray()
        for (id in InputDevice.getDeviceIds()) {
            val dev = InputDevice.getDevice(id) ?: continue
            val sources = dev.sources
            if (sources and InputDevice.SOURCE_KEYBOARD != 0 && !dev.isVirtual) {
                if (dev.keyboardType == InputDevice.KEYBOARD_TYPE_ALPHABETIC) {
                    arr.put(JSONObject().apply {
                        put("id", dev.id)
                        put("name", dev.name)
                        put("vendorId", dev.vendorId)
                        put("productId", dev.productId)
                        put("descriptor", dev.descriptor)
                    })
                }
            }
        }
        return arr
    }

    fun detectMice(): JSONArray {
        val arr = JSONArray()
        for (id in InputDevice.getDeviceIds()) {
            val dev = InputDevice.getDevice(id) ?: continue
            val sources = dev.sources
            val isMouse = (sources and InputDevice.SOURCE_MOUSE != 0) ||
                    (sources and InputDevice.SOURCE_TOUCHPAD != 0)
            if (isMouse && !dev.isVirtual) {
                arr.put(JSONObject().apply {
                    put("id", dev.id)
                    put("name", dev.name)
                    put("vendorId", dev.vendorId)
                    put("productId", dev.productId)
                })
            }
        }
        return arr
    }

    fun detectBarcodeScanners(): JSONArray {
        val arr = JSONArray()
        for (id in InputDevice.getDeviceIds()) {
            val dev = InputDevice.getDevice(id) ?: continue
            if (dev.isVirtual) continue
            val sources = dev.sources
            val isKeyboard = sources and InputDevice.SOURCE_KEYBOARD != 0
            if (!isKeyboard) continue

            val name = dev.name.lowercase()
            val isScannerByName = name.contains("scanner") || name.contains("barcode") ||
                    name.contains("symbol") || name.contains("honeywell") ||
                    name.contains("zebra") || name.contains("datalogic") ||
                    name.contains("opticon") || name.contains("newland") ||
                    name.contains("hid") || name.contains("reader")

            val isNonAlphaKeyboard = dev.keyboardType != InputDevice.KEYBOARD_TYPE_ALPHABETIC

            if (isScannerByName || isNonAlphaKeyboard) {
                arr.put(JSONObject().apply {
                    put("id", dev.id)
                    put("name", dev.name)
                    put("vendorId", dev.vendorId)
                    put("productId", dev.productId)
                    put("detectedBy", if (isScannerByName) "name" else "type")
                })
            }
        }
        return arr
    }

    fun detectUsbDevices(context: Context): JSONArray {
        val arr = JSONArray()
        try {
            val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager ?: return arr
            for ((_, device) in usbManager.deviceList) {
                val classes = mutableListOf<String>()
                for (i in 0 until device.interfaceCount) {
                    val intf = device.getInterface(i)
                    when (intf.interfaceClass) {
                        UsbConstants.USB_CLASS_PRINTER -> classes.add("printer")
                        UsbConstants.USB_CLASS_HID -> classes.add("hid")
                        UsbConstants.USB_CLASS_MASS_STORAGE -> classes.add("storage")
                        UsbConstants.USB_CLASS_CDC_DATA -> classes.add("serial")
                        UsbConstants.USB_CLASS_COMM -> classes.add("comm")
                    }
                }
                arr.put(JSONObject().apply {
                    put("name", device.productName ?: "USB Device")
                    put("vendorId", device.vendorId)
                    put("productId", device.productId)
                    put("deviceId", device.deviceId)
                    put("classes", JSONArray(classes))
                    put("hasPermission", usbManager.hasPermission(device))
                })
            }
        } catch (e: Exception) {
            Log.e(TAG, "USB detection failed: ${e.message}")
        }
        return arr
    }

    @SuppressLint("MissingPermission")
    fun detectBluetoothDevices(context: Context): JSONArray {
        val arr = JSONArray()
        try {
            val btManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
            val adapter = btManager?.adapter ?: BluetoothAdapter.getDefaultAdapter()
            if (adapter == null || !adapter.isEnabled) return arr

            for (device in adapter.bondedDevices) {
                val name = try { device.name ?: device.address } catch (_: Exception) { device.address }
                val devClass = try { device.bluetoothClass?.majorDeviceClass } catch (_: Exception) { null }
                arr.put(JSONObject().apply {
                    put("name", name)
                    put("address", device.address)
                    put("majorClass", devClass ?: JSONObject.NULL)
                    put("type", device.type)
                })
            }
        } catch (e: Exception) {
            Log.e(TAG, "Bluetooth detection failed: ${e.message}")
        }
        return arr
    }

    private fun buildSummary(context: Context): JSONObject {
        return JSONObject().apply {
            put("hasPhysicalKeyboard", detectKeyboards().length() > 0)
            put("hasMouse", detectMice().length() > 0)
            put("hasBarcodeScanner", detectBarcodeScanners().length() > 0)
            put("hasUsbPrinter", hasUsbPrinter(context))
            put("hasBluetoothPaired", detectBluetoothDevices(context).length() > 0)
        }
    }

    private fun hasUsbPrinter(context: Context): Boolean {
        try {
            val usbManager = context.getSystemService(Context.USB_SERVICE) as? UsbManager ?: return false
            for ((_, device) in usbManager.deviceList) {
                for (i in 0 until device.interfaceCount) {
                    if (device.getInterface(i).interfaceClass == UsbConstants.USB_CLASS_PRINTER) {
                        return true
                    }
                }
            }
        } catch (_: Exception) { }
        return false
    }
}
