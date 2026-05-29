package com.insapos.v2.printers

import android.content.Context
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbDeviceConnection
import android.hardware.usb.UsbEndpoint
import android.hardware.usb.UsbInterface
import android.hardware.usb.UsbManager
import android.util.Log

class UsbPrinter(
    private val context: Context,
    private val device: UsbDevice
) : Printer {

    companion object {
        private const val TAG = "UsbPrinter"
        private const val TIMEOUT_MS = 5000
    }

    override val type = "usb"
    override val name: String get() = PrinterNames.sanitize(
        device.productName?.takeIf { it.isNotBlank() }
            ?: "USB Printer (${device.vendorId}:${device.productId})"
    ).ifBlank { "USB Printer (${device.deviceId})" }

    val usbDevice: UsbDevice get() = device

    private var connection: UsbDeviceConnection? = null
    private var endpoint: UsbEndpoint? = null
    private var usbInterface: UsbInterface? = null

    fun hasUsbPermission(): Boolean {
        val manager = context.getSystemService(Context.USB_SERVICE) as UsbManager
        return manager.hasPermission(device)
    }

    override fun connect(): Boolean {
        return try {
            val manager = context.getSystemService(Context.USB_SERVICE) as UsbManager
            if (!manager.hasPermission(device)) {
                Log.w(TAG, "No USB permission for $name")
                return false
            }

            for (i in 0 until device.interfaceCount) {
                val intf = device.getInterface(i)
                if (intf.interfaceClass == UsbConstants.USB_CLASS_PRINTER) {
                    for (j in 0 until intf.endpointCount) {
                        val ep = intf.getEndpoint(j)
                        if (ep.direction == UsbConstants.USB_DIR_OUT) {
                            usbInterface = intf
                            endpoint = ep
                            break
                        }
                    }
                    if (endpoint != null) break
                }
            }

            if (endpoint == null) {
                Log.e(TAG, "No printer endpoint found")
                return false
            }

            connection = manager.openDevice(device)
            connection?.claimInterface(usbInterface, true)
            Log.i(TAG, "Connected to $name")
            true
        } catch (e: Exception) {
            Log.e(TAG, "Connection failed: ${e.message}")
            disconnect()
            false
        }
    }

    override fun disconnect() {
        try {
            usbInterface?.let { connection?.releaseInterface(it) }
            connection?.close()
        } catch (_: Exception) { }
        connection = null
        endpoint = null
        usbInterface = null
    }

    override fun isConnected(): Boolean = connection != null && endpoint != null

    override fun send(data: ByteArray): Boolean {
        if (!isConnected()) {
            if (!connect()) return false
        }
        return try {
            val sent = connection?.bulkTransfer(endpoint, data, data.size, TIMEOUT_MS) ?: -1
            sent >= 0
        } catch (e: Exception) {
            Log.e(TAG, "Send failed: ${e.message}")
            disconnect()
            false
        }
    }

    override fun getStatus(): PrinterStatus {
        return PrinterStatus(connected = isConnected(), type = type, name = name)
    }
}
