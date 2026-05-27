# ePayPlus ADB control tests — 10 scenarios with screenshots
$ErrorActionPreference = "Continue"
$adb = "C:\Users\Admin\Android\Sdk\platform-tools\adb.exe"
$serial = "6HT8KR8P8DCI9TS8"
$pkg = "com.epayplus.v2.debug"
$dumpDevice = "/sdcard/window_dump.xml"
$dumpLocal = "$env:TEMP\epay_ui_dump.xml"
$screensDir = "C:\Users\Admin\Downloads"
$pace = 5
$results = @()

function Adb { param([string[]]$Args) & $adb -s $serial @Args 2>&1 | Out-String }

function Wait-Pace { Start-Sleep -Seconds $pace }

function Dump-Ui {
    Adb @("shell", "uiautomator", "dump", $dumpDevice) | Out-Null
    Start-Sleep -Milliseconds 400
    Adb @("pull", $dumpDevice, $dumpLocal) | Out-Null
    if (Test-Path $dumpLocal) { return Get-Content $dumpLocal -Raw -Encoding UTF8 }
    return ""
}

function Get-BoundsCenter {
    param([string]$bounds)
    if ($bounds -match '\[(\d+),(\d+)\]\[(\d+),(\d+)\]') {
        $cx = [int](([int]$Matches[1] + [int]$Matches[3]) / 2)
        $cy = [int](([int]$Matches[2] + [int]$Matches[4]) / 2)
        return @{ X = $cx; Y = $cy; Raw = $bounds }
    }
    return $null
}

function Find-NodeBounds {
    param([string]$Xml, [string[]]$Terms, [switch]$PreferClickable)
    $nodes = [regex]::Matches($Xml, '<node[^>]+>')
    $candidates = @()
    foreach ($m in $nodes) {
        $n = $m.Value
        $hit = $false
        foreach ($t in $Terms) {
            if ($n -match "text=`"$([regex]::Escape($t))`"" -or $n -match "content-desc=`"$([regex]::Escape($t))`"") {
                $hit = $true
                break
            }
        }
        if (-not $hit) { continue }
        if ($n -match 'bounds="(\[[^\]]+\]\[[^\]]+\])"') {
            $clickable = $n -match 'clickable="true"'
            $candidates += [pscustomobject]@{ Bounds = $Matches[1]; Clickable = $clickable; Node = $n.Substring(0, [Math]::Min(120, $n.Length)) }
        }
    }
    if ($PreferClickable) {
        $pick = $candidates | Where-Object { $_.Clickable } | Select-Object -First 1
        if ($pick) { return $pick.Bounds }
    }
    if ($candidates.Count -gt 0) { return $candidates[0].Bounds }
    return $null
}

function Tap-Terms {
    param([string[]]$Terms, [int]$Retries = 3)
    for ($i = 0; $i -lt $Retries; $i++) {
        $xml = Dump-Ui
        $b = Find-NodeBounds -Xml $xml -Terms $Terms -PreferClickable
        if (-not $b) { $b = Find-NodeBounds -Xml $xml -Terms $Terms }
        if ($b) {
            $c = Get-BoundsCenter $b
            Adb @("shell", "input", "tap", "$($c.X)", "$($c.Y)") | Out-Null
            return $true
        }
        Start-Sleep -Seconds 2
    }
    return $false
}

function Tap-Coords {
    param([int]$X, [int]$Y)
    Adb @("shell", "input", "tap", "$X", "$Y") | Out-Null
}

function Screenshot {
    param([string]$OutPath)
    $remote = "/sdcard/epay_scr.png"
    & $adb -s $serial shell screencap -p $remote | Out-Null
    Start-Sleep -Milliseconds 400
    if (Test-Path $OutPath) { Remove-Item $OutPath -Force -ErrorAction SilentlyContinue }
    & $adb -s $serial pull $remote $OutPath 2>$null | Out-Null
    return ((Test-Path $OutPath) -and ((Get-Item $OutPath).Length -gt 5000))
}

