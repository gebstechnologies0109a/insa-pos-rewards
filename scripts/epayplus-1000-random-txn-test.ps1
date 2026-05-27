# ePayPlus 1000 Random API Transaction Stress Test
# Extends epayplus-100-random-txn-test.ps1 — API-only for throughput (no UI subset).
param(
    [string]$ApiBase = "https://epayplus.diybizrewards.com/api/v2",
    [string]$MobileNumber = "09171234567",
    [string]$Pin = "1234",
    [int]$TotalTests = 1000,
    [int]$MaxMoneyTxns = 25,
    [int]$LoginRefreshEvery = 100,
    [int]$ProgressEvery = 50,
    [int]$ThrottleMinMs = 40,
    [int]$ThrottleMaxMs = 120,
    [string]$Adb = "C:\Android\sdk\platform-tools\adb.exe",
    [string]$Serial = "10.139.7.209:5555",
    [string]$Package = "com.epayplus.v2.debug",
    [string]$ReportOut = "C:\Users\Admin\Downloads\ePayPlus-1000-Random-Txn-Report.txt"
)

$ErrorActionPreference = "Continue"
$script:Results = [System.Collections.Generic.List[object]]::new()
$script:EndpointStats = @{}
$script:Token = $null
$script:DeviceId = "stress-1k-" + [guid]::NewGuid().ToString("N").Substring(0, 10)
$script:ProductCache = @{}
$script:InsufficientStreak = 0
$script:StopMoneyTxns = $false
$script:MoneyCount = 0
$script:DemoRetailerId = $null

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
        $script:EndpointStats[$Endpoint] = @{ Pass = 0; Fail = 0; Latencies = [System.Collections.Generic.List[int]]::new() }
    }
    if ($Pass) { $script:EndpointStats[$Endpoint].Pass++ } else { $script:EndpointStats[$Endpoint].Fail++ }
    [void]$script:EndpointStats[$Endpoint].Latencies.Add($LatencyMs)
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
            TimeoutSec = 45; UseBasicParsing = $true
        }
        if ($null -ne $Body) {
            $params["Body"] = ($Body | ConvertTo-Json -Depth 8 -Compress)
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
    Write-Log "Logging in ($MobileNumber)..."
    $r = Invoke-Api -Method POST -Path "/auth/login" -NoAuth -Body @{
        mobile_number = $MobileNumber; pin = $Pin; device_id = $script:DeviceId
    }
    if ($r.Ok -and $r.Json.success -and $r.Json.token) {
        $script:Token = $r.Json.token
        if ($r.Json.account -and $r.Json.account.id) {
            $script:DemoRetailerId = $r.Json.account.id
        }
        Write-Log "Login OK (${($r.LatencyMs)}ms) account=$($script:DemoRetailerId)"
        return $true
    }
    Write-Error "Login failed: $($r.Raw)"
    return $false
}

function Get-SmallestEloadProducts {
    if ($script:ProductCache.ContainsKey("eload_list")) {
        return $script:ProductCache["eload_list"]
    }
    $r = Invoke-Api -Method GET -Path "/products/eload"
    if (-not $r.Ok -or -not $r.Json) { return @() }
    $products = @()
    if ($r.Json.products) { $products = @($r.Json.products) }
    elseif ($r.Json.data) { $products = @($r.Json.data) }
    $sorted = $products | Where-Object { $_.code } | Sort-Object {
        $p = if ($null -ne $_.amount) { $_.amount }
             elseif ($null -ne $_.retailer_price) { $_.retailer_price }
             else { 9999 }
        [double]$p
    }
    $script:ProductCache["eload_list"] = @($sorted | Select-Object -First 5)
    return $script:ProductCache["eload_list"]
}

function Get-SmallestBillProduct {
    if ($script:ProductCache.ContainsKey("bill")) { return $script:ProductCache["bill"] }
    $r = Invoke-Api -Method GET -Path "/products/bills"
    if (-not $r.Ok -or -not $r.Json) { return $null }
    $products = @()
    if ($r.Json.products) { $products = @($r.Json.products) }
    elseif ($r.Json.data) { $products = @($r.Json.data) }
    $pick = $products | Where-Object { $_.code } | Sort-Object {
        $p = if ($null -ne $_.amount) { $_.amount } elseif ($null -ne $_.retailer_price) { $_.retailer_price } else { 9999 }
        [double]$p
    } | Select-Object -First 1
    if ($pick) { $script:ProductCache["bill"] = $pick }
    return $pick
}

