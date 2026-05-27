# ePayPlus 10-Transaction Test Suite
$ErrorActionPreference = "Continue"
$ADB = "C:\Users\Admin\Android\Sdk\platform-tools\adb.exe"
$SERIAL = "MTK0002601041044200"
$PKG = "com.epayplus.v2"
$MAIN = "$PKG/.ui.MainActivity"
$DL = "C:\Users\Admin\Downloads"
$REPORT = "$DL\ePayPlus-10-Transactions-Test-Report.txt"
$UI_XML = "/sdcard/window_dump.xml"
$LOCAL_UI = "$env:TEMP\epay_ui_dump.xml"

$script:Results = @()

function Invoke-Adb {
    param([string]$Cmd)
    $out = & $ADB -s $SERIAL shell $Cmd 2>&1
    if ($out -is [array]) { return ($out -join "`n") }
    return [string]$out
}

function Screenshot {
    param([string]$Path)
    & $ADB -s $SERIAL shell screencap -p /sdcard/sc.png | Out-Null
    & $ADB -s $SERIAL pull /sdcard/sc.png $Path 2>&1 | Out-Null
    Start-Sleep -Seconds 1
}

function Wait-Screen {
    param([int]$Sec = 4)
    Start-Sleep -Seconds $Sec
}

function Press-Back {
    Invoke-Adb "input keyevent KEYCODE_BACK"
    Wait-Screen -Sec 2
}

function Press-Home {
    Invoke-Adb "input keyevent KEYCODE_HOME"
    Wait-Screen -Sec 2
}

function Get-UiDump {
    Invoke-Adb "uiautomator dump $UI_XML" | Out-Null
    & $ADB -s $SERIAL pull $UI_XML $LOCAL_UI 2>&1 | Out-Null
    if (Test-Path $LOCAL_UI) {
        return [xml](Get-Content $LOCAL_UI -Raw -Encoding UTF8)
    }
    return $null
}

function Find-NodeByText {
    param([xml]$Doc, [string[]]$Texts, [switch]$Partial)
    if (-not $Doc) { return $null }
    foreach ($t in $Texts) {
        $nodes = $Doc.SelectNodes("//node[@text]")
        foreach ($n in $nodes) {
            $txt = $n.text
            if ($Partial) {
                if ($txt -like "*$t*") { return $n }
            } else {
                if ($txt -eq $t) { return $n }
            }
        }
        $nodes = $Doc.SelectNodes("//node[@content-desc]")
        foreach ($n in $nodes) {
            $txt = $n.'content-desc'
            if ($Partial) {
                if ($txt -like "*$t*") { return $n }
            } else {
                if ($txt -eq $t) { return $n }
            }
        }
    }
    return $null
}

function Get-BoundsCenter {
    param([System.Xml.XmlElement]$Node)
    if (-not $Node -or -not $Node.bounds) { return $null }
    if ($Node.bounds -match '\[(\d+),(\d+)\]\[(\d+),(\d+)\]') {
        $x1 = [int]$Matches[1]; $y1 = [int]$Matches[2]
        $x2 = [int]$Matches[3]; $y2 = [int]$Matches[4]
        return @{ X = [int](($x1+$x2)/2); Y = [int](($y1+$y2)/2) }
    }
    return $null
}

function Tap-Node {
    param([System.Xml.XmlElement]$Node)
    $c = Get-BoundsCenter $Node
    if ($c) {
        Invoke-Adb "input tap $($c.X) $($c.Y)"
        return $true
    }
    return $false
}

function Tap-Text {
    param([string[]]$Texts, [switch]$Partial, [int]$Retries = 3)
    for ($i = 0; $i -lt $Retries; $i++) {
        $doc = Get-UiDump
        $node = Find-NodeByText -Doc $doc -Texts $Texts -Partial:$Partial
        if ($node) {
            if (Tap-Node $node) {
                Wait-Screen
                return $true
            }
        }
        Wait-Screen -Sec 2
    }
    return $false
}

function Tap-Coord {
    param([int]$X, [int]$Y)
    Invoke-Adb "input tap $X $Y"
    Wait-Screen
}

