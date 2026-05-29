package com.insapos.v2

import android.util.Log
import android.webkit.JavascriptInterface
import com.insapos.v2.db.OfflineDatabase
import com.insapos.v2.printers.PrinterSettings
import com.insapos.v2.printers.PrinterType
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID
import java.util.concurrent.Callable
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit
import java.util.concurrent.TimeoutException

class AndroidBridge(private val activity: MainActivity) {

    companion object {
        private const val TAG = "INSAPOSv3Bridge"
        const val BRIDGE_NAME = "INSAPOS"
        private const val LOCAL_HTTP_TIMEOUT_SEC = 8L
        private const val SALE_HTTP_TIMEOUT_SEC = 25L
    }

    private val session by lazy { SessionManager(activity) }
    private val httpExecutor = Executors.newSingleThreadExecutor { r ->
        Thread(r, "insapos-bridge-http").apply { isDaemon = true }
    }
    private val saleExecutor = Executors.newSingleThreadExecutor { r ->
        Thread(r, "insapos-bridge-sale").apply { isDaemon = true }
    }

    private fun activityAlive(): Boolean =
        !activity.isFinishing && !activity.isDestroyed

    private fun unavailable(): String =
        JSONObject().put("ok", false).put("error", "Activity not available").toString()

    private fun safeBridge(block: () -> String): String {
        return try {
            if (!activityAlive()) return unavailable()
            block()
        } catch (t: Throwable) {
            Log.e(TAG, "Bridge error", t)
            JSONObject().put("ok", false).put("error", t.message ?: "error").toString()
        }
    }

    @JavascriptInterface
    fun getDeviceInfo(): String = safeBridge {
        DeviceInfo.toJsonString(activity)
    }

    @JavascriptInterface
    fun notifySuperAdminStatus(isSuperAdmin: Boolean) {
        if (!activityAlive()) return
        activity.runOnUiThread {
            if (activityAlive()) activity.setSuperAdminFromWeb(isSuperAdmin)
        }
    }

    @JavascriptInterface
    fun openPosMode() {
        if (!activityAlive()) return
        activity.runOnUiThread {
            if (activityAlive()) activity.openPosMode()
        }
    }

    @JavascriptInterface
    fun openSuperAdminPanel() {
        if (!activityAlive()) return
        activity.runOnUiThread {
            if (activityAlive()) activity.openSuperAdminPanel()
        }
    }

    @JavascriptInterface
    fun openPosSettings() {
        if (!activityAlive()) return
        activity.runOnUiThread {
            if (activityAlive()) activity.openPosSettings()
        }
    }

    @JavascriptInterface
    fun openPrinterSettings() {
        if (!activityAlive()) return
        activity.runOnUiThread {
            if (activityAlive()) activity.openPrinterSettings()
        }
    }

    @JavascriptInterface
    fun getAppVersion(): String = BuildConfig.VERSION_NAME

    @JavascriptInterface
    fun getDeviceFingerprint(): String = safeBridge {
        DeviceFingerprint.get(activity)
    }

    @JavascriptInterface
    fun getTerminalId(): String = session.terminalSessionId ?: ""

    @JavascriptInterface
    fun setTerminalSessionId(sessionId: String?) {
        session.terminalSessionId = sessionId?.takeIf { it.isNotBlank() }
    }

    @JavascriptInterface
    fun setBranchId(branchId: Int) {
        if (branchId > 0) {
            session.branchId = branchId
            if (!activityAlive()) return
            activity.onBranchIdSetFromWeb(branchId)
        }
    }

    @JavascriptInterface
    fun isDebug(): Boolean = BuildConfig.DEBUG

    @JavascriptInterface
    fun printReceipt(data: String): String = safeBridge {
        val svc = activity.posService ?: return@safeBridge serviceBindingError()
        val pm = svc.waitForPrinterManager(15_000)
        if (pm != null) {
            return@safeBridge printViaService(svc, data)
        }
        httpPostJson("/print", data, SALE_HTTP_TIMEOUT_SEC)
    }

    @JavascriptInterface
    fun selectPrinter(type: String, name: String): String = safeBridge {
        val svc = activity.posService ?: return@safeBridge serviceBindingError()
        val pm = svc.waitForPrinterManager(15_000)
            ?: return@safeBridge printerInitializingError()
        val normType = PrinterType.normalize(type)
        val (ok, err) = pm.selectByTypeAndNameWithMessage(normType, name)
        if (ok) {
            val active = pm.getActivePrinter()
            return@safeBridge JSONObject().apply {
                put("ok", true)
                put("success", true)
                put("selected", name)
                put("name", active?.name ?: name)
                put("type", active?.type ?: normType)
                put("connected", true)
            }.toString()
        }
        return@safeBridge JSONObject().put("ok", false).put("error", err ?: "Could not connect to $name").toString()
    }

