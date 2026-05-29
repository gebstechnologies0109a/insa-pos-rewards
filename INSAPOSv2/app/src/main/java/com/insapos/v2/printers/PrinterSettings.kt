package com.insapos.v2.printers

import com.insapos.v2.db.OfflineDatabase

/**
 * Reads printer layout settings: local device override first, then synced POS defaults.
 */
class PrinterSettings(private val db: OfflineDatabase?) {

    fun layout(): PrinterConfig.Layout {
        val paper = localOrSynced(PrinterConfig.KEY_PAPER_SIZE, PrinterConfig.SYNC_KEY_PAPER_SIZE)
        val font = localOrSynced(PrinterConfig.KEY_FONT_MODE, PrinterConfig.SYNC_KEY_FONT_MODE)
        return PrinterConfig.resolve(paper, font)
    }

    fun saveLocal(paperSize: String, fontMode: String) {
        db?.setSetting(PrinterConfig.KEY_PAPER_SIZE, PrinterConfig.normalizePaperSize(paperSize))
        db?.setSetting(PrinterConfig.KEY_FONT_MODE, PrinterConfig.normalizeFontMode(fontMode))
    }

    fun toJson(): org.json.JSONObject = org.json.JSONObject().apply {
        val layout = layout()
        put("paper_size", layout.paperSize)
        put("font_mode", layout.fontMode)
        put("char_width", layout.charWidth)
        put("dot_width", layout.dotWidth)
    }

    private fun localOrSynced(localKey: String, syncedKey: String): String? =
        db?.getSetting(localKey)?.takeIf { it.isNotBlank() }
            ?: db?.getSetting(syncedKey)?.takeIf { it.isNotBlank() }
}
