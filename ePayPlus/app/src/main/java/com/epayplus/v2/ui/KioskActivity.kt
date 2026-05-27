package com.epayplus.v2.ui

import android.app.ActivityManager
import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.util.Log
import android.view.KeyEvent
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.navigation.compose.rememberNavController
import com.epayplus.v2.BuildConfig
import com.epayplus.v2.data.repository.AccountRepository
import com.epayplus.v2.receiver.DeviceAdminReceiver
import com.epayplus.v2.service.KioskService
import com.epayplus.v2.ui.navigation.KioskNavigation
import com.epayplus.v2.ui.screens.KioskExitPinDialog
import com.epayplus.v2.ui.theme.EPayPlusTheme
import com.epayplus.v2.util.KioskManager
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject

@AndroidEntryPoint
class KioskActivity : ComponentActivity() {

    @Inject lateinit var accountRepository: AccountRepository

    private val kioskManager by lazy { KioskManager(this) }
    private var lockTaskActive = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        window.addFlags(
            WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON or
                WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD or
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED
        )

        kioskManager.enableKioskMode(this)
        tryStartLockTask()

        val kioskIntent = Intent(this, KioskService::class.java).apply {
            action = KioskService.ACTION_START
        }
        startForegroundService(kioskIntent)

        setContent {
            EPayPlusTheme(dynamicColor = false) {
                var showExitDialog by remember { mutableStateOf(false) }
                val navController = rememberNavController()

                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    KioskNavigation(
                        navController = navController,
                        onAdminExitRequested = { showExitDialog = true }
                    )
                }

                if (showExitDialog) {
                    KioskExitPinDialog(
                        onDismiss = { showExitDialog = false },
                        onVerified = {
                            showExitDialog = false
                            exitKiosk()
                        },
                        validatePin = { pin -> verifyKioskExitPin(pin) }
                    )
                }
            }
        }
    }

    private suspend fun verifyKioskExitPin(pin: String): Boolean {
        val account = accountRepository.getAccountSync() ?: return false
        val expected = account.kioskPin.ifBlank { account.pin }
        return expected.isNotEmpty() && pin == expected
    }

    private fun tryStartLockTask() {
        if (BuildConfig.DEBUG) {
            Log.i(TAG, "Skipping lock task in DEBUG build")
            return
        }
        val dpm = getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        if (!dpm.isDeviceOwnerApp(packageName)) {
            Log.i(TAG, "Skipping lock task — not device owner")
            return
        }
        val am = getSystemService(Context.ACTIVITY_SERVICE) as ActivityManager
        if (am.lockTaskModeState != ActivityManager.LOCK_TASK_MODE_NONE) {
            lockTaskActive = true
            return
        }
        try {
            startLockTask()
            lockTaskActive = true
        } catch (e: Exception) {
            Log.w(TAG, "startLockTask failed", e)
        }
    }

    private fun exitKiosk() {
        if (lockTaskActive) {
            try {
                stopLockTask()
            } catch (e: Exception) {
                Log.w(TAG, "stopLockTask failed", e)
            }
            lockTaskActive = false
        }
        kioskManager.disableKioskMode(this)
        val stopIntent = Intent(this, KioskService::class.java).apply {
            action = KioskService.ACTION_STOP
        }
        startService(stopIntent)
        val mainIntent = Intent(this, MainActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
        }
        startActivity(mainIntent)
        finish()
    }

    override fun onPause() {
        super.onPause()
        val am = getSystemService(Context.ACTIVITY_SERVICE) as ActivityManager
        am.moveTaskToFront(taskId, 0)
    }

    override fun dispatchKeyEvent(event: KeyEvent?): Boolean {
        if (event?.keyCode == KeyEvent.KEYCODE_HOME ||
            event?.keyCode == KeyEvent.KEYCODE_APP_SWITCH ||
            event?.keyCode == KeyEvent.KEYCODE_BACK
        ) {
            return true
        }
        return super.dispatchKeyEvent(event)
    }

    @Deprecated("Use OnBackPressedCallback")
    override fun onBackPressed() {
        // Disabled in kiosk mode
    }

    override fun onDestroy() {
        if (lockTaskActive) {
            try {
                stopLockTask()
            } catch (_: Exception) { }
        }
        super.onDestroy()
    }

    companion object {
        private const val TAG = "KioskActivity"
    }
}