function Get-AllTexts {
    $doc = Get-UiDump
    if (-not $doc) { return @() }
    $texts = @()
    foreach ($n in $doc.SelectNodes("//node[@text]")) {
        if ($n.text -and $n.text.Trim().Length -gt 0) { $texts += $n.text.Trim() }
    }
    return ($texts | Select-Object -Unique)
}

function Ensure-LoggedIn {
    $doc = Get-UiDump
    $loginNode = Find-NodeByText -Doc $doc -Texts @("Sign In","Login","Retailer ID","EPDEMO001") -Partial
    $texts = Get-AllTexts
    if ($texts -match "Sign In|Retailer ID|Enter PIN|Login") {
        Write-Host "Login screen detected - logging in..."
        # Tap retailer ID field area (landscape tablet ~1280x800)
        Tap-Coord 640 350
        Invoke-Adb "input text EPDEMO001"
        Wait-Screen -Sec 2
        Tap-Coord 640 420
        Invoke-Adb "input text 1234"
        Wait-Screen -Sec 2
        Tap-Text @("Sign In") -Partial
        Wait-Screen -Sec 6
        return $true
    }
    return $false
}

function Go-Home {
    Invoke-Adb "am task lock stop" | Out-Null
    & $ADB -s $SERIAL shell am start -n $MAIN -a android.intent.action.MAIN 2>&1 | Out-Null
    Wait-Screen -Sec 5
    Ensure-LoggedIn | Out-Null
    # Tap Home in bottom nav if visible
    Tap-Text @("Home") -Partial
    Wait-Screen -Sec 3
}

function Record-Test {
    param([int]$Num, [string]$Type, [string]$Result, [string]$Screenshot, [string]$Notes)
    $script:Results += [PSCustomObject]@{
        Num = $Num; Type = $Type; Result = $Result
        Screenshot = $Screenshot; Notes = $Notes
    }
}

function Run-Test1 {
    Write-Host "=== Test 1: E-Load Globe ==="
    Go-Home
    Tap-Text @("LOAD") -Partial
    Tap-Text @("Globe") -Partial
    Tap-Text @("Globe 5","Globe5","5","₱5","PHP 5") -Partial
    Tap-Coord 640 400
    Invoke-Adb "input text 09171234567"
    Wait-Screen -Sec 2
    Tap-Text @("Continue","Proceed","Next","Confirm","Process") -Partial
    Wait-Screen -Sec 4
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-01.png"
    Screenshot $path
    $pass = ($texts -match "09171234567|Globe|Confirm|Process|Amount|5")
    Record-Test 1 "E-Load prepaid (Globe)" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back; Press-Back; Press-Back
}

function Run-Test2 {
    Write-Host "=== Test 2: E-Load DITO ==="
    Go-Home
    Tap-Text @("LOAD") -Partial
    Tap-Text @("DITO") -Partial
    Tap-Text @("DITO 5","DITO5","5","₱5") -Partial
    Tap-Coord 640 400
    Invoke-Adb "input text 09171234567"
    Wait-Screen -Sec 2
    Tap-Text @("Continue","Proceed","Next","Confirm") -Partial
    Wait-Screen -Sec 4
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-02.png"
    Screenshot $path
    $pass = ($texts -match "DITO|09171234567|Confirm|Process|5")
    Record-Test 2 "E-Load DITO" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back; Press-Back; Press-Back
}

function Run-Test3 {
    Write-Host "=== Test 3: Bills Electricity Meralco ==="
    Go-Home
    Tap-Text @("Bills Payment","Bills") -Partial
    Tap-Text @("Electricity") -Partial
    Tap-Text @("Meralco") -Partial
    Wait-Screen -Sec 4
    Tap-Coord 640 350
    Invoke-Adb "input text 1234567890"
    Wait-Screen -Sec 2
    Tap-Coord 640 450
    Invoke-Adb "input text 100"
    Wait-Screen -Sec 2
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-03.png"
    Screenshot $path
    $pass = ($texts -match "Meralco|Account|Amount|Electricity|Bill")
    Record-Test 3 "Bills Electricity (Meralco)" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back; Press-Back; Press-Back
}

