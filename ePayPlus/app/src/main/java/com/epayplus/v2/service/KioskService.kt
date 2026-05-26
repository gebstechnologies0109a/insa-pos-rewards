package com.epayplus.v2.service

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import android.view.Gravity
import android.view.WindowManager
import androidx.core.app.NotificationCompat
import com.epayplus.v2.R
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.*

@AndroidEntryPoint
class KioskService : Service() {

    companion object {
        const val CHANNEL_ID = "kiosk_service"
        const val NOTIFICATION_ID = 1002
        const val ACTION_START = "com.epayplus.v2.KIOSK_START"
        const val ACTION_STOP = "com.epayplus.v2.KIOSK_STOP"
    }

    private val scope = CoroutineScope(Dispatchers.Main + SupervisorJob())
    private var statusBarBlocker: android.view.View? = null

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_STOP -> {
                stopKioskMode()
                stopSelf()
                return START_NOT_STICKY
            }
        }

        startForeground(NOTIFICATION_ID, buildNotification())
        startKioskMode()
        return START_STICKY
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun startKioskMode() {
        blockStatusBar()
        scope.launch {
            while (isActive) {
                ensureKioskForeground()
                delay(2000)
            }
        }
    }

    private fun stopKioskMode() {
        removeStatusBarBlocker()
        scope.cancel()
    }

    private fun blockStatusBar() {
        try {
            val wm = getSystemService(Context.WINDOW_SERVICE) as WindowManager
            val params = WindowManager.LayoutParams().apply {
                type = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O)
                    WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
                else
                    @Suppress("DEPRECATION")
                    WindowManager.LayoutParams.TYPE_SYSTEM_ERROR
                flags = WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
                        WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL or
                        WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN
                width = WindowManager.LayoutParams.MATCH_PARENT
                height = 50
                gravity = Gravity.TOP
            }
            statusBarBlocker = android.view.View(this).apply {
                setBackgroundColor(android.graphics.Color.TRANSPARENT)
                setOnTouchListener { _, _ -> true }
            }
            wm.addView(statusBarBlocker, params)
        } catch (_: Exception) { }
    }

    private fun removeStatusBarBlocker() {
        statusBarBlocker?.let {
            try {
                val wm = getSystemService(Context.WINDOW_SERVICE) as WindowManager
                wm.removeView(it)
            } catch (_: Exception) { }
        }
        statusBarBlocker = null
    }

    private fun ensureKioskForeground() {
        val am = getSystemService(Context.ACTIVITY_SERVICE) as android.app.ActivityManager
        val tasks = am.getRunningTasks(1)
        if (tasks.isNotEmpty()) {
            val topActivity = tasks[0].topActivity
            if (topActivity?.packageName != packageName) {
                val intent = Intent(this, com.epayplus.v2.ui.KioskActivity::class.java).apply {
                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_REORDER_TO_FRONT)
                }
                startActivity(intent)
            }
        }
    }

    private fun buildNotification(): Notification {
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Kiosk Mode Active")
            .setContentText("Device is locked in kiosk mode")
            .setSmallIcon(R.drawable.ic_notification)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setOngoing(true)
            .build()
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID, "Kiosk Service", NotificationManager.IMPORTANCE_LOW
            ).apply { description = "Maintains kiosk lock-down mode" }
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }
    }

    override fun onDestroy() {
        stopKioskMode()
        super.onDestroy()
    }
}
