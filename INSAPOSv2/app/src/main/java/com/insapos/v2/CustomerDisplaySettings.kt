package com.insapos.v2

import com.insapos.v2.db.OfflineDatabase
import org.json.JSONObject

/**
 * Reads customer display settings from synced SQLite keys (pos_customer_display.*).
 */
object CustomerDisplaySettings {

    const val KEY_ENABLED = "customer_display.enabled"
    const val KEY_PHOTO = "customer_display.photo"
    const val KEY_VIDEO = "customer_display.video"
    const val KEY_ORIENTATION = "customer_display.orientation"
    const val KEY_ROTATION_MODE = "customer_display.rotation_mode"
    const val KEY_SHOW_CART = "customer_display.show_cart"

    private fun dbKey(key: String) = "pos_$key"

    fun get(db: OfflineDatabase?, key: String, default: String = ""): String {
        return db?.getSetting(dbKey(key))?.takeIf { it.isNotBlank() } ?: default
    }

    fun isEnabled(db: OfflineDatabase?): Boolean {
        val raw = get(db, KEY_ENABLED, "1")
        return raw == "1" || raw.equals("true", ignoreCase = true)
    }

    fun showCart(db: OfflineDatabase?): Boolean {
        val raw = get(db, KEY_SHOW_CART, "1")
        return raw != "0" && !raw.equals("false", ignoreCase = true)
    }

    fun toJson(db: OfflineDatabase?, storeName: String = "INSAPOS"): JSONObject {
        return JSONObject().apply {
            put("enabled", isEnabled(db))
            put("photo", get(db, KEY_PHOTO))
            put("video", get(db, KEY_VIDEO))
            put("orientation", get(db, KEY_ORIENTATION, "auto"))
            put("rotation_mode", get(db, KEY_ROTATION_MODE, "mix"))
            put("show_cart", showCart(db))
            put("store_name", storeName)
        }
    }
}
