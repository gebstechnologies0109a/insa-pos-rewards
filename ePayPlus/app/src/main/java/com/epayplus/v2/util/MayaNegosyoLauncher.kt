package com.epayplus.v2.util

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.core.net.toUri

object MayaNegosyoLauncher {

    const val NEGOSYO_PACKAGE = "com.paymaya.negosyo"
    const val BUSINESS_PACKAGE = "ph.maya.business.android"
    const val NEGOSYO_SPLASH = "com.paymaya.negosyo.splash.SplashActivity"
    const val NEGOSYO_DEEP_LINK = "negosyo://"
    const val NEGOSYO_PLAY_STORE = "market://details?id=$NEGOSYO_PACKAGE"
    const val NEGOSYO_PLAY_STORE_HTTPS =
        "https://play.google.com/store/apps/details?id=$NEGOSYO_PACKAGE"
    const val BUSINESS_PLAY_STORE_HTTPS =
        "https://play.google.com/store/apps/details?id=$BUSINESS_PACKAGE"

    fun launchNegosyo(context: Context): Boolean {
        val pm = context.packageManager

        val splashIntent = Intent(Intent.ACTION_MAIN).apply {
            setClassName(NEGOSYO_PACKAGE, NEGOSYO_SPLASH)
            addCategory(Intent.CATEGORY_LAUNCHER)
            flags = Intent.FLAG_ACTIVITY_NEW_TASK
        }
        if (splashIntent.resolveActivity(pm) != null) {
            return startSafe(context, splashIntent)
        }

        val launchIntent = pm.getLaunchIntentForPackage(NEGOSYO_PACKAGE)
        if (launchIntent != null) {
            launchIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            return startSafe(context, launchIntent)
        }

        val deepLink = Intent(Intent.ACTION_VIEW, NEGOSYO_DEEP_LINK.toUri()).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK
        }
        if (deepLink.resolveActivity(pm) != null) {
            return startSafe(context, deepLink)
        }

        return openPlayStore(context, NEGOSYO_PLAY_STORE, NEGOSYO_PLAY_STORE_HTTPS)
    }

    fun launchBusiness(context: Context): Boolean {
        val pm = context.packageManager
        val launchIntent = pm.getLaunchIntentForPackage(BUSINESS_PACKAGE)
        if (launchIntent != null) {
            launchIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            return startSafe(context, launchIntent)
        }
        return openPlayStore(context, "market://details?id=$BUSINESS_PACKAGE", BUSINESS_PLAY_STORE_HTTPS)
    }

    fun openCheckoutUrl(context: Context, url: String): Boolean {
        val intent = Intent(Intent.ACTION_VIEW, url.toUri()).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK
        }
        return startSafe(context, intent)
    }

    fun isPackageInstalled(context: Context, packageName: String): Boolean {
        return try {
            context.packageManager.getPackageInfo(packageName, 0)
            true
        } catch (_: Exception) {
            false
        }
    }

    private fun openPlayStore(context: Context, marketUri: String, httpsUri: String): Boolean {
        val market = Intent(Intent.ACTION_VIEW, marketUri.toUri()).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK
        }
        if (startSafe(context, market)) return true
        val https = Intent(Intent.ACTION_VIEW, httpsUri.toUri()).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK
        }
        return startSafe(context, https)
    }

    private fun startSafe(context: Context, intent: Intent): Boolean {
        return try {
            context.startActivity(intent)
            true
        } catch (_: ActivityNotFoundException) {
            false
        }
    }
}
