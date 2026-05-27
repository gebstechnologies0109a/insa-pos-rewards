# ePayPlus ADB login — matches LoginScreen.kt + LoginRequest API
# Fields: Mobile Number + PIN (max 6 digits). Button: "Sign In".
# Credentials: 09171234567 / 1234 (EPayPlusSeeder.php, retailer EPDEMO001)
param(
    [string]$Adb = "C:\Users\Admin\Android\Sdk\platform-tools\adb.exe",
    [string]$Serial = "",
    [string]$MobileNumber = "09171234567",
    [string]$Pin = "1234",
    [string]$Package = "com.epayplus.v2.debug",
    [string]$ScreenshotOut = "C:\Users\Admin\Downloads\epayplus-logged-in.png",
    [switch]$ClearData,
    [switch]$SkipLaunch
)

$ErrorActionPreference = "Stop"

function Get-AdbSerial {
    param([string]$AdbPath)
    $lines = & $AdbPath devices -l 2>&1 | Where-Object { $_ -match "^\S+\s+device" }
    if (-not $lines) { throw "No ADB device connected." }
    if ($lines.Count -gt 1 -and -not $Serial) {
        Write-Warning "Multiple devices; using first: $($lines[0].Split()[0])"
    }
    return ($lines[0] -split '\s+')[0]
}

function Invoke-Adb {
    param([string[]]$Args)
    & $script:Adb -s $script:Serial @Args 2>&1 | Out-String
}

function Wait-Sec { param([int]$Sec = 2) Start-Sleep -Seconds $Sec }

$dumpDevice = "/sdcard/epay_login_ui.xml"
$dumpLocal = "$env:TEMP\epay_login_ui.xml"

function Get-UiXml {
    Invoke-Adb @("shell", "uiautomator", "dump", $dumpDevice) | Out-Null
    Start-Sleep -Milliseconds 350
    Invoke-Adb @("pull", $dumpDevice, $dumpLocal) | Out-Null
    if (Test-Path $dumpLocal) { return Get-Content $dumpLocal -Raw -Encoding UTF8 }
    return ""
}

function Get-BoundsCenter {
    param([string]$bounds)
    if ($bounds -match '\[(\d+),(\d+)\]\[(\d+),(\d+)\]') {
        return @{
            X = [int](([int]$Matches[1] + [int]$Matches[3]) / 2)
            Y = [int](([int]$Matches[2] + [int]$Matches[4]) / 2)
        }
    }
    return $null
}

function Find-Bounds {
    param([string]$Xml, [string[]]$Terms, [switch]$EditableOnly)
    foreach ($m in [regex]::Matches($Xml, '<node[^>]+>')) {
        $n = $m.Value
        $hit = $false
        foreach ($t in $Terms) {
            if ($n -match [regex]::Escape($t)) { $hit = $true; break }
        }
        if (-not $hit) { continue }
        if ($EditableOnly -and $n -notmatch 'class="android\.widget\.EditText"') { continue }
        if ($n -match 'bounds="(\[[^\]]+\]\[[^\]]+\])"') {
            return $Matches[1]
        }
    }
    return $null
}

function Tap-Bounds {
    param([string]$bounds)
    $c = Get-BoundsCenter $bounds
    if ($c) {
        Invoke-Adb @("shell", "input", "tap", "$($c.X)", "$($c.Y)")
        return $true
    }
    return $false
}

function Tap-LabelField {
    param([string[]]$LabelTerms, [int]$FallbackX, [int]$FallbackY)
    $xml = Get-UiXml
    $labelBounds = Find-Bounds -Xml $xml -Terms $LabelTerms
    if ($labelBounds) {
        Tap-Bounds $labelBounds | Out-Null
        Wait-Sec 1
        $xml = Get-UiXml
        $edit = Find-Bounds -Xml $xml -Terms @('android.widget.EditText') -EditableOnly
        if ($edit) { Tap-Bounds $edit | Out-Null; return }
    }
    $edits = [regex]::Matches($xml, '<node[^>]+class="android\.widget\.EditText"[^>]+bounds="(\[[^\]]+\]\[[^\]]+\])"')
    if ($LabelTerms -contains "PIN" -and $edits.Count -ge 2) {
        Tap-Bounds $edits[1].Groups[1].Value | Out-Null
        return
    }
    if ($edits.Count -ge 1) {
        Tap-Bounds $edits[0].Groups[1].Value | Out-Null
        return
    }
    Invoke-Adb @("shell", "input", "tap", "$FallbackX", "$FallbackY") | Out-Null
}

function Clear-And-Type {
    param([string]$Text)
    Invoke-Adb @("shell", "input", "keyevent", "KEYCODE_MOVE_END") | Out-Null
    for ($i = 0; $i -lt 24; $i++) {
        Invoke-Adb @("shell", "input", "keyevent", "KEYCODE_DEL") | Out-Null
    }
    $escaped = $Text -replace ' ', '%s'
    Invoke-Adb @("shell", "input", "text", $escaped) | Out-Null
}

