package com.insapos.v2.printers

object PrinterScanPolicy {

    fun shouldIncludeBluetooth(
        forType: String?,
        savedType: String? = null,
        lastSelectedType: String? = null,
        currentType: String? = null
    ): Boolean {
        if (PrinterType.isBuiltin(forType)) return false
        if (PrinterType.isBuiltin(savedType)) return false
        if (PrinterType.isBuiltin(lastSelectedType)) return false
        if (PrinterType.isBuiltin(currentType)) return false
        if (PrinterType.normalize(forType) == PrinterType.BLUETOOTH) return true
        if (PrinterType.normalize(savedType) == PrinterType.BLUETOOTH) return true
        if (PrinterType.normalize(lastSelectedType) == PrinterType.BLUETOOTH) return true
        if (PrinterType.normalize(currentType) == PrinterType.BLUETOOTH) return true
        return false
    }

    fun includeBluetoothForSelection(type: String, savedType: String?, lastSelectedType: String?, currentType: String?): Boolean {
        if (PrinterType.isBuiltin(type)) return false
        return shouldIncludeBluetooth(type, savedType, lastSelectedType, currentType) || type.isBlank()
    }

    /**
     * Settings / discovery UI: include bonded Bluetooth unless explicitly opted out.
     * Default true so /printer/list without query params still finds paired printers.
     */
    fun includeBluetoothForDiscovery(bluetoothParam: String?): Boolean {
        when (bluetoothParam?.trim()?.lowercase()) {
            "0", "false", "no" -> return false
            "1", "true", "yes" -> return true
        }
        return true
    }
}
