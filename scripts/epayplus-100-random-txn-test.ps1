# ePayPlus 100 Random Transaction Stress Test
# API-heavy (90) + optional ADB UI subset (10)
param(
    [string]$ApiBase = "https://epayplus.diybizrewards.com/api/v2",
    [string]$MobileNumber = "09171234567",
    [string]$Pin = "1234",
    [int]$TotalTests = 100,
    [int]$ApiTests = 90,
    [int]$UiTests = 10,
    [int]$MaxMoneyTxns = 5,
    [string]$Adb = "C:\Android\sdk\platform-tools\adb.exe",
    [string]$Serial = "10.139.7.209:5555",
    [string]$Package = "com.epayplus.v2.debug",
    [string]$ReportOut = "C:\Users\Admin\Downloads\ePayPlus-100-Random-Txn-Report.txt"
)

$ErrorActionPreference = "Continue"
$script:Results = [System.Collections.Generic.List[object]]::new()
$script:EndpointStats = @{}
$script:Token = $null
$script:DeviceId = "stress-test-" + [guid]::NewGuid().ToString("N").Substring(0, 12)
$script:ProductCache = @{}

function Write-Log { param([string]$Msg) Write-Host $Msg }

function Add-Result {
    param(
        [int]$Num, [string]$Category, [string]$Endpoint, [bool]$Pass,
        [int]$LatencyMs, [string]$Detail = ""
    )
    $status = if ($Pass) { "PASS" } else { "FAIL" }
    $script:Results.Add([PSCustomObject]@{
        Num = $Num; Category = $Category; Endpoint = $Endpoint
        Status = $status; LatencyMs = $LatencyMs; Detail = $Detail
    }) | Out-Null
    if (-not $script:EndpointStats.ContainsKey($Endpoint)) {
        $script:EndpointStats[$Endpoint] = @{ Pass = 0; Fail = 0; Latencies = @() }
    }
    if ($Pass) { $script:EndpointStats[$Endpoint].Pass++ } else { $script:EndpointStats[$Endpoint].Fail++ }
    $script:EndpointStats[$Endpoint].Latencies += $LatencyMs
}

function Invoke-Api {
    param(
        [string]$Method, [string]$Path,
        [object]$Body = $null, [hashtable]$Headers = @{},
        [switch]$NoAuth
    )
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $uri = "$ApiBase$Path"
    $hdrs = @{ "Accept" = "application/json"; "Content-Type" = "application/json" }
    if (-not $NoAuth -and $script:Token) { $hdrs["Authorization"] = "Bearer $($script:Token)" }
    foreach ($k in $Headers.Keys) { $hdrs[$k] = $Headers[$k] }
    try {
        $params = @{
            Uri = $uri; Method = $Method; Headers = $hdrs
            TimeoutSec = 30; UseBasicParsing = $true
        }
        if ($Body -ne $null) {
            $params["Body"] = ($Body | ConvertTo-Json -Depth 6 -Compress)
        }
        $resp = Invoke-WebRequest @params
        $sw.Stop()
        $json = $null
        try { $json = $resp.Content | ConvertFrom-Json } catch {}
        return @{
            Ok = ($resp.StatusCode -ge 200 -and $resp.StatusCode -lt 300)
            Status = $resp.StatusCode; LatencyMs = [int]$sw.ElapsedMilliseconds
            Json = $json; Raw = $resp.Content
        }
    } catch {
        $sw.Stop()
        $status = 0; $raw = $_.Exception.Message
        if ($_.Exception.Response) {
            try {
                $status = [int]$_.Exception.Response.StatusCode
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $raw = $reader.ReadToEnd()
                $reader.Close()
            } catch {}
        }
        $json = $null
        try { if ($raw) { $json = $raw | ConvertFrom-Json } } catch {}
        return @{
            Ok = $false; Status = $status; LatencyMs = [int]$sw.ElapsedMilliseconds
            Json = $json; Raw = $raw
        }
    }
}

function Login-Api {
    Write-Log "Logging in via API ($MobileNumber)..."
    $r = Invoke-Api -Method POST -Path "/auth/login" -NoAuth -Body @{
        mobile_number = $MobileNumber; pin = $Pin; device_id = $script:DeviceId
    }
    if ($r.Ok -and $r.Json.success -and $r.Json.token) {
        $script:Token = $r.Json.token
        Write-Log "Login OK (token acquired, latency $($r.LatencyMs)ms)"
        return $true
    }
    Write-Error "Login failed: $($r.Raw)"
    return $false
}

