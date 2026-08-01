# Signature LAN — HTTPS for offline phone/tablet PWA install
# Chrome/Android only allow "Install app" on HTTPS (HTTP IP is blocked).
#
# Run as Administrator:
#   scripts\enable-signature-lan-https.bat
#
# After setup, on phone/tablet open:
#   https://YOUR-LAN-IP/
# First time: install CA once from https://YOUR-LAN-IP/lan-ca.crt
#   Android: Settings → Security → Install a certificate → CA certificate

$ErrorActionPreference = 'Stop'

$ProjectRoot = 'C:/laragon/www/signature/public'
$HttpdConf   = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
$ApacheBin   = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
$SslDir      = 'C:\laragon\etc\ssl\signature-lan'
$VhostDest   = 'C:\laragon\etc\apache2\sites-enabled\auto.signature-lan-ssl.conf'
$EnvFile     = 'C:\laragon\www\signature\.env'
$LanCaDir    = 'C:\laragon\www\signature\storage\app\lan'

function Get-LanIPv4 {
    $preferred = @(
        Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
            Where-Object {
                $_.IPAddress -notlike '127.*' -and
                $_.PrefixOrigin -ne 'WellKnown' -and
                $_.IPAddress -notlike '169.254.*' -and
                ($_.InterfaceAlias -match 'Ethernet|Wi-?Fi|WLAN|Local Area')
            } |
            Sort-Object -Property @{Expression = {
                if ($_.InterfaceAlias -match 'Ethernet') { 0 } else { 1 }
            }} |
            Select-Object -ExpandProperty IPAddress
    )
    if ($preferred -and $preferred.Count -gt 0) {
        return [string]$preferred[0]
    }
    return '192.168.1.105'
}

$ServerIp = Get-LanIPv4

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host 'ERROR: Admin PowerShell chahiye.' -ForegroundColor Red
    exit 1
}

