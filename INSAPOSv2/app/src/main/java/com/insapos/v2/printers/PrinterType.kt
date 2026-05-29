package com.insapos.v2.printers

object PrinterType {
    const val BUILTIN = "builtin"
    const val BLUETOOTH = "bluetooth"
    const val USB = "usb"
    const val NETWORK = "network"

    /** Normalize UI / legacy aliases to canonical printer type ids. */
    fun normalize(type: String?): String {
        if (type.isNullOrBlank()) return ""
        return when (type.trim().lowercase().replace('_', '-')) {
            "built-in", "builtin", "internal", "embedded" -> BUILTIN
            "bt", "ble", "bluetooth" -> BLUETOOTH
            "usb", "usb-printer" -> USB
            "network", "ip", "wifi", "lan" -> NETWORK
            else -> type.trim().lowercase()
        }
    }

    fun isBuiltin(type: String?): Boolean = normalize(type) == BUILTIN
}