function Check-Logcat {
    $out = Adb @("logcat", "-d", "-t", "40", "*:E")
    $crash = $out -match "FATAL EXCEPTION|AndroidRuntime.*FATAL"
    $pkgCrash = $out -match [regex]::Escape($pkg)
    if ($crash -and $pkgCrash) { return "FAIL (crash)" }
    if ($crash) { return "WARN (other crash)" }
    return "PASS"
}

function Run-Test {
    param([int]$Num, [string]$Action, [scriptblock]$Do, [string[]]$VerifyTerms = @())
    Write-Host "`n=== Test $Num : $Action ===" -ForegroundColor Cyan
    & $Do
    Wait-Pace
    $path = Join-Path $screensDir ("epay-control-test-{0:D2}.png" -f $Num)
    $shotOk = Screenshot $path
    $xml = Dump-Ui
    $verified = $true
    $seen = ""
    if ($VerifyTerms.Count -gt 0) {
        $verified = $false
        foreach ($t in $VerifyTerms) {
            if ($xml -match [regex]::Escape($t)) {
                $verified = $true
                $seen = $t
                break
            }
        }
        if (-not $verified) { $seen = "expected: $($VerifyTerms -join ', ')" }
    } else {
        $seen = "screenshot captured"
    }
    $log = Check-Logcat
    $result = if ($shotOk -and $verified -and $log -notmatch "^FAIL") { "PASS" } elseif (-not $shotOk) { "FAIL (no screenshot)" } elseif ($log -match "^FAIL") { "FAIL ($log)" } else { "PARTIAL" }
    $script:results += [pscustomobject]@{
        Num = $Num; Action = $Action; Result = $result; Path = $path
        Seen = $seen; Logcat = $log
    }
    Write-Host "  -> $result | $path | $seen"
}

function Login-IfNeeded {
    $xml = Dump-Ui
    if ($xml -notmatch "Account ID|Sign In") { return }
    Write-Host "  Login screen detected" -ForegroundColor Yellow
    $acct = Find-NodeBounds -Xml $xml -Terms @("Account ID", "Account") -PreferClickable
    if ($acct) {
        $c = Get-BoundsCenter $acct
        Tap-Coords $c.X $c.Y
    } else { Tap-Coords 540 400 }
    Start-Sleep -Seconds 1
    & $adb -s $serial shell input keyevent KEYCODE_CTRL_A 2>$null | Out-Null
    & $adb -s $serial shell input keyevent KEYCODE_DEL 2>$null | Out-Null
    & $adb -s $serial shell input text "EPDEMO001" | Out-Null
    Wait-Pace
    $xml = Dump-Ui
    $pin = Find-NodeBounds -Xml $xml -Terms @("PIN") -PreferClickable
    if ($pin) {
        $c = Get-BoundsCenter $pin
        Tap-Coords $c.X $c.Y
    } else { Tap-Coords 540 700 }
    Start-Sleep -Seconds 1
    & $adb -s $serial shell input text "1234" | Out-Null
    Wait-Pace
    Tap-Terms @("Sign In", "Login") | Out-Null
    Wait-Pace
    Wait-Pace
}

# --- Setup ---
Write-Host "Device setup + launch..." -ForegroundColor Yellow
& $adb -s $serial shell input keyevent KEYCODE_WAKEUP | Out-Null
& $adb -s $serial shell wm dismiss-keyguard 2>$null | Out-Null
& $adb -s $serial shell input swipe 540 2000 540 800 350 | Out-Null
Start-Sleep -Seconds 2
& $adb -s $serial shell am task lock stop 2>$null | Out-Null
& $adb -s $serial shell settings put system accelerometer_rotation 0 | Out-Null
& $adb -s $serial shell settings put system user_rotation 1 | Out-Null
Start-Sleep -Seconds 1
& $adb -s $serial shell am force-stop $pkg | Out-Null
Start-Sleep -Seconds 1
& $adb -s $serial shell monkey -p $pkg -c android.intent.category.LAUNCHER 1 | Out-Null
Wait-Pace
Login-IfNeeded

# Optional UI dump for test 1 prep
Copy-Item $dumpLocal "C:\Users\Admin\Downloads\epay-control-test-01-ui.xml" -ErrorAction SilentlyContinue

