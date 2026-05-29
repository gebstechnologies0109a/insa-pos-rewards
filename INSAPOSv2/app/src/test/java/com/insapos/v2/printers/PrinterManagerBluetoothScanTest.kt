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

    @Test
    fun discoveryDefaultsToBluetoothIncluded() {
        assertTrue(PrinterScanPolicy.includeBluetoothForDiscovery(null))
        assertTrue(PrinterScanPolicy.includeBluetoothForDiscovery(""))
        assertTrue(PrinterScanPolicy.includeBluetoothForDiscovery("1"))
    }

    @Test
    fun discoveryCanOptOutOfBluetooth() {
        assertFalse(PrinterScanPolicy.includeBluetoothForDiscovery("0"))
        assertFalse(PrinterScanPolicy.includeBluetoothForDiscovery("false"))
    }

    @Test
    fun shouldNotIncludeBluetoothForBuiltinSelection() {
        assertFalse(
            PrinterScanPolicy.includeBluetoothForSelection(
                type = PrinterType.BUILTIN,
                savedType = null,
                lastSelectedType = null,
                currentType = null
            )
        )
        assertFalse(
            PrinterScanPolicy.shouldIncludeBluetooth(
                forType = "built-in",
                savedType = null,
                lastSelectedType = null,
                currentType = null
            )
        )
    }
}
