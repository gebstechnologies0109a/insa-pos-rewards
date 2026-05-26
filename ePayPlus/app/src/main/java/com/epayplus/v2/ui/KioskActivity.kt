package com.epayplus.v2.ui

import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.ui.Modifier
import com.epayplus.v2.ui.screens.KioskHomeScreen
import com.epayplus.v2.ui.theme.EPayPlusTheme
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class KioskActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        window.addFlags(
            WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON or
            WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD or
            WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED
        )

        setContent {
            EPayPlusTheme(dynamicColor = false) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    KioskHomeScreen()
                }
            }
        }
    }

    @Deprecated("Use OnBackPressedCallback")
    override fun onBackPressed() {
        // Disabled in kiosk mode
    }
}
