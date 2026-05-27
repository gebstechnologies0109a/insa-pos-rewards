package com.insapos.v2

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
import com.insapos.v2.db.OfflineDatabase
import com.insapos.v2.printers.PrinterManager
import com.insapos.v2.sync.SyncEngine
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch

class PosService : Service() {

    companion object {
        private const val TAG = "INSAPOSv2Service"
        private const val CHANNEL_ID = "insapos_v2_service"
        private const val NOTIFICATION_ID = 2001
    }

    private val binder = LocalBinder()
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    var printerManager: PrinterManager? = null
        private set
    var localServer: PosLocalServer? = null
        private set
    var offlineDb: OfflineDatabase? = null
        private set
    var syncEngine: SyncEngine? = null
        private set
    var hidScannerDriver: HidScannerDriver? = null
    var onCameraScanRequested: (() -> Unit)? = null

    inner class LocalBinder : Binder() {
        fun getService(): PosService = this@PosService
    }

    override fun onBind(intent: Intent?): IBinder = binder

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
        startForeground(NOTIFICATION_ID, buildNotification())

        scope.launch {
            try {
                offlineDb = OfflineDatabase(this@PosService)
                Log.i(TAG, "Offline database initialized")
            } catch (e: Exception) {
                Log.e(TAG, "Offline DB init failed", e)
            }

            try {
                printerManager = PrinterManager(this@PosService)
                printerManager?.initialize()
                Log.i(TAG, "PrinterManager initialized")
            } catch (e: Exception) {
                Log.e(TAG, "PrinterManager init failed", e)
            }
        }
    }

    fun ensureLocalServerStarted() {
        if (localServer != null) return
        scope.launch {
            synchronized(this@PosService) {
                if (localServer != null) return@launch
                try {
                    localServer = PosLocalServer(
                        context = this@PosService,
                        getPrinterManager = { printerManager },
                        getHidScanner = { hidScannerDriver },
                        getDatabase = { offlineDb },
                        getSyncEngine = { syncEngine },
                        launchCameraScan = { onCameraScanRequested?.invoke() }
                    )
                    localServer?.start()
                    Log.i(TAG, "Local server started on port ${PosLocalServer.PORT}")
                } catch (e: Exception) {
                    Log.e(TAG, "Failed to start local server", e)
                }
            }
        }
    }

    fun startSyncEngine(connectivity: ConnectivityMonitor) {
        if (syncEngine != null) return
        val db = offlineDb ?: return
        val session = SessionManager(this)
        syncEngine = SyncEngine(this, db, session, connectivity).also { it.start() }
        Log.i(TAG, "Sync engine started")
    }

    override fun onDestroy() {
        syncEngine?.stop()
        localServer?.stop()
        printerManager?.release()
        offlineDb?.close()
        super.onDestroy()
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                getString(R.string.channel_name),
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = getString(R.string.channel_description)
            }
            val nm = getSystemService(NotificationManager::class.java)
            nm.createNotificationChannel(channel)
        }
    }

    private fun buildNotification(): Notification {
        val intent = Intent(this, MainActivity::class.java)
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S)
            PendingIntent.FLAG_IMMUTABLE else 0
        val pi = PendingIntent.getActivity(this, 0, intent, flags)

        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(this, CHANNEL_ID)
                .setContentTitle("INSAPOS v2")
                .setContentText("POS hardware bridge running")
                .setSmallIcon(R.drawable.ic_notification)
                .setContentIntent(pi)
                .setOngoing(true)
                .build()
        } else {
            @Suppress("DEPRECATION")
            Notification.Builder(this)
                .setContentTitle("INSAPOS v2")
                .setContentText("POS hardware bridge running")
                .setSmallIcon(R.drawable.ic_notification)
                .setContentIntent(pi)
                .setOngoing(true)
                .build()
        }
    }
}