$ReadOnlyEndpoints = @(
    @{ Method = "GET"; Path = "/health"; Label = "GET /health" },
    @{ Method = "GET"; Path = "/integrations/maya"; Label = "GET /integrations/maya" },
    @{ Method = "GET"; Path = "/products/providers"; Label = "GET /products/providers" },
    @{ Method = "GET"; Path = "/products/eload"; Label = "GET /products/eload" },
    @{ Method = "GET"; Path = "/products/bills"; Label = "GET /products/bills" },
    @{ Method = "GET"; Path = "/products/ecash"; Label = "GET /products/ecash" },
    @{ Method = "GET"; Path = "/products/rfid"; Label = "GET /products/rfid" },
    @{ Method = "GET"; Path = "/products/announcements"; Label = "GET /products/announcements" },
    @{ Method = "GET"; Path = "/account/balance"; Label = "GET /account/balance" },
    @{ Method = "GET"; Path = "/wallets"; Label = "GET /wallets" },
    @{ Method = "GET"; Path = "/account/profile"; Label = "GET /account/profile" },
    @{ Method = "GET"; Path = "/account/topup-history"; Label = "GET /account/topup-history" },
    @{ Method = "GET"; Path = "/transactions/history?page=1&limit=20"; Label = "GET /transactions/history" },
    @{ Method = "GET"; Path = "/pos/catalog"; Label = "GET /pos/catalog" },
    @{ Method = "GET"; Path = "/sync/providers"; Label = "GET /sync/providers" },
    @{ Method = "GET"; Path = "/sync/config"; Label = "GET /sync/config" }
)

function Test-ReadOnly {
    param([int]$Num)
    $ep = $ReadOnlyEndpoints[(Get-Random -Maximum $ReadOnlyEndpoints.Count)]
    $r = Invoke-Api -Method $ep.Method -Path $ep.Path
    $pass = $r.Ok
    if ($r.Json -and $null -ne $r.Json.success) { $pass = $pass -and [bool]$r.Json.success }
    $detail = if ($pass) { "HTTP $($r.Status)" } else {
        $snippet = if ($r.Raw) { $r.Raw.Substring(0, [Math]::Min(100, $r.Raw.Length)) } else { "no body" }
        "HTTP $($r.Status) $snippet"
    }
    Add-Result -Num $Num -Category "API" -Endpoint $ep.Label -Pass $pass -LatencyMs $r.LatencyMs -Detail $detail
    return $pass
}

function Test-SyncEmpty {
    param([int]$Num)
    # Android sends a JSON array at root (Retrofit List<SyncTransactionRequest>)
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $uri = "$ApiBase/transactions/sync"
    $hdrs = @{
        "Accept" = "application/json"; "Content-Type" = "application/json"
        "Authorization" = "Bearer $($script:Token)"
    }
    try {
        $resp = Invoke-WebRequest -Uri $uri -Method POST -Headers $hdrs -Body "[]" -TimeoutSec 45 -UseBasicParsing
        $sw.Stop()
        $json = $resp.Content | ConvertFrom-Json
        $pass = $json.success -eq $true
        Add-Result -Num $Num -Category "API" -Endpoint "POST /transactions/sync" -Pass $pass -LatencyMs ([int]$sw.ElapsedMilliseconds) -Detail $(if ($pass) { "empty array sync OK" } else { $resp.Content.Substring(0, [Math]::Min(120, $resp.Content.Length)) })
    } catch {
        $sw.Stop()
        Add-Result -Num $Num -Category "API" -Endpoint "POST /transactions/sync" -Pass $false -LatencyMs ([int]$sw.ElapsedMilliseconds) -Detail $_.Exception.Message
        $pass = $false
    }
    return $pass
}