    @JavascriptInterface
    fun testPrint(type: String, name: String): String = safeBridge {
        val svc = activity.posService ?: return@safeBridge serviceBindingError()
        val pm = svc.waitForPrinterManager(15_000)
            ?: return@safeBridge printerInitializingError()
        val normType = PrinterType.normalize(type)
        val (printer, ensureErr) = pm.ensureActivePrinter(
            normType.ifBlank { null },
            name.ifBlank { null }
        )
        if (printer == null) {
            return@safeBridge JSONObject().put("ok", false).put("error", ensureErr ?: "No printer connected").toString()
        }
        if (!printer.isConnected() && !printer.connect()) {
            return@safeBridge JSONObject().put("ok", false).put("error", "Printer disconnected").toString()
        }
        val db = svc.offlineDb
        val layout = PrinterSettings(db).layout()
        val text = buildTestPrintText(layout)
        val ok = pm.printText(text, layout)
        if (ok) {
            return@safeBridge JSONObject().apply {
                put("ok", true)
                put("success", true)
                put("printed", true)
                put("name", printer.name)
                put("type", printer.type)
            }.toString()
        }
        return@safeBridge JSONObject().put("ok", false).put("error", "Print failed on ${printer.name}").toString()
    }

    @JavascriptInterface
    fun openDrawer(): String = safeBridge { httpPostJson("/drawer/open", null) }

    @JavascriptInterface
    fun scanBarcode(): String = safeBridge { httpGet("/scan", 30_000, 30_000) }

    @JavascriptInterface
    fun getPrinterStatus(): String = safeBridge { httpGet("/printer/status", 3000, 3000) }

    /** Bonded BT + USB + built-in printers for settings UI (same as GET /printer/list). */
    @JavascriptInterface
    fun listPrinters(): String = safeBridge {
        httpGet("/printer/list?bluetooth=1", 5000, 15_000)
    }

    @JavascriptInterface
    fun getServicePort(): Int = PosLocalServer.PORT

    @JavascriptInterface
    fun isOfflineCapable(): Boolean = true

    @JavascriptInterface
    fun getOfflineStats(): String = safeBridge { httpGet("/offline/stats", 3000, 3000) }

    @JavascriptInterface
    fun getSyncStatus(): String = safeBridge { httpGet("/offline/sync/status", 3000, 3000) }

    @JavascriptInterface
    fun getCatalogImportStatus(): String = safeBridge {
        activity.posService?.syncEngine?.getCatalogImportJson()?.toString()
            ?: JSONObject().put("ok", true).put("state", "idle").put("progress", 0).toString()
    }

    @JavascriptInterface
    fun triggerSync(): String = prefetchCatalog()

    @JavascriptInterface
    fun prefetchCatalog(): String = safeBridge {
        val branchId = session.branchId
        if (branchId != null && branchId > 0) {
            activity.onBranchIdSetFromWeb(branchId)
            activity.posService?.syncEngine?.forceCatalogRefresh()
        } else {
            httpPostJson("/local/sync/now", null)
        }
        JSONObject().put("ok", true).put("triggered", true).toString()
    }

    @JavascriptInterface
    fun setScanInputFocused(focused: Boolean) {
        if (!activityAlive()) return
        activity.notifyScanInputFocused(focused)
        if (focused) {
            activity.posService?.syncEngine?.suppressCatalogPull()
        }
    }

    @JavascriptInterface
    fun setCashierId(cashierId: Int) {
        if (cashierId > 0) session.cashierId = cashierId
    }

    @JavascriptInterface
    fun getLocalProducts(query: String?): String =
        getLocalProductsPage(query, 0, OfflineDatabase.DEFAULT_PRODUCT_PAGE_SIZE, 0)

    @JavascriptInterface
    fun getLocalProductsPage(query: String?, offset: Int, limit: Int, categoryId: Int): String = safeBridge {
        activity.posService?.syncEngine?.suppressCatalogPull(30_000L)
        val safeLimit = limit.coerceIn(1, OfflineDatabase.MAX_PRODUCT_PAGE_SIZE)
        val safeOffset = offset.coerceAtLeast(0)
        val base = "/local/products?limit=$safeLimit&offset=$safeOffset"
        val withCat = if (categoryId > 0) "$base&category_id=$categoryId" else base
        val path = if (!query.isNullOrBlank()) {
            "$withCat&q=${java.net.URLEncoder.encode(query, "UTF-8")}"
        } else {
            withCat
        }
        httpGet(path, 5000, 15_000)
    }

