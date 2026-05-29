package com.insapos.v2

import android.Manifest
import android.annotation.SuppressLint
import android.app.ActivityManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.ServiceConnection
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbManager
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.http.SslError
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.util.Log
import android.view.KeyEvent
import android.view.View
import android.view.WindowManager
import android.webkit.CookieManager
import android.webkit.PermissionRequest
import android.webkit.SslErrorHandler
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.net.Uri
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import com.google.android.material.floatingactionbutton.ExtendedFloatingActionButton
import com.google.android.material.floatingactionbutton.FloatingActionButton
import androidx.activity.result.ActivityResultLauncher
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import com.insapos.v2.sync.SyncEngine
import com.insapos.v2.ui.DashboardScreen
import java.util.concurrent.Executors

class MainActivity : AppCompatActivity() {

    companion object {
        private const val TAG = "INSAPOSv3"
        private const val PERMISSION_REQUEST = 1001
        private const val ACTION_USB_PERMISSION = "com.insapos.v2.USB_PERMISSION"
    }

    private lateinit var webView: WebView
    private lateinit var offlineOverlay: LinearLayout
    private lateinit var statusBar: LinearLayout
    private lateinit var statusDot: View
    private lateinit var statusText: TextView
    private lateinit var syncBadge: LinearLayout
    private lateinit var tvSyncBadge: TextView
    private lateinit var tvOfflineStats: TextView
    private lateinit var fabModeToggle: ExtendedFloatingActionButton
    private lateinit var fabSettings: FloatingActionButton
    private lateinit var session: SessionManager
    private lateinit var connectivity: ConnectivityMonitor

    private var isSuperAdminFromWeb = false
    private var allowSuperAdminPanel = false

    private val handler = Handler(Looper.getMainLooper())
    internal var posService: PosService? = null
    private var serviceBound = false
    private var syncEngineStarted = false
    private var syncEngineSchedulePending = false
    private var pageLoaded = false
    private var isOfflineShown = false
    private var usingHttp = false
    /** After a successful sign-in, do not switch HTTP/HTTPS (would drop session cookies). */
    private var protocolLocked = false
    private var protocolProbeComplete = false
    private var cachedDeviceInfoJson: String? = null
    private var syncBadgeUpdatePending = false
    private var lastProgressUpdate = 0
    private val syncScheduler = Executors.newSingleThreadExecutor { r ->
        Thread(r, "insapos-sync-scheduler").apply { isDaemon = true }
    }

    private var hidScanner: HidScannerDriver? = null
    lateinit var customerDisplayManager: CustomerDisplayManager
        private set
    /** When true, HID keys go to the WebView scan/search field — do not intercept. */
    @Volatile
    private var scanInputFocused: Boolean = false
    private var usbPermissionCallback: ((Boolean) -> Unit)? = null

    fun notifyScanInputFocused(focused: Boolean) {
        scanInputFocused = focused
    }

    fun isNetworkOnline(): Boolean =
        if (::connectivity.isInitialized) connectivity.isConnected() else true

    /** Called from WebView when cashier sets branch — schedule sync off the main thread. */
    fun onBranchIdSetFromWeb(branchId: Int) {
        Log.i(TAG, "Branch set from web: $branchId — scheduling background sync")
        val service = posService ?: return
        service.signalCashierPageReady()
        scheduleSyncEngineAndPull(service, forceFullCatalog = false)
    }

    /** Deliver async createLocalSale result to the WebView without blocking the bridge thread. */
    fun dispatchLocalSaleResult(requestId: String, resultJson: String) {
        if (!isWebViewUsable()) return
        val quotedId = org.json.JSONObject.quote(requestId)
        val quotedResult = org.json.JSONObject.quote(resultJson)
        runOnUiThread {
            if (!isWebViewUsable()) return@runOnUiThread
            webView.evaluateJavascript(
                "if(window.onINSAPOSLocalSaleResult) window.onINSAPOSLocalSaleResult($quotedId, JSON.parse($quotedResult));",
                null
            )
        }
    }

    /** WebView session cookies are scoped to the cashier URL scheme (usually HTTPS). */
    private fun syncSessionCookies(): String? {
        val cm = CookieManager.getInstance()
        val base = session.getBaseUrl()
        val httpAlt = if (base.startsWith("https://")) {
            "http://" + base.removePrefix("https://")
        } else {
            "https://" + base.removePrefix("http://")
        }
        return listOf(base, httpAlt, "https://${session.serverDomain}", "http://${session.serverDomain}")
            .distinct()
            .mapNotNull { cm.getCookie(it)?.takeIf { c -> c.isNotBlank() } }
            .firstOrNull()
    }

    private fun attachSyncEngineCallbacks(engine: SyncEngine) {
        engine.onSyncStatusChanged = { status ->
            runOnUiThread {
                updateSyncBadge()
                dispatchSyncStatusToWeb(status)
                if (status == SyncEngine.SyncStatus.IDLE) {
                    customerDisplayManager.onSettingsSynced()
                }
            }
        }
        engine.onDownloadProgress = { progress ->
            runOnUiThread {
                updateSyncBadge()
                dispatchCatalogImportToWeb(progress)
            }
        }
    }

    private fun ensureSyncEngineStarted(service: PosService): SyncEngine? {
        if (!syncEngineStarted) {
            service.startSyncEngine(connectivity, ::syncSessionCookies)
            syncEngineStarted = true
            service.syncEngine?.let { attachSyncEngineCallbacks(it) }
        }
        return service.syncEngine
    }

    /** DB checks + sync trigger never run on the main thread (avoids ANR during catalog upsert). */
    private fun scheduleSyncEngineAndPull(service: PosService, forceFullCatalog: Boolean) {
        syncScheduler.execute {
            val engine = ensureSyncEngineStarted(service) ?: return@execute
            val branchId = session.branchId ?: 0
            val full = forceFullCatalog || needsFullCatalogPull(service, branchId)
            if (full) engine.syncNowFull() else engine.syncNowIncremental()
            handler.post { updateSyncBadge() }
        }
    }

    private fun needsFullCatalogPull(service: PosService, branchId: Int): Boolean {
        if (branchId <= 0) return true
        val db = service.offlineDb ?: return true
        if (db.getProductCount() == 0) return true
        val readyBranch = db.getSetting("cache_ready_branch_id")?.toIntOrNull()
        if (readyBranch != branchId) return true
        val syncedAt = db.getSetting("catalog_synced_at")
            ?: db.getSetting("catalog_last_sync")
            ?: db.getSetting("cache_ready_at")
        if (syncedAt.isNullOrBlank()) return true
        return catalogAgeMs(syncedAt) >= 1_800_000L
    }

