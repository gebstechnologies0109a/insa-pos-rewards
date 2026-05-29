package com.insapos.v2.printers

import android.content.Context
import android.util.Log
import java.io.File
import java.io.FileOutputStream
import java.util.UUID

/**
 * Disk-backed print spool in app cache. Keeps only small job metadata in RAM;
 * receipt payloads live on disk until the print worker streams them to the printer.
 */
class PrintSpooler private constructor(private val queueDir: File) {

    data class SpooledJob(val id: String, val file: File)

    constructor(context: Context) : this(
        File(context.cacheDir, QUEUE_DIR).also { it.mkdirs() }
    )

    fun queueDirectory(): File = queueDir

    fun pendingJobCount(): Int = listJobFiles().size

    /** Jobs left on disk after a low-memory kill — newest first for retry priority. */
    fun recoverableJobs(): List<File> {
        purgeStaleJobs()
        return listJobFiles().sortedByDescending { it.lastModified() }
    }

    fun enqueueText(text: String, layout: PrinterConfig.Layout): SpooledJob? =
        enqueueBytes(buildEscPos(text, layout), "escpos")

    fun enqueueRaw(data: ByteArray): SpooledJob? = enqueueBytes(data, "bin")

    fun deleteJob(file: File) {
        try {
            file.delete()
        } catch (_: Exception) {
        }
    }

    fun purgeStaleJobs() {
        val cutoff = System.currentTimeMillis() - MAX_JOB_AGE_MS
        listJobFiles().forEach { file ->
            if (file.lastModified() < cutoff) {
                file.delete()
                Log.i(TAG, "Purged stale spool job ${file.name}")
            }
        }
    }

    private fun enqueueBytes(data: ByteArray, ext: String): SpooledJob? {
        if (data.isEmpty()) return null
        if (!ensureCapacity(data.size)) {
            Log.w(TAG, "Spool capacity exceeded (${pendingJobCount()} jobs)")
            return null
        }
        val id = UUID.randomUUID().toString()
        val file = File(queueDir, "$id.$ext")
        val tmp = File(queueDir, "$id.$ext.tmp")
        return try {
            FileOutputStream(tmp).use { it.write(data) }
            if (!tmp.renameTo(file)) {
                tmp.copyTo(file, overwrite = true)
                tmp.delete()
            }
            SpooledJob(id, file)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to spool print job", e)
            tmp.delete()
            null
        }
    }

    private fun ensureCapacity(incomingBytes: Int): Boolean {
        purgeStaleJobs()
        val files = listJobFiles()
        if (files.size >= MAX_JOBS) return false
        val total = files.sumOf { it.length() } + incomingBytes
        if (total > MAX_TOTAL_BYTES) {
            evictOldestUntilUnder(MAX_TOTAL_BYTES - incomingBytes)
        }
        return listJobFiles().size < MAX_JOBS
    }

    private fun evictOldestUntilUnder(maxBytes: Long) {
        val files = listJobFiles().toMutableList()
        var total = files.sumOf { it.length() }
        while (files.isNotEmpty() && total > maxBytes) {
            val oldest = files.removeAt(0)
            total -= oldest.length()
            oldest.delete()
            Log.w(TAG, "Evicted spool job ${oldest.name} (cache cap)")
        }
    }

    private fun listJobFiles(): List<File> =
        queueDir.listFiles()
            ?.filter { file ->
                file.isFile &&
                    !file.name.endsWith(".tmp") &&
                    (file.name.endsWith(".escpos") || file.name.endsWith(".bin"))
            }
            ?.sortedBy { it.lastModified() }
            ?: emptyList()

    fun buildEscPos(text: String, layout: PrinterConfig.Layout): ByteArray {
        val lineFeed = byteArrayOf(0x0A)
        val cut = byteArrayOf(0x1D, 0x56, 0x00)
        return PrinterConfig.escPosPrefix(layout) +
            text.toByteArray(Charsets.UTF_8) +
            lineFeed + lineFeed + lineFeed + cut
    }

    companion object {
        private const val TAG = "PrintSpooler"
        private const val QUEUE_DIR = "print-queue"

        /** Bounded queue — low-RAM POS devices should not buffer many receipts in memory or on disk. */
        const val MAX_JOBS = 15
        /** Cap spool footprint in cache (device storage is often nearly full). */
        const val MAX_TOTAL_BYTES = 3L * 1024 * 1024
        /** Orphan jobs older than this are discarded on startup cleanup. */
        const val MAX_JOB_AGE_MS = 60L * 60 * 1000

        /** Visible for unit tests — production uses [Context] constructor (app cache). */
        internal fun forDirectory(dir: File): PrintSpooler {
            dir.mkdirs()
            return PrintSpooler(dir)
        }
    }
}
