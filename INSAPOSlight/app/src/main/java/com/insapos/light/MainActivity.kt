package com.insapos.light

import android.Manifest
import android.annotation.SuppressLint
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import android.net.http.SslError
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.KeyEvent
import android.view.View
import android.view.WindowInsets
import android.view.WindowInsetsController
import android.view.WindowManager
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.PermissionRequest
import android.webkit.SslErrorHandler
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.LinearLayout
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat

class MainActivity : AppCompatActivity() {

    companion object {
        private const val TAG = "INSAPOSLite"
        private const val CAMERA_PERMISSION_REQUEST = 1001
    }

    private lateinit var webView: WebView
    private lateinit var offlineOverlay: LinearLayout
    private lateinit var session: SessionManager
    private val handler = Handler(Looper.getMainLooper())

    private var pageLoaded = false
    private var isOfflineShown = false
    private var usingHttp = false

    private var connectivityCallback: ConnectivityManager.NetworkCallback? = null

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        goFullscreen()

        session = SessionManager(this)
        usingHttp = session.useHttp

        webView = findViewById(R.id.webView)
        offlineOverlay = findViewById(R.id.offlineOverlay)

        findViewById<Button>(R.id.btnRetry).setOnClickListener {
            hideOffline()
            probeAndLoad()
        }

