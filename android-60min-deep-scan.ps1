# Android 60-minute Deep Monitoring Session
# Device: JH2404230714 | DaFox: com.dafox.eloading

$ErrorActionPreference = "Continue"
$ADB = "C:\Users\Admin\Android\Sdk\platform-tools\adb.exe"
$SERIAL = "JH2404230714"
$REPORT_TXT = "C:\Users\Admin\Downloads\Android-60min-Deep-Scan-Report.txt"
$REPORT_PDF = "C:\Users\Admin\Downloads\Android-60min-Deep-Scan-Report.pdf"
$SCREENSHOT = "C:\Users\Admin\Downloads\android-60min-scan-final.png"
$SEARCH_ID = "09NET256071439"
$DAFOX_PKG = "com.dafox.eloading"

$script:Found09NET = $false
$script:AllFindings = [System.Collections.Generic.List[string]]::new()
$script:Timeline = [System.Collections.Generic.List[hashtable]]::new()
$script:PeripheralHistory = [System.Collections.Generic.List[string]]::new()
$script:NetworkHistory = [System.Collections.Generic.List[string]]::new()

function Write-Report {
    param([string]$Text)
    Add-Content -Path $REPORT_TXT -Value $Text -Encoding UTF8
}

function Invoke-Adb {
    param([string]$ShellCmd)
    try {
        $out = & $ADB -s $SERIAL shell $ShellCmd 2>&1
        if ($out -is [array]) { return ($out -join "`n") }
        return [string]$out
    } catch {
        return "ERROR: $_"
    }
}

function Invoke-AdbRaw {
    param([string[]]$Args)
    try {
        $out = & $ADB -s $SERIAL @Args 2>&1
        if ($out -is [array]) { return ($out -join "`n") }
        return [string]$out
    } catch {
        return "ERROR: $_"
    }
}

function Test-DeviceConnected {
    $state = & $ADB -s $SERIAL get-state 2>&1
    return ($state -match "device")
}

