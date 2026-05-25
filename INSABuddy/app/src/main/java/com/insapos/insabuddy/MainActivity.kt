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
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.insapos.insabuddy.databinding.ActivityMainBinding
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private var service: INSABuddyService? = null
    private var bound = false
    private val logLines = mutableListOf<String>()

    private val connection = object : ServiceConnection {
        override fun onServiceConnected(name: ComponentName?, binder: IBinder?) {
            val localBinder = binder as INSABuddyService.LocalBinder
            service = localBinder.getService()
            bound = true

            service?.scannerBridge?.setActivity(this@MainActivity)
            service?.onLog = { msg -> runOnUiThread { appendLog(msg) } }

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

    @Deprecated("Use Activity Result API")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        if (service?.scannerBridge?.handleScanResult(requestCode, resultCode, data) == true) {
            val value = service?.scannerBridge?.lastResult
            if (value != null) {
                appendLog("Scanned: $value")
                Toast.makeText(this, "Scanned: $value", Toast.LENGTH_SHORT).show()
            }
            return
        }
        @Suppress("DEPRECATION")
        super.onActivityResult(requestCode, resultCode, data)
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
            Thread {
                try {
                    val printers = service?.printerManager?.scanAll() ?: emptyList()
                    runOnUiThread {
                        binding.btnScanPrinters.isEnabled = true
                        if (printers.isEmpty()) {
                            appendLog("No printers found")
                            binding.tvPrinterStatus.text = "No printers found"
                        } else {
                            printers.forEach { p -> appendLog("Found: ${p.type} — ${p.name}") }
                            binding.tvPrinterStatus.text = "Found ${printers.size} printer(s)"
                        }
                    }
                    // Auto-connect on background thread (Bluetooth connect is blocking)
                    if (printers.isNotEmpty()) {
                        val first = printers.first()
                        val connected = service?.printerManager?.selectPrinter(first)
                        runOnUiThread {
                            if (connected == true) {
                                appendLog("Connected to: ${first.name}")
                                binding.tvPrinterStatus.text = "Connected: ${first.name}"
                            }
                        }
                    }
                } catch (e: Exception) {
                    runOnUiThread {
                        binding.btnScanPrinters.isEnabled = true
                        appendLog("Scan failed: ${e.message}")
                    }
                }
            }.start()
        }

        binding.btnTestPrint.setOnClickListener {
            if (service?.printerManager?.currentPrinter?.isConnected() != true) {
                Toast.makeText(this, "No printer connected", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            Thread {
                val ok = service?.printerManager?.printText(
                    "================================\n" +
                    "       INSABuddy Test Print     \n" +
                    "================================\n" +
                    "Printer is working correctly!\n" +
                    "Date: ${SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())}\n" +
                    "================================\n"
                ) ?: false
                runOnUiThread {
                    appendLog(if (ok) "Test print sent" else "Test print failed")
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
                        appendLog("Scanned: $result")
                    } else {
                        appendLog("Scan cancelled or timed out")
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
        binding.tvPrinterStatus.text = if (printerStatus?.connected == true) {
            "Connected: ${printerStatus.name}"
        } else {
            "No printer connected"
        }
    }

    private fun appendLog(message: String) {
        val timestamp = SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(Date())
        logLines.add("[$timestamp] $message")
        if (logLines.size > 50) logLines.removeAt(0)
        binding.tvLog.text = logLines.joinToString("\n")
    }
}
