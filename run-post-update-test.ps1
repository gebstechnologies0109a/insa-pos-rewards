# ePayPlus Post-Update Deep Scan + Functional Test
$ErrorActionPreference = "Continue"
$ADB = "C:\Users\Admin\Android\Sdk\platform-tools\adb.exe"
$SERIAL = "MTK0002601041044200"
$PKG = "com.epayplus.v2"
$DAFOX = "com.dafox.eloading"
$REPORT = "C:\Users\Admin\Downloads\ePayPlus-Post-Update-Test-Report.txt"
$DL = "C:\Users\Admin\Downloads"
$API = "https://epayplus.diybizrewards.com/api/v2/"

function A-Shell { param([string]$cmd)
    $o = & $ADB -s $SERIAL shell $cmd 2>&1
    if ($o -is [array]) { return ($o -join "`n") }
    return [string]$o
}
function A-Raw { param([string[]]$args)
    $o = & $ADB -s $SERIAL @args 2>&1
    if ($o -is [array]) { return ($o -join "`n") }
    return [string]$o
}
function R { param([string]$t) Add-Content -Path $REPORT -Value $t -Encoding UTF8 }

if (Test-Path $REPORT) { Remove-Item $REPORT -Force }
$ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
R "================================================================================"
R "ePayPlus Post-Update Deep Scan & Functional Test Report"
R "Generated: $ts"
R "Device Serial: $SERIAL"
R "Target: ePayPlus v3.1 (com.epayplus.v2)"
R "API Server: $API"
R "================================================================================"

# Part 0
R "`n=== PART 0: INSTALL VERIFICATION ==="
R (A-Raw @("devices","-l"))
$ver = A-Shell "dumpsys package $PKG" | Select-String "versionName|versionCode|firstInstall|lastUpdate"
R ($ver | Out-String)

# Part 1 - Device
R "`n=== PART 1: DEEP SCAN ==="
R "`n--- 1. Device Info ---"
R "Model: $(A-Shell 'getprop ro.product.model')"
R "Manufacturer: $(A-Shell 'getprop ro.product.manufacturer')"
R "Android: $(A-Shell 'getprop ro.build.version.release') (SDK $(A-Shell 'getprop ro.build.version.sdk'))"
R "Serial: $(A-Shell 'getprop ro.serialno')"
R "Battery: $(A-Shell 'dumpsys battery' | Select-String 'level|status|health' | Out-String)"
R "Storage:`n$(A-Shell 'df -h /data /sdcard 2>/dev/null' | Select-Object -First 5 | Out-String)"

# Apps
R "`n--- 2. Installed Apps ---"
R "ePayPlus:`n$(A-Shell "pm list packages $PKG")"
R "ePayPlus version from dumpsys:`n$($ver | Out-String)"
R "DaFox:`n$(A-Shell "pm list packages $DAFOX")"
if ((A-Shell "pm list packages $DAFOX") -match $DAFOX) {
    R "DaFox version:`n$(A-Shell "dumpsys package $DAFOX" | Select-String 'versionName|versionCode' | Out-String)"
}

# Package details
R "`n--- 3. ePayPlus Package (permissions, services, receivers) ---"
R "Permissions (requested):`n$(A-Shell "dumpsys package $PKG" | Select-String 'android.permission' | Select-Object -First 40 | Out-String)"
R "Services:`n$(A-Shell "dumpsys package $PKG" | Select-String 'Service{' | Out-String)"
R "Receivers:`n$(A-Shell "dumpsys package $PKG" | Select-String 'Receiver{' | Select-Object -First 25 | Out-String)"
R "Heartbeat/Command grep:`n$(A-Shell "dumpsys package $PKG" | Select-String 'Heartbeat|DeviceCommand|DeviceHeartbeat' | Out-String)"

# Network
R "`n--- 4. Network ---"
R "WiFi:`n$(A-Shell 'dumpsys wifi' | Select-String 'Wi-Fi is|SSID|mNetworkInfo|connected' | Select-Object -First 15 | Out-String)"
R "Ping epayplus (from device):`n$(A-Shell 'ping -c 3 epayplus.diybizrewards.com 2>&1' | Out-String)"
R "Ping 8.8.8.8:`n$(A-Shell 'ping -c 3 8.8.8.8 2>&1' | Out-String)"

# Processes
R "`n--- 5. Running Processes / Foreground ---"
R "ePayPlus processes:`n$(A-Shell "ps -A | grep -i epay")"
R "Foreground:`n$(A-Shell 'dumpsys activity activities' | Select-String 'mResumedActivity|topResumedActivity' | Select-Object -First 5 | Out-String)"

# Logcat
R "`n--- 6. Logcat (filtered, last 500 lines buffer) ---"
A-Raw @("logcat","-c") | Out-Null
Start-Sleep -Seconds 2
$log = A-Raw @("logcat","-d","-t","500")
$filtered = $log | Select-String -Pattern 'epayplus|retrofit|okhttp|heartbeat|license|error|exception|FATAL' -CaseSensitive:$false
R ($filtered | Out-String)
if (-not $filtered) { R '(no matching log lines in recent buffer after clear)' }

# DataStore / prefs
R "`n--- 7. DataStore / SharedPreferences ---"
$runas = A-Shell "run-as $PKG ls shared_prefs/ 2>&1"
R "run-as shared_prefs: $runas"
$runas2 = A-Shell "run-as $PKG ls files/datastore/ 2>&1"
R "run-as datastore: $runas2"

# Part 3 API from PC
R "`n=== PART 3: API SMOKE (from PC) ==="
try {
    $health = curl.exe -s "https://epayplus.diybizrewards.com/api/v2/health"
    R "GET /health: $health"
} catch { R "GET /health ERROR: $_" }
try {
    $hbCode = curl.exe -s -o NUL -w "%{http_code}" "https://epayplus.diybizrewards.com/api/v2/device/heartbeat" -X POST -H "Content-Type: application/json" -d '{\"device_id\":\"test\"}'
    R "POST /device/heartbeat HTTP code: $hbCode"
} catch { R "POST heartbeat ERROR: $_" }

# Part 4 DaFox compare
R "`n=== PART 4: DaFox COMPARISON (brief) ==="
R "DaFox installed: $((A-Shell "pm path $DAFOX") -match 'package')"
R "Lock task:`n$(A-Shell 'dumpsys activity' | Select-String 'lockTask|LockTask' | Select-Object -First 8 | Out-String)"
R "Device owner:`n$(A-Shell 'dumpsys device_policy' | Select-String 'Device Owner|admin' | Select-Object -First 10 | Out-String)"

Write-Host "Report written to $REPORT"
