package com.insapos.v2

import android.content.Context
import android.util.Log
import com.insapos.v2.db.OfflineDatabase
import com.insapos.v2.printers.PrinterManager
import com.insapos.v2.sync.SyncEngine
import fi.iki.elonen.NanoHTTPD
import org.json.JSONArray
import org.json.JSONObject

class PosLocalServer(
    private val context: Context,
    private val getPrinterManager: () -> PrinterManager?,
    private val getHidScanner: () -> HidScannerDriver?,
    private val getDatabase: () -> OfflineDatabase?,
    private val getSyncEngine: () -> SyncEngine?,
    private val launchCameraScan: (() -> Unit)? = null
) : NanoHTTPD("127.0.0.1", PORT) {

    companion object {
        const val PORT = 18182
        private const val TAG = "INSAPOSv2Server"
    }

    @Volatile
    var lastCameraScanResult: String? = null

    override fun serve(session: IHTTPSession): Response {
        val uri = session.uri.trimEnd('/')
        val method = session.method

        val headers = mutableMapOf<String, String>()
        headers["Access-Control-Allow-Origin"] = "*"
        headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
        headers["Access-Control-Allow-Headers"] = "Content-Type"

        if (method == Method.OPTIONS) {
            return cors(newFixedLengthResponse(Response.Status.OK, MIME_PLAINTEXT, "OK"), headers)
        }

        return try {
            val resp = when {
                uri == "/ping" -> handlePing()
                uri == "/device/info" -> handleDeviceInfo()
                uri == "/print" && method == Method.POST -> handlePrint(session)
                uri == "/drawer/open" && method == Method.POST -> handleDrawerOpen()
                uri == "/printer/status" -> handlePrinterStatus()
                uri == "/printer/list" -> handlePrinterList()
                uri == "/printer/select" && method == Method.POST -> handlePrinterSelect(session)
                uri == "/scan" -> handleCameraScan()
                uri == "/scan/hid" -> handleHidScan()
                // Offline data endpoints
                uri == "/offline/products" -> handleGetProducts(session)
                uri == "/offline/products/barcode" -> handleProductByBarcode(session)
                uri == "/offline/customers" -> handleGetCustomers()
                uri == "/offline/transaction" && method == Method.POST -> handleSaveTransaction(session)
                uri == "/offline/receipt" && method == Method.POST -> handleSaveReceipt(session)
                uri == "/offline/stats" -> handleOfflineStats()
                uri == "/offline/sync/status" -> handleSyncStatus()
                uri == "/offline/sync/now" && method == Method.POST -> handleSyncNow()
                else -> json404("Unknown endpoint: $uri")
            }
            cors(resp, headers)
        } catch (e: Exception) {
            Log.e(TAG, "Server error on $uri", e)
            cors(jsonError(e.message ?: "Unknown error"), headers)
        }
    }

    private fun handlePing(): Response {
        return jsonOk(JSONObject().put("ok", true).put("app", "INSAPOSv2").put("port", PORT))
    }

    private fun handleDeviceInfo(): Response {
        return jsonOk(DeviceInfo.toJson(context).put("ok", true))
    }

    private fun handlePrint(session: IHTTPSession): Response {
        val body = readBody(session)
        val pm = getPrinterManager()
            ?: return jsonError("Printer service not ready")

        val printer = pm.getActivePrinter()
            ?: return jsonError("No printer connected")

        val json = JSONObject(body)
        val data = json.optString("data", "")
        val raw = json.optJSONArray("raw")

        if (raw != null) {
            val bytes = ByteArray(raw.length()) { raw.getInt(it).toByte() }
            printer.printRaw(bytes)
        } else if (data.isNotBlank()) {
            printer.printText(data)
        } else {
            return jsonError("No print data provided")
        }

        return jsonOk(JSONObject().put("ok", true).put("printed", true))
    }

    private fun handleDrawerOpen(): Response {
        val pm = getPrinterManager()
            ?: return jsonError("Printer service not ready")
        val printer = pm.getActivePrinter()
            ?: return jsonError("No printer connected")
        printer.openDrawer()
        return jsonOk(JSONObject().put("ok", true))
    }

    private fun handlePrinterStatus(): Response {
        val pm = getPrinterManager() ?: return jsonOk(
            JSONObject().put("ok", true).put("connected", false).put("reason", "Service starting")
        )
        val p = pm.getActivePrinter()
        return jsonOk(JSONObject().apply {
            put("ok", true)
            put("connected", p != null)
            put("name", p?.name ?: JSONObject.NULL)
            put("type", p?.type ?: JSONObject.NULL)
        })
    }

    private fun handlePrinterList(): Response {
        val pm = getPrinterManager() ?: return jsonOk(
            JSONObject().put("ok", true).put("printers", JSONArray())
        )
        val list = pm.scanAll()
        val arr = JSONArray()
        for (p in list) {
            arr.put(JSONObject().apply {
                put("name", p.name)
                put("type", p.type)
                put("connected", p.isConnected())
            })
        }
        return jsonOk(JSONObject().put("ok", true).put("printers", arr))
    }

    private fun handlePrinterSelect(session: IHTTPSession): Response {
        val body = readBody(session)
        val json = JSONObject(body)
        val name = json.optString("name", "")
        if (name.isBlank()) return jsonError("Printer name required")

        val pm = getPrinterManager() ?: return jsonError("Service not ready")
        val ok = pm.selectByName(name)
        return jsonOk(JSONObject().put("ok", ok).put("selected", name))
    }

    private fun handleCameraScan(): Response {
        lastCameraScanResult = null
        launchCameraScan?.invoke()
        val timeout = System.currentTimeMillis() + 25000
        while (lastCameraScanResult == null && System.currentTimeMillis() < timeout) {
            Thread.sleep(200)
        }
        val code = lastCameraScanResult
        return jsonOk(JSONObject().apply {
            put("ok", code != null)
            put("code", code ?: JSONObject.NULL)
        })
    }

    private fun handleHidScan(): Response {
        val hid = getHidScanner()
        val barcode = hid?.getLastBarcode() ?: ""
        return jsonOk(JSONObject().apply {
            put("ok", barcode.isNotBlank())
            put("code", barcode)
        })
    }

    // --- Offline data handlers ---

    private fun handleGetProducts(session: IHTTPSession): Response {
        val db = getDatabase() ?: return jsonError("Database not ready")
        val query = session.parms?.get("q")
        val products = if (!query.isNullOrBlank()) db.searchProducts(query) else db.getProducts()
        return jsonOk(JSONObject().put("ok", true).put("products", products).put("count", products.length()))
    }

    private fun handleProductByBarcode(session: IHTTPSession): Response {
        val db = getDatabase() ?: return jsonError("Database not ready")
        val barcode = session.parms?.get("code") ?: return jsonError("Barcode required")
        val product = db.getProductByBarcode(barcode)
        return jsonOk(JSONObject().apply {
            put("ok", product != null)
            put("product", product ?: JSONObject.NULL)
        })
    }

    private fun handleGetCustomers(): Response {
        val db = getDatabase() ?: return jsonError("Database not ready")
        val customers = db.getCustomers()
        return jsonOk(JSONObject().put("ok", true).put("customers", customers).put("count", customers.length()))
    }

    private fun handleSaveTransaction(session: IHTTPSession): Response {
        val db = getDatabase() ?: return jsonError("Database not ready")
        val body = readBody(session)
        val txn = JSONObject(body)
        if (!txn.has("local_id")) {
            txn.put("local_id", java.util.UUID.randomUUID().toString())
        }
        val id = db.saveTransaction(txn)
        db.enqueueSyncAction("push-transaction", "transactions_local", txn.getString("local_id"), txn)
        return jsonOk(JSONObject().put("ok", true).put("local_db_id", id).put("local_id", txn.getString("local_id")))
    }

    private fun handleSaveReceipt(session: IHTTPSession): Response {
        val db = getDatabase() ?: return jsonError("Database not ready")
        val body = readBody(session)
        val json = JSONObject(body)
        val txnId = json.optString("transaction_local_id", "")
        if (txnId.isBlank()) return jsonError("transaction_local_id required")
        db.saveReceipt(txnId, json.optString("json", ""), json.optString("text", ""), json.optString("html", ""))
        return jsonOk(JSONObject().put("ok", true))
    }

    private fun handleOfflineStats(): Response {
        val db = getDatabase() ?: return jsonError("Database not ready")
        val stats = db.getOfflineStats()
        stats.put("ok", true)
        return jsonOk(stats)
    }

    private fun handleSyncStatus(): Response {
        val sync = getSyncEngine()
        val db = getDatabase()
        return jsonOk(JSONObject().apply {
            put("ok", true)
            put("status", sync?.lastSyncStatus?.name ?: "UNKNOWN")
            put("unsynced_count", db?.getUnsyncedCount() ?: 0)
            put("sync_queue_count", db?.getSyncQueueCount() ?: 0)
        })
    }

    private fun handleSyncNow(): Response {
        val sync = getSyncEngine() ?: return jsonError("Sync engine not ready")
        sync.syncNow()
        return jsonOk(JSONObject().put("ok", true).put("triggered", true))
    }

    // --- Helpers ---

    private fun readBody(session: IHTTPSession): String {
        val map = HashMap<String, String>()
        session.parseBody(map)
        return map["postData"] ?: ""
    }

    private fun jsonOk(obj: JSONObject): Response =
        newFixedLengthResponse(Response.Status.OK, "application/json", obj.toString())

    private fun jsonError(msg: String): Response =
        newFixedLengthResponse(
            Response.Status.INTERNAL_ERROR, "application/json",
            JSONObject().put("ok", false).put("error", msg).toString()
        )

    private fun json404(msg: String): Response =
        newFixedLengthResponse(
            Response.Status.NOT_FOUND, "application/json",
            JSONObject().put("ok", false).put("error", msg).toString()
        )

    private fun cors(resp: Response, headers: Map<String, String>): Response {
        headers.forEach { (k, v) -> resp.addHeader(k, v) }
        return resp
    }
}