    @JavascriptInterface
    fun getLocalCategories(): String = safeBridge { httpGet("/local/categories", 3000, 5000) }

    @JavascriptInterface
    fun searchProducts(query: String?, limit: Int): String = safeBridge {
        activity.posService?.syncEngine?.suppressCatalogPull(30_000L)
        val db = activity.posService?.offlineDb
        if (db == null) {
            unavailable()
        } else {
            val q = query?.trim().orEmpty()
            if (q.isEmpty()) {
                JSONObject().put("ok", true).put("products", JSONArray()).put("count", 0).toString()
            } else {
                val products = db.searchProducts(q, limit.coerceIn(1, 200))
                JSONObject().put("ok", true).put("products", products).put("count", products.length()).toString()
            }
        }
    }

    @JavascriptInterface
    fun getProductByBarcode(barcode: String?): String = safeBridge {
        val db = activity.posService?.offlineDb
        if (db == null) {
            unavailable()
        } else {
            val code = barcode?.trim().orEmpty()
            if (code.isEmpty()) {
                JSONObject().put("ok", false).put("error", "Barcode required").toString()
            } else {
                val product = db.getProductByBarcode(code)
                JSONObject().put("ok", product != null).put("product", product ?: JSONObject.NULL).toString()
            }
        }
    }

    @JavascriptInterface
    fun getProductById(productId: Int): String = safeBridge {
        val db = activity.posService?.offlineDb
        if (db == null) {
            unavailable()
        } else if (productId <= 0) {
            JSONObject().put("ok", false).put("error", "Invalid product id").toString()
        } else {
            val product = db.getProductByServerId(productId)
            JSONObject().put("ok", product != null).put("product", product ?: JSONObject.NULL).toString()
        }
    }

    @JavascriptInterface
    fun getLocalInventory(): String = safeBridge { httpGet("/local/inventory", 5000, 15_000) }

    @JavascriptInterface
    fun getLocalCustomers(): String = safeBridge { httpGet("/local/customers", 5000, 15_000) }

    @JavascriptInterface
    fun createLocalSale(jsonPayload: String): String {
        if (!activityAlive()) return unavailable()
        val async = try {
            JSONObject(jsonPayload).optBoolean("async", false)
        } catch (_: Exception) {
            false
        }
        if (!async) {
            return safeBridge { runSaleBlocking(jsonPayload) }
        }
        val requestId = UUID.randomUUID().toString()
        saleExecutor.execute {
            activity.dispatchLocalSaleResult(requestId, runSaleBlocking(jsonPayload))
        }
        return JSONObject()
            .put("ok", true)
            .put("pending", true)
            .put("request_id", requestId)
            .toString()
    }

    /** Runs sale + optional auto-print on [saleExecutor]; safe to call from bridge or async callback. */
    private fun runSaleBlocking(jsonPayload: String): String {
        val syncEngine = activity.posService?.syncEngine
        syncEngine?.saleInProgress = true
        return try {
            if (Thread.currentThread().name.startsWith("insapos-bridge-sale")) {
                executeCreateLocalSale(jsonPayload)
            } else {
                saleExecutor.submit(Callable { executeCreateLocalSale(jsonPayload) })
                    .get(SALE_HTTP_TIMEOUT_SEC, TimeUnit.SECONDS)
                    ?: JSONObject().put("ok", false).put("error", "No sale result").toString()
            }
        } catch (e: TimeoutException) {
            Log.e(TAG, "createLocalSale timed out", e)
            JSONObject().put("ok", false).put("error", "Sale timed out").toString()
        } catch (t: Throwable) {
            Log.e(TAG, "createLocalSale failed", t)
            JSONObject().put("ok", false).put("error", t.message ?: "error").toString()
        } finally {
            syncEngine?.saleInProgress = false
        }
    }

    private fun executeCreateLocalSale(jsonPayload: String): String {
        val payload = JSONObject(jsonPayload)
        val autoPrint = payload.optBoolean("auto_print", true)
        val engine = activity.posService?.posEngine
        val resultJson = if (engine != null) {
            engine.createSale(payload).toString()
        } else {
            runOnHttpThread(SALE_HTTP_TIMEOUT_SEC) {
                httpPostJsonBlocking("/local/sale", jsonPayload)
            }
        }
        if (!autoPrint) return resultJson
        return maybeAutoPrintReceipt(resultJson)
    }

