package com.insapos.insabuddy.printers

import android.annotation.SuppressLint
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.util.Log
import java.io.IOException
import java.io.OutputStream
import java.util.UUID

@SuppressLint("MissingPermission")
class BluetoothPrinter(private val device: BluetoothDevice) : Printer {

    companion object {
        private const val TAG = "BluetoothPrinter"
        private val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    }

    override val type = "bluetooth"
    override val name: String get() = device.name ?: device.address

    private var socket: BluetoothSocket? = null
    private var outputStream: OutputStream? = null

    override fun connect(): Boolean {
        return try {
            disconnect()
            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            socket?.connect()
            outputStream = socket?.outputStream
            Log.i(TAG, "Connected to ${device.name}")
            true
        } catch (e: IOException) {
            Log.e(TAG, "Connection failed: ${e.message}")
            disconnect()
            false
        }
    }

    override fun disconnect() {
        try {
            outputStream?.close()
            socket?.close()
        } catch (_: IOException) { }
        outputStream = null
        socket = null
    }

    override fun isConnected(): Boolean {
        return socket?.isConnected == true
    }

    override fun send(data: ByteArray): Boolean {
        if (!isConnected()) {
            if (!connect()) return false
        }
        return try {
            outputStream?.write(data)
            outputStream?.flush()
            true
        } catch (e: IOException) {
            Log.e(TAG, "Send failed: ${e.message}")
            disconnect()
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
}
