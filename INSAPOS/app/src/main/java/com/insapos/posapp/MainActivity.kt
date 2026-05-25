package com.insapos.posapp

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.net.http.SslError
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.util.Log
import android.view.View
import android.view.WindowManager
import android.webkit.CookieManager
import android.webkit.ConsoleMessage
import android.webkit.SslErrorHandler
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONArray
import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class MainActivity : AppCompatActivity() {

    companion object {
        private const val TAG = "INSAPOS"
        private const val BASE_URL = "https://insapos.diybizrewards.com"
        private const val LOG_ENDPOINT = "$BASE_URL/api/pos/device-log"
        private const val MAX_LOCAL_LOGS = 200
    }

    private lateinit var webView: WebView
    private lateinit var loadingOverlay: LinearLayout
    private lateinit var debugOverlay: ScrollView
    private lateinit var debugText: TextView

    private val logBuffer = mutableListOf<JSONObject>()
    private var deviceId: String = "unknown"

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        goImmersive()

        deviceId = try {
            Settings.Secure.getString(contentResolver, Settings.Secure.ANDROID_ID) ?: "unknown"
        } catch (_: Exception) { "unknown" }

        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        loadingOverlay = findViewById(R.id.loadingOverlay)
        debugOverlay = findViewById(R.id.debugOverlay)
        debugText = findViewById(R.id.debugText)

        // Show debug overlay immediately
        debugOverlay.visibility = View.VISIBLE
        debugLog("INFO", "App started")
        debugLog("INFO", "Device: ${Build.MANUFACTURER} ${Build.MODEL}")
        debugLog("INFO", "Android: ${Build.VERSION.RELEASE} (API ${Build.VERSION.SDK_INT})")
        debugLog("INFO", "Device ID: $deviceId")
        debugLog("INFO", "Network: ${getNetworkType()}")
        debugLog("INFO", "Target URL: $BASE_URL")

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.KITKAT) {
            WebView.setWebContentsDebuggingEnabled(true)
        }

        CookieManager.getInstance().apply {
            setAcceptCookie(true)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                setAcceptThirdPartyCookies(webView, true)
            }
        }

        debugLog("INFO", "Configuring WebView settings...")

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            allowFileAccess = true
            loadWithOverviewMode = true
            useWideViewPort = true
            setSupportZoom(false)
            builtInZoomControls = false
            displayZoomControls = false
            mediaPlaybackRequiresUserGesture = false
            mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
            cacheMode = WebSettings.LOAD_DEFAULT
            userAgentString = "$userAgentString INSAPOS/${BuildConfig.VERSION_NAME}"

            @Suppress("DEPRECATION")
            allowUniversalAccessFromFileURLs = true
        }

        debugLog("INFO", "WebView UA: ${webView.settings.userAgentString.takeLast(60)}")

        webView.webViewClient = object : WebViewClient() {

            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                super.onPageStarted(view, url, favicon)
                debugLog("PAGE", "Started: $url")
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                debugLog("PAGE", "Finished: $url")
                loadingOverlay.visibility = View.GONE

                // Hide debug overlay after successful page load (user can tap to show)
                if (url != null && !url.startsWith("data:")) {
                    debugOverlay.visibility = View.GONE
                }

                CookieManager.getInstance().flush()
            }

            override fun onReceivedError(
                view: WebView?, request: WebResourceRequest?, error: WebResourceError?
            ) {
                super.onReceivedError(view, request, error)
                val url = request?.url?.toString() ?: "?"
                val code = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) error?.errorCode else null
                val desc = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) error?.description?.toString() else "unknown"
                val isMain = request?.isForMainFrame == true

                debugLog("ERROR", "onReceivedError [main=$isMain] code=$code desc=$desc url=$url")

                if (isMain) {
                    loadingOverlay.visibility = View.GONE
                    debugOverlay.visibility = View.VISIBLE
                    view?.loadData(errorPageHtml("$desc (code: $code)"), "text/html", "UTF-8")
                }
            }

            override fun onReceivedHttpError(
                view: WebView?, request: WebResourceRequest?, errorResponse: WebResourceResponse?
            ) {
                super.onReceivedHttpError(view, request, errorResponse)
                val url = request?.url?.toString() ?: "?"
                val status = errorResponse?.statusCode ?: 0
                val isMain = request?.isForMainFrame == true

                debugLog("HTTP", "HTTP $status [main=$isMain] $url")

                if (isMain && status >= 400) {
                    debugOverlay.visibility = View.VISIBLE
                }
            }

            @SuppressLint("WebViewClientOnReceivedSslError")
            override fun onReceivedSslError(
                view: WebView?, handler: SslErrorHandler?, error: SslError?
            ) {
                val errType = error?.primaryError
                val errUrl = error?.url ?: "?"
                debugLog("SSL", "SSL error type=$errType url=$errUrl")

                // Accept SSL for our domain
                if (errUrl.contains("insapos.diybizrewards.com") || errUrl.contains("127.0.0.1")) {
                    debugLog("SSL", "Proceeding despite SSL error for trusted domain")
                    handler?.proceed()
                } else {
                    debugLog("SSL", "Cancelling SSL for untrusted domain")
                    handler?.cancel()
                }
            }

            override fun shouldOverrideUrlLoading(
                view: WebView?, request: WebResourceRequest?
            ): Boolean {
                val url = request?.url?.toString() ?: return false
                debugLog("NAV", "shouldOverrideUrlLoading: $url")
                if (url.contains("insapos.diybizrewards.com") ||
                    url.startsWith("http://127.0.0.1") ||
                    url.startsWith("http://localhost")
                ) {
                    return false
                }
                debugLog("NAV", "Blocked external URL: $url")
                return true
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                if (newProgress % 25 == 0 || newProgress == 100) {
                    debugLog("LOAD", "Progress: $newProgress%")
                }
            }

            override fun onConsoleMessage(consoleMessage: ConsoleMessage?): Boolean {
                consoleMessage?.let {
                    val level = when (it.messageLevel()) {
                        ConsoleMessage.MessageLevel.ERROR -> "ERROR"
                        ConsoleMessage.MessageLevel.WARNING -> "WARN"
                        else -> "JS"
                    }
                    debugLog(level, "Console: ${it.message()} [${it.sourceId()}:${it.lineNumber()}]")
                }
                return true
            }
        }

        // Tap the debug overlay 3 times to toggle visibility
        var tapCount = 0
        var lastTap = 0L
        webView.setOnLongClickListener {
            debugOverlay.visibility = if (debugOverlay.visibility == View.VISIBLE) View.GONE else View.VISIBLE
            true
        }

        debugLog("INFO", "Loading URL: $BASE_URL")

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState)
            debugLog("INFO", "Restored WebView state")
        } else {
            webView.loadUrl(BASE_URL)
        }

        // Periodic log flush every 15 seconds
        startLogFlushTimer()
    }

    private val logFlushHandler = android.os.Handler(android.os.Looper.getMainLooper())
    private val logFlushRunnable = object : Runnable {
        override fun run() {
            flushLogsToServer()
            logFlushHandler.postDelayed(this, 15_000)
        }
    }

    private fun startLogFlushTimer() {
        logFlushHandler.removeCallbacks(logFlushRunnable)
        logFlushHandler.postDelayed(logFlushRunnable, 5_000)
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    @Deprecated("Use OnBackPressedDispatcher")
    override fun onBackPressed() {
        if (debugOverlay.visibility == View.VISIBLE) {
            debugOverlay.visibility = View.GONE
            return
        }
        if (webView.canGoBack()) {
            webView.goBack()
        } else {
            @Suppress("DEPRECATION")
            super.onBackPressed()
        }
    }

    override fun onResume() {
        super.onResume()
        webView.onResume()
        goImmersive()
    }

    override fun onPause() {
        super.onPause()
        webView.onPause()
        CookieManager.getInstance().flush()
        flushLogsToServer()
    }

    override fun onDestroy() {
        logFlushHandler.removeCallbacks(logFlushRunnable)
        flushLogsToServer()
        super.onDestroy()
    }

    private fun goImmersive() {
        @Suppress("DEPRECATION")
        window.decorView.systemUiVisibility = (
            View.SYSTEM_UI_FLAG_FULLSCREEN or
            View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
        )
    }

    private fun getNetworkType(): String {
        return try {
            val cm = getSystemService(CONNECTIVITY_SERVICE) as ConnectivityManager
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                val nc = cm.getNetworkCapabilities(cm.activeNetwork)
                when {
                    nc == null -> "NONE"
                    nc.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "WiFi"
                    nc.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> "Cellular"
                    nc.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) -> "Ethernet"
                    else -> "Other"
                }
            } else {
                @Suppress("DEPRECATION")
                cm.activeNetworkInfo?.typeName ?: "NONE"
            }
        } catch (_: Exception) { "Error" }
    }

    // ── Debug Logging ─────────────────────────────────────

    private fun debugLog(tag: String, message: String) {
        Log.d(TAG, "[$tag] $message")

        val ts = SimpleDateFormat("HH:mm:ss.SSS", Locale.getDefault()).format(Date())
        val line = "[$ts][$tag] $message"

        runOnUiThread {
            debugText.append(line + "\n")
            debugOverlay.post { debugOverlay.fullScroll(View.FOCUS_DOWN) }
        }

        synchronized(logBuffer) {
            if (logBuffer.size >= MAX_LOCAL_LOGS) logBuffer.removeAt(0)
            logBuffer.add(JSONObject().apply {
                put("level", when(tag) {
                    "ERROR", "SSL" -> "error"
                    "WARN" -> "warn"
                    "DEBUG" -> "debug"
                    else -> "info"
                })
                put("tag", tag)
                put("message", message)
                put("url", "")
            })
        }
    }

    private fun flushLogsToServer() {
        val entries: List<JSONObject>
        synchronized(logBuffer) {
            if (logBuffer.isEmpty()) return
            entries = ArrayList(logBuffer)
            logBuffer.clear()
        }

        Thread {
            try {
                val body = JSONObject().apply {
                    put("device_id", deviceId)
                    put("device_model", "${Build.MANUFACTURER} ${Build.MODEL}")
                    put("app_version", BuildConfig.VERSION_NAME)
                    put("android_version", Build.VERSION.RELEASE)
                    put("message", "batch")
                    put("logs", JSONArray().apply {
                        entries.forEach { put(it) }
                    })
                }

                val conn = URL(LOG_ENDPOINT).openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.setRequestProperty("Content-Type", "application/json")
                conn.setRequestProperty("Accept", "application/json")
                conn.connectTimeout = 10000
                conn.readTimeout = 10000
                conn.doOutput = true

                OutputStreamWriter(conn.outputStream).use {
                    it.write(body.toString())
                    it.flush()
                }

                val code = conn.responseCode
                val responseBody = try {
                    if (code in 200..299) conn.inputStream.bufferedReader().readText()
                    else conn.errorStream?.bufferedReader()?.readText() ?: "no body"
                } catch (_: Exception) { "read error" }
                conn.disconnect()

                if (code in 200..299) {
                    Log.d(TAG, "Flushed ${entries.size} logs to server (HTTP $code)")
                } else {
                    Log.w(TAG, "Log flush HTTP $code: $responseBody")
                    synchronized(logBuffer) { logBuffer.addAll(0, entries) }
                }
            } catch (e: Exception) {
                Log.w(TAG, "Log flush failed: ${e.javaClass.simpleName}: ${e.message}")
                synchronized(logBuffer) { logBuffer.addAll(0, entries) }
            }
        }.start()
    }

    private fun errorPageHtml(detail: String): String {
        return """
            <!DOCTYPE html>
            <html>
            <head>
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <style>
                    body { font-family: sans-serif; display:flex; align-items:center; justify-content:center;
                           height:100vh; margin:0; background:#f3f4f6; text-align:center; }
                    .c { max-width:500px; padding:40px; }
                    h1 { color:#1e40af; font-size:28px; }
                    p { color:#6b7280; line-height:1.5; }
                    .err { background:#fef2f2; border:1px solid #fecaca; padding:12px; border-radius:8px;
                           font-size:13px; color:#991b1b; margin:16px 0; word-break:break-all; }
                    button { background:#1e40af; color:white; border:none; padding:14px 40px;
                             border-radius:8px; font-size:18px; cursor:pointer; margin-top:16px; }
                </style>
            </head>
            <body>
                <div class="c">
                    <h1>INSA POS</h1>
                    <p>Unable to connect to the server.</p>
                    <div class="err">$detail</div>
                    <p style="font-size:13px">Long-press the WebView to show debug logs.</p>
                    <button onclick="window.location.href='$BASE_URL'">Retry</button>
                </div>
            </body>
            </html>
        """.trimIndent()
    }
}
