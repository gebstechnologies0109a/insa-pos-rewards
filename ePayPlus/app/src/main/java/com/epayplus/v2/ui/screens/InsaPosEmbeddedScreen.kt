package com.epayplus.v2.ui.screens

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.net.Uri
import android.net.http.SslError
import android.os.Build
import android.view.ViewGroup
import android.webkit.CookieManager
import android.webkit.SslErrorHandler
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.OpenInNew
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.viewinterop.AndroidView
import androidx.navigation.NavController
import com.epayplus.v2.BuildConfig
import com.epayplus.v2.ui.theme.EPayGreen
import com.epayplus.v2.util.InsaPosLauncher
import com.epayplus.v2.util.InsaPosWebBridge

/**
 * Full-screen INSA retail POS (cashier) embedded via WebView on the INSA product host.
 *
 * Session/auth: ePay Plus login (retailer token) does not authenticate INSA cashier.
 * Users may need to log in on insapos.diybizrewards.com the first time; cookies persist in WebView.
 */
@OptIn(ExperimentalMaterial3Api::class)
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun InsaPosEmbeddedScreen(navController: NavController) {
    val context = LocalContext.current
    val cashierUrl = BuildConfig.INSA_POS_CASHIER_URL
    val bridge = remember { InsaPosWebBridge(context) }

    var webViewRef by remember { mutableStateOf<WebView?>(null) }
    var isLoading by remember { mutableStateOf(true) }
    var loadProgress by remember { mutableIntStateOf(0) }

    BackHandler {
        val webView = webViewRef
        if (webView != null && webView.canGoBack()) {
            webView.goBack()
        } else {
            navController.popBackStack()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("INSA POS", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = {
                        val webView = webViewRef
                        if (webView != null && webView.canGoBack()) {
                            webView.goBack()
                        } else {
                            navController.popBackStack()
                        }
                    }) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                actions = {
                    IconButton(onClick = { InsaPosLauncher.openWebCashier(context) }) {
                        Icon(Icons.Default.OpenInNew, contentDescription = "Open in browser")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = EPayGreen,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White,
                    actionIconContentColor = Color.White
                )
            )
        }
    ) { padding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
        ) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { ctx ->
                    WebView(ctx).apply {
                        layoutParams = ViewGroup.LayoutParams(
                            ViewGroup.LayoutParams.MATCH_PARENT,
                            ViewGroup.LayoutParams.MATCH_PARENT
                        )

                        val cookieManager = CookieManager.getInstance()
                        cookieManager.setAcceptCookie(true)
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                            cookieManager.setAcceptThirdPartyCookies(this, true)
                        }

                        settings.apply {
                            javaScriptEnabled = true
                            domStorageEnabled = true
                            databaseEnabled = true
                            allowFileAccess = false
                            useWideViewPort = true
                            loadWithOverviewMode = true
                            setSupportZoom(false)
                            builtInZoomControls = false
                            displayZoomControls = false
                            javaScriptCanOpenWindowsAutomatically = true
                            mixedContentMode = android.webkit.WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE
                            val appUa =
                                "ePayPlus/${BuildConfig.VERSION_NAME} Android/${Build.VERSION.RELEASE}"
                            userAgentString = "$userAgentString $appUa"
                        }

                        addJavascriptInterface(bridge, InsaPosWebBridge.BRIDGE_NAME)

                        webViewClient = object : WebViewClient() {
                            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                                isLoading = true
                            }

                            override fun onPageFinished(view: WebView?, url: String?) {
                                isLoading = false
                                if (url != null && isInsaSuperAdminPath(url)) {
                                    view?.loadUrl(cashierUrl)
                                    return
                                }
                                view?.evaluateJavascript(bridge.injectReadyScript(), null)
                            }

                            override fun onReceivedError(
                                view: WebView?,
                                request: WebResourceRequest?,
                                error: WebResourceError?
                            ) {
                                if (request?.isForMainFrame == true) {
                                    isLoading = false
                                }
                            }

                            override fun onReceivedSslError(
                                view: WebView?,
                                handler: SslErrorHandler?,
                                error: SslError?
                            ) {
                                val host = error?.url?.let {
                                    runCatching { java.net.URL(it).host }.getOrNull()
                                } ?: ""
                                if (host.endsWith("diybizrewards.com")) {
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
                                if (isInsaSuperAdminPath(url)) {
                                    view?.loadUrl(cashierUrl)
                                    return true
                                }
                                return when {
                                    url.startsWith("http://") || url.startsWith("https://") -> false
                                    else -> {
                                        runCatching {
                                            ctx.startActivity(
                                                android.content.Intent(
                                                    android.content.Intent.ACTION_VIEW,
                                                    request.url
                                                )
                                            )
                                        }
                                        true
                                    }
                                }
                            }
                        }

                        webChromeClient = object : WebChromeClient() {
                            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                                loadProgress = newProgress
                                if (newProgress >= 100) isLoading = false
                            }
                        }

                        if (BuildConfig.DEBUG) {
                            WebView.setWebContentsDebuggingEnabled(true)
                        }

                        loadUrl(cashierUrl)
                        webViewRef = this
                    }
                },
                update = { view ->
                    webViewRef = view
                }
            )

            if (isLoading && loadProgress < 100) {
                CircularProgressIndicator(
                    modifier = Modifier.align(Alignment.Center),
                    color = EPayGreen
                )
            }
        }
    }

    DisposableEffect(Unit) {
        onDispose {
            webViewRef?.apply {
                stopLoading()
                destroy()
            }
            webViewRef = null
        }
    }
}

private fun isInsaSuperAdminPath(url: String): Boolean {
    return try {
        Uri.parse(url).path?.contains("/super-admin", ignoreCase = true) == true
    } catch (_: Exception) {
        false
    }
}
