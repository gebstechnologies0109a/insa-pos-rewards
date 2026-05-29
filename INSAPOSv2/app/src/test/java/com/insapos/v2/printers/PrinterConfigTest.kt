package com.insapos.v2.printers

import org.junit.Assert.assertEquals
import org.junit.Test

class PrinterConfigTest {

    @Test
    fun paperSize57mmPaperSizeModeUses32CharsAnd384Dots() {
        val layout = PrinterConfig.resolve("57mm", "paper_size")
        assertEquals(32, layout.charWidth)
        assertEquals(384, layout.dotWidth)
        assertEquals(PrinterConfig.PAPER_57MM, layout.paperSize)
    }

    @Test
    fun paperSize87mmPaperSizeModeUses48CharsAnd576Dots() {
        val layout = PrinterConfig.resolve("87mm", "paper_size")
        assertEquals(48, layout.charWidth)
        assertEquals(576, layout.dotWidth)
    }

    @Test
    fun finePrintIncreasesCharWidth() {
        val narrow = PrinterConfig.resolve("57mm", "fine_print")
        val wide = PrinterConfig.resolve("87mm", "fine_print")
        assertEquals(42, narrow.charWidth)
        assertEquals(64, wide.charWidth)
    }

    @Test
    fun normalizePaperSizeAccepts80mmAlias() {
        assertEquals(PrinterConfig.PAPER_87MM, PrinterConfig.normalizePaperSize("80mm"))
    }

    @Test
    fun wrapTextRespectsWidth() {
        val lines = PrinterConfig.wrapText("hello world foo bar", 10)
        assertEquals(listOf("hello", "world foo", "bar"), lines)
    }

    @Test
    fun dividerMatchesWidth() {
        assertEquals(32, PrinterConfig.divider(32).length)
        assertEquals(48, PrinterConfig.divider(48).length)
    }
}