function Run-Test4 {
    Write-Host "=== Test 4: Bills Telecommunications PLDT ==="
    Go-Home
    Tap-Text @("Bills Payment","Bills") -Partial
    Tap-Text @("Telecommunications","Telecom") -Partial
    Wait-Screen -Sec 3
    $preTexts = (Get-AllTexts) -join " | "
    Tap-Text @("PLDT") -Partial
    Wait-Screen -Sec 4
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-04.png"
    Screenshot $path
    $isPrepaid = ($texts -match "Globe 5|Globe prepaid|Smart 5|prepaid load|LOAD|E-Load|Eload" -and $texts -notmatch "Postpaid|PLDT|Account")
    $isPostpaid = ($texts -match "PLDT|Postpaid|Account|Bill|Telecom")
    $pass = $isPostpaid -and -not $isPrepaid
    $notes = "Screen: $texts | Prepaid leak: $isPrepaid | Postpaid OK: $isPostpaid | Category screen had: $preTexts"
    Record-Test 4 "Bills Telecommunications (PLDT postpaid)" $(if($pass){"PASS"}else{"FAIL"}) $path $notes
    Press-Back; Press-Back; Press-Back
}

function Run-Test5 {
    Write-Host "=== Test 5: Bills Water Maynilad ==="
    Go-Home
    Tap-Text @("Bills Payment","Bills") -Partial
    Tap-Text @("Water") -Partial
    Tap-Text @("Maynilad","Manila Water","Prime Water") -Partial
    Wait-Screen -Sec 4
    Tap-Coord 640 350
    Invoke-Adb "input text 9876543210"
    Wait-Screen -Sec 2
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-05.png"
    Screenshot $path
    $pass = ($texts -match "Maynilad|Water|Account|Manila|Bill")
    Record-Test 5 "Bills Water (Maynilad)" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back; Press-Back; Press-Back
}

function Run-Test6 {
    Write-Host "=== Test 6: Cash-in GCash ==="
    Go-Home
    Tap-Text @("Cash-in","Cash in") -Partial
    Tap-Text @("GCash") -Partial
    Wait-Screen -Sec 4
    Tap-Coord 640 400
    Invoke-Adb "input text 100"
    Wait-Screen -Sec 2
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-06.png"
    Screenshot $path
    $pass = ($texts -match "GCash|Amount|Cash|Wallet|100")
    Record-Test 6 "Cash-in GCash" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back; Press-Back
}

function Run-Test7 {
    Write-Host "=== Test 7: Cash-in Maya ==="
    Go-Home
    Tap-Text @("Cash-in","Cash in") -Partial
    Tap-Text @("Maya","PayMaya") -Partial
    Wait-Screen -Sec 4
    Tap-Coord 640 400
    Invoke-Adb "input text 100"
    Wait-Screen -Sec 2
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-07.png"
    Screenshot $path
    $pass = ($texts -match "Maya|PayMaya|Amount|Cash|100")
    Record-Test 7 "Cash-in Maya" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back; Press-Back
}

function Run-Test8 {
    Write-Host "=== Test 8: RFID EasyTrip ==="
    Go-Home
    Tap-Text @("RFID") -Partial
    Tap-Text @("EasyTrip","Easy Trip") -Partial
    Wait-Screen -Sec 4
    Tap-Coord 640 400
    Invoke-Adb "input text 123456789012"
    Wait-Screen -Sec 2
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-08.png"
    Screenshot $path
    $pass = ($texts -match "EasyTrip|Account|RFID|Tag|12")
    Record-Test 8 "RFID EasyTrip" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back; Press-Back
}

function Run-Test9 {
    Write-Host "=== Test 9: RFID Autosweep ==="
    Go-Home
    Tap-Text @("RFID") -Partial
    Tap-Text @("Autosweep","Auto Sweep") -Partial
    Wait-Screen -Sec 4
    Tap-Coord 640 400
    Invoke-Adb "input text ABC1234"
    Wait-Screen -Sec 2
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-09.png"
    Screenshot $path
    $pass = ($texts -match "Autosweep|RFID|Account|plate|Tag")
    Record-Test 9 "RFID Autosweep" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back; Press-Back
}

