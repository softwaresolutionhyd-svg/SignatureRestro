# Signature LAN HTTPS hata kar HTTP restore karta hai (software dubara chale).
# Run as Administrator: scripts\disable-signature-lan-https.bat

$ErrorActionPreference = 'Stop'

$ServerIp    = '192.168.1.100'
$HttpdConf   = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
$ApacheBin   = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
$SslVhost    = 'C:\laragon\etc\apache2\sites-enabled\auto.signature-lan-ssl.conf'
$LanVhost    = 'C:\laragon\etc\apache2\sites-enabled\00-signature-lan-ip.conf'
$ProjectRoot = 'C:/laragon/www/signature/public'

Write-Host ''
Write-Host '=== Signature — HTTP restore (HTTPS off) ===' -ForegroundColor Cyan
Write-Host ''

if (Test-Path $SslVhost) {
    $disabled = $SslVhost + '.disabled'
    if (Test-Path $disabled) { Remove-Item $disabled -Force }
    Rename-Item -Path $SslVhost -NewName (Split-Path $disabled -Leaf) -Force
    Write-Host '[OK] HTTPS vhost disabled.' -ForegroundColor Green
} else {
    Write-Host '[OK] HTTPS vhost already off.' -ForegroundColor DarkGray
}

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
Write-Host "[OK] LAN vhost updated: $ServerIp" -ForegroundColor Green

if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
    Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
}
if (Test-Path $ApacheBin) {
    Start-Process -FilePath $ApacheBin -ArgumentList '-f', $HttpdConf -WindowStyle Hidden | Out-Null
    Start-Sleep -Seconds 2
    Write-Host '[OK] Apache restarted.' -ForegroundColor Green
} else {
    Write-Host '[WARN] Laragon se Apache manually Start karein.' -ForegroundColor Yellow
}

Write-Host ''
Write-Host "Ab yeh URL try karein: http://${ServerIp}" -ForegroundColor Green
Write-Host 'HTTPS baad mein dubara setup kar sakte hain — abhi HTTP theek hai.' -ForegroundColor Yellow
Write-Host ''
