# Apache ko port 8080 par Signature serve karne ke liye configure karta hai.
# setup-signature-lan-network.ps1 is script ko call karta hai.

$ErrorActionPreference = 'Stop'

$HttpdConf = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
$VhostSrc  = Join-Path $PSScriptRoot 'apache\signature-lan-8080.conf'
$VhostDest = 'C:\laragon\etc\apache2\sites-enabled\auto.signature-lan-8080.conf'
$ApacheBin = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'

function Ensure-Listen8080 {
    if (-not (Test-Path $HttpdConf)) {
        throw "Apache httpd.conf not found: $HttpdConf"
    }

    $lines = Get-Content $HttpdConf
    if ($lines -match '^\s*Listen\s+8080\s*$') {
        Write-Host '[OK] Apache already listens on port 8080.' -ForegroundColor DarkGray
        return $false
    }

    $updated = New-Object System.Collections.Generic.List[string]
    $inserted  = $false
    foreach ($line in $lines) {
        $updated.Add($line)
        if (-not $inserted -and $line -match '^\s*Listen\s+80\s*$') {
            $updated.Add('Listen 8080')
            $inserted = $true
        }
    }

    if (-not $inserted) {
        throw 'Could not find "Listen 80" in httpd.conf — add "Listen 8080" manually.'
    }

    Set-Content -Path $HttpdConf -Value $updated -Encoding ASCII
    Write-Host '[OK] Added Listen 8080 to Apache httpd.conf.' -ForegroundColor Green
    return $true
}

function Ensure-Vhost8080 {
    if (-not (Test-Path $VhostSrc)) {
        throw "Vhost template missing: $VhostSrc"
    }

    $needsCopy = $true
    if (Test-Path $VhostDest) {
        $current = Get-Content $VhostDest -Raw
        $source  = Get-Content $VhostSrc -Raw
        if ($current.Trim() -eq $source.Trim()) {
            $needsCopy = $false
        }
    }

    if ($needsCopy) {
        Copy-Item -Path $VhostSrc -Destination $VhostDest -Force
        Write-Host '[OK] Apache vhost for Signature on port 8080 installed.' -ForegroundColor Green
        return $true
    }

    Write-Host '[OK] Apache vhost for port 8080 already installed.' -ForegroundColor DarkGray
    return $false
}

function Invoke-ApacheCommand {
    param([string[]]$Args)
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        return & $ApacheBin @Args 2>&1
    } finally {
        $ErrorActionPreference = $prev
    }
}

function Test-ApacheConfig {
    if (-not (Test-Path $ApacheBin)) {
        throw "Apache binary not found: $ApacheBin"
    }

    if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
        Write-Host '[OK] Apache already running — config syntax check skipped (ports in use).' -ForegroundColor DarkGray
        return
    }

    $output = Invoke-ApacheCommand -Args @('-t')
    $output | ForEach-Object { Write-Host $_ }
    $text = ($output | Out-String)
    if ($text -notmatch 'Syntax OK') {
        throw 'Apache config test failed (httpd -t).'
    }
}

function Restart-Apache {
    if (-not (Test-Path $ApacheBin)) {
        Write-Host '[WARN] Apache restart skipped — binary not found.' -ForegroundColor Yellow
        return
    }

    $conf = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
    $running = Get-Process -Name httpd -ErrorAction SilentlyContinue

    if ($running) {
        # Laragon runs Apache as a process (not a Windows service).
        Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }

    Start-Process -FilePath $ApacheBin -ArgumentList '-f', $conf -WindowStyle Hidden | Out-Null
    Start-Sleep -Seconds 2
    Write-Host '[OK] Apache restarted (Laragon process mode).' -ForegroundColor Green
}

function Test-Port8080 {
    try {
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8080/' -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            Write-Host '[OK] Signature responds on http://127.0.0.1:8080/' -ForegroundColor Green
            return $true
        }
    } catch {
        Write-Host "[WARN] Port 8080 not responding yet: $($_.Exception.Message)" -ForegroundColor Yellow
    }
    return $false
}

$changed = $false
if (Ensure-Listen8080) { $changed = $true }
if (Ensure-Vhost8080) { $changed = $true }

Test-ApacheConfig

if ($changed) {
    Restart-Apache
    Start-Sleep -Seconds 2
} else {
    Write-Host '[OK] Apache 8080 config unchanged — restart skipped.' -ForegroundColor DarkGray
}

Test-Port8080 | Out-Null
