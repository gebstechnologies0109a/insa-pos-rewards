package com.insapos.v2

import android.app.Presentation
import android.content.Context
import android.os.Bundle
import android.util.Log
import android.view.Display
import android.webkit.WebSettings
import android.webkit.WebView
import org.json.JSONObject

class CustomerDisplayPresentation(
    context: Context,
    display: Display,
    private val manager: CustomerDisplayManager,
    private val bridge: CustomerDisplayBridge,
) : Presentation(context, display) {

    companion object {
        private const val TAG = "INSAPOSCustomerDisplay"
        private const val ASSET_URL = "file:///android_asset/customer-display/index.html"
    }

    private lateinit var webView: WebView
    private var pendingPayload: JSONObject? = null
    private var pageReady = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.presentation_customer_display)
        webView = findViewById(R.id.customerDisplayWebView)
        setupWebView()
        webView.loadUrl(ASSET_URL)
    }

    private fun setupWebView() {
        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            allowFileAccess = true
            allowContentAccess = true
            mediaPlaybackRequiresUserGesture = false
            cacheMode = WebSettings.LOAD_DEFAULT
        }
        webView.addJavascriptInterface(bridge, "INSAPOS_CD")
        webView.webViewClient = object : android.webkit.WebViewClient() {
            override fun onPageFinished(view: WebView?, url: String?) {
                pageReady = true
                reloadSettings()
                pendingPayload?.let { deliverCart(it) }
            }
        }
    }

    fun render(payload: JSONObject) {
        pendingPayload = payload
        if (!::webView.isInitialized || !pageReady) return
        deliverCart(payload)
    }

    fun reloadSettings() {
        if (!::webView.isInitialized || !pageReady) return
        webView.evaluateJavascript("if (window.loadSettings) loadSettings();", null)
    }

    private fun deliverCart(payload: JSONObject) {
        val json = payload.toString()
            .replace("\\", "\\\\")
            .replace("'", "\\'")
        webView.evaluateJavascript(
            "if (window.updateCustomerDisplayCart) updateCustomerDisplayCart('$json');",
            null,
        )
    }

    override fun onStop() {
        try {
            webView.destroy()
        } catch (t: Throwable) {
            Log.w(TAG, "WebView destroy: ${t.message}")
        }
        super.onStop()
    }
}
