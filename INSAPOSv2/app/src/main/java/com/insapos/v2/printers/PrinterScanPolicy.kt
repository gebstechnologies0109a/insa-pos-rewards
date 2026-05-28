package com.insapos.v2.printers

object PrinterScanPolicy {

    fun shouldIncludeBluetooth(
        forType: String?,
        savedType: String? = null,
        lastSelectedType: String? = null,
        currentType: String? = null
    ): Boolean {
        if (forType.equals("bluetooth", ignoreCase = true)) return true
        if (savedType.equals("bluetooth", ignoreCase = true)) return true
        if (lastSelectedType.equals("bluetooth", ignoreCase = true)) return true
        if (currentType == "bluetooth") return true
        return false
    }

    fun includeBluetoothForSelection(type: String, savedType: String?, lastSelectedType: String?, currentType: String?): Boolean {
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
