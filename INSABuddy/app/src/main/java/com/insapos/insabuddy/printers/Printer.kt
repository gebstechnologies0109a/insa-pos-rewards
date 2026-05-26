package com.insapos.insabuddy.printers

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

    fun printText(text: String): Boolean {
        val escInit = byteArrayOf(0x1B, 0x40)
        val lineFeed = byteArrayOf(0x0A)
        val cut = byteArrayOf(0x1D, 0x56, 0x00)
        val payload = escInit + text.toByteArray(Charsets.UTF_8) + lineFeed + lineFeed + lineFeed + cut
        return send(payload)
    }

    fun printRaw(data: ByteArray): Boolean = send(data)

    fun printImage(imageData: ByteArray): Boolean = send(imageData)
}