function Run-Test10 {
    Write-Host "=== Test 10: Transaction History ==="
    Go-Home
    $found = Tap-Text @("Transactions","Transaction History","History") -Partial
    if (-not $found) {
        Tap-Text @("Settings") -Partial
        Wait-Screen -Sec 3
        Tap-Text @("Transaction History","Transactions","History") -Partial
    }
    Wait-Screen -Sec 5
    $texts = (Get-AllTexts) -join " | "
    $path = "$DL\txn-test-10.png"
    Screenshot $path
    $pass = ($texts -match "Transaction|History|Globe|DITO|Meralco|PLDT|GCash|Maya|EasyTrip|No transactions|Recent")
    Record-Test 10 "Transaction History" $(if($pass){"PASS"}else{"FAIL"}) $path $texts
    Press-Back
}

# ===== MAIN =====
Write-Host "ePayPlus 10-Transaction Test Suite"
Write-Host "Device: $SERIAL"

# Part 1: Deep scan brief
$model = Invoke-Adb "getprop ro.product.model"
$version = Invoke-Adb "getprop ro.build.version.release"
$battery = Invoke-Adb "dumpsys battery" | Select-String "level|status"
$wifi = Invoke-Adb "dumpsys wifi" | Select-String "mWifiInfo SSID" | Select-Object -First 1

$scanInfo = @"
ePayPlus 10-Transaction Test Report
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
Device Serial: $SERIAL
Model: $model
Android: $version
Battery: $($battery -join ', ')
WiFi: $($wifi -join ', ')
Package: $PKG v3.1.9
"@

# Unlock
Invoke-Adb "input keyevent KEYCODE_WAKEUP"
Wait-Screen -Sec 2
Invoke-Adb "wm dismiss-keyguard" | Out-Null
Tap-Coord 640 700  # swipe up approx
Invoke-Adb "input swipe 640 700 640 300 300"
Wait-Screen -Sec 2

# Stop lock task, launch MainActivity
Invoke-Adb "am task lock stop"
& $ADB -s $SERIAL shell am force-stop $PKG 2>&1 | Out-Null
Wait-Screen -Sec 2
& $ADB -s $SERIAL shell am start -n $MAIN 2>&1 | Out-Null
Wait-Screen -Sec 6
Ensure-LoggedIn | Out-Null
Wait-Screen -Sec 3

# Home screenshot
$homePath = "$DL\txn-test-home.png"
Screenshot $homePath
Write-Host "Home screenshot: $homePath"

# Run all tests
Run-Test1
Run-Test2
Run-Test3
Run-Test4
Run-Test5
Run-Test6
Run-Test7
Run-Test8
Run-Test9
Run-Test10

# Logcat
$logcat = Invoke-Adb "logcat -d -t 200 | grep -i epayplus"

# Build report
$report = $scanInfo + "`n`nHome Screenshot: $homePath`n`n"
$report += "=" * 80 + "`nTEST RESULTS`n" + "=" * 80 + "`n`n"
$report += "{0,-4} {1,-35} {2,-6} {3}`n" -f "#", "Type", "Result", "Screenshot"
$report += "-" * 120 + "`n"
foreach ($r in $script:Results) {
    $report += "{0,-4} {1,-35} {2,-6} {3}`n" -f $r.Num, $r.Type, $r.Result, $r.Screenshot
    $report += "     Notes: $($r.Notes)`n`n"
}

$passCount = ($script:Results | Where-Object { $_.Result -eq "PASS" }).Count
$failCount = ($script:Results | Where-Object { $_.Result -eq "FAIL" }).Count
$report += "`nSummary: $passCount PASS / $failCount FAIL out of $($script:Results.Count) tests`n`n"
$report += "=" * 80 + "`nLOGCAT (last 200, grep epayplus)`n" + "=" * 80 + "`n"
$report += $logcat

Set-Content -Path $REPORT -Value $report -Encoding UTF8
Write-Host "`nReport saved: $REPORT"
Write-Host "PASS: $passCount  FAIL: $failCount"

# Output results as JSON for parsing
$script:Results | ConvertTo-Json | Set-Content "$env:TEMP\txn-results.json"
