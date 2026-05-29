package com.insapos.v2.printers

/**
 * Thermal receipt layout derived from synced POS settings
 * [KEY_PAPER_SIZE] and [KEY_FONT_MODE].
 */
object PrinterConfig {

    const val KEY_PAPER_SIZE = "printer_paper_size"
    const val KEY_FONT_MODE = "printer_font_mode"
    const val SYNC_KEY_PAPER_SIZE = "pos_printer_paper_size"
    const val SYNC_KEY_FONT_MODE = "pos_printer_font_mode"

    const val PAPER_57MM = "57mm"
    const val PAPER_87MM = "87mm"
    const val FONT_FINE_PRINT = "fine_print"
    const val FONT_PAPER_SIZE = "paper_size"

    data class Layout(
        val paperSize: String,
        val fontMode: String,
        val charWidth: Int,
        val dotWidth: Int,
    )

    fun normalizePaperSize(raw: String?): String =
        when (raw?.lowercase()?.trim()) {
            PAPER_87MM, "80mm" -> PAPER_87MM
            else -> PAPER_57MM
        }

    fun normalizeFontMode(raw: String?): String =
        when (raw?.lowercase()?.trim()) {
            FONT_FINE_PRINT -> FONT_FINE_PRINT
            else -> FONT_PAPER_SIZE
        }

    fun resolve(paperSizeRaw: String?, fontModeRaw: String?): Layout {
        val paperSize = normalizePaperSize(paperSizeRaw)
        val fontMode = normalizeFontMode(fontModeRaw)
        val dotWidth = if (paperSize == PAPER_87MM) 576 else 384
        val charWidth = charWidthFor(paperSize, fontMode)
        return Layout(paperSize, fontMode, charWidth, dotWidth)
    }

    fun charWidthFor(paperSize: String, fontMode: String): Int = when {
        paperSize == PAPER_87MM && fontMode == FONT_FINE_PRINT -> 64
        paperSize == PAPER_87MM -> 48
        fontMode == FONT_FINE_PRINT -> 42
        else -> 32
    }

    fun divider(width: Int): String = "=".repeat(width)

    fun centered(text: String, width: Int): String {
        if (text.length >= width) return text.take(width)
        val pad = (width - text.length) / 2
        return " ".repeat(pad) + text
    }

    fun moneyLine(label: String, amount: String, width: Int): String {
        val valueWidth = (width / 3).coerceAtLeast(8)
        val labelWidth = width - valueWidth
        return label.take(labelWidth).padEnd(labelWidth) + amount.padStart(valueWidth)
    }

    fun wrapText(text: String, width: Int): List<String> {
        if (text.isBlank() || width <= 0) return listOf("")
        val words = text.split(Regex("\\s+"))
        val lines = mutableListOf<String>()
        var current = StringBuilder()
        for (word in words) {
            if (word.length > width) {
                if (current.isNotEmpty()) {
                    lines.add(current.toString())
                    current = StringBuilder()
                }
                var i = 0
                while (i < word.length) {
                    lines.add(word.substring(i, (i + width).coerceAtMost(word.length)))
                    i += width
                }
                continue
            }
            val candidate = if (current.isEmpty()) word else "${current} $word"
            if (candidate.length <= width) {
                current = StringBuilder(candidate)
            } else {
                lines.add(current.toString())
                current = StringBuilder(word)
            }
        }
        if (current.isNotEmpty()) lines.add(current.toString())
        return lines.ifEmpty { listOf("") }
    }

    /** ESC/POS prefix: init, print-area width, font selection. */
    fun escPosPrefix(layout: Layout): ByteArray {
        val bytes = mutableListOf<Byte>()
        bytes.addAll(listOf(0x1B, 0x40)) // ESC @ initialize
        bytes.addAll(listOf(0x1D, 0x57)) // GS W — print area width in dots
        bytes.add((layout.dotWidth and 0xFF).toByte())
        bytes.add(((layout.dotWidth shr 8) and 0xFF).toByte())
        val font = if (layout.fontMode == FONT_FINE_PRINT) 1 else 0
        bytes.addAll(listOf(0x1B, 0x4D, font.toByte())) // ESC M — Font A/B
        return bytes.toByteArray()
    }
}
