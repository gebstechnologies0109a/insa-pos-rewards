package com.insapos.v2.printers

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class PrinterManagerBluetoothScanTest {

    @Test
    fun shouldIncludeBluetoothWhenTypeIsBluetooth() {
        assertTrue(PrinterScanPolicy.shouldIncludeBluetooth("bluetooth"))
        assertTrue(PrinterScanPolicy.shouldIncludeBluetooth("Bluetooth"))
    }

    @Test
    fun shouldIncludeBluetoothWhenSavedTypeIsBluetooth() {
        assertTrue(PrinterScanPolicy.shouldIncludeBluetooth(forType = null, savedType = "bluetooth"))
    }

    @Test
    fun shouldNotIncludeBluetoothForUsbOnlyContext() {
        assertFalse(PrinterScanPolicy.shouldIncludeBluetooth(forType = "usb", savedType = "usb"))
    }

    @Test
    fun selectionScanIncludesBluetoothWhenTypeOmitted() {
        assertTrue(
            PrinterScanPolicy.includeBluetoothForSelection("", savedType = "usb", lastSelectedType = null, currentType = null)
        )
    }
}
