package com.epayplus.v2

import android.app.Application
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class EPayPlusApp : Application() {

    override fun onCreate() {
        super.onCreate()
        instance = this
    }

    companion object {
        lateinit var instance: EPayPlusApp
            private set
    }
}
