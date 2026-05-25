package com.insapos.posapp

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.net.http.SslError
import android.os.Build
import android.os.Bundle
import android.util.Log
import android.view.View
import android.view.WindowManager
import android.webkit.CookieManager
import android.webkit.SslErrorHandler
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.LinearLayout
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity

class MainActivity : AppCompatActivity() {

    companion object {
        private const val TAG = "INSAPOS"
        private const val BASE_URL = "https://insapos.diybizrewards.com"
        private const val POS_URL = "$BASE_URL/pos/cashier"
    }

    private lateinit var webView: WebView
    private lateinit var loadingOverlay: LinearLayout
    private var pageLoaded = false

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        @Suppress("DEPRECATION")
        window.decorView.systemUiVisibility = (
            View.SYSTEM_UI_FLAG_FULLSCREEN or
            View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
        )

        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        loadingOverlay = findViewById(R.id.loadingOverlay)

        // Enable WebView debugging in debug builds
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.KITKAT) {
            WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG)
        }

        // Enable cookies so login sessions persist
        CookieManager.getInstance().apply {
            setAcceptCookie(true)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                setAcceptThirdPartyCookies(webView, true)
            }
        }

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

            // Allow IndexedDB
            @Suppress("DEPRECATION")
            allowUniversalAccessFromFileURLs = true

            // User agent so the server knows it's the POS app
            userAgentString = "$userAgentString INSAPOS/1.0"
        }

        webView.webViewClient = object : WebViewClient() {

            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                super.onPageStarted(view, url, favicon)
                Log.d(TAG, "Page started: $url")
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                Log.d(TAG, "Page finished: $url")
                pageLoaded = true
                loadingOverlay.visibility = View.GONE

                // Flush cookies to persistent storage
                CookieManager.getInstance().flush()
            }

            override fun onReceivedError(
                view: WebView?,
                request: WebResourceRequest?,
                error: WebResourceError?
            ) {
                super.onReceivedError(view, request, error)
                val desc = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    error?.description?.toString() ?: "Unknown"
                } else "Unknown"
                Log.e(TAG, "Error loading ${request?.url}: $desc")

                if (request?.isForMainFrame == true) {
                    loadingOverlay.visibility = View.GONE
                    view?.loadData(
                        offlineErrorHtml(desc),
                        "text/html",
                        "UTF-8"
                    )
                }
            }

            @SuppressLint("WebViewClientOnReceivedSslError")
            override fun onReceivedSslError(
                view: WebView?,
                handler: SslErrorHandler?,
                error: SslError?
            ) {
                Log.w(TAG, "SSL error: ${error?.primaryError} on ${error?.url}")
                // Accept SSL for our own domain (handles Let's Encrypt on older devices)
                if (error?.url?.contains("insapos.diybizrewards.com") == true) {
                    handler?.proceed()
                } else {
                    handler?.cancel()
                }
            }

            override fun shouldOverrideUrlLoading(
                view: WebView?,
                request: WebResourceRequest?
            ): Boolean {
                val url = request?.url?.toString() ?: return false
                Log.d(TAG, "Navigation: $url")
                // Keep all our domain + localhost (INSABuddy) navigation in the WebView
                if (url.contains("insapos.diybizrewards.com") ||
                    url.startsWith("http://127.0.0.1") ||
                    url.startsWith("http://localhost")
                ) {
                    return false
                }
                return true
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                super.onProgressChanged(view, newProgress)
                Log.d(TAG, "Loading: $newProgress%")
            }
        }

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState)
        } else {
            // Start at root — the server will redirect to login if not authenticated,
            // and after login the user navigates to POS
            Log.d(TAG, "Loading: $BASE_URL")
            webView.loadUrl(BASE_URL)
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    @Deprecated("Use OnBackPressedDispatcher", ReplaceWith("onBackPressedDispatcher"))
    override fun onBackPressed() {
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
        @Suppress("DEPRECATION")
        window.decorView.systemUiVisibility = (
            View.SYSTEM_UI_FLAG_FULLSCREEN or
            View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
        )
    }

    override fun onPause() {
        super.onPause()
        webView.onPause()
        CookieManager.getInstance().flush()
    }

    private fun offlineErrorHtml(errorDetail: String = ""): String {
        val detail = if (errorDetail.isNotEmpty()) "<p style='font-size:12px;color:#9ca3af;margin-top:8px'>Error: $errorDetail</p>" else ""
        return """
            <!DOCTYPE html>
            <html>
            <head>
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, sans-serif;
                        display: flex; align-items: center; justify-content: center;
                        height: 100vh; margin: 0; background: #f3f4f6;
                        text-align: center;
                    }
                    .container { max-width: 500px; padding: 40px; }
                    h1 { color: #1e40af; font-size: 28px; margin-bottom: 8px; }
                    p { color: #6b7280; margin: 12px 0; line-height: 1.5; }
                    button {
                        background: #1e40af; color: white; border: none;
                        padding: 14px 40px; border-radius: 8px; font-size: 18px;
                        cursor: pointer; margin-top: 20px;
                    }
                    button:active { background: #1e3a8a; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>INSA POS</h1>
                    <p>Unable to connect to the server.<br>
                    Please check your internet connection and try again.</p>
                    $detail
                    <button onclick="window.location.href='$BASE_URL'">Retry Connection</button>
                </div>
            </body>
            </html>
        """.trimIndent()
    }
}
