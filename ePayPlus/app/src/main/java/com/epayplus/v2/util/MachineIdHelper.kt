package com.epayplus.v2.util

import android.content.Context
import android.os.Build
import android.provider.Settings

object MachineIdHelper {

    /**
     * Generates EPAY-prefixed machine UID or preserves legacy 09NET* identifiers.
     */
    fun getMachineUid(context: Context): String {
        val stored = context.getSharedPreferences("epay_machine", Context.MODE_PRIVATE)
            .getString("machine_uid", null)
        if (!stored.isNullOrBlank()) return stored

        val androidId = Settings.Secure.getString(
            context.contentResolver,
            Settings.Secure.ANDROID_ID
        ) ?: "UNKNOWN"

        return if (androidId.startsWith("09NET", ignoreCase = true)) {
            androidId.uppercase()
        } else {
            "EPAY${androidId.take(12).uppercase()}"
        }
    }

    fun getDeviceId(context: Context): String {
        return Settings.Secure.getString(
            context.contentResolver,
            Settings.Secure.ANDROID_ID
        ) ?: Build.SERIAL.takeIf { it != Build.UNKNOWN } ?: "EPAY-DEV-${System.currentTimeMillis()}"
    }

    fun saveMachineUid(context: Context, machineUid: String) {
        context.getSharedPreferences("epay_machine", Context.MODE_PRIVATE)
            .edit()
            .putString("machine_uid", machineUid)
            .apply()
    }
}
