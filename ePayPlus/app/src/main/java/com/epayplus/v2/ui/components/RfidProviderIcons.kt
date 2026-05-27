package com.epayplus.v2.ui.components

import androidx.annotation.DrawableRes
import com.epayplus.v2.R

object RfidProviderIcons {

    @DrawableRes
    fun iconRes(providerCode: String): Int = when (providerCode.uppercase()) {
        "EASYTRIP" -> R.drawable.ic_rfid_easytrip
        "AUTOSWEEP" -> R.drawable.ic_rfid_autosweep
        "TAPNGO" -> R.drawable.ic_rfid_tapngo
        "CONNECT" -> R.drawable.ic_rfid_connect
        "ETC" -> R.drawable.ic_rfid_etc
        "OTHER" -> R.drawable.ic_rfid_other
        else -> R.drawable.ic_rfid_other
    }
}
