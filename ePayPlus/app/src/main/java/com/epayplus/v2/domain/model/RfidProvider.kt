package com.epayplus.v2.domain.model

import androidx.annotation.DrawableRes

data class RfidProvider(
    val code: String,
    val name: String,
    @DrawableRes val iconRes: Int,
    val accountLabel: String = "RFID Account / Plate No.",
    val accountHint: String = "Enter account or plate number",
    val category: String = "RFID Services"
)
