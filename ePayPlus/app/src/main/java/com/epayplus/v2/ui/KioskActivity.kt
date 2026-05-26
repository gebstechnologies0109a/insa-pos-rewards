package com.epayplus.v2.ui

import android.app.ActivityManager
import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.view.KeyEvent
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import com.epayplus.v2.service.KioskService
import com.epayplus.v2.ui.screens.*
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

        startLockTask()

        val kioskIntent = Intent(this, KioskService::class.java).apply {
            action = KioskService.ACTION_START
        }
        startForegroundService(kioskIntent)

        setContent {
            EPayPlusTheme(dynamicColor = false) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    KioskNavHost()
                }
            }
        }
    }

    @Composable
    private fun KioskNavHost() {
        var currentScreen by remember { mutableStateOf("home") }
        var selectedNumber by remember { mutableStateOf("") }
        var selectedAmount by remember { mutableStateOf(0.0) }

        when (currentScreen) {
            "home" -> KioskHomeScreen()
            "phone_input" -> KioskPhoneInputScreen(
                onBack = { currentScreen = "home" },
                onConfirm = { number ->
                    selectedNumber = number
                    currentScreen = "amount"
                }
            )
            "amount" -> KioskAmountScreen(
                phoneNumber = selectedNumber,
                onBack = { currentScreen = "phone_input" },
                onAmountSelected = { amount ->
                    selectedAmount = amount
                    currentScreen = "payment"
                }
            )
            "payment" -> KioskPaymentScreen(
                requiredAmount = selectedAmount,
                onBack = { currentScreen = "amount" },
                onConfirm = { currentScreen = "processing" }
            )
            "processing" -> KioskProcessingScreen()
            "result_success" -> KioskResultScreen(
                isSuccess = true,
                amount = String.format("%.2f", selectedAmount),
                targetNumber = selectedNumber,
                onDone = { currentScreen = "home" }
            )
            "result_failed" -> KioskResultScreen(
                isSuccess = false,
                onDone = { currentScreen = "home" }
            )
        }
    }

    override fun onPause() {
        super.onPause()
        val am = getSystemService(Context.ACTIVITY_SERVICE) as ActivityManager
        am.moveTaskToFront(taskId, 0)
    }

    override fun dispatchKeyEvent(event: KeyEvent?): Boolean {
        if (event?.keyCode == KeyEvent.KEYCODE_HOME ||
            event?.keyCode == KeyEvent.KEYCODE_APP_SWITCH ||
            event?.keyCode == KeyEvent.KEYCODE_BACK) {
            return true
        }
        return super.dispatchKeyEvent(event)
    }

    @Deprecated("Use OnBackPressedCallback")
    override fun onBackPressed() {
        // Disabled in kiosk mode
    }

    override fun onDestroy() {
        stopLockTask()
        val intent = Intent(this, KioskService::class.java).apply {
            action = KioskService.ACTION_STOP
        }
        startService(intent)
        super.onDestroy()
    }
}
