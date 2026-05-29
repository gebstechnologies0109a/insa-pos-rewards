package com.insapos.v2.printers

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class PrinterTypeTest {

    @Test
    fun normalizeBuiltinAliases() {
        assertEquals(PrinterType.BUILTIN, PrinterType.normalize("builtin"))
        assertEquals(PrinterType.BUILTIN, PrinterType.normalize("built-in"))
        assertEquals(PrinterType.BUILTIN, PrinterType.normalize("BUILT_IN"))
        assertEquals(PrinterType.BUILTIN, PrinterType.normalize("internal"))
    }

    @Test
    fun isBuiltinRecognizesAliases() {
        assertTrue(PrinterType.isBuiltin("built-in"))
        assertTrue(PrinterType.isBuiltin("builtin"))
        assertFalse(PrinterType.isBuiltin("usb"))
    }
}
