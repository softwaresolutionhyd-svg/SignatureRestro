# Cafe emergency fix — software + printers + mobile IP (Admin PowerShell)
# Double-click: scripts\emergency-cafe-network-fix.bat

$ErrorActionPreference = 'Continue'
$Root = Split-Path $PSScriptRoot -Parent
$Php  = 'C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe'
$Httpd = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'

Write-Host ''
Write-Host '========================================' -ForegroundColor Cyan
Write-Host '  SIGNATURE — EMERGENCY NETWORK FIX' -ForegroundColor Cyan
Write-Host '========================================' -ForegroundColor Cyan
Write-Host ''

# 1) Network Private (Public profile blocks LAN)
Get-NetConnectionProfile | ForEach-Object {
    if ($_.NetworkCategory -ne 'Private') {
        Set-NetConnectionProfile -InterfaceIndex $_.InterfaceIndex -NetworkCategory Private -ErrorAction SilentlyContinue
        Write-Host "[OK] Network '$($_.Name)' -> Private" -ForegroundColor Green
    }
}

# 2) Firewall — HTTP + printer port 9100 (all profiles)
function Ensure-PortRule {
    param([string]$Name, [int]$Port)
    $rule = Get-NetFirewallRule -DisplayName $Name -ErrorAction SilentlyContinue
    if ($rule) {
        Set-NetFirewallRule -DisplayName $Name -Enabled True -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
    } else {
        New-NetFirewallRule -DisplayName $Name -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
    }
    Write-Host "[OK] Firewall inbound TCP $Port" -ForegroundColor Green
}

foreach ($p in @(80, 8080, 443, 9100)) {
    Ensure-PortRule -Name "Signature Cafe LAN (port $p)" -Port $p
}

if (Test-Path $Httpd) {
    $appRule = 'Signature Apache httpd.exe (LAN)'
    if (-not (Get-NetFirewallRule -DisplayName $appRule -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $appRule -Direction Inbound -Program $Httpd -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
    } else {
        Set-NetFirewallRule -DisplayName $appRule -Enabled True -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
    }
    Write-Host '[OK] Firewall httpd.exe' -ForegroundColor Green
}

# Network Discovery + File and Printer Sharing (Windows firewall groups)
foreach ($group in @(
    'Network Discovery (LLMNR-UDP-In)',
    'Network Discovery (NB-Datagram-In)',
    'Network Discovery (NB-Name-In)',
    'Network Discovery (Pub-WSD-In)',
    'Network Discovery (SSDP-In)',
    'Network Discovery (UPnP-In)',
    'Network Discovery (WSD Events-In)',
    'Network Discovery (WSD-In)',
    'File and Printer Sharing (Echo Request - ICMPv4-In)',
    'File and Printer Sharing (Spooler Service - RPC)',
    'File and Printer Sharing (Spooler Service - RPC-EPMAP)'
)) {
    Enable-NetFirewallRule -DisplayGroup 'Network Discovery' -ErrorAction SilentlyContinue | Out-Null
    Enable-NetFirewallRule -DisplayGroup 'File and Printer Sharing' -ErrorAction SilentlyContinue | Out-Null
}
Write-Host '[OK] Network Discovery + Printer Sharing rules enabled' -ForegroundColor Green

# 3) Apache vhost + restart
$serverIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -match '^192\.168\.' } |
    Select-Object -ExpandProperty IPAddress -First 1)

if ($serverIp) {
    $vhostDest = 'C:\laragon\etc\apache2\sites-enabled\00-signature-lan-ip.conf'
    $vhost = @"
# Signature — phone/tablet IP access (port 80)
<VirtualHost *:80>
    ServerName $serverIp
    ServerAlias connectivitycheck.gstatic.com www.gstatic.com connectivitycheck.android.com clients3.google.com captive.apple.com www.msftconnecttest.com www.msftncsi.com detectportal.firefox.com
    DocumentRoot "C:/laragon/www/signature/public"
    <Directory "C:/laragon/www/signature/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
"@
    Set-Content -Path $vhostDest -Value $vhost.TrimEnd() -NoNewline
    Write-Host "[OK] Apache vhost for $serverIp" -ForegroundColor Green
}