function Run-EloadTxn {
    param([int]$Num)
    $products = Get-SmallestEloadProducts
    if (-not $products -or $products.Count -eq 0) {
        Add-Result -Num $Num -Category "API-TXN" -Endpoint "POST /transactions/eload" -Pass $false -LatencyMs 0 -Detail "No products"
        return $false
    }
    $prod = $products[(Get-Random -Maximum $products.Count)]
    $providerCode = if ($prod.providerCode) { $prod.providerCode } elseif ($prod.provider_code) { $prod.provider_code } else { "GLOBE" }
    $productId = if ($prod.id) { $prod.id } elseif ($prod.product_id) { $prod.product_id } else { $null }
    if ($null -ne $prod.amount) { $amount = [double]$prod.amount }
    elseif ($null -ne $prod.retailer_price) { $amount = [double]$prod.retailer_price }
    else { $amount = 10.0 }
    if ($amount -gt 20) { $amount = 20.0 }
    if ($amount -lt 10) { $amount = 10.0 }

    # API v2 fields (also send aliases for older clients / docs)
    $body = [ordered]@{
        provider_code = $providerCode
        product_code  = $prod.code
        mobile_number = "09991234567"
        phone_number  = "09991234567"
        amount        = $amount
        reference_id  = "stress1k-$Num-$(Get-Date -Format 'yyyyMMddHHmmssfff')"
    }
    if ($productId) { $body["product_id"] = $productId }

    $r = Invoke-Api -Method POST -Path "/transactions/eload" -Body $body -Headers @{ "X-Device-Id" = $script:DeviceId }
    $pass = $r.Ok -and $r.Json.success
    $detail = if ($pass) {
        "ref=$($r.Json.referenceNumber) amt=$amount code=$($prod.code)"
    } else {
        $msg = if ($r.Json.message) { $r.Json.message } else { $r.Raw }
        if ($msg -match "Insufficient") {
            $script:InsufficientStreak++
            if ($script:InsufficientStreak -ge 3) { $script:StopMoneyTxns = $true }
        } else {
            $script:InsufficientStreak = 0
        }
        $msg.Substring(0, [Math]::Min(150, $msg.Length))
    }
    if ($pass) {
        $script:InsufficientStreak = 0
        $script:MoneyCount++
    }
    Add-Result -Num $Num -Category "API-TXN" -Endpoint "POST /transactions/eload" -Pass $pass -LatencyMs $r.LatencyMs -Detail $detail
    return $pass
}

function Run-BillValidateDry {
    param([int]$Num)
    $prod = Get-SmallestBillProduct
    if (-not $prod) {
        Add-Result -Num $Num -Category "API" -Endpoint "GET /products/bills (validate prep)" -Pass $false -LatencyMs 0 -Detail "No bill product"
        return $false
    }
    $r = Invoke-Api -Method GET -Path "/products/bills"
    $pass = $r.Ok -and ($r.Json.products -or $r.Json.data)
    Add-Result -Num $Num -Category "API" -Endpoint "Bills catalog (validate prep)" -Pass $pass -LatencyMs $r.LatencyMs -Detail "biller=$($prod.code)"
    return $pass
}

function Run-RandomTest {
    param([int]$Num, [switch]$AllowMoney)
    $roll = Get-Random -Minimum 0 -Maximum 100
    if ($AllowMoney -and -not $script:StopMoneyTxns -and $roll -lt 8) {
        return Run-EloadTxn -Num $Num
    }
    if ($roll -lt 3) {
        return Test-SyncEmpty -Num $Num
    }
    if ($roll -lt 5) {
        return Run-BillValidateDry -Num $Num
    }
    return Test-ReadOnly -Num $Num
}

# ========== MAIN ==========
Write-Log "=== ePayPlus 1000 Random API Stress Test ==="
Write-Log "API: $ApiBase | Total: $TotalTests | Max money txns: $MaxMoneyTxns"
Write-Log "Started: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"

if (-not (Login-Api)) { exit 1 }
Get-SmallestEloadProducts | Out-Null
Get-SmallestBillProduct | Out-Null

