# Apache fix - Laragon Failed (duplicate Listen 443 / HTTPS mess)
# Run as Administrator: scripts\fix-laragon-apache.bat

$ErrorActionPreference = 'Stop'

$HttpdConf   = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
$ApacheBin   = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
$SslVhost    = 'C:\laragon\etc\apache2\sites-enabled\auto.signature-lan-ssl.conf'
$LanVhost    = 'C:\laragon\etc\apache2\sites-enabled\00-signature-lan-ip.conf'
$ServerIp    = '192.168.1.105'
$ProjectRoot = 'C:/laragon/www/signature/public'

Write-Host ''
Write-Host '=== Laragon Apache fix ===' -ForegroundColor Cyan
Write-Host ''

if (Test-Path $SslVhost) {
    Remove-Item $SslVhost -Force
    Write-Host '[OK] Removed HTTPS vhost.' -ForegroundColor Green
}

$lanConf = @"
# Signature LAN IP access (port 80)
<VirtualHost *:80>
    ServerName $ServerIp
    DocumentRoot "$ProjectRoot"
    <Directory "$ProjectRoot">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
"@
Set-Content -Path $LanVhost -Value $lanConf -Encoding ASCII
Write-Host "[OK] LAN vhost: $ServerIp" -ForegroundColor Green

if (Test-Path $HttpdConf) {
    $newLines = @()
    $removed443 = $false
    foreach ($line in (Get-Content $HttpdConf)) {
        if ($line -match '^\s*Listen\s+443\s*$' -and -not $removed443) {
            $removed443 = $true
            Write-Host '[OK] Removed duplicate Listen 443 from httpd.conf' -ForegroundColor Green
            continue
        }
        $newLines += $line
    }
    Set-Content -Path $HttpdConf -Value $newLines -Encoding ASCII
}

Write-Host ''
Write-Host 'Port 80 check:' -ForegroundColor Yellow
try {
    $port80 = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 3 OwningProcess, LocalAddress
    if ($port80) {
        foreach ($p in $port80) {
            $proc = Get-Process -Id $p.OwningProcess -ErrorAction SilentlyContinue
            $name = if ($proc) { $proc.ProcessName } else { 'unknown' }
            Write-Host "  Port 80 in use: PID $($p.OwningProcess) - $name" -ForegroundColor Red
        }
        Write-Host '  Agar httpd nahi hai to IIS/Skype band karein.' -ForegroundColor Yellow
    } else {
        Write-Host '  Port 80 free.' -ForegroundColor Green
    }
} catch {
    Write-Host '  Port check skip.' -ForegroundColor DarkGray
}

Get-Process -Name httpd -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

if (Test-Path $ApacheBin) {
    $testLines = & $ApacheBin -t 2>&1
    foreach ($tl in $testLines) { Write-Host $tl }
    $testText = ($testLines | Out-String)
    if ($testText -notmatch 'Syntax OK') {
        Write-Host '[ERROR] Apache config broken. Laragon Apache Reload error dekhein.' -ForegroundColor Red
        exit 1
    }
    Start-Process -FilePath $ApacheBin -ArgumentList '-d', 'C:/laragon/bin/apache/httpd-2.4.54-win64-VS16' -WindowStyle Hidden | Out-Null
    Start-Sleep -Seconds 2
    if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
        Write-Host '[OK] Apache started.' -ForegroundColor Green
    } else {
        Write-Host '[WARN] Laragon se Start All try karein.' -ForegroundColor Yellow
    }
}

Write-Host ''
Write-Host ('Open: http://' + $ServerIp) -ForegroundColor Green
Write-Host ''
