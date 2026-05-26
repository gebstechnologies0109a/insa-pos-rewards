package com.epayplus.v2.service

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.bluetooth.BluetoothSocket
import android.content.Context
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.OutputStream
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class BluetoothPrinterService @Inject constructor(
    @ApplicationContext private val context: Context
) {
    companion object {
        private val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
        private const val ESC = 0x1B
        private const val GS = 0x1D
    }

    private var socket: BluetoothSocket? = null
    private var outputStream: OutputStream? = null
    private var connectedDevice: BluetoothDevice? = null

    val isConnected: Boolean get() = socket?.isConnected == true

    @SuppressLint("MissingPermission")
    fun getPairedPrinters(): List<BluetoothDevice> {
        val bluetoothManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
        val adapter = bluetoothManager?.adapter ?: return emptyList()
        return adapter.bondedDevices?.toList() ?: emptyList()
    }

    @SuppressLint("MissingPermission")
    suspend fun connect(device: BluetoothDevice): Boolean = withContext(Dispatchers.IO) {
        try {
            disconnect()
            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            socket?.connect()
            outputStream = socket?.outputStream
            connectedDevice = device
            true
        } catch (e: Exception) {
            disconnect()
            false
        }
    }

    fun disconnect() {
        try {
            outputStream?.close()
            socket?.close()
        } catch (_: Exception) { }
        outputStream = null
        socket = null
        connectedDevice = null
    }

    suspend fun printReceipt(receipt: ReceiptData): Boolean = withContext(Dispatchers.IO) {
        val os = outputStream ?: return@withContext false
        try {
            os.write(byteArrayOf(ESC.toByte(), 0x40.toByte()))

            os.write(byteArrayOf(ESC.toByte(), 0x61.toByte(), 0x01.toByte()))

            os.write(byteArrayOf(ESC.toByte(), 0x45.toByte(), 0x01.toByte()))
            os.write(byteArrayOf(GS.toByte(), 0x21.toByte(), 0x11.toByte()))
            printLine(os, receipt.storeName)
            os.write(byteArrayOf(GS.toByte(), 0x21.toByte(), 0x00.toByte()))
            os.write(byteArrayOf(ESC.toByte(), 0x45.toByte(), 0x00.toByte()))

            printLine(os, receipt.storeAddress)
            printLine(os, "")
            printLine(os, "================================")

            os.write(byteArrayOf(ESC.toByte(), 0x61.toByte(), 0x00.toByte()))

            printLine(os, "Type: ${receipt.transactionType}")
            printLine(os, "Provider: ${receipt.provider}")
            printLine(os, "Product: ${receipt.product}")
            printLine(os, "Number: ${receipt.targetNumber}")
            printLine(os, "Amount: PHP ${receipt.amount}")
            if ((receipt.fee.toDoubleOrNull() ?: 0.0) > 0) {
                printLine(os, "Fee: PHP ${receipt.fee}")
            }
            printLine(os, "")

            printLine(os, "================================")
            printLine(os, "Ref #: ${receipt.referenceNumber}")
            printLine(os, "Status: ${receipt.status}")
            printLine(os, "Date: ${receipt.dateTime}")
            printLine(os, "================================")

            os.write(byteArrayOf(ESC.toByte(), 0x61.toByte(), 0x01.toByte()))
            printLine(os, "")
            printLine(os, "Thank you!")
            printLine(os, "")
            printLine(os, "")
            printLine(os, "")

            os.write(byteArrayOf(GS.toByte(), 0x56.toByte(), 0x00.toByte()))

            os.flush()
            true
        } catch (e: Exception) {
            false
        }
    }

    private fun printLine(os: OutputStream, text: String) {
        os.write(text.toByteArray(Charsets.UTF_8))
        os.write(byteArrayOf(0x0A.toByte()))
    }

    data class ReceiptData(
        val storeName: String = "ePayPlus",
        val storeAddress: String = "",
        val transactionType: String = "",
        val provider: String = "",
        val product: String = "",
        val targetNumber: String = "",
        val amount: String = "0.00",
        val fee: String = "0.00",
        val referenceNumber: String = "",
        val status: String = "",
        val dateTime: String = ""
    )
}
