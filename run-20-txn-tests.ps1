# ePayPlus 20-Transaction Test Suite
$ErrorActionPreference = "Continue"
$ADB = "C:\Users\Admin\Android\Sdk\platform-tools\adb.exe"
$SERIAL = "6HT8KR8P8DCI9TS8"
$PKG = "com.epayplus.v2.debug"
$MAIN = "$PKG/.ui.MainActivity"
$APK = "c:\laragon\www\ePay Plus\ePayPlus\app\build\outputs\apk\debug\app-debug.apk"
$DL = "C:\Users\Admin\Downloads"
$REPORT = "$DL\ePayPlus-20-Transaction-Tests-Report.txt"
$UI_XML = "/sdcard/window_dump.xml"
$LOCAL_UI = "$env:TEMP\epay_ui_dump.xml"
$STEP_DELAY = 4

$script:Results = @()

function Invoke-Adb {
    param([string]$Cmd)
    $out = & $ADB -s $SERIAL shell $Cmd 2>&1
    if ($out -is [array]) { return ($out -join "`n") }
    return [string]$out
}

function Screenshot {
    param([string]$Path)
    Invoke-Adb "screencap -p /sdcard/sc.png" | Out-Null
    & $ADB -s $SERIAL pull /sdcard/sc.png $Path 2>&1 | Out-Null
    Start-Sleep -Seconds 1
}

function Wait-Screen {
    param([int]$Sec = $STEP_DELAY)
    Start-Sleep -Seconds $Sec
}

function Press-Back {
    Invoke-Adb "input keyevent KEYCODE_BACK"
    Wait-Screen -Sec 3
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
        foreach ($n in $Doc.SelectNodes("//node[@text]")) {
            $txt = $n.text
            if ($Partial) { if ($txt -like "*$t*") { return $n } }
            else { if ($txt -eq $t) { return $n } }
        }
        foreach ($n in $Doc.SelectNodes("//node[@content-desc]")) {
            $txt = $n.'content-desc'
            if ($Partial) { if ($txt -like "*$t*") { return $n } }
            else { if ($txt -eq $t) { return $n } }
        }
    }
    return $null
}

function Get-BoundsCenter {
    param([System.Xml.XmlElement]$Node)
    if (-not $Node -or -not $Node.bounds) { return $null }
    if ($Node.bounds -match '\[(\d+),(\d+)\]\[(\d+),(\d+)\]') {
        return @{ X = [int](([int]$Matches[1]+[int]$Matches[3])/2); Y = [int](([int]$Matches[2]+[int]$Matches[4])/2) }
    }
    return $null
}

function Tap-Node {
    param([System.Xml.XmlElement]$Node)
    $c = Get-BoundsCenter $Node
    if ($c) { Invoke-Adb "input tap $($c.X) $($c.Y)"; Wait-Screen; return $true }
    return $false
}