$swTotal = [System.Diagnostics.Stopwatch]::StartNew()
for ($i = 1; $i -le $TotalTests; $i++) {
    if ($i % $LoginRefreshEvery -eq 0 -and $i -gt 1) {
        Login-Api | Out-Null
    }
    $allowMoney = ($script:MoneyCount -lt $MaxMoneyTxns) -and -not $script:StopMoneyTxns
    Run-RandomTest -Num $i -AllowMoney:$allowMoney | Out-Null

    if ($i % $ProgressEvery -eq 0) {
        $p = ($script:Results | Where-Object { $_.Status -eq "PASS" }).Count
        $f = ($script:Results | Where-Object { $_.Status -eq "FAIL" }).Count
        Write-Log "  Progress $i / $TotalTests | PASS: $p | FAIL: $f | money txns: $($script:MoneyCount) | stopMoney=$($script:StopMoneyTxns)"
    }
    Start-Sleep -Milliseconds (Get-Random -Minimum $ThrottleMinMs -Maximum $ThrottleMaxMs)
}
$swTotal.Stop()

# Device snapshot (WiFi ADB — no adb usb)
if (Test-Path $Adb) {
    Write-Log "Device snapshot via $Serial..."
    $ver = & $Adb -s $Serial shell dumpsys package $Package 2>$null | Select-String "versionName" | Select-Object -First 1
    Write-Log "  App: $($ver.Line.Trim())"
}

# --- Report ---
$passTotal = ($script:Results | Where-Object { $_.Status -eq "PASS" }).Count
$failTotal = ($script:Results | Where-Object { $_.Status -eq "FAIL" }).Count
$allLatencies = $script:Results | ForEach-Object { $_.LatencyMs }
$avgLatency = if ($allLatencies.Count -gt 0) { [math]::Round(($allLatencies | Measure-Object -Average).Average, 1) } else { 0 }
$pct = if ($script:Results.Count -gt 0) { [math]::Round(100.0 * $passTotal / $script:Results.Count, 2) } else { 0 }
$failSamples = $script:Results | Where-Object { $_.Status -eq "FAIL" } | Select-Object -First 25

$report = @"
ePayPlus 1000 Random API Stress Test Report
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
Duration: $([math]::Round($swTotal.Elapsed.TotalMinutes, 2)) minutes
WiFi ADB Serial: $Serial
API Base: $ApiBase
Login: $MobileNumber (demo $($script:DemoRetailerId))
Device ID header: $($script:DeviceId)

SUMMARY
-------
Total: $($script:Results.Count) | PASS: $passTotal | FAIL: $failTotal | Pass rate: ${pct}%
Avg Latency: ${avgLatency}ms
Money-moving eload txns: $($script:MoneyCount) (max $MaxMoneyTxns, stopped=$($script:StopMoneyTxns))

ENDPOINT BREAKDOWN
------------------
"@
$report += "{0,-42} {1,6} {2,6} {3,10}`n" -f "Endpoint", "Pass", "Fail", "Avg ms"
$report += "-" * 72 + "`n"
foreach ($ep in ($script:EndpointStats.Keys | Sort-Object)) {
    $s = $script:EndpointStats[$ep]
    $avg = if ($s.Latencies.Count -gt 0) { [math]::Round(($s.Latencies | Measure-Object -Average).Average, 1) } else { 0 }
    $report += "{0,-42} {1,6} {2,6} {3,10}`n" -f $ep, $s.Pass, $s.Fail, $avg
}

if ($failSamples.Count -gt 0) {
    $report += "`nFAIL SAMPLES (up to 25)`n" + ("-" * 72) + "`n"
    foreach ($f in $failSamples) {
        $report += "#$($f.Num) $($f.Endpoint): $($f.Detail)`n"
    }
}

$report += "`nDETAILED LOG (last 30)`n" + ("-" * 72) + "`n"
foreach ($r in ($script:Results | Select-Object -Last 30)) {
    $report += "#$($r.Num) [$($r.Category)] $($r.Endpoint) $($r.Status) $($r.LatencyMs)ms $($r.Detail)`n"
}

Set-Content -Path $ReportOut -Value $report -Encoding UTF8
Write-Log "`nReport saved: $ReportOut"
Write-Log "PASS: $passTotal | FAIL: $failTotal | ${pct}% | Avg: ${avgLatency}ms | $($swTotal.Elapsed.TotalMinutes.ToString('0.0')) min"

$script:Results | ConvertTo-Json -Depth 4 | Set-Content "$env:TEMP\epayplus-1000-results.json" -Encoding UTF8

exit $(if ($pct -lt 85) { 1 } else { 0 })
