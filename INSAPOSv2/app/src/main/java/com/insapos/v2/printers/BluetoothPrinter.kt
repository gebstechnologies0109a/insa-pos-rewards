package com.insapos.v2.printers

import android.annotation.SuppressLint
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.util.Log
import java.io.IOException
import java.io.OutputStream
import java.util.UUID
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit

@SuppressLint("MissingPermission")
class BluetoothPrinter(private val device: BluetoothDevice) : Printer {

    companion object {
        private const val TAG = "BluetoothPrinter"
        private val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
        private const val CONNECT_TIMEOUT_SEC = 8L
    }

    override val type = "bluetooth"
    override val name: String get() = try { device.name ?: device.address } catch (_: Exception) { device.address }

    private var socket: BluetoothSocket? = null
    private var outputStream: OutputStream? = null

    override fun connect(): Boolean {
        return try {
            disconnect()
            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)

            val executor = Executors.newSingleThreadExecutor()
            val future = executor.submit<Boolean> {
                try {
                    socket?.connect()
                    true
                } catch (e: IOException) {
                    false
                }
            }

            var connected = try {
                future.get(CONNECT_TIMEOUT_SEC, TimeUnit.SECONDS)
            } catch (_: Exception) {
                future.cancel(true)
                false
            } finally {
                executor.shutdownNow()
            }

            if (!connected) {
                connected = connectFallbackRfcomm()
            }

            if (connected) {
                outputStream = socket?.outputStream
                Log.i(TAG, "Connected to ${device.name}")
                true
            } else {
                Log.w(TAG, "Connection timed out or failed for ${device.name}")
                disconnect()
                false
            }
        } catch (e: Exception) {
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

    /** Fallback for devices that reject SPP UUID connect (channel 1). */
    private fun connectFallbackRfcomm(): Boolean {
        return try {
            disconnect()
            val method = device.javaClass.getMethod("createRfcommSocket", Int::class.javaPrimitiveType)
            socket = method.invoke(device, 1) as BluetoothSocket
            socket?.connect()
            outputStream = socket?.outputStream
            Log.i(TAG, "Connected via RFCOMM fallback to $name")
            true
        } catch (e: Exception) {
            Log.w(TAG, "RFCOMM fallback failed for $name: ${e.message}")
            disconnect()
            false
        }
    }

    override fun isConnected(): Boolean = socket?.isConnected == true

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
        return PrinterStatus(connected = isConnected(), type = type, name = name)
    }
}
