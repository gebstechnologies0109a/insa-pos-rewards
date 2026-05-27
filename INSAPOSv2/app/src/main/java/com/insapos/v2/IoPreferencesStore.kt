package com.insapos.v2

import android.content.Context
import android.content.SharedPreferences
import org.json.JSONObject

class IoPreferencesStore(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    companion object {
        private const val PREFS_NAME = "insaposv2_io_prefs"
        private const val KEY_KEYBOARD = "default_keyboard_id"
        private const val KEY_MOUSE = "default_mouse_id"
        private const val KEY_SCANNER = "default_scanner_id"
        private const val KEY_USE_CAMERA = "use_camera_for_scan"
    }

    var defaultKeyboardId: String?
        get() = prefs.getString(KEY_KEYBOARD, null)
        set(value) = prefs.edit().putString(KEY_KEYBOARD, value).apply()

    var defaultMouseId: String?
        get() = prefs.getString(KEY_MOUSE, null)
        set(value) = prefs.edit().putString(KEY_MOUSE, value).apply()

    var defaultScannerId: String?
        get() = prefs.getString(KEY_SCANNER, null)
        set(value) = prefs.edit().putString(KEY_SCANNER, value).apply()

    var useCameraForScan: Boolean
        get() = prefs.getBoolean(KEY_USE_CAMERA, true)
        set(value) = prefs.edit().putBoolean(KEY_USE_CAMERA, value).apply()

    fun save(
        keyboardId: String? = null,
        mouseId: String? = null,
        scannerId: String? = null,
        useCamera: Boolean? = null
    ) {
        prefs.edit().apply {
            if (keyboardId != null) putString(KEY_KEYBOARD, keyboardId)
            if (mouseId != null) putString(KEY_MOUSE, mouseId)
            if (scannerId != null) putString(KEY_SCANNER, scannerId)
            if (useCamera != null) putBoolean(KEY_USE_CAMERA, useCamera)
            apply()
        }
    }

    fun toJson(): JSONObject = JSONObject().apply {
        put("default_keyboard_id", defaultKeyboardId ?: JSONObject.NULL)
        put("default_mouse_id", defaultMouseId ?: JSONObject.NULL)
        put("default_scanner_id", defaultScannerId ?: JSONObject.NULL)
        put("use_camera_for_scan", useCameraForScan)
    }
}