if (Test-Path $Httpd) {
    Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    $conf = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
    Start-Process -FilePath $Httpd -ArgumentList '-f', $conf -WindowStyle Hidden | Out-Null
    Start-Sleep -Seconds 2
    Write-Host '[OK] Apache restarted' -ForegroundColor Green
}

# 4) .env update
$envFile = Join-Path $Root '.env'
if ($serverIp -and (Test-Path $envFile)) {
    $content = Get-Content $envFile -Raw
    $content = $content -replace '(?m)^LAN_SERVER_IP=.*$', "LAN_SERVER_IP=$serverIp"
    $content = $content -replace '(?m)^LAN_SERVER_URL=.*$', "LAN_SERVER_URL=http://${serverIp}"
    Set-Content -Path $envFile -Value $content.TrimEnd() -NoNewline
    Write-Host "[OK] .env LAN_SERVER_IP=$serverIp" -ForegroundColor Green
}

# 5) Test printer IPs from Laravel
Write-Host ''
Write-Host '--- Printer connectivity test (server -> printer) ---' -ForegroundColor Yellow
$testScript = Join-Path $PSScriptRoot 'test-printer-connectivity.php'
if ((Test-Path $Php) -and (Test-Path $testScript)) {
    & $Php $testScript
} else {
    Write-Host '[SKIP] PHP test script not found' -ForegroundColor DarkGray
}

# 6) WiFi client reachability (sample)
Write-Host ''
Write-Host '--- WiFi device reachability (server -> tablet) ---' -ForegroundColor Yellow
foreach ($testIp in @('192.168.1.28', '192.168.1.26', '192.168.1.52', '192.168.1.12')) {
    $ok = Test-Connection -ComputerName $testIp -Count 1 -Quiet -ErrorAction SilentlyContinue
    $color = if ($ok) { 'Green' } else { 'Red' }
    $status = if ($ok) { 'REACHABLE' } else { 'BLOCKED / no reply' }
    Write-Host "  $testIp -> $status" -ForegroundColor $color
}

# 7) Local HTTP test
Write-Host ''
if ($serverIp) {
    foreach ($url in @("http://${serverIp}/", "http://${serverIp}/lan-test.html")) {
        try {
            $code = (Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5).StatusCode
            Write-Host "[OK] $url -> HTTP $code" -ForegroundColor Green
        } catch {
            Write-Host "[FAIL] $url" -ForegroundColor Red
        }
    }
}

Write-Host ''
Write-Host '========================================' -ForegroundColor Red
Write-Host '  AGAR TABLET / PRINTER AB BHI NA CHALE' -ForegroundColor Red
Write-Host '========================================' -ForegroundColor Red
Write-Host ''
Write-Host 'Masla: Server PC CABLE se router par hai.' -ForegroundColor Yellow
Write-Host '       Tablets + WiFi printers EXTENDER se hain.' -ForegroundColor Yellow
Write-Host '       Router WiFi ko wired LAN se BLOCK kar raha hai.' -ForegroundColor Yellow
Write-Host ''
Write-Host 'FIX (5 minute — sab kuch theek ho jayega):' -ForegroundColor Green
Write-Host ''
Write-Host '  1) Server ka ETHERNET cable ROUTER se nikalo' -ForegroundColor White
Write-Host '  2) Wohi cable EXTENDER ke LAN port mein lagao (WAN nahi)' -ForegroundColor White
Write-Host '  3) PC restart ya ipconfig /renew' -ForegroundColor White
Write-Host '  4) Is script ko dubara chalao (Admin)' -ForegroundColor White
Write-Host '  5) Tablet par naya IP try karo (script output dekho)' -ForegroundColor White
Write-Host ''
Write-Host 'Router par (192.168.1.1): AP Isolation = OFF' -ForegroundColor Cyan
Write-Host '                          Allow WiFi -> Wired LAN = ON' -ForegroundColor Cyan
Write-Host ''
Write-Host "Current server IP: $serverIp" -ForegroundColor Cyan
Write-Host "Tablet URL:        http://${serverIp}/lan-test.html" -ForegroundColor Cyan
Write-Host ''
