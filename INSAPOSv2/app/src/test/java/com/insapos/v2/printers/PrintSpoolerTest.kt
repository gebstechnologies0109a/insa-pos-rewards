package com.insapos.v2.printers

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.File
import java.nio.file.Files

class PrintSpoolerTest {

    private fun tempSpooler(): PrintSpooler {
        val dir = Files.createTempDirectory("print-spool-test").toFile()
        return PrintSpooler.forDirectory(dir)
    }

    @Test
    fun enqueueTextCreatesEscPosFileAndDeletesOnSuccess() {
        val spooler = tempSpooler()
        val layout = PrinterConfig.resolve("57mm", "paper_size")
        val job = spooler.enqueueText("Hello receipt\n", layout)
        assertNotNull(job)
        assertTrue(job!!.file.exists())
        assertTrue(job.file.name.endsWith(".escpos"))
        assertEquals(1, spooler.pendingJobCount())
        spooler.deleteJob(job.file)
        assertEquals(0, spooler.pendingJobCount())
    }

    @Test
    fun enqueueRawUsesBinExtension() {
        val spooler = tempSpooler()
        val job = spooler.enqueueRaw(byteArrayOf(0x1B, 0x40, 0x0A))
        assertNotNull(job)
        assertTrue(job!!.file.name.endsWith(".bin"))
        spooler.deleteJob(job.file)
    }
}