function Collect-Sample {
    param([int]$SampleNum, [string]$Label)

    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $header = @"

================================================================================
=== SAMPLE $SampleNum @ $ts ($Label) ===
================================================================================
"@
    Write-Report $header

    if (-not (Test-DeviceConnected)) {
        Write-Report "!!! DEVICE DISCONNECTED - retrying in 10s..."
        Start-Sleep -Seconds 10
        if (-not (Test-DeviceConnected)) {
            Write-Report "!!! STILL DISCONNECTED"
            return
        }
        Write-Report "Device reconnected."
    }

    # A. Peripherals
    Write-Report "`n--- A. CONNECTED DEVICES / PERIPHERALS ---"
    Write-Report "`n>> dumpsys usb (summary):"
    $usb = Invoke-Adb "dumpsys usb" 
    $usbLines = ($usb -split "`n") | Select-Object -First 80
    Write-Report ($usbLines -join "`n")

    Write-Report "`n>> dumpsys bluetooth_manager (summary):"
    $bt = Invoke-Adb "dumpsys bluetooth_manager"
    $btSummary = ($bt -split "`n") | Where-Object { $_ -match "enabled|Connected|bonded|name|address|state" } | Select-Object -First 40
    Write-Report ($btSummary -join "`n")

    Write-Report "`n>> dumpsys input (summary):"
    $input = Invoke-Adb "dumpsys input"
    $inputSum = ($input -split "`n") | Where-Object { $_ -match "Device|Keyboard|Touch|Mouse|Vendor|Product" } | Select-Object -First 30
    Write-Report ($inputSum -join "`n")

    Write-Report "`n>> USB/serial devices:"
    $devs = Invoke-Adb "ls /dev/bus/usb/ 2>/dev/null; ls /dev/ttyUSB* /dev/ttyACM* 2>/dev/null"
    Write-Report $devs
    $script:PeripheralHistory.Add("$ts | USB/serial: $devs")

    Write-Report "`n>> telephony.registry (first 40 lines):"
    $tel = Invoke-Adb "dumpsys telephony.registry"
    $tel40 = ($tel -split "`n") | Select-Object -First 40
    Write-Report ($tel40 -join "`n")

    # B. Search 09NET256071439
    Write-Report "`n--- B. SEARCH $SEARCH_ID ---"
    $logSearch = Invoke-Adb "logcat -d -t 300 | grep -i '09NET256071439\|09NET\|256071439'"
    Write-Report "logcat grep:`n$logSearch"
    if ($logSearch -and $logSearch -notmatch "ERROR" -and $logSearch.Trim().Length -gt 0) {
        $script:Found09NET = $true
        $script:AllFindings.Add("[$ts] 09NET found in logcat: $($logSearch.Substring(0, [Math]::Min(200, $logSearch.Length)))")
    }

    $sdcardSearch = Invoke-Adb "grep -r '09NET256071439' /sdcard/ 2>/dev/null | head -20"
    Write-Report "sdcard grep:`n$sdcardSearch"
    if ($sdcardSearch -and $sdcardSearch.Trim().Length -gt 5) {
        $script:Found09NET = $true
        $script:AllFindings.Add("[$ts] 09NET found on sdcard")
    }

    Write-Report "`n>> dumpsys window (Fox/machine IDs):"
    $win = Invoke-Adb "dumpsys window"
    $foxWin = ($win -split "`n") | Where-Object { $_ -match "Fox-|09NET|dafox|mCurrentFocus|mFocusedApp|machine" } | Select-Object -First 25
    Write-Report ($foxWin -join "`n")
    if ($win -match "09NET256071439") { $script:Found09NET = $true }

    # C. DaFox app state
    Write-Report "`n--- C. DAFOX APP STATE ---"
    $act = Invoke-Adb "dumpsys activity activities"
    $dafoxAct = ($act -split "`n") | Where-Object { $_ -match "dafox|Resumed|lockTask|mResumedActivity" } | Select-Object -First 20
    Write-Report ($dafoxAct -join "`n")

    Write-Report "`n>> ps dafox:"
    $ps = Invoke-Adb "ps -A | grep dafox"
    Write-Report $ps

    Write-Report "`n>> meminfo:"
    $mem = Invoke-Adb "dumpsys meminfo $DAFOX_PKG"
    $mem30 = ($mem -split "`n") | Select-Object -First 30
    Write-Report ($mem30 -join "`n")

    # D. Network
    Write-Report "`n--- D. NETWORK ACTIVITY ---"
    $wifi = Invoke-Adb "dumpsys wifi"
    $wifiSum = ($wifi -split "`n") | Where-Object { $_ -match "SSID|mNetworkInfo|ipaddress|state|WebShoppe" } | Select-Object -First 20
    Write-Report "WiFi:`n$($wifiSum -join "`n")"

    $conn = Invoke-Adb "dumpsys connectivity"
    $conn50 = ($conn -split "`n") | Select-Object -First 50
    Write-Report "Connectivity:`n$($conn50 -join "`n")"

    Write-Report "`n>> /proc/net/tcp (first 30 lines):"
    $tcp = Invoke-Adb "cat /proc/net/tcp"
    $tcp30 = ($tcp -split "`n") | Select-Object -First 30
    Write-Report ($tcp30 -join "`n")

    Write-Report "`n>> ss/netstat:"
    $ss = Invoke-Adb "ss -tnp 2>/dev/null || netstat -tnp 2>/dev/null"
    $ssFiltered = ($ss -split "`n") | Where-Object { $_ -match "ESTAB|25565|443|1883|8883|dafox|eloading" } | Select-Object -First 40
    if ($ssFiltered.Count -eq 0) { $ssFiltered = ($ss -split "`n") | Select-Object -First 25 }
    Write-Report ($ssFiltered -join "`n")
    $script:NetworkHistory.Add("$ts | $($ssFiltered -join ' | ')")

    # E. System log slice
    Write-Report "`n--- E. FILTERED LOGCAT SLICE ---"
    $logSlice = Invoke-Adb "logcat -d -t 200 -v time | grep -iE 'dafox|eloading|09NET|Fox-|mqtt|redis|heartbeat|usb|bluetooth|serial|activate|machine|socket|okhttp|connect|port:25565'"
    Write-Report $logSlice

    if ($logSlice -match "09NET256071439") { $script:Found09NET = $true }
    if ($logSlice -match "Fox-") {
        $foxMatch = [regex]::Matches($logSlice, "Fox-[A-Fa-f0-9]+") | ForEach-Object { $_.Value } | Select-Object -Unique
        foreach ($f in $foxMatch) {
            $script:AllFindings.Add("[$ts] Fox ID in logs: $f")
        }
    }
    if ($logSlice -match "25565|redis|Redis") {
        $script:AllFindings.Add("[$ts] Redis/port 25565 activity in logs")
    }

    $script:Timeline.Add(@{
        Sample = $SampleNum
        Time = $ts
        Label = $Label
        DafoxRunning = ($ps -match "dafox")
        HasRedisLog = ($logSlice -match "25565|redis")
        HasFoxId = ($logSlice -match "Fox-")
    })
}

# Initialize report
$startTime = Get-Date
$initHeader = @"
================================================================================
ANDROID 60-MINUTE DEEP MONITORING SESSION
================================================================================
Started: $($startTime.ToString("yyyy-MM-dd HH:mm:ss"))
Device Serial: $SERIAL
Model: Smart_9 (Android 8.1)
Target App: $DAFOX_PKG
Search Target: $SEARCH_ID
Prior Context:
  - Machine on device showed Fox-B068B8 Connected (not $SEARCH_ID)
  - RedisThread logged port 25565
  - WiFi: WebShoppePH 5G
  - Device owner: com.dafox.eloading FoxDeviceAdminReceiver
================================================================================
"@
Set-Content -Path $REPORT_TXT -Value $initHeader -Encoding UTF8

Write-Host "Starting 60-minute monitoring at $startTime"

