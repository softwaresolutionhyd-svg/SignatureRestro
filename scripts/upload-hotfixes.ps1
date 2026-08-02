#Requires -Version 5.1
<#
.SYNOPSIS
  Force-upload mobile UI + analytics hotfix files when GitHub FTP deploy fails.
#>
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $root

$envFile = Join-Path $root '.env.deploy'
if (-not (Test-Path $envFile)) {
    Write-Host '.env.deploy missing' -ForegroundColor Red
    exit 1
}

$config = @{}
Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -and -not $line.StartsWith('#') -and $line.Contains('=')) {
        $parts = $line.Split('=', 2)
        $key = $parts[0].Trim()
        $val = if ($parts.Length -gt 1) { $parts[1].Trim() } else { '' }
        if (($val.StartsWith('"') -and $val.EndsWith('"')) -or ($val.StartsWith("'") -and $val.EndsWith("'"))) {
            $val = $val.Substring(1, $val.Length - 2)
        }
        $config[$key] = $val
    }
}

$hostName = $config['FTP_SERVER']
$user = $config['FTP_USERNAME']
$pass = $config['FTP_PASSWORD']
if (-not $pass -and $config['FTP_PASS']) { $pass = $config['FTP_PASS'] }
$dir = if ($config['FTP_SERVER_DIR']) { $config['FTP_SERVER_DIR'] } else { '/' }
$dir = $dir.Trim().Trim('/')
# Chrooted FTP accounts already land in the Laravel root — do not prefix public_html/signature
if ($dir -eq 'public_html/signature' -or $dir -eq 'public_html\signature') {
    Write-Host 'NOTE: FTP_SERVER_DIR=public_html/signature looks nested; using project root /' -ForegroundColor Yellow
    $dir = ''
}

if ([string]::IsNullOrWhiteSpace($pass)) {
    Write-Host 'FTP_PASSWORD empty in .env.deploy — password set karein, phir dubara run karein.' -ForegroundColor Red
    Write-Host 'File: .env.deploy' -ForegroundColor Yellow
    exit 1
}

$files = @(
    'public/css/admin-shell.css',
    'public/css/admin-shell-v14.css',
    'resources/views/layouts/admin.blade.php',
    'resources/views/partials/admin/topbar.blade.php',
    'resources/views/partials/locale-switcher.blade.php',
    'app/Http/Controllers/AnalyticsController.php',
    'resources/views/analytics/index.blade.php',
    'lang/ur.json'
)

$prefix = if ($dir) { "$dir/" } else { '' }
Write-Host "Uploading hotfixes to ftp://$hostName/$prefix ..." -ForegroundColor Cyan

function Upload-Ftp([string]$localRel) {
    $localPath = Join-Path $root ($localRel -replace '/', '\')
    if (-not (Test-Path $localPath)) {
        throw "Missing: $localRel"
    }
    $remote = "ftp://$hostName/$prefix$localRel"
    & curl.exe -sS --ftp-create-dirs -T $localPath --user "${user}:${pass}" $remote
    if ($LASTEXITCODE -ne 0) {
        throw "Upload failed: $localRel (exit $LASTEXITCODE)"
    }
    Write-Host "OK $localRel"
}

foreach ($f in $files) {
    Upload-Ftp $f
}

Write-Host ''
Write-Host 'Verifying live CSS...' -ForegroundColor Cyan
$css = & curl.exe -s "https://signature.softwaresolutions.pk/css/admin-shell-v14.css?v=14"
if ($css -match 'admin-user-dropdown') {
    Write-Host '[OK] Live CSS has mobile/logout dropdown fixes' -ForegroundColor Green
} else {
    Write-Host '[WARN] Live CSS still looks stale (CDN may need a minute)' -ForegroundColor Yellow
    Write-Host ("Got length={0}" -f $css.Length)
}

Write-Host 'Done. Hard-refresh online app (Ctrl+F5).' -ForegroundColor Green
