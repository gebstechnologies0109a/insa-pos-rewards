package com.insapos.posapp

import android.annotation.SuppressLint
import android.os.Bundle
import android.view.View
import android.view.WindowManager
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.LinearLayout
import androidx.appcompat.app.AppCompatActivity

class MainActivity : AppCompatActivity() {

    companion object {
        private const val POS_URL = "https://insapos.diybizrewards.com/pos/cashier"
    }

    private lateinit var webView: WebView
    private lateinit var loadingOverlay: LinearLayout

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
            mixedContentMode = android.webkit.WebSettings.MIXED_CONTENT_ALWAYS_ALLOW

            @Suppress("DEPRECATION")
            allowUniversalAccessFromFileURLs = true
        }

        webView.webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                loadingOverlay.visibility = View.GONE
            }

            override fun onReceivedError(
                view: WebView?,
                request: WebResourceRequest?,
                error: WebResourceError?
            ) {
                super.onReceivedError(view, request, error)
                if (request?.isForMainFrame == true) {
                    view?.loadData(
                        offlineErrorHtml(),
                        "text/html",
                        "UTF-8"
                    )
                }
            }

            override fun shouldOverrideUrlLoading(
                view: WebView?,
                request: WebResourceRequest?
            ): Boolean {
                val url = request?.url?.toString() ?: return false
                if (url.contains("insapos.diybizrewards.com") || url.startsWith("http://127.0.0.1")) {
                    return false
                }
                return true
            }
        }

        webView.webChromeClient = WebChromeClient()

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState)
        } else {
            webView.loadUrl(POS_URL)
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
        @Suppress("DEPRECATION")
        window.decorView.systemUiVisibility = (
            View.SYSTEM_UI_FLAG_FULLSCREEN or
            View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
        )
    }

    private fun offlineErrorHtml(): String {
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
                    .container { max-width: 400px; padding: 40px; }
                    h1 { color: #1e40af; font-size: 24px; }
                    p { color: #6b7280; margin: 16px 0; }
                    button {
                        background: #1e40af; color: white; border: none;
                        padding: 12px 32px; border-radius: 8px; font-size: 16px;
                        cursor: pointer; margin-top: 16px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>INSA POS</h1>
                    <p>Unable to connect to the server.<br>
                    Please check your internet connection and try again.</p>
                    <p style="font-size: 13px; color: #9ca3af;">
                        If this device was previously used online, your cached data
                        is still available in the browser storage.</p>
                    <button onclick="window.location.href='$POS_URL'">Retry</button>
                </div>
            </body>
            </html>
        """.trimIndent()
    }
}
