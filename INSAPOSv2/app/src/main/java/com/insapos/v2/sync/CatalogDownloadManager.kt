package com.insapos.v2.sync

import android.content.Context
import android.util.Log
import com.insapos.v2.db.OfflineDatabase
import org.json.JSONObject
import java.io.BufferedInputStream
import java.io.File
import java.io.FileInputStream
import java.io.FileOutputStream
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

/**
 * Downloads the product catalog to disk, then streams it into SQLite in batches.
 * Keeps peak RAM low on devices with 10k+ products.
 */
class CatalogDownloadManager(
    private val context: Context,
    private val db: OfflineDatabase,
    private val cookieProvider: () -> String?,
) {
    data class CatalogImportStatus(
        val state: String,
        val progress: Int,
        val productsImported: Int,
        val message: String,
    ) {
        fun toJson(): JSONObject = JSONObject().apply {
            put("state", state)
            put("progress", progress)
            put("products_imported", productsImported)
            put("message", message)
        }
    }

    data class SyncResult(
        val skipped: Boolean,
        val productsImported: Int,
        val categoriesImported: Int,
        val readyForUi: Boolean,
    )

    @Volatile
    private var status = CatalogImportStatus("idle", 0, 0, "")

    fun getStatus(): CatalogImportStatus = status

    fun syncCatalog(
        branchId: Int,
        baseUrl: String,
        onProgress: (CatalogImportStatus) -> Unit,
    ): SyncResult {
        val catalogDir = File(context.cacheDir, "catalog").apply { mkdirs() }
        cleanupOldFiles(catalogDir, branchId)

        val dest = File(catalogDir, "catalog_b${branchId}.json")
        val etagKey = "$KEY_CATALOG_ETAG_PREFIX$branchId"
        val storedEtag = db.getSetting(etagKey)

        updateStatus("downloading", 2, 0, "Downloading catalog…", onProgress)
        val download = downloadToFile(
            url = "${baseUrl.trimEnd('/')}/api/pos/products/all?branch_id=" +
                URLEncoder.encode(branchId.toString(), "UTF-8"),
            dest = dest,
            etag = storedEtag,
        )

        if (download.notModified) {
            if (db.getProductCount() > 0 && db.getCategoryCount() > 0) {
                Log.i(TAG, "Catalog unchanged (304) — SQLite already populated")
                updateStatus("ready", 100, db.getProductCount(), "Catalog up to date", onProgress)
                return SyncResult(
                    skipped = true,
                    productsImported = db.getProductCount(),
                    categoriesImported = 0,
                    readyForUi = true,
                )
            }
            if (!dest.exists() || dest.length() == 0L) {
                updateStatus("idle", 100, 0, "Catalog up to date", onProgress)
                return SyncResult(skipped = true, productsImported = 0, categoriesImported = 0, readyForUi = false)
            }
            Log.i(TAG, "Catalog unchanged (304) — importing cached file into empty DB")
        } else if (!download.success) {
            updateStatus("error", 0, 0, download.error ?: "Catalog download failed", onProgress)
            return SyncResult(false, 0, 0, db.getProductCount() > 0)
        } else {
            download.etag?.let { db.setSetting(etagKey, it) }
            db.setSetting(KEY_CATALOG_FILE, dest.absolutePath)
        }

        updateStatus("importing", 10, 0, "Importing catalog…", onProgress)

        val importer = CatalogStreamImporter(db)
        var lastReportedCount = 0
        var lastReportAtMs = 0L
        val importResult = FileInputStream(dest).use { input ->
            BufferedInputStream(input, 64 * 1024).use { buffered ->
                importer.importFromStream(buffered) { imported, _ ->
                    val pct = (10 + ((imported * 85L) / maxOf(imported + 500, 1))).toInt().coerceIn(10, 95)
                    val now = System.currentTimeMillis()
                    if (imported != lastReportedCount || now - lastReportAtMs > 2_000) {
                        lastReportedCount = imported
                        lastReportAtMs = now
                        updateStatus(
                            "importing",
                            pct,
                            imported,
                            "Importing catalog ($imported products)…",
                            onProgress,
                        )
                    }
                }
            }
        }

        val ready = importResult.readyAfterFirstBatch || db.getProductCount() > 0
        updateStatus("ready", 100, importResult.productsImported, "Catalog ready", onProgress)
        Log.i(
            TAG,
            "Catalog sync done: products=${importResult.productsImported} categories=${importResult.categoriesImported}",
        )

        return SyncResult(
            skipped = false,
            productsImported = importResult.productsImported,
            categoriesImported = importResult.categoriesImported,
            readyForUi = ready,
        )
    }

    private data class DownloadOutcome(
        val success: Boolean,
        val notModified: Boolean = false,
        val etag: String? = null,
        val error: String? = null,
    )

    private fun downloadToFile(url: String, dest: File, etag: String?): DownloadOutcome {
        val tmp = File(dest.parentFile, "${dest.name}.tmp")
        return try {
            val conn = URL(url).openConnection() as HttpURLConnection
            conn.requestMethod = "GET"
            conn.setRequestProperty("Accept", "application/json")
            if (!etag.isNullOrBlank()) {
                conn.setRequestProperty("If-None-Match", etag)
            }
            cookieProvider()?.takeIf { it.isNotBlank() }?.let {
                conn.setRequestProperty("Cookie", it)
            }
            conn.connectTimeout = 20_000
            conn.readTimeout = 180_000
            conn.instanceFollowRedirects = true

            when (conn.responseCode) {
                HttpURLConnection.HTTP_NOT_MODIFIED -> {
                    conn.disconnect()
                    DownloadOutcome(success = true, notModified = true)
                }
                in 200..299 -> {
                    val newEtag = conn.getHeaderField("ETag")?.trim('"')
                    conn.inputStream.use { input ->
                        FileOutputStream(tmp).use { output ->
                            val buffer = ByteArray(16 * 1024)
                            var read: Int
                            while (input.read(buffer).also { read = it } != -1) {
                                output.write(buffer, 0, read)
                            }
                        }
                    }
                    conn.disconnect()
                    if (!tmp.renameTo(dest)) {
                        tmp.copyTo(dest, overwrite = true)
                        tmp.delete()
                    }
                    DownloadOutcome(success = true, etag = newEtag)
                }
                else -> {
                    val err = conn.errorStream?.bufferedReader()?.readText()?.take(200)
                    conn.disconnect()
                    DownloadOutcome(success = false, error = "HTTP ${conn.responseCode}: $err")
                }
            }
        } catch (e: Exception) {
            tmp.delete()
            Log.e(TAG, "Catalog download failed: ${e.message}")
            DownloadOutcome(success = false, error = e.message)
        }
    }

    private fun cleanupOldFiles(catalogDir: File, keepBranchId: Int) {
        val keepName = "catalog_b${keepBranchId}.json"
        var totalBytes = 0L
        catalogDir.listFiles()?.sortedByDescending { it.lastModified() }?.forEach { file ->
            if (file.name == keepName || file.name.endsWith(".tmp")) return@forEach
            totalBytes += file.length()
            if (file.name.startsWith("catalog_b") && file.name != keepName) {
                file.delete()
            }
        }
        // Cap cache at ~80 MB on storage-constrained devices
        if (totalBytes > MAX_CACHE_BYTES) {
            catalogDir.listFiles()
                ?.filter { it.name.startsWith("catalog_b") && it.name != keepName }
                ?.sortedBy { it.lastModified() }
                ?.forEach { it.delete() }
        }
    }

    private fun updateStatus(
        state: String,
        progress: Int,
        productsImported: Int,
        message: String,
        onProgress: (CatalogImportStatus) -> Unit,
    ) {
        status = CatalogImportStatus(state, progress, productsImported, message)
        db.setSetting(KEY_CATALOG_IMPORT_STATE, state)
        db.setSetting(KEY_CATALOG_IMPORT_PROGRESS, progress.toString())
        onProgress(status)
    }

    companion object {
        private const val TAG = "CatalogDownload"
        private const val MAX_CACHE_BYTES = 80L * 1024 * 1024
        const val KEY_CATALOG_ETAG_PREFIX = "catalog_etag_b"
        const val KEY_CATALOG_FILE = "catalog_file_path"
        const val KEY_CATALOG_IMPORT_STATE = "catalog_import_state"
        const val KEY_CATALOG_IMPORT_PROGRESS = "catalog_import_progress"
    }
}
