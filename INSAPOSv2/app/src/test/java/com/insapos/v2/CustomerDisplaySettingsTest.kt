package com.insapos.v2

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class CustomerDisplaySettingsTest {

    @Test
    fun keys_useDottedNamespace() {
        assertEquals("customer_display.enabled", CustomerDisplaySettings.KEY_ENABLED)
        assertEquals("customer_display.rotation_mode", CustomerDisplaySettings.KEY_ROTATION_MODE)
    }

    @Test
    fun toJson_defaultsWhenDbNull() {
        val json = CustomerDisplaySettings.toJson(null, "Test Store")
        assertTrue(json.getBoolean("enabled"))
        assertTrue(json.getBoolean("show_cart"))
        assertEquals("auto", json.getString("orientation"))
        assertEquals("mix", json.getString("rotation_mode"))
        assertEquals("Test Store", json.getString("store_name"))
    }
}