    private fun catalogAgeMs(isoTimestamp: String): Long {
        return try {
            val fmt = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", java.util.Locale.US)
            fmt.timeZone = java.util.TimeZone.getTimeZone("UTC")
            System.currentTimeMillis() - (fmt.parse(isoTimestamp)?.time ?: return Long.MAX_VALUE)
        } catch (_: Exception) {
            Long.MAX_VALUE
        }
    }

    private val usbPermissionReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            if (intent?.action != ACTION_USB_PERMISSION) return
            val granted = intent.getBooleanExtra(UsbManager.EXTRA_PERMISSION_GRANTED, false)
            Log.i(TAG, "USB permission result: granted=$granted")
            usbPermissionCallback?.invoke(granted)
            usbPermissionCallback = null
        }
    }

    private val barcodeLauncher: ActivityResultLauncher<ScanOptions> =
        registerForActivityResult(ScanContract()) { result ->
            val code = result.contents
            Log.i(TAG, "ZXing scan result: $code")
            if (code != null) {
                posService?.localServer?.lastCameraScanResult = code
                handler.post {
                    dispatchBarcodeToWeb(code)
                }
            } else {
                posService?.localServer?.lastCameraScanResult = ""
            }
        }

    private val serviceConnection = object : ServiceConnection {
        override fun onServiceConnected(name: ComponentName?, binder: IBinder?) {
            val service = (binder as PosService.LocalBinder).getService()
            posService = service
            service.customerDisplayManager = customerDisplayManager
            customerDisplayManager.refreshDisplays()
            customerDisplayManager.showWelcome()
            service.hidScannerDriver = hidScanner
            service.onCameraScanRequested = { launchCameraScanner() }
            service.onRequestUsbPermission = { deviceId, onResult ->
                runOnUiThread { requestUsbPermissionForDevice(deviceId, onResult) }
            }
            serviceBound = true
            Log.i(TAG, "PosService bound")

            if (session.branchId != null) {
                scheduleSyncEngineAndPull(service, forceFullCatalog = false)
            }

            service.ensureLocalServerStarted()
            service.requestPrinterManager()
            Thread {
                service.waitForPrinterManager(15_000)
            }.start()
            service.syncEngine?.let { attachSyncEngineCallbacks(it) }
            injectLocalHardwareReady()

            if (pageLoaded) {
                onPageReadyForService(service)
            }
        }

        override fun onServiceDisconnected(name: ComponentName?) {
            posService = null
            serviceBound = false
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        session = SessionManager(this)
        setupKioskDisplay()
        setContentView(R.layout.activity_main)
        customerDisplayManager = CustomerDisplayManager(this)
        customerDisplayManager.dbProvider = { posService?.offlineDb }
        customerDisplayManager.storeNameProvider = { "INSAPOS" }
        usingHttp = session.useHttp

        webView = findViewById(R.id.webView)
        offlineOverlay = findViewById(R.id.offlineOverlay)
        statusBar = findViewById(R.id.statusBar)
        statusDot = findViewById(R.id.statusDot)
        statusText = findViewById(R.id.statusText)
        syncBadge = findViewById(R.id.syncBadge)
        tvSyncBadge = findViewById(R.id.tvSyncBadge)
        tvOfflineStats = findViewById(R.id.tvOfflineStats)
        fabModeToggle = findViewById(R.id.fabModeToggle)
        fabModeToggle.setOnClickListener { onModeToggleClicked() }
        fabSettings = findViewById(R.id.fabSettings)
        fabSettings.setOnClickListener { openPosSettings() }

        val versionText = findViewById<TextView>(R.id.versionText)
        versionText.text = "v${BuildConfig.VERSION_NAME}"

        hidScanner = HidScannerDriver { barcode ->
            Log.i(TAG, "HID Scan: $barcode")
            runOnUiThread { dispatchBarcodeToWeb(barcode) }
        }

        requestPermissions()
        registerUsbPermissionReceiver()
        setupCookies()
        setupConnectivity()
        setupWebView()
        injectLocalHardwareReady()
        warmDns()
        probeAndLoad()
        startPosService()
    }

    override fun onResume() {
        super.onResume()
        applyDisplayMode()
        tryEnterLockTaskIfAllowed()
        if (!serviceBound) {
            startPosService()
        } else {
            posService?.ensureLocalServerStarted()
            injectLocalHardwareReady()
        }
        if (::customerDisplayManager.isInitialized) {
            customerDisplayManager.refreshDisplays()
            customerDisplayManager.showWelcome()
        }
        if (::webView.isInitialized) {
            webView.requestFocus()
        }
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (hasFocus) {
            applyDisplayMode()
        }
    }

    override fun onDestroy() {
        try {
            unregisterReceiver(usbPermissionReceiver)
        } catch (_: Exception) { }
        if (serviceBound) {
            try {
                unbindService(serviceConnection)
            } catch (_: Exception) { }
            serviceBound = false
        }
        customerDisplayManager.onActivityDestroy()
        posService = null
        connectivity.stop()
        syncScheduler.shutdownNow()
        destroyWebView()
        super.onDestroy()
    }

    private fun shouldUseSoftwareWebViewLayer(): Boolean {
        val m = Build.MANUFACTURER.lowercase()
        val h = Build.HARDWARE.lowercase()
        val b = Build.BRAND.lowercase()
        return m.contains("mediatek") || m.contains("mtk") || h.contains("mt") ||
            b.contains("yqh") || b.contains("tab")
    }

    private fun destroyWebView() {
        if (!::webView.isInitialized) return
        try {
            webView.stopLoading()
            webView.removeJavascriptInterface(AndroidBridge.BRIDGE_NAME)
            webView.webChromeClient = null
            webView.webViewClient = object : WebViewClient() {}
            webView.destroy()
        } catch (e: Exception) {
            Log.w(TAG, "WebView destroy: ${e.message}")
        }
    }

    private fun isWebViewUsable(): Boolean {
        return !isFinishing && !isDestroyed && ::webView.isInitialized
    }

    override fun dispatchKeyEvent(event: KeyEvent): Boolean {
        if (!scanInputFocused && hidScanner?.handleKeyEvent(event) == true) return true
        return super.dispatchKeyEvent(event)
    }

    // --- Kiosk / immersive fullscreen ---

    private fun isAllowMinimizeEnabled(): Boolean =
        if (::session.isInitialized) session.allowMinimize else false

    /** POS Settings toggle — re-applies kiosk immersive or relaxed system bars. */
    fun setAllowMinimizeEnabled(enabled: Boolean) {
        session.allowMinimize = enabled
        updateKioskWindowBehavior()
        applyDisplayMode()
    }

    private fun setupKioskDisplay() {
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            window.attributes = window.attributes.apply {
                layoutInDisplayCutoutMode =
                    WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES
            }
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        }

        updateKioskWindowBehavior()
        applyDisplayMode()
    }

    private fun updateKioskWindowBehavior() {
        if (!isAllowMinimizeEnabled()) {
            WindowCompat.setDecorFitsSystemWindows(window, false)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                window.decorView.setOnApplyWindowInsetsListener { view, insets ->
                    applyKioskImmersiveMode()
                    view.onApplyWindowInsets(insets)
                }
            } else {
                @Suppress("DEPRECATION")
                window.decorView.setOnSystemUiVisibilityChangeListener {
                    applyKioskImmersiveMode()
                }
            }
        } else {
            WindowCompat.setDecorFitsSystemWindows(window, true)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                window.decorView.setOnApplyWindowInsetsListener(null)
            } else {
                @Suppress("DEPRECATION")
                window.decorView.setOnSystemUiVisibilityChangeListener(null)
            }
        }
    }

    /** Applies kiosk immersive or normal system bars based on {@link SessionManager#allowMinimize}. */
    private fun applyDisplayMode() {
        if (isAllowMinimizeEnabled()) {
            applyAllowMinimizeMode()
        } else {
            applyKioskImmersiveMode()
        }
    }

    /** Status/nav bars visible; edge swipe opens recents/home without sticky re-hide. */
    private fun applyAllowMinimizeMode() {
        WindowCompat.setDecorFitsSystemWindows(window, true)
        val controller = WindowCompat.getInsetsController(window, window.decorView)
        controller.systemBarsBehavior = WindowInsetsControllerCompat.BEHAVIOR_DEFAULT
        controller.show(WindowInsetsCompat.Type.systemBars())

        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.R) {
            @Suppress("DEPRECATION")
            window.decorView.systemUiVisibility = View.SYSTEM_UI_FLAG_VISIBLE
        }
    }

    private fun applyKioskImmersiveMode() {
        WindowCompat.setDecorFitsSystemWindows(window, false)
        val controller = WindowCompat.getInsetsController(window, window.decorView)
        controller.systemBarsBehavior =
            WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
        controller.hide(WindowInsetsCompat.Type.systemBars())

        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.R) {
            @Suppress("DEPRECATION")
            window.decorView.systemUiVisibility = (
                View.SYSTEM_UI_FLAG_FULLSCREEN or
                View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
                View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY or
                View.SYSTEM_UI_FLAG_LAYOUT_STABLE or
                View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN or
                View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
            )
        }
    }

    /** Uses lock-task only in strict kiosk mode when the device already pinned the app. */
    private fun tryEnterLockTaskIfAllowed() {
        if (isAllowMinimizeEnabled()) return
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return
        try {
            val state = getSystemService(ActivityManager::class.java).lockTaskModeState
            if (state == ActivityManager.LOCK_TASK_MODE_PINNED ||
                state == ActivityManager.LOCK_TASK_MODE_LOCKED
            ) {
                startLockTask()
            }
        } catch (e: Exception) {
            Log.d(TAG, "Lock task unavailable — pin INSAPOS from Recents for kiosk lock-down")
        }
    }

    // --- Permissions ---

    private fun requestPermissions() {
        val needed = mutableListOf<String>()

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
            != PackageManager.PERMISSION_GRANTED
        ) needed.add(Manifest.permission.CAMERA)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_CONNECT)
                != PackageManager.PERMISSION_GRANTED
            ) needed.add(Manifest.permission.BLUETOOTH_CONNECT)

            if (ContextCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_SCAN)
                != PackageManager.PERMISSION_GRANTED
            ) needed.add(Manifest.permission.BLUETOOTH_SCAN)
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
            ) needed.add(Manifest.permission.POST_NOTIFICATIONS)
        }

        if (needed.isNotEmpty()) {
            ActivityCompat.requestPermissions(this, needed.toTypedArray(), PERMISSION_REQUEST)
        }
    }

    private fun registerUsbPermissionReceiver() {
        val filter = IntentFilter(ACTION_USB_PERMISSION)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(usbPermissionReceiver, filter, Context.RECEIVER_NOT_EXPORTED)
        } else {
            registerReceiver(usbPermissionReceiver, filter)
        }
    }

    private fun requestUsbPermissionForDevice(deviceId: Int, onResult: (Boolean) -> Unit) {
        val usbManager = getSystemService(Context.USB_SERVICE) as UsbManager
        val device = usbManager.deviceList.values.find { it.deviceId == deviceId }
        if (device == null) {
            Log.w(TAG, "USB device $deviceId not found")
            onResult(false)
            return
        }
        requestUsbPermissionForDevice(device, onResult)
    }

    private fun requestUsbPermissionForDevice(device: UsbDevice, onResult: (Boolean) -> Unit) {
        val usbManager = getSystemService(Context.USB_SERVICE) as UsbManager
        if (usbManager.hasPermission(device)) {
            onResult(true)
            return
        }
        usbPermissionCallback = onResult
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            PendingIntent.FLAG_MUTABLE
        } else {
            0
        }
        val intent = Intent(ACTION_USB_PERMISSION).setPackage(packageName)
        val pendingIntent = PendingIntent.getBroadcast(
            this, device.deviceId, intent, flags
        )
        Log.i(TAG, "Requesting USB permission for ${device.productName ?: device.deviceId}")
        usbManager.requestPermission(device, pendingIntent)
    }

    // --- Cookie management ---

    private fun setupCookies() {
        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, true)
            cookieManager.flush()
        }
    }

    private fun persistCookies() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            CookieManager.getInstance().flush()
        }
    }

    // --- WebView ---

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {
        if (BuildConfig.DEBUG) {
            WebView.setWebContentsDebuggingEnabled(true)
        }

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            allowFileAccess = true
            useWideViewPort = true
            loadWithOverviewMode = true
            setSupportZoom(false)
            builtInZoomControls = false
            displayZoomControls = false
            databaseEnabled = true
            javaScriptCanOpenWindowsAutomatically = true
            mediaPlaybackRequiresUserGesture = false
            mixedContentMode = android.webkit.WebSettings.MIXED_CONTENT_ALWAYS_ALLOW

            cacheMode = android.webkit.WebSettings.LOAD_DEFAULT

            val appUa = "INSAPOSv3/${BuildConfig.VERSION_NAME} Android/${Build.VERSION.RELEASE}"
            userAgentString = "$userAgentString $appUa"
        }

        if (shouldUseSoftwareWebViewLayer()) {
            webView.setLayerType(View.LAYER_TYPE_SOFTWARE, null)
            Log.i(TAG, "WebView using software layer (MTK/low-GPU stability)")
        } else {
            webView.setLayerType(View.LAYER_TYPE_HARDWARE, null)
        }

        webView.isFocusable = true
        webView.isFocusableInTouchMode = true
        webView.requestFocus()

        webView.addJavascriptInterface(AndroidBridge(this), AndroidBridge.BRIDGE_NAME)

        webView.webViewClient = object : WebViewClient() {

            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                showStatus("Loading...")
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                pageLoaded = true
                hideOffline()
                hideStatus()
                persistCookies()

                url?.let {
                    if (!isSuperAdminPath(it) && !isLoginPath(it)) {
                        session.lastUrl = it
                    }
                    if (isAuthenticatedPosPath(it)) {
                        protocolLocked = true
                        if (it.startsWith("https://")) {
                            usingHttp = false
                            session.lockHttps()
                        }
                    }
                    if (isLoginPath(it) && it.startsWith("https://")) {
                        usingHttp = false
                        session.useHttp = false
                    }
                }

                if (url != null && shouldRedirectFromSuperAdmin(url)) {
                    handler.post { loadPosUrl() }
                    return
                }

                view?.requestFocus()
                injectBridgeReady()
                detectSuperAdminFromPage()
                updateModeToggleFab(url)
                updateSettingsFabVisibility(url)
                if (isCashierPath(url)) {
                    handler.postDelayed({ injectCashierToolbarIfNeeded() }, 500)
                    handler.postDelayed({ injectCashierToolbarIfNeeded() }, 2500)
                    handler.postDelayed({ injectCashierCustomerDisplayHook() }, 800)
                    handler.postDelayed({ injectCashierCustomerDisplayHook() }, 3000)
                }
                posService?.let { onPageReadyForService(it) }
            }

            override fun onReceivedError(
                view: WebView?, request: WebResourceRequest?, error: WebResourceError?
            ) {
                val url = request?.url?.toString() ?: ""
                val code = error?.errorCode ?: -1
                val desc = error?.description?.toString() ?: "unknown"
                if (request?.isForMainFrame == true) {
                    Log.e(TAG, "WebView main-frame error code=$code desc=$desc url=$url")

                    if (!usingHttp && !protocolLocked) {
                        Log.w(TAG, "HTTPS failed, falling back to HTTP")
                        usingHttp = true
                        session.useHttp = true
                        handler.post { loadPosUrl() }
                        return
                    }

                    Log.w(TAG, "Main frame load failed — retrying from WebView cache if available")
                    webView.settings.cacheMode = android.webkit.WebSettings.LOAD_CACHE_ELSE_NETWORK
                    val cached = session.lastUrl
                    if (!cached.isNullOrBlank() && isAuthenticatedPosPath(cached)) {
                        handler.post { webView.loadUrl(cached) }
                    }
                } else {
                    Log.w(TAG, "WebView subresource error code=$code url=$url desc=$desc")
                }
            }

            override fun onReceivedHttpError(
                view: WebView?,
                request: WebResourceRequest?,
                errorResponse: android.webkit.WebResourceResponse?
            ) {
                if (request?.isForMainFrame != true) return
                val url = request.url?.toString() ?: ""
                val status = errorResponse?.statusCode ?: 0
                Log.e(TAG, "WebView HTTP error status=$status url=$url")
            }

            override fun onReceivedSslError(
                view: WebView?, handler: SslErrorHandler?, error: SslError?
            ) {
                Log.w(TAG, "SSL error: ${error?.primaryError} on ${error?.url}")

                if (!usingHttp && !protocolLocked) {
                    usingHttp = true
                    session.useHttp = true
                    handler?.cancel()
                    this@MainActivity.handler.post { loadPosUrl() }
                    return
                }

                val host = error?.url?.let { java.net.URL(it).host } ?: ""
                if (host.endsWith("diybizrewards.com")) {
                    handler?.proceed()
                } else {
                    handler?.cancel()
                }
            }

            override fun shouldOverrideUrlLoading(
                view: WebView?, request: WebResourceRequest?
            ): Boolean {
                val url = request?.url?.toString() ?: return false
                if (shouldRedirectFromSuperAdmin(url)) {
                    handler.post { loadPosUrl() }
                    return true
                }
                val domain = session.serverDomain
                val sameOrigin = url.contains(domain) || url.contains("127.0.0.1")
                if (sameOrigin) {
                    persistCookies()
                }
                return !sameOrigin
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                if (newProgress >= 100 || newProgress - lastProgressUpdate < 25) return
                lastProgressUpdate = newProgress
                if (newProgress < 100) showStatus("Loading $newProgress%...")
            }

            override fun onPermissionRequest(request: PermissionRequest?) {
                request?.let { req ->
                    val granted = req.resources.filter { resource ->
                        when (resource) {
                            PermissionRequest.RESOURCE_VIDEO_CAPTURE -> {
                                ContextCompat.checkSelfPermission(
                                    this@MainActivity, Manifest.permission.CAMERA
                                ) == PackageManager.PERMISSION_GRANTED
                            }
                            PermissionRequest.RESOURCE_AUDIO_CAPTURE -> false
                            else -> false
                        }
                    }.toTypedArray()

                    runOnUiThread {
                        if (granted.isNotEmpty()) {
                            req.grant(granted)
                            Log.i(TAG, "WebView permission granted: ${granted.joinToString()}")
                        } else {
                            req.deny()
                            Log.w(TAG, "WebView permission denied — camera not permitted")
                        }
                    }
                }
            }
        }
    }

    // --- Protocol probe: HTTPS first, fallback to HTTP (complete before WebView load) ---

    private fun probeAndLoad() {
        if (protocolProbeComplete) {
            loadPosUrl()
            return
        }

        if (usingHttp || session.useHttp) {
            usingHttp = true
            protocolProbeComplete = true
            loadPosUrl()
            return
        }

        protocolProbeComplete = true
        loadPosUrl()

        Thread {
            val httpsOk = probeHttpsAvailable()
            if (!httpsOk && !protocolLocked) {
                runOnUiThread {
                    if (!usingHttp && !protocolLocked && !pageLoaded) {
                        Log.i(TAG, "HTTPS unavailable, using HTTP for this device")
                        usingHttp = true
                        session.useHttp = true
                        loadPosUrl()
                    }
                }
            }
        }.start()
    }

    private fun probeHttpsAvailable(): Boolean {
        return try {
            val url = java.net.URL("https://${session.serverDomain}/api/pos/ping")
            val conn = url.openConnection() as javax.net.ssl.HttpsURLConnection
            conn.connectTimeout = 1200
            conn.readTimeout = 1200
            conn.requestMethod = "HEAD"
            val code = conn.responseCode
            conn.disconnect()
            code in 200..399
        } catch (e: Exception) {
            Log.w(TAG, "HTTPS probe failed: ${e.message}")
            false
        }
    }

    private fun isAuthenticatedPosPath(url: String): Boolean {
        return try {
            val path = Uri.parse(url).path?.lowercase() ?: return false
            path.startsWith("/pos/cashier") || path.startsWith("/stockman/")
        } catch (_: Exception) {
            false
        }
    }

    private fun warmDns() {
        Thread {
            try {
                java.net.InetAddress.getByName(session.serverDomain)
                Log.d(TAG, "DNS warmed for ${session.serverDomain}")
            } catch (e: Exception) {
                Log.w(TAG, "DNS warm-up failed: ${e.message}")
            }
        }.start()
    }

    private fun dispatchBarcodeToWeb(barcode: String) {
        if (!isWebViewUsable()) return
        val quoted = org.json.JSONObject.quote(barcode)
        runOnUiThread {
            if (!isWebViewUsable()) return@runOnUiThread
            webView.evaluateJavascript(
                "if(window.onINSAPOSBarcode) window.onINSAPOSBarcode($quoted);",
                null
            )
        }
    }

    private fun onPageReadyForService(service: PosService) {
        if (syncEngineSchedulePending) return
        syncEngineSchedulePending = true
        val startSync = {
            syncEngineSchedulePending = false
            if (session.branchId != null) {
                scheduleSyncEngineAndPull(service, forceFullCatalog = false)
            } else {
                ensureSyncEngineStarted(service)
                updateSyncBadge()
            }
        }
        // Defer briefly so WebView/Alpine init is not competing with SQLite on first paint.
        webView.post {
            webView.post {
                handler.postDelayed(startSync, 3_000)
            }
        }
    }

    private fun loadPosUrl() {
        allowSuperAdminPanel = false
        if (!connectivity.isConnected()) {
            webView.settings.cacheMode = android.webkit.WebSettings.LOAD_CACHE_ELSE_NETWORK
        } else {
            webView.settings.cacheMode = android.webkit.WebSettings.LOAD_DEFAULT
        }
        if (shouldStartAtLogin()) {
            loadLoginUrl()
            return
        }
        if (protocolLocked && session.useHttp) {
            Log.i(TAG, "Authenticated session — forcing HTTPS for cashier")
            usingHttp = false
            session.lockHttps()
        }
        val url = session.getPosUrl()
        Log.i(TAG, "Loading: $url (useHttp=$usingHttp protocolLocked=$protocolLocked)")
        webView.loadUrl(url)
    }

    private fun loadLoginUrl() {
        val url = "${session.getBaseUrl()}/login"
        Log.i(TAG, "Loading login: $url")
        webView.loadUrl(url)
    }

    /** Skip cashier redirect when the last visit was login or we have no authenticated POS URL saved. */
    private fun shouldStartAtLogin(): Boolean {
        val last = session.lastUrl ?: return true
        if (isLoginPath(last)) return true
        return !isAuthenticatedPosPath(last) && !isStockmanPath(last)
    }

    private fun isStockmanPath(url: String): Boolean {
        return try {
            val path = Uri.parse(url).path?.lowercase() ?: return false
            path.startsWith("/stockman/")
        } catch (_: Exception) {
            false
        }
    }

    private fun isLoginPath(url: String): Boolean {
        return try {
            val path = Uri.parse(url).path?.lowercase() ?: return false
            path == "/login" || path.startsWith("/login/")
        } catch (_: Exception) {
            false
        }
    }

    fun setSuperAdminFromWeb(isSuperAdmin: Boolean) {
        isSuperAdminFromWeb = isSuperAdmin
        updateModeToggleFab(webView.url)
    }

    fun openPosMode() {
        allowSuperAdminPanel = false
        loadPosUrl()
    }

    /** Opens POS settings in the WebView (web header gear or JS bridge). */
    fun openPosSettings() {
        if (!isWebViewUsable()) return
        runOnUiThread {
            if (!isWebViewUsable()) return@runOnUiThread
            webView.evaluateJavascript(
                """
                (function() {
                    if (window.posAppInstance && window.posAppInstance.openPosSettings) {
                        window.posAppInstance.openPosSettings();
                    } else {
                        document.dispatchEvent(new CustomEvent('insapos:openSettings'));
                    }
                })();
                """.trimIndent(),
                null
            )
        }
    }

    /** Opens printer settings modal in the WebView (web header or JS bridge). */
    fun openPrinterSettings() {
        if (!isWebViewUsable()) return
        runOnUiThread {
            if (!isWebViewUsable()) return@runOnUiThread
            webView.evaluateJavascript(
                """
                (function() {
                    if (window.posAppInstance && window.posAppInstance.openPrinterSettings) {
                        window.posAppInstance.openPrinterSettings();
                    } else {
                        document.dispatchEvent(new CustomEvent('insapos:openPrinter'));
                    }
                })();
                """.trimIndent(),
                null
            )
        }
    }

    fun openSuperAdminPanel() {
        if (!isSuperAdminFromWeb) {
            loadPosUrl()
            return
        }
        allowSuperAdminPanel = true
        val url = "${session.getBaseUrl()}/super-admin"
        Log.i(TAG, "Opening super admin: $url")
        webView.loadUrl(url)
    }

    private fun onModeToggleClicked() {
        if (!isSuperAdminFromWeb) return
        if (allowSuperAdminPanel || isSuperAdminPath(webView.url ?: "")) {
            openPosMode()
        } else {
            openSuperAdminPanel()
        }
    }

    private fun detectSuperAdminFromPage() {
        webView.evaluateJavascript(
            "(window.INSA_IS_SUPER_ADMIN === true)"
        ) { result ->
            val isSa = result == "true"
            if (isSa != isSuperAdminFromWeb) {
                isSuperAdminFromWeb = isSa
                runOnUiThread { updateModeToggleFab(webView.url) }
            }
        }
    }

    private fun updateModeToggleFab(currentUrl: String?) {
        if (!isSuperAdminFromWeb) {
            fabModeToggle.visibility = View.GONE
            updateSettingsFabVisibility(currentUrl)
            return
        }
        fabModeToggle.visibility = View.VISIBLE
        val onSuperAdmin = isSuperAdminPath(currentUrl ?: "")
        fabModeToggle.text = if (onSuperAdmin) "POS Mode" else "Super Admin"
        updateSettingsFabVisibility(currentUrl)
    }

    private fun isCashierPath(url: String?): Boolean {
        return try {
            val path = Uri.parse(url ?: "").path?.lowercase() ?: ""
            path.startsWith("/pos/cashier")
        } catch (_: Exception) {
            false
        }
    }

    /** Hide native settings FAB on cashier — web header has gear beside printer. */
    private fun updateSettingsFabVisibility(currentUrl: String?) {
        val onCashier = isCashierPath(currentUrl)
        fabSettings.visibility = if (onCashier) View.GONE else View.VISIBLE
    }

    /**
     * Production cashier pages may lag behind the latest blade.
     * Inject header gear + fallback settings panel when the web modal is absent.
     */
    private fun injectCashierToolbarIfNeeded() {
        if (!isWebViewUsable()) return
        val js = """
            (function() {
                function openSettings() {
                    if (window.posAppInstance && window.posAppInstance.openPosSettings) {
                        window.posAppInstance.openPosSettings();
                        return;
                    }
                    document.dispatchEvent(new CustomEvent('insapos:openSettings'));
                    setTimeout(function() {
                        var alpineModal = document.querySelector('[x-show="showPosSettingsModal"]');
                        if (alpineModal && window.getComputedStyle(alpineModal).display !== 'none') return;
                        if (window.__insaposShowFallbackSettings) window.__insaposShowFallbackSettings();
                    }, 300);
                }

                window.__insaposShowFallbackSettings = function() {
                    var panel = document.getElementById('insaposFallbackSettings');
                    if (!panel) {
                        panel = document.createElement('div');
                        panel.id = 'insaposFallbackSettings';
                        panel.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:12px';
                        panel.innerHTML = '<div style="background:#fff;border-radius:16px;max-width:420px;width:100%;max-height:90vh;overflow:auto;padding:16px;font-family:system-ui,sans-serif">'
                            + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">'
                            + '<strong style="color:#065f46;font-size:18px">POS Settings</strong>'
                            + '<button id="insaposFallbackClose" style="border:0;background:transparent;font-size:20px;color:#9ca3af">&times;</button></div>'
                            + '<div id="insaposFallbackBody" style="font-size:13px;color:#374151">Loading…</div>'
                            + '<button id="insaposFallbackClose2" style="margin-top:12px;width:100%;padding:10px;border:0;border-radius:8px;background:#e5e7eb;color:#374151;font-weight:600">Close</button></div>';
                        document.body.appendChild(panel);
                        panel.addEventListener('click', function(e) { if (e.target === panel) panel.style.display = 'none'; });
                        document.getElementById('insaposFallbackClose').onclick = function() { panel.style.display = 'none'; };
                        document.getElementById('insaposFallbackClose2').onclick = function() { panel.style.display = 'none'; };
                    }
                    panel.style.display = 'flex';
                    var body = document.getElementById('insaposFallbackBody');
                    try {
                        var raw = (typeof INSAPOS !== 'undefined' && INSAPOS.getPosSettings) ? INSAPOS.getPosSettings() : '{}';
                        var s = typeof raw === 'string' ? JSON.parse(raw) : raw;
                        var cd = s.customer_display || {};
                        var dev = s.device || {};
                        var online = s.network_online !== false;
                        body.innerHTML = ''
                            + section('Network', online ? 'Online — sync available' : 'Offline — using cached data', online ? '#ecfdf5' : '#fffbeb')
                            + section('Customer Display', cd.available ? ('Secondary: ' + (cd.display_name || 'Detected')) : 'No secondary display', '#ecfdf5')
                            + section('Printer', 'Paper: ' + (s.paper_size || '57mm') + ' · Font: ' + (s.font_mode || 'paper_size'), '#f9fafb')
                            + section('Sync', 'Last sync: ' + (s.last_sync_at || '—') + '<br>Catalog: ' + (s.products_cached || 0) + ' products', '#f9fafb')
                            + section('About', 'App: INSA POS v' + (s.app_version || '—') + '<br>Device: ' + ((dev.manufacturer || '') + ' ' + (dev.model || '')).trim(), '#f9fafb')
                            + '<div style="margin-top:8px;display:flex;gap:8px">'
                            + '<button id="insaposFbSync" style="flex:1;padding:8px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:600">Sync Now</button>'
                            + '<button id="insaposFbPrinter" style="flex:1;padding:8px;border:0;border-radius:8px;background:#f3f4f6;color:#1f2937;font-weight:600">Printer Setup</button></div>';
                        document.getElementById('insaposFbSync').onclick = function() {
                            try { if (INSAPOS.triggerLocalSync) INSAPOS.triggerLocalSync(); } catch(e) {}
                        };
                        document.getElementById('insaposFbPrinter').onclick = function() {
                            panel.style.display = 'none';
                            if (window.posAppInstance && window.posAppInstance.openPrinterSettings) window.posAppInstance.openPrinterSettings();
                            else document.dispatchEvent(new CustomEvent('insapos:openPrinter'));
                        };
                    } catch (e) {
                        body.textContent = 'Could not load settings.';
                    }
                };

                function section(title, html, bg) {
                    return '<div style="border:1px solid #d1fae5;border-radius:12px;padding:10px;margin-bottom:8px;background:' + bg + '">'
                        + '<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:4px">' + title + '</div>'
                        + '<div>' + html + '</div></div>';
                }

                var gear = document.getElementById('posSettingsGear');
                if (!gear) {
                    var scanBtn = document.querySelector('[aria-label="Scan"], [title="Scan"], [title*="Scan Product"]');
                    var toolbar = document.getElementById('posHeaderToolbar');
                    if (!toolbar && scanBtn) toolbar = scanBtn.parentElement;
                    if (!toolbar) {
                        var icons = document.querySelectorAll('.pos-header-icon');
                        if (icons.length) toolbar = icons[icons.length - 1].parentElement;
                    }
                    if (!toolbar) return;
                    gear = document.createElement('button');
                    gear.id = 'posSettingsGear';
                    gear.type = 'button';
                    gear.className = 'pos-header-icon rounded-lg hover:bg-gray-100 active:bg-gray-200';
                    gear.title = 'Settings';
                    gear.setAttribute('aria-label', 'Settings');
                    gear.style.display = 'flex';
                    gear.innerHTML = '<svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>';
                    if (scanBtn && scanBtn.parentElement === toolbar) {
                        scanBtn.insertAdjacentElement('afterend', gear);
                    } else {
                        toolbar.appendChild(gear);
                    }
                }
                gear.onclick = function(e) { e.preventDefault(); openSettings(); };

                if (window.posAppInstance) {
                    window.posAppInstance.hasNativeBridge = true;
                    window.posAppInstance.buddyConnected = true;
                }
            })();
        """.trimIndent()
        webView.evaluateJavascript(js, null)
    }

    /**
     * Fallback when production blade has not yet wired cart → customer display hooks.
     * Polls Alpine cart state and mirrors via native bridge.
     */
    private fun injectCashierCustomerDisplayHook() {
        if (!isWebViewUsable()) return
        val storeName = "INSAPOS"
        val js = """
            (function() {
                if (window.__insaposCustomerDisplayHook) return;
                window.__insaposCustomerDisplayHook = true;
                var lastJson = '';
                var storeName = '$storeName';
                function mirrorCart() {
                    try {
                        if (typeof INSAPOS === 'undefined' || typeof INSAPOS.updateCustomerDisplay !== 'function') return;
                        var app = window.posAppInstance;
                        if (!app || !Array.isArray(app.cart)) return;
                        var subtotal = typeof app.cartSubtotal === 'number' ? app.cartSubtotal
                            : app.cart.reduce(function(s, i) { return s + (i.qty * i.price); }, 0);
                        var discount = typeof app.cartDiscount === 'number' ? app.cartDiscount : 0;
                        var total = typeof app.cartTotal === 'number' ? app.cartTotal : Math.max(0, subtotal - discount);
                        var payload = {
                            mode: app.cart.length ? 'cart' : 'welcome',
                            store_name: storeName,
                            items: app.cart.map(function(i) {
                                return {
                                    name: i.product_name || i.name || 'Item',
                                    qty: i.qty || 1,
                                    price: i.price || 0,
                                    lineTotal: (i.qty || 1) * (i.price || 0)
                                };
                            }),
                            subtotal: subtotal,
                            discount: discount,
                            total: total
                        };
                        var json = JSON.stringify(payload);
                        if (json === lastJson) return;
                        lastJson = json;
                        INSAPOS.updateCustomerDisplay(json);
                    } catch (e) {}
                }
                setInterval(mirrorCart, 500);
                mirrorCart();
            })();
        """.trimIndent()
        webView.evaluateJavascript(js, null)
    }

    private fun isSuperAdminPath(url: String): Boolean {
        return try {
            val path = Uri.parse(url).path?.lowercase() ?: return false
            path.contains("/super-admin")
        } catch (_: Exception) {
            false
        }
    }

    private fun shouldRedirectFromSuperAdmin(url: String): Boolean {
        if (!isSuperAdminPath(url)) return false
        return !isSuperAdminFromWeb || !allowSuperAdminPanel
    }

    // --- Connectivity ---

    private fun setupConnectivity() {
        connectivity = ConnectivityMonitor(
            context = this,
            onOnline = {
                runOnUiThread {
                    if (!isWebViewUsable()) return@runOnUiThread
                    Log.i(TAG, "Network online")
                    updateSyncBadge()
                    posService?.let { scheduleSyncEngineAndPull(it, forceFullCatalog = false) }
                    if (isOfflineShown) hideOffline()
                    webView.evaluateJavascript(
                        "if(window.onINSAPOSConnectivity) window.onINSAPOSConnectivity(true);", null
                    )
                }
            },
            onOffline = {
                runOnUiThread {
                    if (!isWebViewUsable()) return@runOnUiThread
                    Log.w(TAG, "Network offline — cashier continues on local cache")
                    updateSyncBadge()
                    webView.evaluateJavascript(
                        "if(window.onINSAPOSConnectivity) window.onINSAPOSConnectivity(false);", null
                    )
                }
            }
        )
        connectivity.start()
    }

    // --- UI helpers ---

    private fun showOffline() {
        isOfflineShown = true
        offlineOverlay.visibility = View.GONE
        updateOfflineStats()
    }

    private fun hideOffline() {
        isOfflineShown = false
        offlineOverlay.visibility = View.GONE
    }

    private fun updateOfflineStats() {
        val db = posService?.offlineDb ?: return
        Thread {
            val stats = db.getOfflineStats()
            val products = stats.optInt("products", 0)
            val txns = stats.optInt("transactions", 0)
            val unsynced = stats.optInt("unsynced_transactions", 0)
            runOnUiThread {
                tvOfflineStats.text = when {
                    products > 0 -> "$products products cached · $txns transactions ($unsynced pending sync)"
                    else -> "No offline data yet — connect to sync"
                }
            }
        }.start()
    }

    private fun dispatchCatalogImportToWeb(progress: SyncEngine.DownloadProgress) {
        val engine = posService?.syncEngine ?: return
        syncScheduler.execute {
            val catalog = engine.getCatalogImportJson()
            val json = catalog.toString()
                .replace("\\", "\\\\")
                .replace("'", "\\'")
            val msg = progress.message.replace("'", "\\'")
            val js = """
                (function() {
                    var detail = JSON.parse('$json');
                    detail.message = '$msg';
                    detail.progress = ${progress.percent};
                    document.dispatchEvent(new CustomEvent('insapos:catalogImport', { detail: detail }));
                    if (window.posAppInstance && window.posAppInstance.applyCatalogImportStatus) {
                        window.posAppInstance.applyCatalogImportStatus(detail);
                    }
                })();
            """.trimIndent()
            handler.post {
                if (!isWebViewUsable()) return@post
                webView.evaluateJavascript(js, null)
            }
        }
    }

    private fun dispatchSyncStatusToWeb(status: SyncEngine.SyncStatus) {
        val engine = posService?.syncEngine ?: return
        syncScheduler.execute {
            val json = engine.getStatusJson().toString()
                .replace("\\", "\\\\")
                .replace("'", "\\'")
            val js = """
                (function() {
                    var detail = JSON.parse('$json');
                    detail.engine_status = '${status.name}';
                    document.dispatchEvent(new CustomEvent('insapos:syncStatus', { detail: detail }));
                    if (window.posAppInstance && window.posAppInstance.applyNativeSyncStatus) {
                        window.posAppInstance.applyNativeSyncStatus(detail);
                    }
                })();
            """.trimIndent()
            handler.post {
                if (!isWebViewUsable()) return@post
                webView.evaluateJavascript(js, null)
            }
        }
    }

    private fun updateSyncBadge() {
        if (syncBadgeUpdatePending) return
        syncBadgeUpdatePending = true
        handler.postDelayed({
            syncBadgeUpdatePending = false
            val db = posService?.offlineDb ?: return@postDelayed
            Thread {
                val unsynced = db.getUnsyncedCount()
                val queueCount = db.getSyncQueueCount()
                val total = unsynced + queueCount
                val stats = db.getOfflineStats()
                val products = stats.optInt("products", 0)
                runOnUiThread {
                    syncBadge.visibility = View.GONE
                    dispatchDashboardToWeb(products, total)
                }
            }.start()
        }, 1500)
    }

    private fun dispatchDashboardToWeb(productsCached: Int, pendingSync: Int) {
        if (!pageLoaded) return
        val db = posService?.offlineDb
        val cashierId = session.cashierId
            ?: db?.getActiveShift()?.optInt("cashier_id", 0)
            ?: 0
        val today = if (cashierId > 0 && db != null) {
            db.getCashierTodaySalesStats(cashierId)
        } else {
            org.json.JSONObject().put("total_sales", 0.0).put("transaction_count", 0)
        }
        val shiftOpen = db?.getActiveShift() != null
        val payload = DashboardScreen.buildPayload(
            salesToday = today.optInt("transaction_count", 0),
            revenueToday = today.optDouble("total_sales", 0.0),
            pendingSync = pendingSync,
            shiftOpen = shiftOpen,
            productsCached = productsCached,
        )
        DashboardScreen.dispatchDashboardData(this, webView, payload)
    }

    private fun showStatus(text: String) {
        statusBar.visibility = View.VISIBLE
        statusText.text = text
    }

    private fun hideStatus() {
        handler.postDelayed({ statusBar.visibility = View.GONE }, 300)
    }

    // --- JS Bridge ready notification ---

    private fun injectLocalHardwareReady() {
        if (!::webView.isInitialized) return
        val js = """
            (function() {
                window.INSAPOS_SERVICE_PORT = ${PosLocalServer.PORT};
                window.INSAPOS_LOCAL_HARDWARE_READY = true;
                window.INSAPOS_OFFLINE_CAPABLE = true;
                if (typeof INSABuddy !== 'undefined' && INSABuddy.detectV2) INSABuddy.detectV2();
                document.dispatchEvent(new CustomEvent('insapos:hardwareReady'));
            })();
        """.trimIndent()
        runOnUiThread {
            if (isWebViewUsable()) webView.evaluateJavascript(js, null)
        }
    }

    private fun injectBridgeReady() {
        val isOnline = connectivity.isConnected()
        Thread {
            val deviceInfo = getDeviceInfoJson()
                .replace("'", "\\'")
                .replace("\n", "")
            val js = """
                (function() {
                    window.INSAPOS_DEVICE = JSON.parse('$deviceInfo');
                    window.INSAPOS_SERVICE_PORT = ${PosLocalServer.PORT};
                    window.INSAPOS_LOCAL_HARDWARE_READY = true;
                    window.INSAPOS_OFFLINE_CAPABLE = true;
                    window.INSAPOS_ONLINE = $isOnline;
                    try {
                        if (typeof INSAPOS !== 'undefined' && INSAPOS.getDeviceFingerprint) {
                            localStorage.setItem('insapos_device_fingerprint', INSAPOS.getDeviceFingerprint());
                        }
                    } catch (e) {}
                    if (window.onINSAPOSReady) window.onINSAPOSReady(window.INSAPOS_DEVICE);
                    document.dispatchEvent(new CustomEvent('insapos:ready', { detail: window.INSAPOS_DEVICE }));
                })();
            """.trimIndent()
            handler.post {
                if (isWebViewUsable()) webView.evaluateJavascript(js, null)
                updateSyncBadge()
            }
        }.start()
    }

    // --- Camera barcode scanner ---

    private fun launchCameraScanner() {
        runOnUiThread {
            try {
                val options = ScanOptions().apply {
                    setDesiredBarcodeFormats(ScanOptions.ALL_CODE_TYPES)
                    setPrompt("Point camera at barcode or QR code")
                    setCameraId(0)
                    setBeepEnabled(true)
                    setBarcodeImageEnabled(false)
                    setOrientationLocked(false)
                }
                barcodeLauncher.launch(options)
                Log.i(TAG, "ZXing camera scanner launched")
            } catch (e: Exception) {
                Log.e(TAG, "Failed to launch camera scanner", e)
                posService?.localServer?.lastCameraScanResult = ""
            }
        }
    }

    private fun getDeviceInfoJson(): String {
        cachedDeviceInfoJson?.let { return it }
        return DeviceInfo.toJsonString(this).also { cachedDeviceInfoJson = it }
    }

    // --- Service ---

    private fun startPosService() {
        val intent = Intent(this, PosService::class.java)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            startForegroundService(intent)
        } else {
            startService(intent)
        }
        bindService(intent, serviceConnection, Context.BIND_AUTO_CREATE)
    }
}
