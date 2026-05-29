package com.insapos.v2.sync

import org.junit.Assert.assertEquals
import org.junit.Test

class CatalogDownloadManagerTest {

    @Test
    fun defaultBatchSize_is500() {
        assertEquals(500, CatalogStreamImporter.DEFAULT_BATCH_SIZE)
    }

    @Test
    fun etagKey_includesBranchId() {
        val branchId = 7
        val key = "${CatalogDownloadManager.KEY_CATALOG_ETAG_PREFIX}$branchId"
        assertEquals("catalog_etag_b7", key)
    }
}