# Test 1: Home
Run-Test -Num 1 -Action "Home screen (dual wallets + Quick Services)" -VerifyTerms @("Quick Services", "Dual Wallets", "E-Load Wallet", "Today's Sales") -Do {
    Tap-Terms @("Home") | Out-Null
    if (-not (Dump-Ui -match "Quick Services")) {
        # already on home or tap home in rail
        Tap-Terms @("Home") | Out-Null
    }
}

# Test 2: E-Load -> Globe
Run-Test -Num 2 -Action "E-Load -> Globe" -VerifyTerms @("Globe", "Select Provider", "E-Load") -Do {
    Tap-Terms @("E-Load", "LOAD") | Out-Null
    Wait-Pace
    Tap-Terms @("Globe") | Out-Null
}

# Test 3: Globe -> scroll Promos
Run-Test -Num 3 -Action "Globe products -> scroll Promos" -VerifyTerms @("Promos", "Promo", "LOAD") -Do {
    Adb @("shell", "input", "swipe", "1200", "900", "1200", "300", "500") | Out-Null
    Start-Sleep -Seconds 2
    Adb @("shell", "input", "swipe", "1200", "900", "1200", "200", "600") | Out-Null
}

# Test 4: Bills -> Telecommunications
Run-Test -Num 4 -Action "Bills -> Telecommunications" -VerifyTerms @("Telecommunications", "PLDT", "Globe Postpaid") -Do {
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Tap-Terms @("Bills Pay", "Bills Payment", "Bills") | Out-Null
    Wait-Pace
    Tap-Terms @("Telecommunications") | Out-Null
}

# Test 5: Bills -> Electricity
Run-Test -Num 5 -Action "Bills -> Electricity" -VerifyTerms @("Electricity", "Meralco", "MERALCO") -Do {
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Tap-Terms @("Electricity") | Out-Null
}

# Test 6: Cash-in -> GCash
Run-Test -Num 6 -Action "Cash-in -> GCash" -VerifyTerms @("GCash", "E-Wallet", "Cash-In", "Cash-in") -Do {
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Tap-Terms @("Cash-In", "Cash-in") | Out-Null
    Wait-Pace
    Tap-Terms @("GCash") | Out-Null
}

# Test 7: RFID -> EasyTrip
Run-Test -Num 7 -Action "RFID -> EasyTrip" -VerifyTerms @("EasyTrip", "RFID", "Easy Trip") -Do {
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Tap-Terms @("RFID") | Out-Null
    Wait-Pace
    Tap-Terms @("EasyTrip") | Out-Null
}

# Test 8: Maya Negosyo hub
Run-Test -Num 8 -Action "Maya Negosyo hub" -VerifyTerms @("Maya Negosyo", "Open Maya", "Negosyo") -Do {
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Tap-Terms @("Home") | Out-Null
    Wait-Pace
    $ok = Tap-Terms @("Maya Negosyo")
    if (-not $ok) {
        # scroll home if needed
        Adb @("shell", "input", "swipe", "1200", "800", "1200", "400", "400") | Out-Null
        Start-Sleep -Seconds 2
        Tap-Terms @("Maya Negosyo") | Out-Null
    }
}

# Test 9: Transaction History
Run-Test -Num 9 -Action "Transaction History" -VerifyTerms @("Transaction History", "Transactions", "No transactions") -Do {
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Tap-Terms @("More") | Out-Null
    Wait-Pace
    if (-not (Tap-Terms @("Transaction History"))) {
        Tap-Terms @("History") | Out-Null
    }
}

# Test 10: Back to Home
Run-Test -Num 10 -Action "Back to Home" -VerifyTerms @("Quick Services", "Dual Wallets", "E-Load Wallet") -Do {
    Adb @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
    Wait-Pace
    Tap-Terms @("Home") | Out-Null
}

Write-Host "`n========== SUMMARY ==========" -ForegroundColor Green
$results | Format-Table -AutoSize
$results | Export-Csv "$screensDir\epay-control-test-results.csv" -NoTypeInformation