function Get-SmallestEloadProduct {
    if ($script:ProductCache.ContainsKey("eload")) { return $script:ProductCache["eload"] }
    $r = Invoke-Api -Method GET -Path "/products/eload"
    if (-not $r.Ok -or -not $r.Json) { return $null }
    $products = @()
    if ($r.Json.products) { $products = @($r.Json.products) }
    elseif ($r.Json.data) { $products = @($r.Json.data) }
    $pick = $products | Where-Object { $_.code -and ($_.providerCode -or $_.provider_code) } |
        Sort-Object {
            $p = if ($null -ne $_.amount) { $_.amount } elseif ($null -ne $_.retailer_price) { $_.retailer_price } else { 9999 }
            [double]$p
        } | Select-Object -First 1
    if ($pick) { $script:ProductCache["eload"] = $pick }
    return $pick
}

# --- Read-only API endpoints pool ---
$ReadOnlyEndpoints = @(
    @{ Method = "GET"; Path = "/health"; Label = "GET /health" },
    @{ Method = "GET"; Path = "/products/providers"; Label = "GET /products/providers" },
    @{ Method = "GET"; Path = "/products/eload"; Label = "GET /products/eload" },
    @{ Method = "GET"; Path = "/products/bills"; Label = "GET /products/bills" },
    @{ Method = "GET"; Path = "/products/ecash"; Label = "GET /products/ecash" },
    @{ Method = "GET"; Path = "/products/rfid"; Label = "GET /products/rfid" },
    @{ Method = "GET"; Path = "/products/announcements"; Label = "GET /products/announcements" },
    @{ Method = "GET"; Path = "/account/balance"; Label = "GET /account/balance" },
    @{ Method = "GET"; Path = "/wallets"; Label = "GET /wallets" },
    @{ Method = "GET"; Path = "/account/profile"; Label = "GET /account/profile" },
    @{ Method = "GET"; Path = "/transactions/history?page=1&limit=10"; Label = "GET /transactions/history" },
    @{ Method = "GET"; Path = "/pos/catalog"; Label = "GET /pos/catalog" },
    @{ Method = "GET"; Path = "/sync/providers"; Label = "GET /sync/providers" },
    @{ Method = "GET"; Path = "/sync/config"; Label = "GET /sync/config" }
)

function Run-RandomApiTest {
    param([int]$Num, [switch]$AllowMoney)
    $rng = Get-Random -Minimum 0 -Maximum $ReadOnlyEndpoints.Count
    if ($AllowMoney -and (Get-Random -Minimum 0 -Maximum 100) -lt 15) {
        return Run-EloadTxn -Num $Num
    }
    $ep = $ReadOnlyEndpoints[$rng]
    $r = if ($ep.Body) {
        Invoke-Api -Method $ep.Method -Path $ep.Path -Body $ep.Body
    } else {
        Invoke-Api -Method $ep.Method -Path $ep.Path
    }
    $pass = $r.Ok
    if ($r.Json -and $null -ne $r.Json.success) { $pass = $pass -and [bool]$r.Json.success }
    $detail = if ($pass) { "HTTP $($r.Status)" } else { "HTTP $($r.Status) $($r.Raw.Substring(0, [Math]::Min(120, $r.Raw.Length)))" }
    Add-Result -Num $Num -Category "API" -Endpoint $ep.Label -Pass $pass -LatencyMs $r.LatencyMs -Detail $detail
    return $pass
}

function Run-EloadTxn {
    param([int]$Num)
    $prod = Get-SmallestEloadProduct
    if (-not $prod) {
        Add-Result -Num $Num -Category "API-TXN" -Endpoint "POST /transactions/eload" -Pass $false -LatencyMs 0 -Detail "No product cached"
        return $false
    }
    $providerCode = if ($prod.providerCode) { $prod.providerCode } else { $prod.provider_code }
    if ($null -ne $prod.amount) { $amount = [double]$prod.amount }
    elseif ($null -ne $prod.retailer_price) { $amount = [double]$prod.retailer_price }
    else { $amount = 10.0 }
    $body = @{
        provider_code = $providerCode
        product_code = $prod.code
        mobile_number = "09991234567"
        amount = $amount
        reference_id = "stress-$Num-$(Get-Date -Format 'yyyyMMddHHmmss')"
    }
    $r = Invoke-Api -Method POST -Path "/transactions/eload" -Body $body -Headers @{ "X-Device-Id" = $script:DeviceId }
    $pass = $r.Ok -and $r.Json.success
    $detail = if ($pass) { "ref=$($r.Json.referenceNumber) amt=$amount" } else { $r.Raw.Substring(0, [Math]::Min(150, $r.Raw.Length)) }
    Add-Result -Num $Num -Category "API-TXN" -Endpoint "POST /transactions/eload" -Pass $pass -LatencyMs $r.LatencyMs -Detail $detail
    return $pass
}