    private fun maybeAutoPrintReceipt(resultJson: String): String {
        return try {
            val result = JSONObject(resultJson)
            if (!result.optBoolean("ok", false)) return resultJson
            val receipt = result.optJSONObject("receipt")
            val text = receipt?.optString("text", "").orEmpty()
            if (text.isBlank()) {
                Log.w(TAG, "auto_print skipped: empty receipt text")
                return markSalePrintFlags(result, printed = false)
            }
            val svc = activity.posService ?: return markSalePrintFlags(result, printed = false)
            svc.requestPrinterManager()
            val layout = PrinterSettings(svc.offlineDb).layout()
            var printed = false
            var lastErr: String? = "Printer not ready"
            for (attempt in 1..3) {
                val pm = svc.waitForPrinterManager(15_000)
                if (pm == null) {
                    Log.w(TAG, "auto_print attempt $attempt: PrinterManager not ready")
                    Thread.sleep(400L * attempt)
                    continue
                }
                val (ok, err) = pm.printTextReliable(text, layout)
                if (ok) {
                    printed = true
                    lastErr = null
                    Log.i(TAG, "auto_print succeeded on attempt $attempt")
                    break
                }
                lastErr = err
                Log.w(TAG, "auto_print attempt $attempt failed: $err")
                Thread.sleep(400L * attempt)
            }
            if (!printed) Log.e(TAG, "auto_print failed after retries: $lastErr")
            markSalePrintFlags(result, printed, lastErr)
        } catch (t: Throwable) {
            Log.w(TAG, "Auto-print after sale failed", t)
            resultJson
        }
    }

    private fun markSalePrintFlags(
        result: JSONObject,
        printed: Boolean,
        printError: String? = null,
    ): String {
        result.put("printed", printed)
        result.put("already_printed", printed)
        if (!printed && !printError.isNullOrBlank()) result.put("print_error", printError)
        return result.toString()
    }

    @JavascriptInterface
    fun openLocalShift(jsonPayload: String): String = safeBridge {
        activity.posService?.ensureOfflineReady()
        val json = JSONObject(jsonPayload)
        val cashierId = json.optInt("cashier_id", session.cashierId ?: 0)
        val branchId = json.optInt("branch_id", session.branchId ?: 0)
        val openingCash = json.optDouble("opening_cash", 0.0)
        val result = activity.posService?.posEngine?.openShift(cashierId, branchId, openingCash)
            ?: JSONObject(httpPostJsonBlocking("/local/shift/open", jsonPayload))
        activity.posService?.syncEngine?.suppressCatalogPull(60_000L)
        result.toString()
    }

    @JavascriptInterface
    fun closeLocalShift(jsonPayload: String): String = safeBridge {
        activity.posService?.ensureOfflineReady()
        val json = JSONObject(jsonPayload)
        val closingCash = json.optDouble("closing_cash", 0.0)
        val result = activity.posService?.posEngine?.closeShift(closingCash)
            ?: JSONObject(httpPostJsonBlocking("/local/shift/close", jsonPayload))
        result.toString()
    }

    @JavascriptInterface
    fun getLocalShiftStatus(): String = safeBridge {
        activity.posService?.ensureOfflineReady()
        val engine = activity.posService?.posEngine
        if (engine != null) {
            engine.getShiftStatus().toString()
        } else {
            httpGetBlocking("/local/shift/status", 2000, 3000)
        }
    }

    @JavascriptInterface
    fun getShiftSalesTotal(): String = safeBridge {
        activity.posService?.ensureOfflineReady()
        val engine = activity.posService?.posEngine
            ?: return@safeBridge JSONObject().put("ok", false).put("error", "POS engine not ready").toString()
        engine.getShiftSalesTotal().toString()
    }

    @JavascriptInterface
    fun getLocalXReading(): String = safeBridge {
        activity.posService?.ensureOfflineReady()
        val engine = activity.posService?.posEngine
            ?: return@safeBridge JSONObject().put("ok", false).put("error", "POS engine not ready").toString()
        val cashierId = session.cashierId
            ?: activity.posService?.offlineDb?.getSetting("cashier_id")?.toIntOrNull()
            ?: activity.posService?.offlineDb?.getActiveShift()?.optInt("cashier_id", 0)
            ?: 0
        engine.getLocalXReading(cashierId).toString()
    }

