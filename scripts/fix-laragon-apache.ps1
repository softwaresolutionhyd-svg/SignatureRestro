# Apache fix — Laragon "Failed" (duplicate Listen 443 / HTTPS mess)
# Run as Administrator: scripts\fix-laragon-apache.bat

$ErrorActionPreference = 'Stop'

$HttpdConf = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
$ApacheBin = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
$SslVhost  = 'C:\laragon\etc\apache2\sites-enabled\auto.signature-lan-ssl.conf'
$LanVhost  = 'C:\laragon\etc\apache2\sites-enabled\00-signature-lan-ip.conf'
$ServerIp  = '192.168.1.100'
$ProjectRoot = 'C:/laragon/www/signature/public'

Write-Host ''
Write-Host '=== Laragon Apache fix ===' -ForegroundColor Cyan
Write-Host ''

# 1) HTTPS vhost off
if (Test-Path $SslVhost) {
    Remove-Item $SslVhost -Force
    Write-Host '[OK] Removed HTTPS vhost.' -ForegroundColor Green
}

# 2) LAN vhost correct IP
$lanConf = @"
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
Set-Content -Path $LanVhost -Value $lanConf -Encoding ASCII
Write-Host "[OK] LAN vhost: $ServerIp" -ForegroundColor Green

# 3) Duplicate Listen 443 hatao (httpd-ssl.conf mein pehle se hai)
if (Test-Path $HttpdConf) {
    $lines = Get-Content $HttpdConf
    $seen443InMain = $false
    $newLines = foreach ($line in $lines) {
        if ($line -match '^\s*Listen\s+443\s*$') {
            if (-not $seen443InMain) {
                # httpd.conf se hatao — Laragon httpd-ssl.conf use karta hai
                Write-Host '[OK] Removed duplicate Listen 443 from httpd.conf' -ForegroundColor Green
                continue
            }
        }
        $line
    }
    Set-Content -Path $HttpdConf -Value $newLines -Encoding ASCII
}

# 4) Port 80 check
Write-Host ''
Write-Host 'Port 80 check:' -ForegroundColor Yellow
$port80 = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 3 OwningProcess, LocalAddress
if ($port80) {
    foreach ($p in $port80) {
        $proc = Get-Process -Id $p.OwningProcess -ErrorAction SilentlyContinue
        Write-Host "  Port 80 in use: PID $($p.OwningProcess) — $($proc.ProcessName)" -ForegroundColor Red
    }
    Write-Host '  Agar httpd nahi hai to IIS/Skype band karein ya Laragon port 8080 use karein.' -ForegroundColor Yellow
} else {
    Write-Host '  Port 80 free.' -ForegroundColor Green
}

# 5) Stale httpd kill + config test
Get-Process -Name httpd -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

if (Test-Path $ApacheBin) {
    $test = & $ApacheBin -t 2>&1 | Out-String
    Write-Host $test
    if ($test -notmatch 'Syntax OK') {
        Write-Host '[ERROR] Apache config still broken — Laragon > Apache > Reload error dekhein.' -ForegroundColor Red
        exit 1
    }
    Start-Process -FilePath $ApacheBin -ArgumentList '-d', 'C:/laragon/bin/apache/httpd-2.4.54-win64-VS16' -WindowStyle Hidden | Out-Null
    Start-Sleep -Seconds 2
    if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
        Write-Host '[OK] Apache started.' -ForegroundColor Green
    } else {
        Write-Host '[WARN] Apache start — Laragon se Start All try karein.' -ForegroundColor Yellow
    }
}

Write-Host ''
Write-Host "Open: http://${ServerIp}" -ForegroundColor Green
Write-Host ''