function Tap-SignIn {
    $xml = Get-UiXml
    $b = Find-Bounds -Xml $xml -Terms @("Sign In", "Login")
    if ($b) { Tap-Bounds $b | Out-Null; return $true }
    $b = Find-Bounds -Xml $xml -Terms @("Sign in", "sign in")
    if ($b) { Tap-Bounds $b | Out-Null; return $true }
    return $false
}

function Save-Screenshot {
    param([string]$Path)
    $remote = "/sdcard/epay_login_ok.png"
    Invoke-Adb @("shell", "screencap", "-p", $remote) | Out-Null
    Start-Sleep -Milliseconds 400
    if (Test-Path $Path) { Remove-Item $Path -Force -ErrorAction SilentlyContinue }
    Invoke-Adb @("pull", $remote, $Path) | Out-Null
    return (Test-Path $Path) -and ((Get-Item $Path).Length -gt 3000)
}

function Test-LoggedIn {
    $xml = Get-UiXml
    return ($xml -match "Quick Services|E-Load Wallet|Dual Wallets|Today's Sales|Demo ePayPlus")
}

# --- Main ---
if (-not $Serial) { $Serial = Get-AdbSerial -AdbPath $Adb }
Write-Host "ADB device: $Serial" -ForegroundColor Cyan
Write-Host "Package: $Package | Mobile: $MobileNumber" -ForegroundColor Cyan

$main = "$Package/com.epayplus.v2.ui.MainActivity"

Invoke-Adb @("shell", "input", "keyevent", "KEYCODE_WAKEUP") | Out-Null
Invoke-Adb @("shell", "wm", "dismiss-keyguard") | Out-Null

if ($ClearData) {
    Write-Host "Clearing app data..." -ForegroundColor Yellow
    Invoke-Adb @("shell", "pm", "clear", $Package) | Out-Null
    Wait-Sec 3
}

if (-not $SkipLaunch) {
    Invoke-Adb @("shell", "am", "force-stop", $Package) | Out-Null
    Wait-Sec 1
    Invoke-Adb @("shell", "monkey", "-p", $Package, "-c", "android.intent.category.LAUNCHER", "1") | Out-Null
    Wait-Sec 4
}

$xml = Get-UiXml
if ($xml -match "ePayPlus Setup|Server URL|License") {
    Write-Warning "Setup wizard detected but is not in MainActivity nav — clear data or complete setup manually."
}

if (Test-LoggedIn) {
    Write-Host "Already logged in." -ForegroundColor Green
    Save-Screenshot $ScreenshotOut | Out-Null
    exit 0
}

if ($xml -notmatch "Mobile Number|Sign In|Welcome Back") {
    Write-Warning "Login screen not detected. UI texts may differ; attempting login anyway."
}

Write-Host "Filling Mobile Number..." -ForegroundColor Yellow
Tap-LabelField -LabelTerms @("Mobile Number", "Mobile", "09") -FallbackX 640 -FallbackY 380
Wait-Sec 1
Clear-And-Type $MobileNumber
Wait-Sec 1

Write-Host "Filling PIN..." -ForegroundColor Yellow
Tap-LabelField -LabelTerms @("PIN") -FallbackX 640 -FallbackY 480
Wait-Sec 1
Clear-And-Type $Pin
Wait-Sec 1

Write-Host "Tapping Sign In..." -ForegroundColor Yellow
if (-not (Tap-SignIn)) {
    Invoke-Adb @("shell", "input", "tap", "640", "580") | Out-Null
}
Wait-Sec 6

$log = Invoke-Adb @("logcat", "-d", "-t", "80", "OkHttp:I", "AndroidRuntime:E", "*:S")
$log | Out-File "$env:TEMP\epay_login_logcat.txt" -Encoding UTF8
if ($log -match "401|Invalid mobile|Invalid account|Connection error|FATAL") {
    Write-Warning "Logcat may show errors — see $env:TEMP\epay_login_logcat.txt"
}

if (Test-LoggedIn) {
    Write-Host "Login successful." -ForegroundColor Green
    Save-Screenshot $ScreenshotOut | Out-Null
    Write-Host "Screenshot: $ScreenshotOut"
    exit 0
}

$xml2 = Get-UiXml
if ($xml2 -match "Invalid mobile|Invalid account|Please enter|Login failed|Connection error") {
    $err = [regex]::Match($xml2, 'text="([^"]*(?:Invalid|failed|error|enter)[^"]*)"').Groups[1].Value
    Write-Error "Login failed on device. Message: $err"
}

Save-Screenshot $ScreenshotOut | Out-Null
Write-Error "Home screen not detected after login. Screenshot saved for review: $ScreenshotOut"
