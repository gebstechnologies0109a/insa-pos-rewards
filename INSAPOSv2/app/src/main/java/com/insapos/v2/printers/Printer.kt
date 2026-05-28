package com.insapos.v2.printers

data class PrinterStatus(
    val connected: Boolean,
    val type: String,
    val name: String,
    val paperReady: Boolean = true
)

interface Printer {
    val type: String
    val name: String

    fun connect(): Boolean
    fun disconnect()
    fun isConnected(): Boolean
    fun send(data: ByteArray): Boolean
    fun getStatus(): PrinterStatus

    fun printText(text: String, layout: PrinterConfig.Layout = PrinterConfig.resolve(null, null)): Boolean {
        val lineFeed = byteArrayOf(0x0A)
        val cut = byteArrayOf(0x1D, 0x56, 0x00)
        val payload = PrinterConfig.escPosPrefix(layout) + text.toByteArray(Charsets.UTF_8) +
            lineFeed + lineFeed + lineFeed + cut
        return send(payload)
    }

    fun printRaw(data: ByteArray): Boolean = send(data)

    fun printImage(imageData: ByteArray): Boolean = send(imageData)

    fun openDrawer() {
        val pulse = byteArrayOf(0x1B, 0x70, 0x00, 0x19, 0xFA.toByte())
        send(pulse)
    }
}
