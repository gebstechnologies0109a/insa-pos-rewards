package com.insapos.insabuddy

import android.Manifest
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.ServiceConnection
import android.content.pm.PackageManager
import android.graphics.drawable.GradientDrawable
import android.os.Build
import android.os.Bundle
import android.os.IBinder
import android.view.KeyEvent
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.insapos.insabuddy.databinding.ActivityMainBinding
import com.insapos.insabuddy.printers.Printer
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private var service: INSABuddyService? = null
    private var bound = false
    private val logLines = mutableListOf<String>()

    private var scannedPrinters: List<Printer> = emptyList()
    private val hidScanner = HidScannerDriver()

    private val connection = object : ServiceConnection {
        override fun onServiceConnected(name: ComponentName?, binder: IBinder?) {
            val localBinder = binder as INSABuddyService.LocalBinder
            service = localBinder.getService()
            bound = true

            service?.scannerBridge?.setActivity(this@MainActivity)
            service?.onLog = { msg -> runOnUiThread { appendLog(msg) } }

            // Wire HID scanner into the service so LocalServer can access last result
            service?.hidScannerDriver = hidScanner

            updateUI()
        }

        override fun onServiceDisconnected(name: ComponentName?) {
            service = null
            bound = false
            updateUI()
        }
    }

    private val permissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { results ->
        val allGranted = results.all { it.value }
        if (allGranted) {
            startBuddyService()
        } else {
            appendLog("Some permissions denied — features may be limited")
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupButtons()
        setupHidScanner()
        requestPermissions()
    }

    override fun onStart() {
        super.onStart()
        bindToService()
    }

    override fun onStop() {
        super.onStop()
        if (bound) {
            service?.scannerBridge?.setActivity(null)
            unbindService(connection)
            bound = false
        }
    }

    override fun dispatchKeyEvent(event: KeyEvent): Boolean {
        if (hidScanner.handleKeyEvent(event)) return true
        return super.dispatchKeyEvent(event)
    }

    @Deprecated("Use Activity Result API")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        if (service?.scannerBridge?.handleScanResult(requestCode, resultCode, data) == true) {
            val value = service?.scannerBridge?.lastResult
            if (value != null) {
                appendLog("Camera scan: $value")
                Toast.makeText(this, "Scanned: $value", Toast.LENGTH_SHORT).show()
            }
            return
        }
        @Suppress("DEPRECATION")
        super.onActivityResult(requestCode, resultCode, data)
    }

    private fun setupHidScanner() {
        hidScanner.onBarcodeScanned = { barcode ->
            runOnUiThread {
                binding.tvLastBarcode.text = "Last scan: $barcode"
                binding.tvBarcodeScannerStatus.text = "Barcode received from HID device"
                appendLog("HID scanner: $barcode")
            }
        }
    }

    private fun setupButtons() {
        binding.btnToggleService.setOnClickListener {
            if (INSABuddyService.instance?.isServerRunning() == true) {
                stopBuddyService()
            } else {
                startBuddyService()
            }
        }

        binding.btnScanPrinters.setOnClickListener {
            if (!bound) {
                Toast.makeText(this, "Service not running", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            appendLog("Scanning for printers...")
            binding.btnScanPrinters.isEnabled = false
            binding.btnScanPrinters.text = "Scanning..."
            binding.tvPrinterStatus.text = "Scanning for printers..."

            Thread {
                try {
                    val printers = service?.printerManager?.scanAll() ?: emptyList()
                    runOnUiThread {
                        binding.btnScanPrinters.isEnabled = true
                        binding.btnScanPrinters.text = "Step 1: Scan for Printers"
                        scannedPrinters = printers

                        if (printers.isEmpty()) {
                            appendLog("No printers found")
                            binding.tvPrinterStatus.text = "No printers found — make sure Bluetooth is on and devices are paired"
                            binding.printerSelectionGroup.visibility = View.GONE
                        } else {
                            printers.forEach { p -> appendLog("Found: [${p.type}] ${p.name}") }
                            binding.tvPrinterStatus.text = "Found ${printers.size} printer(s) — select one below"

                            val names = printers.map { "[${it.type}] ${it.name}" }
                            val adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, names)
                            binding.spinnerPrinters.adapter = adapter
                            binding.printerSelectionGroup.visibility = View.VISIBLE
                        }
                    }
                } catch (e: Exception) {
                    runOnUiThread {
                        binding.btnScanPrinters.isEnabled = true
                        binding.btnScanPrinters.text = "Step 1: Scan for Printers"
                        appendLog("Scan failed: ${e.message}")
                        binding.tvPrinterStatus.text = "Scan failed: ${e.message}"
                    }
                }
            }.start()
        }

        binding.btnConnectPrinter.setOnClickListener {
            val idx = binding.spinnerPrinters.selectedItemPosition
            if (idx < 0 || idx >= scannedPrinters.size) {
                Toast.makeText(this, "Select a printer from the dropdown first", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            val printer = scannedPrinters[idx]
            appendLog("Connecting to: ${printer.name}...")
            binding.btnConnectPrinter.isEnabled = false
            binding.btnConnectPrinter.text = "Connecting..."

            Thread {
                try {
                    val connected = service?.printerManager?.selectPrinter(printer) ?: false
                    runOnUiThread {
                        binding.btnConnectPrinter.isEnabled = true
                        binding.btnConnectPrinter.text = "Select & Connect Printer"
                        if (connected) {
                            appendLog("Connected to: ${printer.name}")
                            binding.tvPrinterStatus.text = "✓ Connected: ${printer.name}"
                            binding.btnTestPrint.isEnabled = true
                            Toast.makeText(this, "Printer connected! You can now test print.", Toast.LENGTH_SHORT).show()
                        } else {
                            appendLog("Failed to connect to: ${printer.name}")
                            binding.tvPrinterStatus.text = "✗ Connection failed — try another printer"
                            binding.btnTestPrint.isEnabled = false
                            Toast.makeText(this, "Connection failed", Toast.LENGTH_SHORT).show()
                        }
                    }
                } catch (e: Exception) {
                    runOnUiThread {
                        binding.btnConnectPrinter.isEnabled = true
                        binding.btnConnectPrinter.text = "Select & Connect Printer"
                        appendLog("Connect error: ${e.message}")
                        binding.btnTestPrint.isEnabled = false
                    }
                }
            }.start()
        }

        binding.btnTestPrint.setOnClickListener {
            if (service?.printerManager?.currentPrinter?.isConnected() != true) {
                Toast.makeText(this, "No printer connected — scan and select a printer first", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            appendLog("Sending test print...")
            binding.btnTestPrint.isEnabled = false
            binding.btnTestPrint.text = "Printing..."

            Thread {
                val ok = service?.printerManager?.printText(
                    "================================\n" +
                    "    INSABuddy v${BuildConfig.VERSION_NAME}    \n" +
                    "       Test Print               \n" +
                    "================================\n" +
                    "Printer is working correctly!\n" +
                    "Date: ${SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())}\n" +
                    "Device: ${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}\n" +
                    "================================\n"
                ) ?: false
                runOnUiThread {
                    binding.btnTestPrint.isEnabled = true
                    binding.btnTestPrint.text = "Step 3: Test Print"
                    if (ok) {
                        appendLog("Test print sent successfully")
                        Toast.makeText(this, "Test print sent!", Toast.LENGTH_SHORT).show()
                    } else {
                        appendLog("Test print failed")
                        Toast.makeText(this, "Test print failed", Toast.LENGTH_SHORT).show()
                    }
                }
            }.start()
        }

        binding.btnOpenDrawer.setOnClickListener {
            Thread {
                try {
                    service?.printerManager?.openDrawer()
                    runOnUiThread { appendLog("Cash drawer pulse sent") }
                } catch (e: Exception) {
                    runOnUiThread { appendLog("Drawer failed: ${e.message}") }
                }
            }.start()
        }

        binding.btnScanBarcode.setOnClickListener {
            if (!bound) {
                Toast.makeText(this, "Service not running", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            Thread {
                val result = service?.scannerBridge?.requestScan()
                runOnUiThread {
                    if (result != null) {
                        appendLog("Camera scan: $result")
                        binding.tvLastBarcode.text = "Last scan: $result"
                    } else {
                        appendLog("Camera scan cancelled or timed out")
                    }
                }
            }.start()
        }
    }

    private fun requestPermissions() {
        val needed = mutableListOf<String>()

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_CONNECT)
                != PackageManager.PERMISSION_GRANTED
            ) needed.add(Manifest.permission.BLUETOOTH_CONNECT)
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.BLUETOOTH_SCAN)
                != PackageManager.PERMISSION_GRANTED
            ) needed.add(Manifest.permission.BLUETOOTH_SCAN)
        }

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
            != PackageManager.PERMISSION_GRANTED
        ) needed.add(Manifest.permission.CAMERA)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
            ) needed.add(Manifest.permission.POST_NOTIFICATIONS)
        }

        if (needed.isNotEmpty()) {
            permissionLauncher.launch(needed.toTypedArray())
        } else {
            startBuddyService()
        }
    }

    private fun startBuddyService() {
        val intent = Intent(this, INSABuddyService::class.java)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            startForegroundService(intent)
        } else {
            startService(intent)
        }
        bindToService()
        appendLog("Service starting...")
    }

    private fun stopBuddyService() {
        stopService(Intent(this, INSABuddyService::class.java))
        updateUI()
        appendLog("Service stopped")
    }

    private fun bindToService() {
        val intent = Intent(this, INSABuddyService::class.java)
        bindService(intent, connection, Context.BIND_AUTO_CREATE)
    }

    private fun updateUI() {
        val running = INSABuddyService.instance?.isServerRunning() == true
        binding.tvStatus.text = if (running) getString(R.string.status_running) else getString(R.string.status_stopped)
        binding.btnToggleService.text = if (running) getString(R.string.btn_stop_service) else getString(R.string.btn_start_service)

        val dot = binding.statusIndicator.background
        if (dot is GradientDrawable) {
            dot.setColor(
                ContextCompat.getColor(
                    this,
                    if (running) R.color.status_connected else R.color.status_disconnected
                )
            )
        }

        val printerStatus = service?.printerManager?.getStatus()
        if (printerStatus?.connected == true) {
            binding.tvPrinterStatus.text = "✓ Connected: ${printerStatus.name}"
            binding.btnTestPrint.isEnabled = true
        } else {
            binding.tvPrinterStatus.text = "No printer connected"
            binding.btnTestPrint.isEnabled = false
        }
    }

    private fun appendLog(message: String) {
        val timestamp = SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(Date())
        logLines.add("[$timestamp] $message")
        if (logLines.size > 100) logLines.removeAt(0)
        binding.tvLog.text = logLines.joinToString("\n")
    }
}
