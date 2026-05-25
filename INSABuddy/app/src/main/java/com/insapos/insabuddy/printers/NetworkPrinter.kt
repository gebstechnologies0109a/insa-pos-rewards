package com.insapos.insabuddy.printers

import android.util.Log
import java.io.IOException
import java.io.OutputStream
import java.net.InetSocketAddress
import java.net.Socket

class NetworkPrinter(
    private val host: String,
    private val port: Int = 9100
) : Printer {

    companion object {
        private const val TAG = "NetworkPrinter"
        private const val CONNECT_TIMEOUT_MS = 5000
    }

    override val type = "network"
    override val name: String get() = "$host:$port"

    private var socket: Socket? = null
    private var outputStream: OutputStream? = null

    override fun connect(): Boolean {
        return try {
            disconnect()
            socket = Socket().apply {
                connect(InetSocketAddress(host, port), CONNECT_TIMEOUT_MS)
                soTimeout = CONNECT_TIMEOUT_MS
            }
            outputStream = socket?.getOutputStream()
            Log.i(TAG, "Connected to $host:$port")
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
        return socket?.isConnected == true && socket?.isClosed == false
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
