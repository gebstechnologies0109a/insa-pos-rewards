package com.insapos.v2

import android.content.Context
import android.hardware.display.DisplayManager
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.Display
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONArray
import org.json.JSONObject

/**
 * Drives the customer-facing secondary screen via Android Presentation API.
 * On dual-screen POS hardware (e.g. INSA Dual 1b DSI panel), shows cart mirror.
 */
class CustomerDisplayManager(private val activity: AppCompatActivity) {

    companion object {
        private const val TAG = "INSAPOSCustomerDisplay"
        private const val PREFS = "insapos_customer_display"
        private const val KEY_ENABLED = "enabled"
        private const val KEY_WELCOME = "welcome_message"
    }

    private val prefs = activity.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    private val handler = Handler(Looper.getMainLooper())
    private var presentation: CustomerDisplayPresentation? = null
    private var secondaryDisplay: Display? = null
    private var lastPayload: JSONObject? = null

    var enabled: Boolean
        get() = prefs.getBoolean(KEY_ENABLED, true)
        set(value) {
            prefs.edit().putBoolean(KEY_ENABLED, value).apply()
            if (value) showIfAvailable(lastPayload ?: welcomePayload()) else dismiss()
        }

    var welcomeMessage: String
        get() = prefs.getString(KEY_WELCOME, "Welcome to INSAPOS — your order will appear here") ?: ""
        set(value) = prefs.edit().putString(KEY_WELCOME, value).apply()

    fun refreshDisplays() {
        secondaryDisplay = findSecondaryDisplay()
    }

    fun getStatusJson(): JSONObject {
        refreshDisplays()
        val display = secondaryDisplay
        return JSONObject().apply {
            put("ok", true)
            put("enabled", enabled)
            put("available", display != null)
            put("active", presentation?.isShowing == true)
            put("display_id", display?.displayId ?: -1)
            put("display_name", display?.name ?: "")
            put("welcome_message", welcomeMessage)
        }
    }

    fun update(jsonPayload: String): JSONObject {
        return try {
            val payload = JSONObject(jsonPayload)
            updatePayload(payload)
            JSONObject().put("ok", true).put("updated", true)
        } catch (t: Throwable) {
            Log.w(TAG, "update failed: ${t.message}")
            JSONObject().put("ok", false).put("error", t.message ?: "invalid payload")
        }
    }

    fun updatePayload(payload: JSONObject) {
        lastPayload = payload
        if (!enabled) return
        handler.post {
            val display = ensureSecondaryDisplay() ?: return@post
            val pres = ensurePresentation(display)
            pres.render(payload)
        }
    }

    fun testDisplay(): JSONObject {
        val sample = JSONObject().apply {
            put("mode", "cart")
            put("store_name", "INSAPOS")
            put("subtitle", "Customer display test")
            put("items", JSONArray().apply {
                put(JSONObject().apply {
                    put("name", "Sample Item")
                    put("qty", 2)
                    put("price", 49.50)
                })
                put(JSONObject().apply {
                    put("name", "Demo Product")
                    put("qty", 1)
                    put("price", 120.0)
                })
            })
            put("subtotal", 219.0)
            put("discount", 0.0)
            put("total", 219.0)
        }
        updatePayload(sample)
        return JSONObject().apply {
            put("ok", true)
            put("tested", true)
            put("available", secondaryDisplay != null)
        }
    }

    fun showIfAvailable(payload: JSONObject = welcomePayload()) {
        if (!enabled) return
        updatePayload(payload)
    }

    fun dismiss() {
        handler.post {
            try {
                presentation?.dismiss()
            } catch (_: Exception) {
            }
            presentation = null
        }
    }

    fun onActivityDestroy() {
        dismiss()
    }

    private fun welcomePayload(): JSONObject {
        return JSONObject().apply {
            put("mode", "welcome")
            put("store_name", "INSAPOS")
            put("message", welcomeMessage)
        }
    }

    private fun ensureSecondaryDisplay(): Display? {
        if (secondaryDisplay == null) refreshDisplays()
        return secondaryDisplay
    }

    private fun ensurePresentation(display: Display): CustomerDisplayPresentation {
        val existing = presentation
        if (existing != null && existing.display.displayId == display.displayId && existing.isShowing) {
            return existing
        }
        try {
            existing?.dismiss()
        } catch (_: Exception) {
        }
        val pres = CustomerDisplayPresentation(activity, display)
        pres.show()
        presentation = pres
        return pres
    }

    private fun findSecondaryDisplay(): Display? {
        return try {
            val dm = activity.getSystemService(Context.DISPLAY_SERVICE) as DisplayManager
            dm.displays.firstOrNull { it.displayId != Display.DEFAULT_DISPLAY }
        } catch (t: Throwable) {
            Log.w(TAG, "Display scan failed: ${t.message}")
            null
        }
    }
}