function Invoke-AdbShell {
    param([string[]]$Args)
    & $Adb -s $Serial @Args 2>&1 | Out-String
}

function Run-UiTest {
    param([int]$Num, [string]$Action, [scriptblock]$Do, [string[]]$VerifyTerms)
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $ok = $false; $detail = ""
    try {
        & $Do
        Start-Sleep -Seconds 3
        Invoke-AdbShell @("shell", "uiautomator", "dump", "/sdcard/epay_stress_ui.xml") | Out-Null
        Invoke-AdbShell @("pull", "/sdcard/epay_stress_ui.xml", "$env:TEMP\epay_stress_ui.xml") | Out-Null
        $xml = if (Test-Path "$env:TEMP\epay_stress_ui.xml") { Get-Content "$env:TEMP\epay_stress_ui.xml" -Raw } else { "" }
        foreach ($t in $VerifyTerms) {
            if ($xml -match [regex]::Escape($t)) { $ok = $true; $detail = "seen: $t"; break }
        }
        if (-not $ok) { $detail = "expected: $($VerifyTerms -join ', ')" }
    } catch {
        $detail = $_.Exception.Message
    }
    $sw.Stop()
    $latency = [int]$sw.ElapsedMilliseconds
    Add-Result -Num $Num -Category "UI-ADB" -Endpoint $Action -Pass $ok -LatencyMs $latency -Detail $detail
    return $ok
}

# ========== MAIN ==========
Write-Log "=== ePayPlus 100 Random Transaction Stress Test ==="
Write-Log "Device: $Serial | API: $ApiBase | Total: $TotalTests (API:$ApiTests UI:$UiTests)"
Write-Log "Started: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"

if (-not (Login-Api)) { exit 1 }

# Pre-fetch products for potential money txns
Get-SmallestEloadProduct | Out-Null

$moneyCount = 0
for ($i = 1; $i -le $ApiTests; $i++) {
    $allowMoney = ($moneyCount -lt $MaxMoneyTxns)
    Run-RandomApiTest -Num $i -AllowMoney:$allowMoney | Out-Null
    if ($script:Results[-1].Endpoint -eq "POST /transactions/eload" -and $script:Results[-1].Status -eq "PASS") {
        $moneyCount++
    }
    if ($i % 20 -eq 0) { Write-Log "  API progress: $i / $ApiTests" }
    Start-Sleep -Milliseconds (Get-Random -Minimum 50 -Maximum 200)
}

# UI subset via WiFi ADB
Write-Log "Starting UI subset ($UiTests tests)..."
$main = "$Package/com.epayplus.v2.ui.MainActivity"
Invoke-AdbShell @("shell", "input", "keyevent", "KEYCODE_WAKEUP") | Out-Null
Invoke-AdbShell @("shell", "am", "force-stop", $Package) | Out-Null
Start-Sleep -Seconds 1
Invoke-AdbShell @("shell", "monkey", "-p", $Package, "-c", "android.intent.category.LAUNCHER", "1") | Out-Null
Start-Sleep -Seconds 5

# Login via existing script
$loginScript = Join-Path (Split-Path $PSScriptRoot -Parent) "scripts\adb-login-epayplus.ps1"
if (Test-Path $loginScript) {
    try {
        & $loginScript -Serial $Serial -MobileNumber $MobileNumber -Pin $Pin -SkipLaunch 2>&1 | Write-Host
    } catch {
        Write-Warning "ADB login script failed (continuing UI tests): $_"
    }
} else {
    Write-Warning "Login script not found: $loginScript"
}