    @JavascriptInterface
    fun getLocalReceipt(localId: String): String = safeBridge {
        httpGet(
            "/local/receipt?local_id=${java.net.URLEncoder.encode(localId, "UTF-8")}",
            5000,
            15_000
        )
    }

    @JavascriptInterface
    fun triggerLocalSync(): String = safeBridge {
        activity.posService?.syncEngine?.syncNow()
        JSONObject().put("ok", true).put("triggered", true).toString()
    }

    @JavascriptInterface
    fun getCustomerDisplayStatus(): String = safeBridge {
        activity.customerDisplayManager.getStatusJson().toString()
    }

    @JavascriptInterface
    fun setCustomerDisplayEnabled(enabled: Boolean): String = safeBridge {
        activity.customerDisplayManager.enabled = enabled
        activity.customerDisplayManager.getStatusJson().put("saved", true).toString()
    }

    @JavascriptInterface
    fun updateCustomerDisplay(jsonPayload: String): String = safeBridge {
        activity.customerDisplayManager.update(jsonPayload).toString()
    }

    @JavascriptInterface
    fun testCustomerDisplay(): String = safeBridge {
        activity.customerDisplayManager.testDisplay().toString()
    }

    @JavascriptInterface
    fun getCustomerDisplaySettings(): String = safeBridge {
        activity.customerDisplayManager.getSettingsJson().toString()
    }

    @JavascriptInterface
    fun reloadCustomerDisplaySettings(): String = safeBridge {
        activity.customerDisplayManager.onSettingsSynced()
        JSONObject().put("ok", true).put("reloaded", true).toString()
    }

    @JavascriptInterface
    fun updateCustomerDisplayCart(cartJson: String): String = safeBridge {
        activity.customerDisplayManager.update(cartJson).toString()
    }

    @JavascriptInterface
    fun scanHardware(): String = safeBridge {
        HardwareDetector.scanAll(activity).toString()
    }

    @JavascriptInterface
    fun setAllowMinimize(enabled: Boolean): String = safeBridge {
        activity.runOnUiThread {
            if (activityAlive()) activity.setAllowMinimizeEnabled(enabled)
        }
        JSONObject().put("ok", true).put("allow_minimize", enabled).toString()
    }

    @JavascriptInterface
    fun getPosSettings(): String = safeBridge {
        val mgr = activity.customerDisplayManager
        val cdStatus = mgr.getStatusJson()
        val device = DeviceInfo.toJson(activity)
        val db = activity.posService?.offlineDb
        val lastSync = db?.getSetting("catalog_synced_at")
            ?: db?.getSetting("catalog_last_sync")
            ?: ""
        val productCount = db?.getProductCount() ?: 0
        val layout = PrinterSettings(db).layout()
        JSONObject().apply {
            put("ok", true)
            put("app_version", BuildConfig.VERSION_NAME)
            put("version_code", BuildConfig.VERSION_CODE)
            put("online", activity.isNetworkOnline())
            put("network_online", activity.isNetworkOnline())
            put("customer_display", cdStatus)
            put("device", device)
            put("last_sync_at", lastSync)
            put("products_cached", productCount)
            put("paper_size", layout.paperSize)
            put("font_mode", layout.fontMode)
            put("char_width", layout.charWidth)
            put("dot_width", layout.dotWidth)
            put("allow_minimize", session.allowMinimize)
        }.toString()
    }

    private fun printViaService(service: PosService, data: String): String {
        val pm = service.printerManager ?: service.waitForPrinterManager(15_000)
            ?: return printerInitializingError()
        val json = try {
            JSONObject(data)
        } catch (_: Exception) {
            JSONObject().put("text", data)
        }
        val text = json.optString("text", "")
        val raw = json.optJSONArray("raw")
        val name = json.optString("name", "").ifBlank { json.optString("printer", "") }
        val type = PrinterType.normalize(
            json.optString("type", "").ifBlank { json.optString("printer_type", "") }
        )
        val layout = PrinterSettings(service.offlineDb).layout()

        if (raw != null) {
            val (printer, ensureErr) = pm.ensureActivePrinter(type.ifBlank { null }, name.ifBlank { null })
            if (printer == null) {
                return JSONObject().put("ok", false).put("error", ensureErr ?: "No printer connected").toString()
            }
            val bytes = ByteArray(raw.length()) { raw.getInt(it).toByte() }
            val (ok, err) = pm.printRawReliable(bytes)
            return if (ok) {
                JSONObject().put("ok", true).put("printed", true).toString()
            } else {
                JSONObject().put("ok", false).put("error", err ?: "Print failed on ${printer.name}").toString()
            }
        }

        if (text.isBlank()) {
            return JSONObject().put("ok", false).put("error", "No print data").toString()
        }

        if (type.isNotBlank() && name.isNotBlank()) {
            pm.ensureActivePrinter(type, name)
        }

        val (ok, err) = pm.printTextReliable(text, layout)
        return if (ok) {
            JSONObject().put("ok", true).put("printed", true).toString()
        } else {
            JSONObject().put("ok", false).put("error", err ?: "Print failed").toString()
        }
    }

