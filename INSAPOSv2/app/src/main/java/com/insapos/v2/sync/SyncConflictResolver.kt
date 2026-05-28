package com.insapos.v2.sync

import org.json.JSONArray
import org.json.JSONObject

object SyncConflictResolver {

    const val PRICE_MISMATCH = "price_mismatch"
    const val INVENTORY_MISMATCH = "inventory_mismatch"
    const val EXPIRY_MISMATCH = "expiry_mismatch"

    fun parseConflicts(response: JSONObject): List<JSONObject> {
        val list = mutableListOf<JSONObject>()
        val conflicts = response.optJSONArray("conflicts")
            ?: response.optJSONArray("conflict")
            ?: return list

        for (i in 0 until conflicts.length()) {
            list.add(normalize(conflicts.getJSONObject(i)))
        }
        return list
    }

    fun hasBlockingConflicts(response: JSONObject): Boolean =
        parseConflicts(response).isNotEmpty()

    private fun normalize(raw: JSONObject): JSONObject = JSONObject().apply {
        put("type", raw.optString("type", raw.optString("field", PRICE_MISMATCH)))
        put("product_id", raw.optInt("product_id", 0))
        put("product_name", raw.optString("product_name", ""))
        put("field", raw.optString("field", ""))
        put("local_value", raw.opt("local_value") ?: JSONObject.NULL)
        put("server_value", raw.opt("server_value") ?: JSONObject.NULL)
    }
}
