package com.insapos.insabuddy

import android.util.Base64
import android.util.Log
import com.insapos.insabuddy.printers.PrinterManager
import fi.iki.elonen.NanoHTTPD
import org.json.JSONObject

class LocalServer(
    private val printerManager: PrinterManager,
    private val scannerBridge: ScannerBridge,
    private val deviceInfo: DeviceInfo
) : NanoHTTPD(18181) {

    companion object {
        private const val TAG = "LocalServer"
    }

    var onLog: ((String) -> Unit)? = null

    override fun serve(session: IHTTPSession): Response {
        val uri = session.uri
        val method = session.method

        log("${method.name} $uri")

        // Add CORS headers for web access
        val response = when {
            method == Method.OPTIONS -> corsResponse(newFixedLengthResponse(""))
            uri == "/ping" && method == Method.GET -> handlePing()
            uri == "/print" && method == Method.POST -> handlePrint(session)
            uri == "/drawer/open" && method == Method.POST -> handleDrawerOpen()
            uri == "/scan" && method == Method.POST -> handleScan()
            uri == "/scan/continuous" && method == Method.POST -> handleContinuousScan(session)
            uri == "/device/info" && method == Method.GET -> handleDeviceInfo()
            uri == "/printer/status" && method == Method.GET -> handlePrinterStatus()
            uri == "/printer/list" && method == Method.GET -> handlePrinterList()
            uri == "/printer/select" && method == Method.POST -> handlePrinterSelect(session)
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
