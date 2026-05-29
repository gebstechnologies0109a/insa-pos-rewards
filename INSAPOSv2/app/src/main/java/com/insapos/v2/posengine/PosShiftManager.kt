package com.insapos.v2.posengine

import com.insapos.v2.db.OfflineDatabase
import org.json.JSONObject
import java.util.UUID

class PosShiftManager(private val db: OfflineDatabase) {

    fun getStatus(): JSONObject? = db.getActiveShift()

    fun openShift(cashierId: Int, branchId: Int, openingCash: Double): JSONObject {
        val resolvedCashier = cashierId.takeIf { it > 0 }
            ?: db.getSetting("cashier_id")?.toIntOrNull()
            ?: 0
        val resolvedBranch = branchId.takeIf { it > 0 }
            ?: db.getSetting("branch_id")?.toIntOrNull()
            ?: 0
        val existing = db.getActiveShift()
        if (existing != null) {
            return JSONObject().apply {
                put("ok", true)
                put("shift", existing)
                put("resumed", true)
            }
        }

        val localId = UUID.randomUUID().toString()
        val shift = db.openLocalShift(localId, resolvedBranch, resolvedCashier, openingCash)
        db.enqueueSyncAction("shift_open", "shifts", localId, shift)

        return JSONObject().apply {
            put("ok", true)
            put("shift", shift)
            put("resumed", false)
        }
    }

    fun closeShift(closingCash: Double): JSONObject {
        val active = db.getActiveShift()
            ?: return JSONObject().apply {
                put("ok", false)
                put("error", "No active shift")
            }

        val closed = db.closeLocalShift(active.getString("local_id"), closingCash)
        db.enqueueSyncAction("shift_close", "shifts", active.getString("local_id"), closed)

        return JSONObject().apply {
            put("ok", true)
            put("shift", closed)
        }
    }
}
