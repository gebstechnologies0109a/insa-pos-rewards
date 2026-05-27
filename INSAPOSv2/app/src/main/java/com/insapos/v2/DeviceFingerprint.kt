package com.insapos.v2

import android.content.Context
import android.provider.Settings

object DeviceFingerprint {

    fun get(context: Context): String {
        val androidId = Settings.Secure.getString(
            context.contentResolver,
            Settings.Secure.ANDROID_ID
        ) ?: "unknown"
        return "android-$androidId"
    }
}
