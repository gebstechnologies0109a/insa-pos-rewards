package com.insapos.v2.network.models

import org.json.JSONObject

data class LicenseResponse(
    val allowed: Boolean,
    val branchId: Int? = null,
    val companyId: Int? = null,
    val code: String? = null,
    val message: String? = null,
) {
    companion object {
        fun fromJson(json: JSONObject): LicenseResponse = LicenseResponse(
            allowed = json.optBoolean("allowed", false),
            branchId = json.optInt("branch_id", 0).takeIf { it > 0 },
            companyId = json.optInt("company_id", 0).takeIf { it > 0 },
            code = json.optString("code", null),
            message = json.optString("message", null),
        )
    }
}
