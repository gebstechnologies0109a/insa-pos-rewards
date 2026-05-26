package com.insapos.v2

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import android.os.Build
import android.os.Handler
import android.os.Looper

class ConnectivityMonitor(
    context: Context,
    private val onOnline: () -> Unit,
    private val onOffline: () -> Unit
) {
    private val cm = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
    private val handler = Handler(Looper.getMainLooper())
    private var registered = false

    private val callback = object : ConnectivityManager.NetworkCallback() {
        override fun onAvailable(network: Network) {
            handler.post { onOnline() }
        }

        override fun onLost(network: Network) {
            handler.post {
                if (!isConnected()) onOffline()
            }
        }
    }

    fun isConnected(): Boolean {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val net = cm.activeNetwork ?: return false
            val caps = cm.getNetworkCapabilities(net) ?: return false
            return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
        }
        @Suppress("DEPRECATION")
        return cm.activeNetworkInfo?.isConnected == true
    }

    fun start() {
        if (registered) return
        val request = NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build()
        cm.registerNetworkCallback(request, callback)
        registered = true
    }

    fun stop() {
        if (!registered) return
        try {
            cm.unregisterNetworkCallback(callback)
        } catch (_: Exception) { }
        registered = false
    }
}