# Sample 0 - start baseline
Collect-Sample -SampleNum 0 -Label "START BASELINE"

# Samples 1-12 (every 5 minutes)
for ($i = 1; $i -le 12; $i++) {
    $elapsed = ((Get-Date) - $startTime).TotalMinutes
    Write-Host "Sleeping 300s before sample $i (elapsed ~$([math]::Round($elapsed,1)) min)..."
    Start-Sleep -Seconds 300
    Collect-Sample -SampleNum $i -Label "INTERVAL $([int]($i * 5)) min"
}

# End baseline
Collect-Sample -SampleNum 13 -Label "END BASELINE @ 60 min"

# Final screenshot
Write-Report "`n--- FINAL SCREENSHOT ---"
if (Test-DeviceConnected) {
    $pngBytes = & $ADB -s $SERIAL exec-out screencap -p 2>&1
    if ($pngBytes) {
        [System.IO.File]::WriteAllBytes($SCREENSHOT, [byte[]]$pngBytes)
    }
    if (Test-Path $SCREENSHOT) {
        Write-Report "Screenshot saved: $SCREENSHOT"
    } else {
        # Alternative pull method
        & $ADB -s $SERIAL shell screencap -p /sdcard/scan_final.png 2>&1 | Out-Null
        & $ADB -s $SERIAL pull /sdcard/scan_final.png $SCREENSHOT 2>&1 | Out-Null
        Write-Report "Screenshot pull attempted: $SCREENSHOT exists=$(Test-Path $SCREENSHOT)"
    }
}

$endTime = Get-Date
$duration = $endTime - $startTime

# Compile analysis
$foundStr = if ($script:Found09NET) { "YES - 09NET256071439 was detected" } else { "NO - 09NET256071439 was NOT found in any sample" }

$analysis = @"

================================================================================
COMPILED ANALYSIS (60-MINUTE SESSION COMPLETE)
================================================================================
Ended: $($endTime.ToString("yyyy-MM-dd HH:mm:ss"))
Duration: $([math]::Round($duration.TotalMinutes, 1)) minutes

--- EXECUTIVE SUMMARY ---
Device $SERIAL remained monitored for ~60 minutes with 14 sample points (start + 12 intervals + end).
Search target $SEARCH_ID : $foundStr
DaFox package $DAFOX_PKG monitored throughout.
Prior Fox-B068B8 machine ID may appear instead of 09NET prefix.

--- DEVICE & DAFOX STATUS TIMELINE ---
$($script:Timeline | ForEach-Object { "Sample $($_.Sample) @ $($_.Time) [$($_.Label)] | DaFox running: $($_.DafoxRunning) | Redis logs: $($_.HasRedisLog) | Fox-ID logs: $($_.HasFoxId)" } | Out-String)

--- CONNECTED PERIPHERALS OVER TIME ---
$($script:PeripheralHistory -join "`n")

--- 09NET256071439 SEARCH RESULTS ---
$foundStr
Unique findings related to search/Fox IDs:
$($script:AllFindings | Select-Object -Unique | Out-String)

--- NETWORK ACTIVITY PATTERNS ---
$($script:NetworkHistory -join "`n")

--- RECOMMENDATIONS FOR ePayPlus ---
1. Align machine ID format with DaFox (Fox-XXXXXXXX vs 09NET prefix) - verify backend mapping.
2. Monitor Redis port 25565 connectivity for heartbeat/sync reliability.
3. If kiosk lock-task mode active, test ePayPlus coexistence via device owner policies.
4. Log correlation: compare peripheral USB attach events with network spikes.
5. Ensure WebShoppePH 5G WiFi stability for transaction APIs.
6. Parse DaFox logs for Fox-* machine IDs when 09NET not found.
7. Document device owner (FoxDeviceAdminReceiver) constraints before ePayPlus deployment.
8. Schedule periodic dumpsys during peak transaction hours.
9. Map /proc/net/tcp endpoints to known DaFox backend IPs.
10. Cross-reference sdcard grep with cloud machine registry.

================================================================================
END OF REPORT
================================================================================
"@

Write-Report $analysis

# Export metadata for PDF generator
$metaPath = "C:\Users\Admin\Downloads\Android-60min-Deep-Scan-meta.json"
@{
    Found09NET = $script:Found09NET
    Findings = @($script:AllFindings | Select-Object -Unique)
    Timeline = $script:Timeline
    StartTime = $startTime.ToString("o")
    EndTime = $endTime.ToString("o")
    ReportTxt = $REPORT_TXT
    ReportPdf = $REPORT_PDF
} | ConvertTo-Json -Depth 5 | Set-Content $metaPath -Encoding UTF8

Write-Host "TXT report complete: $REPORT_TXT"
Write-Host "Found09NET: $script:Found09NET"

# Generate PDF
Write-Host "Generating PDF..."
python "c:\laragon\www\ePay Plus\generate-android-scan-pdf.py" 2>&1 | ForEach-Object { Write-Host $_ }
Write-Host "DONE"