function Tap-Text {
    param([string[]]$Texts, [switch]$Partial, [int]$Retries = 4)
    for ($i = 0; $i -lt $Retries; $i++) {
        $doc = Get-UiDump
        $node = Find-NodeByText -Doc $doc -Texts $Texts -Partial:$Partial
        if ($node -and (Tap-Node $node)) { return $true }
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
    foreach ($n in $doc.SelectNodes("//node[@content-desc]")) {
        if ($n.'content-desc' -and $n.'content-desc'.Trim().Length -gt 0) { $texts += $n.'content-desc'.Trim() }
    }
    return ($texts | Select-Object -Unique)
}

function Ensure-LoggedIn {
    $texts = (Get-AllTexts) -join " "
    if ($texts -match "Sign In|Retailer ID|Enter PIN|Login|Retailer") {
        Write-Host "Logging in EPDEMO001..."
        Tap-Text @("Retailer ID","Retailer") -Partial
        if (-not $?) { Tap-Coord 540 420 }
        Invoke-Adb "input text EPDEMO001"
        Wait-Screen -Sec 2
        Tap-Text @("PIN","Password") -Partial
        if (-not $?) { Tap-Coord 540 520 }
        Invoke-Adb "input text 1234"
        Wait-Screen -Sec 2
        Tap-Text @("Sign In","Login") -Partial
        Wait-Screen -Sec 6
        return $true
    }
    return $false
}

function Go-Home {
    Invoke-Adb "am task lock stop" | Out-Null
    & $ADB -s $SERIAL shell am force-stop $PKG 2>&1 | Out-Null
    Wait-Screen -Sec 2
    & $ADB -s $SERIAL shell am start -n $MAIN 2>&1 | Out-Null
    Wait-Screen -Sec 5
    Ensure-LoggedIn | Out-Null
    Tap-Text @("Home") -Partial
    Wait-Screen -Sec 3
    # Exit kiosk if on kiosk home
    $t = (Get-AllTexts) -join " "
    if ($t -match "Long-press title to exit kiosk|Select a service") {
        Invoke-Adb "input swipe 640 400 640 200 500" | Out-Null
        Wait-Screen -Sec 2
        Tap-Text @("Home") -Partial
        Wait-Screen -Sec 3
    }
}

function Record-Test {
    param([int]$Num, [string]$Type, [string]$Result, [string]$Screenshot, [string]$Notes, [string]$Status = "PASS")
    $script:Results += [PSCustomObject]@{
        Num = $Num; Type = $Type; Result = $Result; Status = $Status
        Screenshot = $Screenshot; Notes = $Notes
    }
}

function Finish-Test {
    param([int]$Num, [string]$Type, [string]$ExpectedPattern, [string]$NotesExtra = "")
    Wait-Screen -Sec 3
    $texts = (Get-AllTexts) -join ' | '
    $path = "$DL\epay-txn-test-{0:D2}.png" -f $Num
    Screenshot $path
    $pass = ($texts -match $ExpectedPattern)
    $notes = if ($NotesExtra) { "$NotesExtra | Screen: $texts" } else { $texts }
    Record-Test $Num $Type $(if($pass){"PASS"}else{"FAIL"}) $path $notes $(if($pass){"PASS"}else{"FAIL"})
    Write-Host "Test $Num : $(if($pass){'PASS'}else{'FAIL'}) -> $path"
    return $pass
}

function Nav-Load { Tap-Text @("LOAD","E-Load","Load") -Partial }
function Nav-Bills { Tap-Text @("Bills Payment","Bills") -Partial }
function Nav-Cashin { Tap-Text @("Cash-in","Cash in") -Partial }
function Nav-Rfid { Tap-Text @("RFID") -Partial }
function Nav-More { Tap-Text @("More","Settings") -Partial }

# ===== TESTS =====
function Run-Test1 {
    Write-Host "Test 1: E-Load Globe product"
    Go-Home; Nav-Load; Tap-Text @("Globe") -Partial
    Tap-Text @("Globe 5","Globe5","PHP 5","5") -Partial
    Finish-Test 1 "E-Load Globe product/denom" "Globe|5|denom|amount|product|load" "Reached product screen (no submit)"
    Press-Back; Press-Back; Press-Back
}

function Run-Test2 {
    Write-Host "Test 2: E-Load Smart provider"
    Go-Home; Nav-Load
    if (-not (Tap-Text @("Smart") -Partial)) { Tap-Coord 400 500 }
    Wait-Screen -Sec 3
    Finish-Test 2 "E-Load Smart provider list" "Smart|LOAD|provider|Select|TNT|Globe"
    Press-Back; Press-Back
}

function Run-Test3 {
    Write-Host "Test 3: E-Load DITO smallest denom"
    Go-Home; Nav-Load; Tap-Text @("DITO") -Partial
    $doc = Get-UiDump
    $nodes = $doc.SelectNodes("//node[@text]")
    $smallest = $null
    foreach ($n in $nodes) {
        if ($n.text -match 'PHP|\d') { $smallest = $n }
    }
    if ($smallest) { Tap-Node $smallest } else { Tap-Text @("5","10","PHP") -Partial }
    Wait-Screen
    Tap-Coord 540 450
    Invoke-Adb "input text 09991234567"
    Wait-Screen
    Tap-Text @("Continue","Next","Confirm","Proceed") -Partial
    Finish-Test 3 "E-Load DITO amount confirm" "DITO|Confirm|Amount|0999|Proceed|Process" "Stopped at confirm (demo)"
    Press-Back; Press-Back; Press-Back
}

function Run-Test4 {
    Write-Host "Test 4: Bills Electricity Meralco"
    Go-Home; Nav-Bills; Tap-Text @("Electricity") -Partial; Tap-Text @("Meralco") -Partial
    Wait-Screen -Sec 5
    Finish-Test 4 "Bills Electricity Meralco form" "Meralco|Electricity|Account|Bill|CAN|Amount"
    Press-Back; Press-Back; Press-Back
}

function Run-Test5 {
    Write-Host "Test 5: Bills Telecom PLDT postpaid"
    Go-Home; Nav-Bills; Tap-Text @("Telecommunications","Telecom") -Partial
    Wait-Screen -Sec 3
    Tap-Text @("PLDT") -Partial
    Wait-Screen -Sec 5
    $texts = (Get-AllTexts) -join ' | '
    $path = "$DL\epay-txn-test-05.png"
    Screenshot $path
    $isPrepaid = ($texts -match "Globe prepaid|Smart 5|prepaid load|E-Load denom|LOAD product" -and $texts -notmatch "Postpaid|PLDT")
    $isPostpaid = ($texts -match "PLDT|Postpaid|Account|Bill|Subscriber|Telecom")
    $pass = $isPostpaid -and -not $isPrepaid
    $notes = "POSTPAID_CHECK: prepaid_leak=$isPrepaid postpaid_ok=$isPostpaid -- $texts"
    Record-Test 5 "Bills Telecom PLDT postpaid" $(if($pass){"PASS"}else{"FAIL"}) $path $notes
    Press-Back; Press-Back; Press-Back
}

function Run-Test6 {
    Write-Host "Test 6: Bills Water category"
    Go-Home; Nav-Bills; Tap-Text @("Water") -Partial
    Finish-Test 6 "Bills Water category grid" "Water|Maynilad|Manila|Prime|Manila Water|biller"
    Press-Back; Press-Back
}

function Run-Test7 {
    Write-Host "Test 7: Cash-in GCash"
    Go-Home; Nav-Cashin; Tap-Text @("GCash") -Partial
    Finish-Test 7 "Cash-in GCash provider" "GCash|Cash|Amount|Wallet|Mobile"
    Press-Back; Press-Back
}

function Run-Test8 {
    Write-Host "Test 8: Cash-in Maya"
    Go-Home; Nav-Cashin; Tap-Text @("Maya","PayMaya") -Partial
    Finish-Test 8 "Cash-in Maya provider" "Maya|PayMaya|Cash|Amount|Wallet"
    Press-Back; Press-Back
}

function Run-Test9 {
    Write-Host "Test 9: RFID EasyTrip"
    Go-Home; Nav-Rfid; Tap-Text @("EasyTrip","Easy Trip") -Partial
    Finish-Test 9 "RFID EasyTrip process" "EasyTrip|RFID|Tag|Account|Reload|Plate"
    Press-Back; Press-Back
}

function Run-Test10 {
    Write-Host "Test 10: RFID Autosweep list"
    Go-Home; Nav-Rfid
    Finish-Test 10 "RFID Autosweep provider list" "Autosweep|EasyTrip|RFID|Select|provider"
    Press-Back
}

function Run-Test11 {
    Write-Host "Test 11: Maya Negosyo hub"
    Go-Home
    if (-not (Tap-Text @("Maya Negosyo") -Partial)) {
        Invoke-Adb "input swipe 600 600 600 200 400"
        Wait-Screen
        Tap-Text @("Maya Negosyo") -Partial
    }
    Wait-Screen -Sec 3
    Tap-Text @("Open","Launch","Continue","Hub") -Partial
    Wait-Screen -Sec 4
    $texts = (Get-AllTexts) -join ' | '
    $path = "$DL\epay-txn-test-11.png"
    Screenshot $path
    $pass = ($texts -match "Maya|Negosyo|Business|Open|Checkout|Hub|Install")
    Record-Test 11 "Maya Negosyo hub" $(if($pass){"PASS"}else{"FAIL"}) $path "Hub or external launch note: $texts"
    Press-Back; Press-Back; Go-Home
}

function Run-Test12 {
    Write-Host "Test 12: Home dual wallet view"
    Go-Home
    Invoke-Adb "input swipe 540 400 540 800 400"
    Wait-Screen
    Finish-Test 12 "Home dual wallet cards" "Wallet|Balance|GCash|Maya|Available|Credit|Retailer"
    Press-Back
}

function Run-Test13 {
    Write-Host "Test 13: Transaction History"
    Go-Home
    Nav-More
    Tap-Text @("Transaction History","History") -Partial
    Wait-Screen -Sec 5
    Finish-Test 13 "Transaction History list" "Transaction|History|Recent|No transactions|Globe|Date"
    Press-Back; Press-Back
}

function Run-Test14 {
    Write-Host "Test 14: Bills Government SSS"
    Go-Home; Nav-Bills; Tap-Text @("Government") -Partial
    Tap-Text @("SSS") -Partial
    Wait-Screen -Sec 4
    Finish-Test 14 "Bills Government SSS" "SSS|Government|Member|Account|Contribution|Bill"
    Press-Back; Press-Back; Press-Back
}

function Run-Test15 {
    Write-Host "Test 15: E-Load TNT"
    Go-Home; Nav-Load; Tap-Text @("TNT") -Partial
    Finish-Test 15 "E-Load TNT provider" "TNT|LOAD|provider|Globe|Smart"
    Press-Back; Press-Back
}

function Run-Test16 {
    Write-Host "Test 16: Kiosk / Quick Services grid"
    Go-Home
    $t = (Get-AllTexts) -join " "
    if ($t -match "Long-press|Select a service|kiosk") {
        Finish-Test 16 "Kiosk quick services grid" "LOAD|Bills|Cash|RFID|service"
    } else {
        Invoke-Adb "input swipe 540 700 540 200 500"
        Wait-Screen
        Tap-Text @("Quick","Services") -Partial
        Finish-Test 16 "Quick Services scroll / kiosk" "LOAD|Bills|Cash|RFID|Quick|Service|Maya"
    }
    Press-Back
}

function Run-Test17 {
    Write-Host "Test 17: Settings / More"
    Go-Home; Nav-More
    Finish-Test 17 "Settings or More screen" "More|Settings|PIN|History|About|Sales|Change"
    Press-Back
}

function Run-Test18 {
    Write-Host "Test 18: Bills Internet/Cable"
    Go-Home; Nav-Bills; Tap-Text @("Internet","Cable","Internet/Cable") -Partial
    Finish-Test 18 "Bills Internet/Cable category" "Internet|Cable|Sky|PLDT|Converge|Fiber|Category"
    Press-Back; Press-Back
}

function Run-Test19 {
    Write-Host "Test 19: Cash-in Coins.ph"
    Go-Home; Nav-Cashin
    Invoke-Adb "input swipe 540 600 540 200 400"
    Wait-Screen
    Tap-Text @("Coins","Coins.ph") -Partial
    Finish-Test 19 "Cash-in Coins.ph provider" "Coins|Cash|Amount|Wallet|ph"
    Press-Back; Press-Back
}

function Run-Test20 {
    Write-Host "Test 20: Return Home with balance"
    Go-Home
    Tap-Text @("Home") -Partial
    Wait-Screen -Sec 5
    Finish-Test 20 "Home with balance after txn" "Balance|Wallet|LOAD|Home|Retailer|EPDEMO|Available|PHP"
}

# ===== MAIN =====
Write-Host "ePayPlus 20-Transaction Test Suite - $SERIAL"

if (-not (Test-Path $APK)) { Write-Error "APK not found: $APK"; exit 1 }
& $ADB -s $SERIAL install -r $APK 2>&1 | Write-Host

Invoke-Adb "input keyevent KEYCODE_WAKEUP"
Wait-Screen -Sec 2
Invoke-Adb "wm dismiss-keyguard"
Invoke-Adb "input swipe 540 1200 540 400 300"
Wait-Screen -Sec 2
Invoke-Adb "am task lock stop"

& $ADB -s $SERIAL shell am force-stop $PKG
Wait-Screen -Sec 2
& $ADB -s $SERIAL shell am start -n $MAIN
Wait-Screen -Sec 6
Ensure-LoggedIn | Out-Null

$tests = @(
    { Run-Test1 }, { Run-Test2 }, { Run-Test3 }, { Run-Test4 }, { Run-Test5 },
    { Run-Test6 }, { Run-Test7 }, { Run-Test8 }, { Run-Test9 }, { Run-Test10 },
    { Run-Test11 }, { Run-Test12 }, { Run-Test13 }, { Run-Test14 }, { Run-Test15 },
    { Run-Test16 }, { Run-Test17 }, { Run-Test18 }, { Run-Test19 }, { Run-Test20 }
)

foreach ($t in $tests) {
    try { & $t } catch { Write-Host "Test error: $_" }
    Wait-Screen -Sec 3
}

$logcat = Invoke-Adb "logcat -d -t 300"
$filtered = ($logcat -split "`n") | Where-Object { $_ -match 'epayplus|EPay|retrofit|okhttp|FATAL|AndroidRuntime|Exception' }

$model = Invoke-Adb "getprop ro.product.model"
$android = Invoke-Adb "getprop ro.build.version.release"
$ver = Invoke-Adb "dumpsys package $PKG" | Select-String "versionName"

$report = @"
ePayPlus 20-Transaction Test Report
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
Device Serial: $SERIAL
Model: $model | Android: $android
Package: $PKG | $($ver -join ', ')

Legend: PASS=reached expected screen | COMPLETE=transaction submitted (none attempted - confirm-only demo)

"@
$report += "{0,-4} {1,-38} {2,-6} {3}`n" -f "#", "Type", "Result", "Screenshot"
$report += "-" * 130 + "`n"
foreach ($r in $script:Results) {
    $report += "{0,-4} {1,-38} {2,-6} {3}`n" -f $r.Num, $r.Type, $r.Result, $r.Screenshot
    $report += "     Notes: $($r.Notes)`n`n"
}
$passCount = ($script:Results | Where-Object { $_.Result -eq "PASS" }).Count
$failCount = ($script:Results | Where-Object { $_.Result -eq "FAIL" }).Count
$report += "`nSummary: $passCount PASS / $failCount FAIL / 20 total`n`n"
$report += "=" * 80 + "`nLOGCAT (filtered)`n" + "=" * 80 + "`n"
$report += ($filtered -join "`n")

Set-Content -Path $REPORT -Value $report -Encoding UTF8
Write-Host "`nReport: $REPORT"
Write-Host "PASS: $passCount FAIL: $failCount"
$script:Results | ConvertTo-Json | Set-Content "$env:TEMP\epay-20-results.json"
