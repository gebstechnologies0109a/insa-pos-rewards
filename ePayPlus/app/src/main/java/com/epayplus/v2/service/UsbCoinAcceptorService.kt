package com.epayplus.v2.service

import android.content.Context
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbDeviceConnection
import android.hardware.usb.UsbManager
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class UsbCoinAcceptorService @Inject constructor(
    @ApplicationContext private val context: Context
) {
    companion object {
        private const val BAUD_RATE = 9600
    }

    private var connection: UsbDeviceConnection? = null
    private var readJob: Job? = null
    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    private val _amountInserted = MutableStateFlow(0.0)
    val amountInserted: StateFlow<Double> = _amountInserted.asStateFlow()

    private val _isActive = MutableStateFlow(false)
    val isActive: StateFlow<Boolean> = _isActive.asStateFlow()

    private val denominationMap = mapOf(
        0x01 to 1.0,
        0x02 to 5.0,
        0x03 to 10.0,
        0x04 to 20.0,
        0x05 to 50.0,
        0x06 to 100.0,
        0x07 to 200.0,
        0x08 to 500.0,
        0x09 to 1000.0
    )

    fun getConnectedDevices(): List<UsbDevice> {
        val usbManager = context.getSystemService(Context.USB_SERVICE) as UsbManager
        return usbManager.deviceList.values.toList()
    }

    fun startAccepting(device: UsbDevice): Boolean {
        val usbManager = context.getSystemService(Context.USB_SERVICE) as UsbManager

        if (!usbManager.hasPermission(device)) return false

        connection = usbManager.openDevice(device) ?: return false

        val iface = device.getInterface(0)
        connection?.claimInterface(iface, true)

        _amountInserted.value = 0.0
        _isActive.value = true

        val endpoint = iface.getEndpoint(0)
        readJob = scope.launch {
            val buffer = ByteArray(64)
            while (isActive) {
                val bytesRead = connection?.bulkTransfer(endpoint, buffer, buffer.size, 1000) ?: -1
                if (bytesRead > 0) {
                    processCoinData(buffer.copyOf(bytesRead))
                }
            }
        }

        return true
    }

    fun stopAccepting() {
        readJob?.cancel()
        readJob = null
        connection?.close()
        connection = null
        _isActive.value = false
    }

    fun resetAmount() {
        _amountInserted.value = 0.0
    }

    private fun processCoinData(data: ByteArray) {
        for (byte in data) {
            val coinValue = denominationMap[byte.toInt() and 0xFF]
            if (coinValue != null) {
                _amountInserted.value += coinValue
            }
        }
    }
}