        requestCameraPermission()
        setupCookies()
        setupWebView()
        registerConnectivity()
        probeAndLoad()
    }

    override fun onResume() {
        super.onResume()
        goFullscreen()
    }

    override fun onDestroy() {
        connectivityCallback?.let {
            val cm = getSystemService(CONNECTIVITY_SERVICE) as ConnectivityManager
            cm.unregisterNetworkCallback(it)
        }
        super.onDestroy()
    }

    @Deprecated("Deprecated in Java")
    override fun onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack()
        } else {
            @Suppress("DEPRECATION")
            super.onBackPressed()
        }
    }

    override fun dispatchKeyEvent(event: KeyEvent): Boolean {
        if (event.keyCode == KeyEvent.KEYCODE_BACK) {
            return super.dispatchKeyEvent(event)
        }
        return super.dispatchKeyEvent(event)
    }

    // ── Fullscreen ──────────────────────────────────────────────

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

    // ── Permissions ─────────────────────────────────────────────

    private fun requestCameraPermission() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
            != PackageManager.PERMISSION_GRANTED
        ) {
            ActivityCompat.requestPermissions(
                this, arrayOf(Manifest.permission.CAMERA), CAMERA_PERMISSION_REQUEST
            )
        }
    }

    // ── Cookies ─────────────────────────────────────────────────

    private fun setupCookies() {
        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        @Suppress("DEPRECATION")
        cookieManager.setAcceptThirdPartyCookies(webView, true)
    }

    // ── WebView ─────────────────────────────────────────────────

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {
        if (BuildConfig.DEBUG) {
            WebView.setWebContentsDebuggingEnabled(true)
        }

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            useWideViewPort = true
            loadWithOverviewMode = true
            setSupportZoom(false)
            builtInZoomControls = false
            displayZoomControls = false
            databaseEnabled = true
            javaScriptCanOpenWindowsAutomatically = true
            mediaPlaybackRequiresUserGesture = false
            mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
            cacheMode = if (isNetworkAvailable()) WebSettings.LOAD_DEFAULT
                        else WebSettings.LOAD_CACHE_ELSE_NETWORK

            val appUa = "INSAPOSLite/${BuildConfig.VERSION_NAME} Android/${Build.VERSION.RELEASE}"
            userAgentString = "$userAgentString $appUa"
        }

        webView.isFocusable = true
        webView.isFocusableInTouchMode = true
        webView.requestFocus()

        webView.addJavascriptInterface(JsBridge(), "INSAPOS")

        webView.webViewClient = object : WebViewClient() {

            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                Log.d(TAG, "Page loading: $url")
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                pageLoaded = true
                hideOffline()
                url?.let { session.lastUrl = it }
                view?.requestFocus()
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
                view: WebView?, sslHandler: SslErrorHandler?, error: SslError?
            ) {
                Log.w(TAG, "SSL error: ${error?.primaryError} on ${error?.url}")

                if (!usingHttp) {
                    usingHttp = true
                    session.useHttp = true
                    sslHandler?.cancel()
                    handler.post { loadPosUrl() }
                    return
                }

                val host = error?.url?.let { java.net.URL(it).host } ?: ""
                if (host.endsWith("diybizrewards.com")) {
                    sslHandler?.proceed()
                } else {
                    sslHandler?.cancel()
                }
            }

            override fun shouldOverrideUrlLoading(
                view: WebView?, request: WebResourceRequest?
            ): Boolean {
                val url = request?.url?.toString() ?: return false
                return !url.contains(session.serverDomain)
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onPermissionRequest(request: PermissionRequest?) {
                request?.let { req ->
                    val granted = req.resources.filter { resource ->
                        resource == PermissionRequest.RESOURCE_VIDEO_CAPTURE &&
                            ContextCompat.checkSelfPermission(
                                this@MainActivity, Manifest.permission.CAMERA
                            ) == PackageManager.PERMISSION_GRANTED
                    }.toTypedArray()

                    runOnUiThread {
                        if (granted.isNotEmpty()) req.grant(granted) else req.deny()
                    }
                }
            }
        }
    }

    // ── HTTPS probe → load ──────────────────────────────────────

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

    // ── Connectivity ────────────────────────────────────────────

    private fun registerConnectivity() {
        val cm = getSystemService(CONNECTIVITY_SERVICE) as ConnectivityManager
        val cb = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                handler.post {
                    if (isOfflineShown) {
                        hideOffline()
                        webView.reload()
                    }
                }
            }

            override fun onLost(network: Network) {
                handler.post {
                    if (!isNetworkAvailable()) showOffline()
                }
            }
        }
        connectivityCallback = cb

        val request = NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build()
        cm.registerNetworkCallback(request, cb)
    }

    private fun isNetworkAvailable(): Boolean {
        val cm = getSystemService(CONNECTIVITY_SERVICE) as ConnectivityManager
        val net = cm.activeNetwork ?: return false
        val caps = cm.getNetworkCapabilities(net) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    // ── Offline overlay ─────────────────────────────────────────

    private fun showOffline() {
        isOfflineShown = true
        offlineOverlay.visibility = View.VISIBLE
    }

    private fun hideOffline() {
        isOfflineShown = false
        offlineOverlay.visibility = View.GONE
    }

    // ── JS bridge ───────────────────────────────────────────────

    private fun injectBridgeReady() {
        val js = """
            (function() {
                window.INSAPOS_DEVICE = {
                    app: 'INSAPOSLite',
                    version: '${BuildConfig.VERSION_NAME}',
                    platform: 'android',
                    model: '${Build.MODEL}',
                    manufacturer: '${Build.MANUFACTURER}',
                    sdk: ${Build.VERSION.SDK_INT},
                    androidVersion: '${Build.VERSION.RELEASE}',
                    lite: true
                };
                window.INSAPOS_OFFLINE_CAPABLE = false;
                if (window.onINSAPOSReady) window.onINSAPOSReady(window.INSAPOS_DEVICE);
                document.dispatchEvent(new CustomEvent('insapos:ready', { detail: window.INSAPOS_DEVICE }));
            })();
        """.trimIndent()
        webView.evaluateJavascript(js, null)
    }

    inner class JsBridge {
        @JavascriptInterface
        fun getDeviceInfo(): String {
            return """{"app":"INSAPOSLite","version":"${BuildConfig.VERSION_NAME}","platform":"android","model":"${Build.MODEL}","manufacturer":"${Build.MANUFACTURER}","sdk":${Build.VERSION.SDK_INT},"androidVersion":"${Build.VERSION.RELEASE}","lite":true}"""
        }

        @JavascriptInterface
        fun getAppVersion(): String = BuildConfig.VERSION_NAME

        @JavascriptInterface
        fun isLite(): Boolean = true
    }
}
