# Signature LAN — HTTPS (192.168.1.105 par "Not secure" hatane ke liye)
# Run as Administrator:
#   cd C:\laragon\www\signature\scripts
#   .\enable-signature-lan-https.ps1
#
# PC browser: mkcert trust auto. Mobile/tablet: ek dafa root CA install (script batati hai).

$ErrorActionPreference = 'Stop'

$ServerIp    = '192.168.1.105'
$ProjectRoot = 'C:/laragon/www/signature/public'
$HttpdConf   = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
$ApacheBin   = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
$SslDir      = 'C:\laragon\etc\ssl\signature-lan'
$VhostDest   = 'C:\laragon\etc\apache2\sites-enabled\auto.signature-lan-ssl.conf'

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
    try {
        Invoke-WebRequest -Uri $url -OutFile $dest -UseBasicParsing
    } catch {
        Write-Host 'Download fail. Manual:' -ForegroundColor Red
        Write-Host "  Browser: $url" -ForegroundColor Yellow
        Write-Host "  Save as: $dest" -ForegroundColor Yellow
        throw
    }

    if (-not (Test-Path $dest)) {
        throw 'mkcert download complete nahi hua.'
    }

    Write-Host "[OK] mkcert saved: $dest" -ForegroundColor Green
    return $dest
}

function Ensure-ApacheLine {
    param([string]$Path, [string]$Pattern, [string]$InsertAfter, [string]$NewLine)

    $lines = Get-Content $Path
    if ($lines -match $Pattern) { return $false }

    $updated = New-Object System.Collections.Generic.List[string]
    $inserted = $false
    $uncommented = $false
    foreach ($line in $lines) {
        if (-not $inserted -and $line -match '^\s*#LoadModule\s+ssl_module') {
            $updated.Add(($line -replace '^\s*#', ''))
            $uncommented = $true
            $inserted = $true
            continue
        }
        $updated.Add($line)
        if (-not $inserted -and $line -match $InsertAfter) {
            $updated.Add($NewLine)
            $inserted = $true
        }
    }
    if (-not $inserted -and -not $uncommented) {
        $updated.Add($NewLine)
    }
    Set-Content -Path $Path -Value $updated -Encoding ASCII
    return $true
}

Write-Host ''
Write-Host "=== Signature LAN HTTPS ($ServerIp) ===" -ForegroundColor Cyan
Write-Host ''

$mkcert = Find-Mkcert
if (-not $mkcert) {
    $mkcert = Install-Mkcert
}
Write-Host "[OK] mkcert: $mkcert" -ForegroundColor Green

Write-Host 'Installing local trust root (PC browser ke liye)...' -ForegroundColor DarkGray
& $mkcert -install | Out-Null

New-Item -ItemType Directory -Force -Path $SslDir | Out-Null
Push-Location $SslDir
try {
    & $mkcert -cert-file "$SslDir\signature-lan.pem" -key-file "$SslDir\signature-lan-key.pem" $ServerIp localhost 127.0.0.1
} finally {
    Pop-Location
}
Write-Host '[OK] SSL certificate generated.' -ForegroundColor Green

if (-not (Test-Path $HttpdConf)) {
    throw "httpd.conf not found: $HttpdConf"
}

$changed = $false
if (Ensure-ApacheLine -Path $HttpdConf -Pattern '^\s*LoadModule\s+ssl_module' -InsertAfter '^\s*#LoadModule\s+ssl_module' -NewLine 'LoadModule ssl_module modules/mod_ssl.so') { $changed = $true }
# Listen 443 mat add karein — Laragon httpd-ssl.conf mein pehle se hai (duplicate = Apache fail)

$vhost = @"
# Signature LAN HTTPS — auto-generated (HTTP redirect OFF — pehle HTTPS test karein)
<VirtualHost *:443>
    ServerName $ServerIp
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

$ruleName = 'Signature Laragon HTTPS (LAN 443)'
if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow -Profile Private,Domain | Out-Null
    Write-Host '[OK] Firewall port 443 allow.' -ForegroundColor Green
}

if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
    Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
}
Start-Process -FilePath $ApacheBin -ArgumentList '-f', $HttpdConf -WindowStyle Hidden | Out-Null
Start-Sleep -Seconds 2
Write-Host '[OK] Apache restarted.' -ForegroundColor Green

$rootCa = & $mkcert -CAROOT
$httpsUrl = "https://${ServerIp}"

Write-Host ''
Write-Host 'Done — ab yeh URL use karein:' -ForegroundColor Cyan
Write-Host "  $httpsUrl" -ForegroundColor Green
Write-Host "  ${httpsUrl}/order-taker"
Write-Host "  ${httpsUrl}/pos"
Write-Host ''
Write-Host 'PC browser: lock icon / Secure dikhna chahiye (mkcert trust).' -ForegroundColor Green
Write-Host ''
Write-Host 'Mobile / tablet (ek dafa):' -ForegroundColor Yellow
Write-Host "  Root CA file copy karein: $rootCa\rootCA.pem"
Write-Host '  Android: Settings -> Security -> Install certificate -> CA certificate'
Write-Host '  iPhone: AirDrop/email -> Profile install -> Settings -> General -> About -> Certificate Trust'
Write-Host ''
Write-Host 'Settings -> System -> LAN IP: https://192.168.1.105 (port khali)' -ForegroundColor Yellow
Write-Host ''
