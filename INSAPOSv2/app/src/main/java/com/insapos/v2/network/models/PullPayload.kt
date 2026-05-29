package com.insapos.v2.network.models

import org.json.JSONArray
import org.json.JSONObject

data class PullPayload(
    val success: Boolean = true,
    val branchId: Int = 0,
    val products: JSONArray = JSONArray(),
    val categories: JSONArray = JSONArray(),
    val customers: JSONArray = JSONArray(),
    val inventoryBatches: JSONArray = JSONArray(),
    val expiryAlerts: JSONArray = JSONArray(),
    val settings: JSONObject = JSONObject(),
    val serverTimestamp: String = "",
    val pulledAt: String = "",
) {
    fun toJson(): JSONObject = JSONObject().apply {
        put("success", success)
        put("branch_id", branchId)
        put("products", products)
        put("categories", categories)
        put("customers", customers)
        put("inventory_batches", inventoryBatches)
        put("expiry_alerts", expiryAlerts)
        put("settings", settings)
        put("server_timestamp", serverTimestamp)
        put("pulled_at", pulledAt.ifBlank { serverTimestamp })
    }

    companion object {
        fun fromJson(json: JSONObject): PullPayload = PullPayload(
            success = json.optBoolean("success", true),
            branchId = json.optInt("branch_id", 0),
            products = json.optJSONArray("products") ?: JSONArray(),
            categories = json.optJSONArray("categories") ?: JSONArray(),
            customers = json.optJSONArray("customers") ?: JSONArray(),
            inventoryBatches = json.optJSONArray("inventory_batches") ?: JSONArray(),
            expiryAlerts = json.optJSONArray("expiry_alerts") ?: JSONArray(),
            settings = when (val s = json.opt("settings")) {
                is JSONObject -> s
                else -> JSONObject()
            },
            serverTimestamp = json.optString("server_timestamp", json.optString("pulled_at", "")),
            pulledAt = json.optString("pulled_at", ""),
        )
    }
}