$uiScenarios = @(
    @{ Action = "UI Home/Kiosk"; Terms = @("Quick Services", "Dual Wallets", "E-Load Wallet", "Select a service", "LOAD", "Bills"); Do = { } },
    @{ Action = "UI E-Load nav"; Terms = @("Globe", "Smart", "DITO", "E-Load", "LOAD"); Do = { Invoke-AdbShell @("shell", "input", "tap", "200", "300") | Out-Null } },
    @{ Action = "UI Bills nav"; Terms = @("Bills", "Electricity", "Telecom", "Water"); Do = { Invoke-AdbShell @("shell", "input", "tap", "350", "300") | Out-Null } },
    @{ Action = "UI Cash-in nav"; Terms = @("GCash", "Maya", "Cash"); Do = { Invoke-AdbShell @("shell", "input", "tap", "500", "300") | Out-Null } },
    @{ Action = "UI RFID nav"; Terms = @("RFID", "EasyTrip", "Autosweep"); Do = { Invoke-AdbShell @("shell", "input", "tap", "650", "300") | Out-Null } },
    @{ Action = "UI More/Settings"; Terms = @("More", "Settings", "History", "Transaction"); Do = { Invoke-AdbShell @("shell", "input", "tap", "750", "300") | Out-Null } },
    @{ Action = "UI scroll home"; Terms = @("Wallet", "Balance", "Sales", "Services"); Do = { Invoke-AdbShell @("shell", "input", "swipe", "640", "800", "640", "200", "400") | Out-Null } },
    @{ Action = "UI back to home"; Terms = @("Quick Services", "Home", "Dual Wallets"); Do = {
        Invoke-AdbShell @("shell", "input", "keyevent", "KEYCODE_BACK") | Out-Null
        Start-Sleep -Seconds 1
        Invoke-AdbShell @("shell", "input", "tap", "80", "400") | Out-Null
    }},
    @{ Action = "UI relaunch app"; Terms = @("Quick Services", "Sign In", "Welcome", "Dual Wallets"); Do = {
        Invoke-AdbShell @("shell", "am", "start", "-n", $main) | Out-Null
    }},
    @{ Action = "UI transaction history"; Terms = @("Transaction", "History", "Recent", "No transactions"); Do = {
        Invoke-AdbShell @("shell", "input", "tap", "750", "300") | Out-Null
        Start-Sleep -Seconds 2
        Invoke-AdbShell @("shell", "input", "swipe", "640", "600", "640", "200", "300") | Out-Null
    }}
)

for ($j = 0; $j -lt $UiTests; $j++) {
    $sc = $uiScenarios[$j % $uiScenarios.Count]
    Run-UiTest -Num ($ApiTests + $j + 1) -Action $sc.Action -VerifyTerms $sc.Terms -Do $sc.Do | Out-Null
}

# --- Report ---
$passTotal = ($script:Results | Where-Object { $_.Status -eq "PASS" }).Count
$failTotal = ($script:Results | Where-Object { $_.Status -eq "FAIL" }).Count
$allLatencies = $script:Results | ForEach-Object { $_.LatencyMs }
$avgLatency = if ($allLatencies.Count -gt 0) { [math]::Round(($allLatencies | Measure-Object -Average).Average, 1) } else { 0 }

$failSamples = $script:Results | Where-Object { $_.Status -eq "FAIL" } | Select-Object -First 10

$report = @"
ePayPlus 100 Random Transaction Stress Test Report
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
WiFi ADB Serial: $Serial
API Base: $ApiBase
Login: $MobileNumber
Installed Package: $Package (expected 3.3.3+)

SUMMARY
-------
Total: $($script:Results.Count) | PASS: $passTotal | FAIL: $failTotal | Avg Latency: ${avgLatency}ms
Money-moving eload txns attempted: $moneyCount (max $MaxMoneyTxns)

ENDPOINT BREAKDOWN
------------------
"@
$report += "{0,-40} {1,6} {2,6} {3,10}`n" -f "Endpoint", "Pass", "Fail", "Avg ms"
$report += "-" * 70 + "`n"
foreach ($ep in ($script:EndpointStats.Keys | Sort-Object)) {
    $s = $script:EndpointStats[$ep]
    $avg = if ($s.Latencies.Count -gt 0) { [math]::Round(($s.Latencies | Measure-Object -Average).Average, 1) } else { 0 }
    $report += "{0,-40} {1,6} {2,6} {3,10}`n" -f $ep, $s.Pass, $s.Fail, $avg
}

if ($failSamples.Count -gt 0) {
    $report += "`nFAIL SAMPLES (up to 10)`n" + ("-" * 70) + "`n"
    foreach ($f in $failSamples) {
        $report += "#$($f.Num) $($f.Endpoint): $($f.Detail)`n"
    }
}

$report += "`nDETAILED LOG (last 20)`n" + ("-" * 70) + "`n"
foreach ($r in ($script:Results | Select-Object -Last 20)) {
    $report += "#$($r.Num) [$($r.Category)] $($r.Endpoint) $($r.Status) $($r.LatencyMs)ms $($r.Detail)`n"
}

Set-Content -Path $ReportOut -Value $report -Encoding UTF8
Write-Log "`nReport saved: $ReportOut"
Write-Log "PASS: $passTotal | FAIL: $failTotal | Avg: ${avgLatency}ms"

# Export JSON for programmatic use
$script:Results | ConvertTo-Json -Depth 4 | Set-Content "$env:TEMP\epayplus-100-results.json" -Encoding UTF8

exit $(if ($failTotal -gt ($TotalTests * 0.15)) { 1 } else { 0 })
