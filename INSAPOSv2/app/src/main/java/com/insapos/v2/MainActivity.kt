package com.insapos.v2

import android.Manifest
import android.annotation.SuppressLint
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.ServiceConnection
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
import android.view.WindowInsets
import android.view.WindowInsetsController
import android.view.WindowManager
import android.webkit.CookieManager
import android.webkit.SslErrorHandler
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat

class MainActivity : AppCompatActivity() {

    companion object {
        private const val TAG = "INSAPOSv2"
        private const val PERMISSION_REQUEST = 1001
    }

    private lateinit var webView: WebView
    private lateinit var offlineOverlay: LinearLayout
    private lateinit var statusBar: LinearLayout
    private lateinit var statusDot: View
    private lateinit var statusText: TextView
    private lateinit var syncBadge: LinearLayout
    private lateinit var tvSyncBadge: TextView
    private lateinit var tvOfflineStats: TextView
    private lateinit var session: SessionManager
    private lateinit var connectivity: ConnectivityMonitor

    private val handler = Handler(Looper.getMainLooper())
    private var posService: PosService? = null
    private var serviceBound = false
    private var pageLoaded = false
    private var isOfflineShown = false
    private var usingHttp = false

    private var hidScanner: HidScannerDriver? = null

    private val serviceConnection = object : ServiceConnection {
        override fun onServiceConnected(name: ComponentName?, binder: IBinder?) {
            val service = (binder as PosService.LocalBinder).getService()
            posService = service
            service.hidScannerDriver = hidScanner
            service.startSyncEngine(connectivity)
            serviceBound = true
            Log.i(TAG, "PosService bound with sync engine")

            service.syncEngine?.onSyncStatusChanged = { status ->
                runOnUiThread { updateSyncBadge() }
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
        setContentView(R.layout.activity_main)

        goFullscreen()

        session = SessionManager(this)
        usingHttp = session.useHttp

        webView = findViewById(R.id.webView)
        offlineOverlay = findViewById(R.id.offlineOverlay)
        statusBar = findViewById(R.id.statusBar)
        statusDot = findViewById(R.id.statusDot)
        statusText = findViewById(R.id.statusText)
        syncBadge = findViewById(R.id.syncBadge)
        tvSyncBadge = findViewById(R.id.tvSyncBadge)
        tvOfflineStats = findViewById(R.id.tvOfflineStats)

        val versionText = findViewById<TextView>(R.id.versionText)
        versionText.text = "v${BuildConfig.VERSION_NAME}"

        hidScanner = HidScannerDriver { barcode ->
            Log.i(TAG, "HID Scan: $barcode")
            runOnUiThread {
                webView.evaluateJavascript(
                    "if(window.onINSAPOSBarcode) window.onINSAPOSBarcode('$barcode');",
                    null
                )
            }
        }

        requestPermissions()
        setupCookies()
        setupWebView()
        setupConnectivity()
        startPosService()

        probeAndLoad()
    }

    override fun onResume() {
        super.onResume()
        goFullscreen()
    }

    override fun onDestroy() {
        if (serviceBound) {
            unbindService(serviceConnection)
            serviceBound = false
        }
        connectivity.stop()
        super.onDestroy()
    }

    override fun dispatchKeyEvent(event: KeyEvent): Boolean {
        if (hidScanner?.handleKeyEvent(event) == true) return true
        return super.dispatchKeyEvent(event)
    }

    // --- Fullscreen ---

    private fun goFullscreen() {
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            window.insetsController?.let {
                it.hide(WindowInsets.Type.systemBars())
                it.systemBarsBehavior =
                    WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
            }
        } else {
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

    // --- Permissions ---

    private fun requestPermissions() {
        val needed = mutableListOf<String>()

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

    // --- Cookie management ---

    private fun setupCookies() {
        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, true)
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

            cacheMode = if (connectivity.isConnected())
                android.webkit.WebSettings.LOAD_DEFAULT
            else
                android.webkit.WebSettings.LOAD_CACHE_ELSE_NETWORK

            val appUa = "INSAPOSv2/${BuildConfig.VERSION_NAME} Android/${Build.VERSION.RELEASE}"
            userAgentString = "$userAgentString $appUa"
        }

        webView.addJavascriptInterface(AndroidBridge(this), AndroidBridge.BRIDGE_NAME)

        webView.webViewClient = object : WebViewClient() {

            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                showStatus("Loading...")
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                pageLoaded = true
                hideOffline()
                hideStatus()

                url?.let { session.lastUrl = it }

                injectBridgeReady()
            }

            override fun onReceivedError(
                view: WebView?, request: WebResourceRequest?, error: WebResourceError?
            ) {
                if (request?.isForMainFrame == true) {
                    Log.e(TAG, "WebView error: ${error?.description}")

                    if (!usingHttp) {
                        Log.w(TAG, "HTTPS failed, falling back to HTTP")
                        usingHttp = true
                        session.useHttp = true
                        handler.post { loadPosUrl() }
                        return
                    }

                    showOffline()
                }
            }

            override fun onReceivedSslError(
                view: WebView?, handler: SslErrorHandler?, error: SslError?
            ) {
                Log.w(TAG, "SSL error: ${error?.primaryError} on ${error?.url}")

                if (!usingHttp) {
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
                val domain = session.serverDomain
                return !(url.contains(domain) || url.contains("127.0.0.1"))
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                if (newProgress < 100) {
                    showStatus("Loading $newProgress%...")
                }
            }
        }
    }

    // --- Protocol probe: HTTPS first, fallback to HTTP ---

    private fun probeAndLoad() {
        if (usingHttp) {
            loadPosUrl()
            return
        }

        Thread {
            val httpsOk = try {
                val url = java.net.URL("https://${session.serverDomain}/api/pos/ping")
                val conn = url.openConnection() as javax.net.ssl.HttpsURLConnection
                conn.connectTimeout = 5000
                conn.readTimeout = 5000
                conn.requestMethod = "HEAD"
                val code = conn.responseCode
                conn.disconnect()
                code in 200..399
            } catch (e: Exception) {
                Log.w(TAG, "HTTPS probe failed: ${e.message}")
                false
            }

            runOnUiThread {
                if (!httpsOk) {
                    Log.i(TAG, "HTTPS unavailable, using HTTP")
                    usingHttp = true
                    session.useHttp = true
                }
                loadPosUrl()
            }
        }.start()
    }

    private fun loadPosUrl() {
        val url = session.getPosUrl()
        Log.i(TAG, "Loading: $url")
        webView.loadUrl(url)
    }

    // --- Connectivity ---

    private fun setupConnectivity() {
        connectivity = ConnectivityMonitor(
            context = this,
            onOnline = {
                Log.i(TAG, "Network online")
                updateSyncBadge()
                posService?.syncEngine?.syncNow()
                if (isOfflineShown) {
                    handler.postDelayed({
                        if (connectivity.isConnected()) {
                            hideOffline()
                            webView.reload()
                        }
                    }, 2000)
                }
                webView.evaluateJavascript(
                    "if(window.onINSAPOSConnectivity) window.onINSAPOSConnectivity(true);", null
                )
            },
            onOffline = {
                Log.w(TAG, "Network offline")
                updateSyncBadge()
                showOffline()
                webView.evaluateJavascript(
                    "if(window.onINSAPOSConnectivity) window.onINSAPOSConnectivity(false);", null
                )
            }
        )
        connectivity.start()
    }

    // --- UI helpers ---

    private fun showOffline() {
        isOfflineShown = true
        offlineOverlay.visibility = View.VISIBLE
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

    private fun updateSyncBadge() {
        val db = posService?.offlineDb ?: return
        Thread {
            val unsynced = db.getUnsyncedCount()
            val queueCount = db.getSyncQueueCount()
            val total = unsynced + queueCount
            runOnUiThread {
                if (total > 0) {
                    syncBadge.visibility = View.VISIBLE
                    tvSyncBadge.text = "$total pending"
                    val dot = findViewById<View>(R.id.syncDot)
                    dot.setBackgroundColor(
                        if (connectivity.isConnected()) 0xFFFF9800.toInt() else 0xFFF44336.toInt()
                    )
                } else {
                    syncBadge.visibility = View.GONE
                }
            }
        }.start()
    }

    private fun showStatus(text: String) {
        statusBar.visibility = View.VISIBLE
        statusText.text = text
    }

    private fun hideStatus() {
        handler.postDelayed({ statusBar.visibility = View.GONE }, 800)
    }

    // --- JS Bridge ready notification ---

    private fun injectBridgeReady() {
        val deviceInfo = DeviceInfo.toJsonString(this)
            .replace("'", "\\'")
            .replace("\n", "")

        val isOnline = connectivity.isConnected()

        val js = """
            (function() {
                window.INSAPOS_DEVICE = JSON.parse('$deviceInfo');
                window.INSAPOS_SERVICE_PORT = ${PosLocalServer.PORT};
                window.INSAPOS_OFFLINE_CAPABLE = true;
                window.INSAPOS_ONLINE = $isOnline;
                if (window.onINSAPOSReady) window.onINSAPOSReady(window.INSAPOS_DEVICE);
                document.dispatchEvent(new CustomEvent('insapos:ready', { detail: window.INSAPOS_DEVICE }));
            })();
        """.trimIndent()

        webView.evaluateJavascript(js, null)
        handler.postDelayed({ updateSyncBadge() }, 2000)
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
