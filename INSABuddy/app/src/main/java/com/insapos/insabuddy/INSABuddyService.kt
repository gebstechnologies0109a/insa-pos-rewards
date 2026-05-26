package com.insapos.insabuddy

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.os.Binder
import android.os.Build
import android.os.IBinder
import android.util.Log
import com.insapos.insabuddy.printers.PrinterManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

class INSABuddyService : Service() {

    companion object {
        private const val TAG = "INSABuddyService"
        private const val NOTIFICATION_ID = 1001
        private const val CHANNEL_ID = "insabuddy_service"
        private const val RECONNECT_INTERVAL_MS = 30_000L

        var instance: INSABuddyService? = null
            private set
    }

    inner class LocalBinder : Binder() {
        fun getService(): INSABuddyService = this@INSABuddyService
    }

    private val binder = LocalBinder()

    lateinit var printerManager: PrinterManager
        private set
    lateinit var scannerBridge: ScannerBridge
        private set
    lateinit var deviceInfo: DeviceInfo
        private set
    var server: LocalServer? = null
        private set

    private val serviceScope = CoroutineScope(Dispatchers.IO + Job())
    private var reconnectJob: Job? = null

    var onLog: ((String) -> Unit)? = null
    var hidScannerDriver: HidScannerDriver? = null

    override fun onCreate() {
        super.onCreate()
        instance = this

        createNotificationChannel()

        printerManager = PrinterManager(applicationContext)
        scannerBridge = ScannerBridge()
        deviceInfo = DeviceInfo(applicationContext)

        // Initialize printer on background thread — Bluetooth/USB I/O is blocking
        serviceScope.launch {
            try {
                printerManager.initialize()
                log("Printer manager initialized")
            } catch (e: Exception) {
                Log.e(TAG, "Printer init failed: ${e.message}")
                log("Printer init failed: ${e.message}")
            }
        }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        startForeground(NOTIFICATION_ID, buildNotification())
        startServer()
        startReconnectLoop()
        log("Service started")
        return START_STICKY
    }

    override fun onBind(intent: Intent?): IBinder = binder

    override fun onDestroy() {
        stopServer()
        reconnectJob?.cancel()
        serviceScope.cancel()
        try { printerManager.disconnect() } catch (_: Exception) {}
        instance = null
        log("Service destroyed")
        super.onDestroy()
    }

    private fun startServer() {
        if (server?.isAlive == true) return
        try {
            server = LocalServer(applicationContext, printerManager, scannerBridge, deviceInfo, { hidScannerDriver }).apply {
                this.onLog = { msg -> this@INSABuddyService.log(msg) }
                start()
            }
            log("Server started on port 18181")
        } catch (e: Exception) {
            Log.e(TAG, "Server start failed: ${e.message}")
            log("Server start failed: ${e.message}")
        }
    }

    private fun stopServer() {
        try {
            server?.stop()
            server = null
            log("Server stopped")
        } catch (e: Exception) {
            Log.e(TAG, "Server stop failed: ${e.message}")
        }
    }

    private fun startReconnectLoop() {
        reconnectJob?.cancel()
        reconnectJob = serviceScope.launch {
            while (true) {
                delay(RECONNECT_INTERVAL_MS)
                try {
                    if (printerManager.currentPrinter != null &&
                        printerManager.currentPrinter?.isConnected() == false
                    ) {
                        log("Auto-reconnecting printer...")
                        printerManager.reconnect()
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Reconnect failed: ${e.message}")
                }
            }
        }
    }

    fun isServerRunning(): Boolean = server?.isAlive == true

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                getString(R.string.notification_channel_name),
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Keeps INSABuddy running for hardware communication"
                setShowBadge(false)
            }
            val manager = getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(channel)
        }
    }

    private fun buildNotification(): Notification {
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_SINGLE_TOP
        }
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(this, CHANNEL_ID)
                .setContentTitle("INSABuddy")
                .setContentText(getString(R.string.notification_text))
                .setSmallIcon(R.drawable.ic_notification)
                .setContentIntent(pendingIntent)
                .setOngoing(true)
                .build()
        } else {
            @Suppress("DEPRECATION")
            Notification.Builder(this)
                .setContentTitle("INSABuddy")
                .setContentText(getString(R.string.notification_text))
                .setSmallIcon(R.drawable.ic_notification)
                .setContentIntent(pendingIntent)
                .setOngoing(true)
                .build()
        }
    }

    private fun log(message: String) {
        Log.d(TAG, message)
        onLog?.invoke(message)
    }
}
