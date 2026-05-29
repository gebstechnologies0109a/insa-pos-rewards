package com.insapos.v2.printers

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class PrinterNamesTest {

    @Test
    fun sanitize_stripsNullBytesAndTrims() {
        assertEquals("micro-printer", PrinterNames.sanitize("micro-printer\u0000\u0000\u0000"))
    }

    @Test
    fun namesMatch_ignoresNullPadding() {
        assertTrue(
            PrinterNames.namesMatch(
                "micro-printer",
                "micro-printer\u0000\u0000"
            )
        )
    }
}
