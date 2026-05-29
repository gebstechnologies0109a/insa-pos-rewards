package com.insapos.v2.network.models

import org.json.JSONObject

data class LicenseRequest(
    val deviceFingerprint: String,
    val branchId: Int? = null,
    val terminalSessionId: String? = null,
) {
    fun toJson(): JSONObject = JSONObject().apply {
        put("device_fingerprint", deviceFingerprint)
        branchId?.let { put("branch_id", it) }
        terminalSessionId?.let { put("terminal_session_id", it) }
    }
}
