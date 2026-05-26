package com.epayplus.v2.util

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.IOException
import java.io.OutputStream
import java.util.UUID

class BluetoothPrinter {

    companion object {
        private val SPP_UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")

        private const val ESC = 0x1B
        private const val GS = 0x1D
        private const val LF = 0x0A

        // ESC/POS commands
        val CMD_INIT = byteArrayOf(ESC.toByte(), '@'.code.toByte())
        val CMD_ALIGN_CENTER = byteArrayOf(ESC.toByte(), 'a'.code.toByte(), 1)
        val CMD_ALIGN_LEFT = byteArrayOf(ESC.toByte(), 'a'.code.toByte(), 0)
        val CMD_ALIGN_RIGHT = byteArrayOf(ESC.toByte(), 'a'.code.toByte(), 2)
        val CMD_BOLD_ON = byteArrayOf(ESC.toByte(), 'E'.code.toByte(), 1)
        val CMD_BOLD_OFF = byteArrayOf(ESC.toByte(), 'E'.code.toByte(), 0)
        val CMD_DOUBLE_HEIGHT = byteArrayOf(GS.toByte(), '!'.code.toByte(), 0x01)
        val CMD_DOUBLE_WIDTH = byteArrayOf(GS.toByte(), '!'.code.toByte(), 0x10)
        val CMD_NORMAL_SIZE = byteArrayOf(GS.toByte(), '!'.code.toByte(), 0x00)
        val CMD_CUT_PAPER = byteArrayOf(GS.toByte(), 'V'.code.toByte(), 66, 3)
        val CMD_FEED_LINE = byteArrayOf(LF.toByte())
    }

    private var socket: BluetoothSocket? = null
    private var outputStream: OutputStream? = null

    @SuppressLint("MissingPermission")
    suspend fun connect(address: String): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            val adapter = BluetoothAdapter.getDefaultAdapter()
                ?: return@withContext Result.failure(Exception("Bluetooth not available"))

            val device: BluetoothDevice = adapter.getRemoteDevice(address)
            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            socket?.connect()
            outputStream = socket?.outputStream
            Result.success(Unit)
        } catch (e: IOException) {
            Result.failure(e)
        }
    }

    suspend fun disconnect() = withContext(Dispatchers.IO) {
        try {
            outputStream?.close()
            socket?.close()
        } catch (_: IOException) { }
        outputStream = null
        socket = null
    }

    val isConnected: Boolean get() = socket?.isConnected == true

    suspend fun printReceipt(receipt: TransactionReceipt): Result<Unit> = withContext(Dispatchers.IO) {
        val os = outputStream ?: return@withContext Result.failure(Exception("Not connected"))
        try {
            os.write(CMD_INIT)

            // Header
            os.write(CMD_ALIGN_CENTER)
            os.write(CMD_BOLD_ON)
            os.write(CMD_DOUBLE_HEIGHT)
            os.write("ePayPlus\n".toByteArray())
            os.write(CMD_NORMAL_SIZE)
            os.write(CMD_BOLD_OFF)
            os.write("${receipt.businessName}\n".toByteArray())
            os.write("${receipt.address}\n".toByteArray())
            os.write("================================\n".toByteArray())

            // Transaction details
            os.write(CMD_ALIGN_LEFT)
            os.write("Type: ${receipt.type}\n".toByteArray())
            os.write("Provider: ${receipt.provider}\n".toByteArray())
            os.write("Product: ${receipt.product}\n".toByteArray())
            os.write("Number: ${receipt.targetNumber}\n".toByteArray())
            os.write("--------------------------------\n".toByteArray())

            os.write(CMD_BOLD_ON)
            os.write("Amount: PHP ${String.format("%,.2f", receipt.amount)}\n".toByteArray())
            os.write(CMD_BOLD_OFF)

            if (receipt.fee > 0) {
                os.write("Fee: PHP ${String.format("%,.2f", receipt.fee)}\n".toByteArray())
                os.write("Total: PHP ${String.format("%,.2f", receipt.amount + receipt.fee)}\n".toByteArray())
            }

            os.write("--------------------------------\n".toByteArray())
            os.write("Ref #: ${receipt.referenceNumber}\n".toByteArray())
            os.write("Status: ${receipt.status}\n".toByteArray())
            os.write("Date: ${receipt.dateTime}\n".toByteArray())
            os.write("================================\n".toByteArray())

            os.write(CMD_ALIGN_CENTER)
            os.write("Thank you!\n".toByteArray())
            os.write("Powered by ePayPlus V2.0\n".toByteArray())

            // Feed and cut
            os.write(CMD_FEED_LINE)
            os.write(CMD_FEED_LINE)
            os.write(CMD_FEED_LINE)
            os.write(CMD_CUT_PAPER)

            os.flush()
            Result.success(Unit)
        } catch (e: IOException) {
            Result.failure(e)
        }
    }

    @SuppressLint("MissingPermission")
    fun getPairedDevices(): List<BluetoothDevice> {
        val adapter = BluetoothAdapter.getDefaultAdapter() ?: return emptyList()
        return adapter.bondedDevices?.toList() ?: emptyList()
    }
}

data class TransactionReceipt(
    val businessName: String,
    val address: String,
    val type: String,
    val provider: String,
    val product: String,
    val targetNumber: String,
    val amount: Double,
    val fee: Double,
    val referenceNumber: String,
    val status: String,
    val dateTime: String
)
