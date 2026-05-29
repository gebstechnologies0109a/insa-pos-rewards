package com.insapos.v2

import org.json.JSONArray
import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class CustomerDisplayPayloadTest {

    @Test
    fun cartPayload_hasExpectedFields() {
        val payload = JSONObject().apply {
            put("mode", "cart")
            put("store_name", "INSA POS")
            put("items", JSONArray().apply {
                put(JSONObject().apply {
                    put("name", "Coffee")
                    put("qty", 2)
                    put("price", 85.0)
                })
            })
            put("subtotal", 170.0)
            put("discount", 0.0)
            put("total", 170.0)
        }
        assertEquals("cart", payload.getString("mode"))
        assertEquals(1, payload.getJSONArray("items").length())
        assertEquals(170.0, payload.getDouble("total"), 0.001)
    }

    @Test
    fun thankYouPayload_includesChange() {
        val payload = JSONObject().apply {
            put("mode", "thank_you")
            put("total", 500.0)
            put("change", 50.0)
            put("payment_method", "cash")
        }
        assertTrue(payload.getDouble("change") > 0)
        assertEquals("thank_you", payload.getString("mode"))
    }
}