function Find-Mkcert {
    $candidates = @(
        (Join-Path $PSScriptRoot 'tools\mkcert.exe'),
        'C:\laragon\bin\mkcert\mkcert.exe',
        'C:\laragon\bin\laragon\util\mkcert.exe',
        (Get-Command mkcert -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
    ) | Where-Object { $_ -and (Test-Path $_) }
    return $candidates | Select-Object -First 1
}

function Install-Mkcert {
    $destDir = Join-Path $PSScriptRoot 'tools'
    $dest    = Join-Path $destDir 'mkcert.exe'
    $url     = 'https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-windows-amd64.exe'

    New-Item -ItemType Directory -Force -Path $destDir | Out-Null
    Write-Host 'mkcert download ho raha hai (GitHub)...' -ForegroundColor Yellow
    Invoke-WebRequest -Uri $url -OutFile $dest -UseBasicParsing
    if (-not (Test-Path $dest)) {
        throw 'mkcert download complete nahi hua.'
    }
    Write-Host "[OK] mkcert saved: $dest" -ForegroundColor Green
    return $dest
}

function Ensure-ApacheSslModule {
    param([string]$Path)
    $lines = Get-Content $Path
    if ($lines -match '^\s*LoadModule\s+ssl_module') {
        return
    }
    $updated = foreach ($line in $lines) {
        if ($line -match '^\s*#LoadModule\s+ssl_module') {
            ($line -replace '^\s*#', '')
        } else {
            $line
        }
    }
    if (-not ($updated -match '^\s*LoadModule\s+ssl_module')) {
        $updated = @($updated) + 'LoadModule ssl_module modules/mod_ssl.so'
    }
    Set-Content -Path $Path -Value $updated -Encoding ASCII
}

function Set-EnvValue {
    param([string]$File, [string]$Key, [string]$Value)
    if (-not (Test-Path $File)) { return }
    $lines = Get-Content $File
    $found = $false
    $out = foreach ($line in $lines) {
        if ($line -match "^\s*$Key\s*=") {
            $found = $true
            "$Key=$Value"
        } else {
            $line
        }
    }
    if (-not $found) {
        $out = @($out) + "$Key=$Value"
    }
    Set-Content -Path $File -Value $out -Encoding UTF8
}

Write-Host ''
Write-Host "=== Signature LAN HTTPS ($ServerIp) ===" -ForegroundColor Cyan
Write-Host ''

$mkcert = Find-Mkcert
if (-not $mkcert) {
    $mkcert = Install-Mkcert
}
Write-Host "[OK] mkcert: $mkcert" -ForegroundColor Green

Write-Host 'Installing local trust root (PC browser)...' -ForegroundColor DarkGray
& $mkcert -install | Out-Null

New-Item -ItemType Directory -Force -Path $SslDir | Out-Null
Push-Location $SslDir
try {
    & $mkcert -cert-file "$SslDir\signature-lan.pem" -key-file "$SslDir\signature-lan-key.pem" $ServerIp localhost 127.0.0.1
} finally {
    Pop-Location
}
Write-Host '[OK] SSL certificate generated.' -ForegroundColor Green

$rootCa = & $mkcert -CAROOT
$caPem = Join-Path $rootCa 'rootCA.pem'
$lanCaDir = 'C:\laragon\www\signature\storage\app\lan'
if (Test-Path $caPem) {
    New-Item -ItemType Directory -Force -Path $lanCaDir | Out-Null
    Copy-Item $caPem (Join-Path $lanCaDir 'lan-ca.crt') -Force
    Copy-Item $caPem (Join-Path $lanCaDir 'rootCA.pem') -Force
    Write-Host '[OK] Phone CA download route: /lan-ca.crt (LAN only)' -ForegroundColor Green
}

if (-not (Test-Path $HttpdConf)) {
    throw "httpd.conf not found: $HttpdConf"
}
Ensure-ApacheSslModule -Path $HttpdConf

$vhost = @"
# Signature LAN HTTPS — auto-generated for offline PWA install
<VirtualHost *:443>
    ServerName $ServerIp
    ServerAlias localhost 127.0.0.1
    DocumentRoot "$ProjectRoot"
    SSLEngine on
    SSLCertificateFile "C:/laragon/etc/ssl/signature-lan/signature-lan.pem"
    SSLCertificateKeyFile "C:/laragon/etc/ssl/signature-lan/signature-lan-key.pem"
    <Directory "$ProjectRoot">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost _default_:443>
    DocumentRoot "$ProjectRoot"
    SSLEngine on
    SSLCertificateFile "C:/laragon/etc/ssl/signature-lan/signature-lan.pem"
    SSLCertificateKeyFile "C:/laragon/etc/ssl/signature-lan/signature-lan-key.pem"
    <Directory "$ProjectRoot">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
"@

Set-Content -Path $VhostDest -Value $vhost -Encoding ASCII
Write-Host "[OK] Apache vhost: $VhostDest" -ForegroundColor Green

# Keep HTTP IP vhost in sync
$httpVhost = @"
# Signature — phone/tablet IP access (port 80)
<VirtualHost *:80>
    ServerName $ServerIp
    DocumentRoot "$ProjectRoot"
    <Directory "$ProjectRoot">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
"@
Set-Content -Path 'C:\laragon\etc\apache2\sites-enabled\00-signature-lan-ip.conf' -Value $httpVhost -Encoding ASCII

$ruleName = 'Signature Laragon HTTPS (LAN 443)'
if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow -Profile Private,Domain | Out-Null
    Write-Host '[OK] Firewall port 443 allow.' -ForegroundColor Green
}

Set-EnvValue -File $EnvFile -Key 'LAN_SERVER_IP' -Value $ServerIp
Set-EnvValue -File $EnvFile -Key 'LAN_SERVER_URL' -Value "https://$ServerIp"

if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
    Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
}
Start-Process -FilePath $ApacheBin -ArgumentList '-f', $HttpdConf -WindowStyle Hidden | Out-Null
Start-Sleep -Seconds 2
Write-Host '[OK] Apache restarted.' -ForegroundColor Green

$httpsUrl = "https://${ServerIp}"

Write-Host ''
Write-Host 'Done — phone/tablet pe YE URL use karein (http nahi):' -ForegroundColor Cyan
Write-Host "  $httpsUrl" -ForegroundColor Green
Write-Host "  ${httpsUrl}/login"
Write-Host "  ${httpsUrl}/restaurant-pos"
Write-Host "  ${httpsUrl}/order-taker"
Write-Host ''
Write-Host 'Pehli dafa certificate trust (Android):' -ForegroundColor Yellow
Write-Host "  1) Chrome: ${httpsUrl}/lan-ca.crt  download"
Write-Host '  2) Settings → Security → Install a certificate → CA certificate'
Write-Host '  3) Phir wapas https://IP open karke Install app'
Write-Host ''
Write-Host 'Agar warning aaye: Advanced → Proceed (unsafe) — phir bhi Install chal sakta hai.' -ForegroundColor DarkGray
Write-Host ''