    private fun buildTestPrintText(layout: com.insapos.v2.printers.PrinterConfig.Layout): String {
        val div = com.insapos.v2.printers.PrinterConfig.divider(layout.charWidth)
        val title = com.insapos.v2.printers.PrinterConfig.centered("INSAPOS v${BuildConfig.VERSION_NAME}", layout.charWidth)
        val subtitle = com.insapos.v2.printers.PrinterConfig.centered("Test Print", layout.charWidth)
        val paperLine = "Paper: ${layout.paperSize} · Font: ${layout.fontMode}".take(layout.charWidth)
        val widthLine = "Width: ${layout.charWidth} chars / ${layout.dotWidth} dots".take(layout.charWidth)
        return "$div\n$title\n$subtitle\n$div\n" +
            "Printer is working correctly!\n" +
            "$paperLine\n$widthLine\n" +
            "Date: ${java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.getDefault()).format(java.util.Date())}\n" +
            "Device: ${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}\n" +
            "$div\n"
    }

    private fun serviceBindingError(): String =
        JSONObject().put("ok", false).put("error", "Hardware service not bound").put("reason", "initializing").toString()

    private fun printerInitializingError(): String =
        JSONObject()
            .put("ok", false)
            .put("error", "Initializing printer — please wait a moment and try again")
            .put("reason", "initializing")
            .toString()

    @JavascriptInterface
    fun log(level: String, message: String) {
        when (level.lowercase()) {
            "error" -> Log.e(TAG, message)
            "warn" -> Log.w(TAG, message)
            "debug" -> Log.d(TAG, message)
            else -> Log.i(TAG, message)
        }
    }

    private fun httpGet(path: String, connectMs: Int, readMs: Int): String =
        runOnHttpThread { httpGetBlocking(path, connectMs, readMs) }

    private fun httpPostJson(path: String, body: String?, timeoutSec: Long = LOCAL_HTTP_TIMEOUT_SEC): String =
        runOnHttpThread(timeoutSec) { httpPostJsonBlocking(path, body) }

    private fun runOnHttpThread(timeoutSec: Long = LOCAL_HTTP_TIMEOUT_SEC, block: () -> String): String {
        val future = httpExecutor.submit(block)
        return try {
            future.get(timeoutSec, TimeUnit.SECONDS)
        } catch (e: TimeoutException) {
            future.cancel(true)
            Log.w(TAG, "Local HTTP timed out")
            JSONObject().put("ok", false).put("error", "timeout").toString()
        } catch (e: Exception) {
            Log.w(TAG, "Local HTTP failed: ${e.message}")
            JSONObject().put("ok", false).put("error", e.message ?: "error").toString()
        }
    }

    private fun httpGetBlocking(path: String, connectMs: Int, readMs: Int): String {
        val url = "http://127.0.0.1:${PosLocalServer.PORT}$path"
        val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
        conn.requestMethod = "GET"
        conn.connectTimeout = connectMs
        conn.readTimeout = readMs
        return conn.inputStream.bufferedReader().use { it.readText() }.also { conn.disconnect() }
    }

    private fun httpPostJsonBlocking(path: String, body: String?): String {
        val url = "http://127.0.0.1:${PosLocalServer.PORT}$path"
        val conn = java.net.URL(url).openConnection() as java.net.HttpURLConnection
        conn.requestMethod = "POST"
        conn.setRequestProperty("Content-Type", "application/json")
        conn.connectTimeout = 5000
        conn.readTimeout = 15_000
        if (body != null) {
            conn.doOutput = true
            conn.outputStream.bufferedWriter().use { it.write(body) }
        }
        return conn.inputStream.bufferedReader().use { it.readText() }.also { conn.disconnect() }
    }
}
