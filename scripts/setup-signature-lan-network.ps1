# Signature — poore cafe WiFi / extender network par IP se chalane ke liye.
# Run as Administrator (Right-click PowerShell -> Run as administrator):
#   cd C:\laragon\www\signature\scripts
#   .\setup-signature-lan-network.ps1
#
# Zaroori: WiFi extender "Access Point / Repeater" mode mein ho (Router mode NA ho).
# Sab devices same WiFi name se connect hon — phir har extender par same IP URL chalega.

$ErrorActionPreference = 'Stop'

$SignaturePort = 8080
$EnvFile       = Join-Path (Split-Path $PSScriptRoot -Parent) '.env'
$PreferredIp   = '192.168.1.105'

Write-Host ''
Write-Host '=== Signature LAN / Extender setup ===' -ForegroundColor Cyan
Write-Host ''

# --- Apache: Listen 8080 + Signature vhost (IP access without hostname) ---
$apacheScript = Join-Path $PSScriptRoot 'enable-signature-apache-8080.ps1'
if (Test-Path $apacheScript) {
    & $apacheScript
} else {
    Write-Host '[WARN] enable-signature-apache-8080.ps1 missing — port 8080 Apache config skipped.' -ForegroundColor Yellow
}

Write-Host ''

# --- Firewall: allow Signature port on Private network (WiFi / LAN) ---
$ruleName = "Signature Laragon HTTP (port $SignaturePort)"
$existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
if (-not $existing) {
    New-NetFirewallRule `
        -DisplayName $ruleName `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort $SignaturePort `
        -Action Allow `
        -Profile Any `
        | Out-Null
    Write-Host "[OK] Firewall: port $SignaturePort allow (all profiles)." -ForegroundColor Green
} else {
    Set-NetFirewallRule -DisplayName $ruleName -Enabled True -Action Allow -Profile Any | Out-Null
    Write-Host "[OK] Firewall rule updated for port $SignaturePort (all profiles)." -ForegroundColor Green
}

# --- Show current LAN IPs ---
Write-Host ''
Write-Host 'Is PC ke network IPs:' -ForegroundColor Yellow
$ips = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.PrefixOrigin -ne 'WellKnown' } |
    Select-Object -ExpandProperty IPAddress -Unique

if (-not $ips) {
    Write-Host '  (Koi LAN IP nahi — WiFi / Ethernet connect karein)' -ForegroundColor Red
} else {
    foreach ($ip in $ips) {
        Write-Host "  http://${ip}:$SignaturePort/" -ForegroundColor White
    }
}

$activeIp = $ips | Where-Object { $_ -eq $PreferredIp } | Select-Object -First 1
if (-not $activeIp) {
    $activeIp = $ips | Where-Object { $_ -match '^192\.168\.' } | Select-Object -First 1
}
if (-not $activeIp -and $ips) {
    $activeIp = $ips | Select-Object -First 1
}

if ($PreferredIp -notin $ips -and $ips) {
    Write-Host ''
    Write-Host "NOTE: .env / docs mein $PreferredIp hai lekin ab PC ka IP alag hai." -ForegroundColor Yellow
    Write-Host '      Purana URL (192.168.3.50:8080) tab tak timeout dega jab tak fixed IP set na ho.' -ForegroundColor Yellow
}

if ($activeIp) {
    $lanUrl = "http://${activeIp}:$SignaturePort"
    Write-Host ''
    Write-Host "Recommended URL (sab tablets / phones / extenders):" -ForegroundColor Cyan
    Write-Host "  $lanUrl" -ForegroundColor Green
    Write-Host ''
    Write-Host 'Pages:' -ForegroundColor Cyan
    Write-Host "  Restaurant POS  : $lanUrl/restaurant-pos"
    Write-Host "  Order Taker     : $lanUrl/order-taker"
    Write-Host "  Kitchen         : $lanUrl/kitchen"
    Write-Host "  Order Status    : $lanUrl/order-status"
    Write-Host ''

    if (Test-Path $EnvFile) {
        $content = Get-Content $EnvFile -Raw
        $content = $content -replace '(?m)^LAN_SERVER_IP=.*$', "LAN_SERVER_IP=$activeIp"
        $content = $content -replace '(?m)^LAN_SERVER_URL=.*$', "LAN_SERVER_URL=$lanUrl"
        Set-Content -Path $EnvFile -Value $content.TrimEnd() -NoNewline
        Write-Host "[OK] .env updated: LAN_SERVER_IP / LAN_SERVER_URL" -ForegroundColor Green
    }
}

Write-Host ''
Write-Host '--- WiFi Extender (important) ---' -ForegroundColor Yellow
Write-Host '1. Extender ko Access Point / Repeater mode par rakhein — same WiFi name extend kare.'
Write-Host '2. Router mode NA use karein (alag network ban jata hai, IP nahi chalega).'
Write-Host '3. Server PC ko router mein Fixed IP / DHCP Reservation do (recommended: 192.168.1.105).'
Write-Host '4. Laragon: Apache + MySQL GREEN; Apache port 8080 (Signature).'
Write-Host '5. Fixed IP ke liye (optional): .\set-cafe-lan-ip.ps1'
Write-Host ''
Write-Host 'Test: extender se connect phone par browser mein URL kholo.' -ForegroundColor Cyan
Write-Host ''
