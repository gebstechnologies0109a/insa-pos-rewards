package com.insapos.v2

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.hardware.usb.UsbManager
import android.os.Binder
import android.os.Build
import android.os.IBinder
import android.os.Looper
import android.util.Log
import com.insapos.v2.db.OfflineDatabase
import com.insapos.v2.posengine.PosEngine
import com.insapos.v2.printers.PrinterManager
import com.insapos.v2.sync.SyncEngine
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

class PosService : Service() {

    companion object {
        private const val TAG = "INSAPOSv3Service"
        private const val CHANNEL_ID = "insapos_v2_service"
        private const val NOTIFICATION_ID = 2001
    }

    private val binder = LocalBinder()
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    var printerManager: PrinterManager? = null
        private set
    private val printerInitLock = Any()
    private var printerInitScheduled = false
    private val serverLock = Any()
    var localServer: PosLocalServer? = null
        private set
    var offlineDb: OfflineDatabase? = null
        private set
    var posEngine: PosEngine? = null
        private set
    var syncEngine: SyncEngine? = null
        private set
    var hidScannerDriver: HidScannerDriver? = null
    var onCameraScanRequested: (() -> Unit)? = null
    /** Request USB permission from the foreground activity (deviceId, callback). */
    var onRequestUsbPermission: ((deviceId: Int, onResult: (Boolean) -> Unit) -> Unit)? = null
    val ioPreferences: IoPreferencesStore by lazy { IoPreferencesStore(this) }
    private var usbReceiver: BroadcastReceiver? = null

    inner class LocalBinder : Binder() {
        fun getService(): PosService = this@PosService
    }

    override fun onBind(intent: Intent?): IBinder {
        ensureOfflineReady()
        ensureLocalServerStarted()
        scheduleDeferredPrinterInit()
        return binder
    }

    /** SQLite + POS engine must exist before NanoHTTPD serves /local/sale. */
    fun ensureOfflineReady() {
        if (offlineDb != null && posEngine != null) return
        synchronized(serverLock) {
            if (offlineDb != null && posEngine != null) return
            try {
                offlineDb = OfflineDatabase(this)
                posEngine = offlineDb?.let { PosEngine(it) }
                Log.i(TAG, "Offline database and POS engine ready")
            } catch (e: Exception) {
                Log.e(TAG, "Offline DB init failed", e)
            }
        }
    }

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
        startForeground(NOTIFICATION_ID, buildNotification())

        registerUsbReceiver()

        ensureOfflineReady()
    }

    /** Lazily creates [PrinterManager]. Print paths init on a worker thread so deferred startup does not block receipts. */
    fun ensurePrinterManagerReady(): PrinterManager? {
        printerManager?.let { return it }
        synchronized(printerInitLock) {
            printerManager?.let { return it }
            if (Looper.myLooper() == Looper.getMainLooper()) {
                if (!printerInitScheduled) {
                    printerInitScheduled = true
                    scope.launch { initPrinterBlocking() }
                }
                return printerManager
            }
            return initPrinterBlocking()
        }
    }

    private fun initPrinterBlocking(): PrinterManager? {
        synchronized(printerInitLock) {
            printerManager?.let { return it }
            return try {
                PrinterManager(this).also {
                    it.initialize()
                    printerManager = it
                    Log.i(TAG, "PrinterManager initialized")
                }
            } catch (e: Exception) {
                Log.e(TAG, "PrinterManager init failed", e)
                null
            }
        }
    }

    /** Initialize printer stack when settings/print is used (avoids slow BT scan on sale path). */
    fun requestPrinterManager(): PrinterManager? {
        synchronized(printerInitLock) {
            printerInitScheduled = true
            return initPrinterBlocking()
        }
    }

    private fun scheduleDeferredPrinterInit() {
        if (printerInitScheduled || printerManager != null) return
        printerInitScheduled = true
        scope.launch {
            delay(15_000)
            if (printerManager == null) initPrinterBlocking()
        }
    }

    fun ensureLocalServerStarted() {
        synchronized(serverLock) {
            if (localServer != null) return
            try {
                localServer = PosLocalServer(
                    context = this,
                    getPrinterManager = { ensurePrinterManagerReady() },
                    getHidScanner = { hidScannerDriver },
                    getDatabase = { offlineDb },
                    getPosEngine = { posEngine },
                    getSyncEngine = { syncEngine },
                    ioPreferences = ioPreferences,
                    launchCameraScan = { onCameraScanRequested?.invoke() },
                    requestUsbPermission = { deviceId, onResult ->
                        onRequestUsbPermission?.invoke(deviceId, onResult)
                            ?: onResult(false)
                    }
                )
                localServer?.start()
                Log.i(TAG, "Local server started on port ${PosLocalServer.PORT}")
            } catch (e: Exception) {
                Log.e(TAG, "Failed to start local server", e)
            }
        }
    }

    fun startSyncEngine(connectivity: ConnectivityMonitor, cookies: () -> String? = { null }) {
        if (syncEngine != null) return
        val db = offlineDb ?: return
        val session = SessionManager(this)
        syncEngine = SyncEngine(this, db, session, connectivity, cookies).also { it.start() }
        Log.i(TAG, "Sync engine started")
    }

    private fun registerUsbReceiver() {
        if (usbReceiver != null) return
        usbReceiver = object : BroadcastReceiver() {
            override fun onReceive(context: Context?, intent: Intent?) {
                when (intent?.action) {
                    UsbManager.ACTION_USB_DEVICE_ATTACHED -> {
                        Log.i(TAG, "USB device attached — reconnecting printer")
                        scope.launch {
                            ensurePrinterManagerReady()
                            printerManager?.scanUsbPrinters()?.forEach { /* refresh list */ }
                            printerManager?.reconnect()
                        }
                    }
                    UsbManager.ACTION_USB_DEVICE_DETACHED -> {
                        Log.i(TAG, "USB device detached")
                    }
                }
            }
        }
        val filter = IntentFilter().apply {
            addAction(UsbManager.ACTION_USB_DEVICE_ATTACHED)
            addAction(UsbManager.ACTION_USB_DEVICE_DETACHED)
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(usbReceiver, filter, Context.RECEIVER_NOT_EXPORTED)
        } else {
            registerReceiver(usbReceiver, filter)
        }
    }

    override fun onDestroy() {
        usbReceiver?.let {
            try {
                unregisterReceiver(it)
            } catch (_: Exception) {
            }
        }
        usbReceiver = null
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
                .setContentTitle("INSA POS v3")
                .setContentText("POS hardware bridge running")
                .setSmallIcon(R.drawable.ic_notification)
                .setContentIntent(pi)
                .setOngoing(true)
                .build()
        } else {
            @Suppress("DEPRECATION")
            Notification.Builder(this)
                .setContentTitle("INSA POS v3")
                .setContentText("POS hardware bridge running")
                .setSmallIcon(R.drawable.ic_notification)
                .setContentIntent(pi)
                .setOngoing(true)
                .build()
        }
    }
}
