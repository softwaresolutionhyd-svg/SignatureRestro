#Requires -Version 5.1
<#
.SYNOPSIS
  Force-upload only critical entry files to fix recurring 403 Forbidden.
  Uses .env.deploy (same as deploy-ftp-local.bat).
#>
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $root 'public\index.php'))) {
    $root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
}
Set-Location $root

$envFile = Join-Path $root '.env.deploy'
if (-not (Test-Path $envFile)) {
    Write-Host '.env.deploy missing. Copy .env.deploy.example and set FTP_PASSWORD.' -ForegroundColor Red
    exit 1
}

$config = @{}
Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -and -not $line.StartsWith('#') -and $line.Contains('=')) {
        $parts = $line.Split('=', 2)
        $config[$parts[0].Trim()] = $parts[1].Trim()
    }
}

$hostName = $config['FTP_SERVER']
$user = $config['FTP_USERNAME']
$pass = $config['FTP_PASSWORD']
$dir = ($config['FTP_SERVER_DIR'] ?? 'public_html/signature/').TrimEnd('/')

if ([string]::IsNullOrWhiteSpace($pass)) {
    Write-Host 'FTP_PASSWORD empty in .env.deploy' -ForegroundColor Red
    exit 1
}

Write-Host "Uploading critical files to $hostName /$dir ..."

$curl = Get-Command curl.exe -ErrorAction SilentlyContinue
if (-not $curl) {
    Write-Host 'curl.exe required' -ForegroundColor Red
    exit 1
}

function Upload-Ftp([string]$localPath, [string]$remoteRel) {
    if (-not (Test-Path $localPath)) {
        throw "Missing local file: $localPath"
    }
    $remote = "ftp://$hostName/$dir/$remoteRel"
    & curl.exe -sS --ftp-create-dirs -T $localPath --user "${user}:${pass}" $remote
    if ($LASTEXITCODE -ne 0) {
        throw "Upload failed: $remoteRel"
    }
    Write-Host "OK $remoteRel"
}

Upload-Ftp (Join-Path $root 'index.php') 'index.php'
Upload-Ftp (Join-Path $root '.htaccess') '.htaccess'
Upload-Ftp (Join-Path $root 'public\index.php') 'public/index.php'
Upload-Ftp (Join-Path $root 'public\.htaccess') 'public/.htaccess'

Write-Host ''
Write-Host 'Done. Checking live site...'
$code = & curl.exe -s -o NUL -w '%{http_code}' --max-time 20 'https://signature.softwaresolutions.pk/'
Write-Host "GET / => HTTP $code"
if ($code -eq '403') {
    Write-Host 'Still 403. In cPanel check: Domains → signature Document Root = public_html/signature/public' -ForegroundColor Yellow
    Write-Host 'Also check Imunify360 / Virus Scanner quarantine and Restore PHP files.' -ForegroundColor Yellow
    exit 2
}
Write-Host 'Entry point looks reachable.' -ForegroundColor Green
