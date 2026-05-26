package com.insapos.insabuddy

import android.content.Context
import android.util.Base64
import android.util.Log
import com.insapos.insabuddy.printers.PrinterManager
import fi.iki.elonen.NanoHTTPD
import org.json.JSONArray
import org.json.JSONObject
import java.io.File

class LocalServer(
    private val context: Context,
    private val printerManager: PrinterManager,
    private val scannerBridge: ScannerBridge,
    private val deviceInfo: DeviceInfo,
    private val getHidScanner: (() -> HidScannerDriver?) = { null }
) : NanoHTTPD(18181) {

    companion object {
        private const val TAG = "LocalServer"
    }

    var onLog: ((String) -> Unit)? = null

    private val cacheDir: File get() = File(context.filesDir, "offline_cache").also { it.mkdirs() }
    private val transactionsFile: File get() = File(cacheDir, "transactions.json")
    private val receiptsFile: File get() = File(cacheDir, "receipts.json")

    override fun serve(session: IHTTPSession): Response {
        val uri = session.uri
        val method = session.method

        log("${method.name} $uri")

        val response = when {
            method == Method.OPTIONS -> corsResponse(newFixedLengthResponse(""))
            uri == "/ping" && method == Method.GET -> handlePing()
            uri == "/print" && method == Method.POST -> handlePrint(session)
            uri == "/drawer/open" && method == Method.POST -> handleDrawerOpen()
            uri == "/scan" && method == Method.POST -> handleScan()
            uri == "/scan/hid" && method == Method.GET -> handleHidScan()
            uri == "/scan/continuous" && method == Method.POST -> handleContinuousScan(session)
            uri == "/device/info" && method == Method.GET -> handleDeviceInfo()
            uri == "/printer/status" && method == Method.GET -> handlePrinterStatus()
            uri == "/printer/list" && method == Method.GET -> handlePrinterList()
            uri == "/printer/select" && method == Method.POST -> handlePrinterSelect(session)
            uri == "/printer/test" && method == Method.POST -> handlePrinterTest()
            uri == "/receipt/save" && method == Method.POST -> handleReceiptSave(session)
            uri == "/transaction/save" && method == Method.POST -> handleTransactionSave(session)
            uri == "/sync/push" && method == Method.POST -> handleSyncPush(session)
            uri == "/sync/pull" && method == Method.GET -> handleSyncPull()
            else -> jsonError(Response.Status.NOT_FOUND, "Endpoint not found: $uri")
        }

        return corsResponse(response)
    }

    private fun handlePing(): Response {
        val json = JSONObject().apply {
            put("status", "ok")
            put("app", "INSABuddy")
            put("version", BuildConfig.VERSION_NAME)
            put("printer_connected", printerManager.currentPrinter?.isConnected() == true)
        }
        return jsonResponse(json)
    }

    private fun handlePrint(session: IHTTPSession): Response {
        return try {
            val body = readBody(session)
            val json = JSONObject(body)

            val success = when {
                json.has("data") -> {
                    val data = Base64.decode(json.getString("data"), Base64.DEFAULT)
                    printerManager.print(data)
                }
                json.has("text") -> {
                    printerManager.printText(json.getString("text"))
                }
                else -> {
                    return jsonError(Response.Status.BAD_REQUEST, "Missing 'data' or 'text' field")
                }
            }

            val result = JSONObject().apply {
                put("success", success)
                put("message", if (success) "Printed successfully" else "Print failed")
            }
            jsonResponse(result)
        } catch (e: Exception) {
            Log.e(TAG, "Print error: ${e.message}")
            jsonError(Response.Status.INTERNAL_ERROR, "Print error: ${e.message}")
        }
    }

    private fun handleDrawerOpen(): Response {
        return try {
            printerManager.openDrawer()
            val json = JSONObject().apply {
                put("success", true)
                put("message", "Drawer pulse sent")
            }
            jsonResponse(json)
        } catch (e: Exception) {
            jsonError(Response.Status.INTERNAL_ERROR, "Drawer error: ${e.message}")
        }
    }

    private fun handleScan(): Response {
        return try {
            val result = scannerBridge.requestScan()
            val json = JSONObject().apply {
                put("success", result != null)
                put("value", result ?: "")
                put("format", scannerBridge.lastFormat ?: "")
            }
            jsonResponse(json)
        } catch (e: Exception) {
            jsonError(Response.Status.INTERNAL_ERROR, "Scan error: ${e.message}")
        }
    }

    private fun handleContinuousScan(session: IHTTPSession): Response {
        return try {
            val body = readBody(session)
            val json = JSONObject(body)
            val enabled = json.optBoolean("enabled", true)

            scannerBridge.setContinuousMode(enabled)
            val result = JSONObject().apply {
                put("success", true)
                put("continuous", enabled)
            }
            jsonResponse(result)
        } catch (e: Exception) {
            jsonError(Response.Status.INTERNAL_ERROR, "Continuous scan error: ${e.message}")
        }
    }

    private fun handleHidScan(): Response {
        val hid = getHidScanner()
        val barcode = hid?.lastBarcode
        val json = JSONObject().apply {
            put("success", barcode != null)
            put("value", barcode ?: "")
            put("source", "hid")
            put("listening", hid?.isListening ?: false)
        }
        return jsonResponse(json)
    }

    private fun handleDeviceInfo(): Response {
        return jsonResponse(deviceInfo.toJson())
    }

    private fun handlePrinterStatus(): Response {
        val status = printerManager.getStatus()
        val json = JSONObject().apply {
            put("connected", status.connected)
            put("type", status.type)
            put("name", status.name)
            put("paper_ready", status.paperReady)
        }
        return jsonResponse(json)
    }

    private fun handlePrinterList(): Response {
        return try {
            val printers = printerManager.scanAll()
            val json = JSONObject().apply {
                put("success", true)
                put("count", printers.size)
                put("printers", org.json.JSONArray().apply {
                    printers.forEach { p ->
                        put(JSONObject().apply {
                            put("type", p.type)
                            put("name", p.name)
                        })
                    }
                })
            }
            jsonResponse(json)
        } catch (e: Exception) {
            jsonError(Response.Status.INTERNAL_ERROR, "Scan error: ${e.message}")
        }
    }

    private fun handlePrinterSelect(session: IHTTPSession): Response {
        return try {
            val body = readBody(session)
            val json = JSONObject(body)
            val printerType = json.getString("type")
            val printerName = json.optString("name", "")

            val printers = printerManager.scanAll()
            val target = printers.find { it.type == printerType && (printerName.isEmpty() || it.name == printerName) }

            if (target != null) {
                val connected = printerManager.selectPrinter(target)
                val result = JSONObject().apply {
                    put("success", connected)
                    put("printer", target.name)
                }
                jsonResponse(result)
            } else {
                jsonError(Response.Status.NOT_FOUND, "Printer not found")
            }
        } catch (e: Exception) {
            jsonError(Response.Status.INTERNAL_ERROR, "Select error: ${e.message}")
        }
    }

    private fun handlePrinterTest(): Response {
        return try {
            val printer = printerManager.currentPrinter
                ?: return jsonError(Response.Status.BAD_REQUEST, "No printer selected")
            val testPage = buildString {
                append("\u001B@")
                append("\u001Ba\u0001")
                append("================================\n")
                append("    INSA POS - TEST PRINT\n")
                append("================================\n\n")
                append("\u001Ba\u0000")
                append("Printer: ${printer.name}\n")
                append("Type:    ${printer.type}\n")
                append("Date:    ${java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.getDefault()).format(java.util.Date())}\n\n")
                append("If you can read this, your\n")
                append("printer is working correctly!\n\n")
                append("\u001Ba\u0001")
                append("================================\n\n\n")
                append("\u001DVA")
            }
            val ok = printer.printText(testPage)
            jsonResponse(JSONObject().apply {
                put("ok", ok)
                put("success", ok)
                put("printer", printer.name)
            })
        } catch (e: Exception) {
            jsonError(Response.Status.INTERNAL_ERROR, "Test print failed: ${e.message}")
        }
    }

    // ── Offline Cache Endpoints ──────────────────────────────

    private fun handleReceiptSave(session: IHTTPSession): Response {
        return try {
            val body = readBody(session)
            val receipt = JSONObject(body)
            val arr = readJsonArray(receiptsFile)
            arr.put(receipt)
            receiptsFile.writeText(arr.toString())
            log("Receipt saved (total: ${arr.length()})")
            jsonResponse(JSONObject().apply {
                put("success", true)
                put("count", arr.length())
            })
        } catch (e: Exception) {
            Log.e(TAG, "Receipt save error: ${e.message}")
            jsonError(Response.Status.INTERNAL_ERROR, "Receipt save error: ${e.message}")
        }
    }

    private fun handleTransactionSave(session: IHTTPSession): Response {
        return try {
            val body = readBody(session)
            val tx = JSONObject(body)
            val localId = tx.optString("local_id", "")

            val arr = readJsonArray(transactionsFile)

            // Idempotency: don't store duplicates
            var exists = false
            if (localId.isNotEmpty()) {
                for (i in 0 until arr.length()) {
                    if (arr.getJSONObject(i).optString("local_id") == localId) {
                        exists = true
                        break
                    }
                }
            }

            if (!exists) {
                arr.put(tx)
                transactionsFile.writeText(arr.toString())
                log("Transaction saved: $localId (total: ${arr.length()})")
            }

            jsonResponse(JSONObject().apply {
                put("success", true)
                put("duplicate", exists)
                put("count", arr.length())
            })
        } catch (e: Exception) {
            Log.e(TAG, "Transaction save error: ${e.message}")
            jsonError(Response.Status.INTERNAL_ERROR, "Transaction save error: ${e.message}")
        }
    }

    private fun handleSyncPush(session: IHTTPSession): Response {
        return handleTransactionSave(session)
    }

    private fun handleSyncPull(): Response {
        return try {
            val transactions = readJsonArray(transactionsFile)
            val receipts = readJsonArray(receiptsFile)

            // Clear after pull
            transactionsFile.delete()
            receiptsFile.delete()

            log("Sync pull: ${transactions.length()} tx, ${receipts.length()} receipts")

            jsonResponse(JSONObject().apply {
                put("success", true)
                put("transactions", transactions)
                put("receipts", receipts)
            })
        } catch (e: Exception) {
            Log.e(TAG, "Sync pull error: ${e.message}")
            jsonError(Response.Status.INTERNAL_ERROR, "Sync pull error: ${e.message}")
        }
    }

    private fun readJsonArray(file: File): JSONArray {
        return try {
            if (file.exists()) JSONArray(file.readText()) else JSONArray()
        } catch (e: Exception) {
            JSONArray()
        }
    }

    // ── Utilities ─────────────────────────────────────────────

    private fun readBody(session: IHTTPSession): String {
        val contentLength = session.headers["content-length"]?.toIntOrNull() ?: 0
        if (contentLength == 0) return "{}"
        val buffer = ByteArray(contentLength)
        session.inputStream.read(buffer, 0, contentLength)
        return String(buffer, Charsets.UTF_8)
    }

    private fun jsonResponse(json: JSONObject): Response {
        return newFixedLengthResponse(
            Response.Status.OK,
            "application/json",
            json.toString()
        )
    }

    private fun jsonError(status: Response.Status, message: String): Response {
        val json = JSONObject().apply {
            put("success", false)
            put("error", message)
        }
        return newFixedLengthResponse(status, "application/json", json.toString())
    }

    private fun corsResponse(response: Response): Response {
        response.addHeader("Access-Control-Allow-Origin", "*")
        response.addHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        response.addHeader("Access-Control-Allow-Headers", "Content-Type, Authorization")
        response.addHeader("Access-Control-Max-Age", "86400")
        return response
    }

    private fun log(message: String) {
        Log.d(TAG, message)
        onLog?.invoke(message)
    }
}
