# signature.restro virtual host — Laragon Apache + Windows hosts
# Run as Administrator: scripts\setup-signature-restro-vhost.bat

$ErrorActionPreference = 'Stop'

$VhostSrc  = Join-Path $PSScriptRoot 'apache\signature.restro.conf'
$VhostDest = 'C:\laragon\etc\apache2\sites-enabled\auto.signature.restro.conf'
$ApacheBin = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
$HttpdConf = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
$HostsFile = 'C:\Windows\System32\drivers\etc\hosts'
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$EnvFile = Join-Path $ProjectRoot '.env'

function Ensure-HostsEntry {
    $entry = '127.0.0.1      signature.restro       #laragon magic!'
    $content = Get-Content $HostsFile -ErrorAction Stop
    if ($content -match 'signature\.restro') {
        Write-Host '[OK] hosts file already has signature.restro' -ForegroundColor DarkGray
        return
    }
    try {
        Add-Content -Path $HostsFile -Value $entry -Encoding ASCII
        Write-Host '[OK] Added signature.restro to hosts file.' -ForegroundColor Green
    } catch {
        Write-Host '[WARN] hosts file update needs Administrator.' -ForegroundColor Yellow
        Write-Host '       Run scripts\setup-signature-restro-vhost.bat as Admin once.' -ForegroundColor Yellow
    }
}

function Ensure-Vhost {
    if (-not (Test-Path $VhostSrc)) {
        throw "Vhost template missing: $VhostSrc"
    }
    Copy-Item -Path $VhostSrc -Destination $VhostDest -Force
    Write-Host "[OK] Apache vhost installed: $VhostDest" -ForegroundColor Green
}

function Update-EnvUrl {
    if (-not (Test-Path $EnvFile)) {
        Write-Host '[WARN] .env not found — APP_URL update skipped.' -ForegroundColor Yellow
        return
    }
    $lines = Get-Content $EnvFile
    $updated = $false
    $newLines = foreach ($line in $lines) {
        if ($line -match '^APP_URL=') {
            $updated = $true
            'APP_URL=http://signature.restro'
        } else {
            $line
        }
    }
    if ($updated) {
        Set-Content -Path $EnvFile -Value $newLines -Encoding UTF8
        Write-Host '[OK] APP_URL set to http://signature.restro' -ForegroundColor Green
    }
}

function Restart-Apache {
    if (-not (Test-Path $ApacheBin)) {
        Write-Host '[WARN] Apache binary not found — restart Laragon manually.' -ForegroundColor Yellow
        return
    }
    $running = Get-Process -Name httpd -ErrorAction SilentlyContinue
    if ($running) {
        Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }
    Start-Process -FilePath $ApacheBin -ArgumentList '-f', $HttpdConf -WindowStyle Hidden | Out-Null
    Start-Sleep -Seconds 2
    Write-Host '[OK] Apache restarted.' -ForegroundColor Green
}

function Test-SignatureRestro {
    try {
        $response = Invoke-WebRequest -Uri 'http://signature.restro/' -UseBasicParsing -TimeoutSec 8
        if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400) {
            Write-Host '[OK] http://signature.restro/ is responding.' -ForegroundColor Green
            return
        }
    } catch {
        Write-Host "[WARN] Could not reach http://signature.restro/ - $($_.Exception.Message)" -ForegroundColor Yellow
        Write-Host '       Laragon > Apache > Start, then refresh browser.' -ForegroundColor Yellow
    }
}

Write-Host ''
Write-Host '=== Signature Restro virtual host setup ===' -ForegroundColor Cyan
Write-Host ''

Ensure-HostsEntry
Ensure-Vhost
Update-EnvUrl
Restart-Apache
Test-SignatureRestro

Write-Host ''
Write-Host 'Open: http://signature.restro/' -ForegroundColor Green
Write-Host ''
