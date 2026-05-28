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
}
