#Requires -Version 5.1
$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $root

$envFile = Join-Path $root '.env.deploy'
if (-not (Test-Path $envFile)) {
    Write-Host '.env.deploy missing. Copy from .env.deploy.example' -ForegroundColor Red
    exit 1
}

$config = @{}
Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -and -not $line.StartsWith('#') -and $line -match '^([^=]+)=(.*)$') {
        $config[$Matches[1].Trim()] = $Matches[2].Trim()
    }
}

$server = $config['FTP_SERVER']
$username = $config['FTP_USERNAME']
$password = $config['FTP_PASSWORD']
$serverDir = $config['FTP_SERVER_DIR']

if (-not $server -or -not $username) {
    Write-Host 'FTP_SERVER and FTP_USERNAME required in .env.deploy' -ForegroundColor Red
    exit 1
}

if (-not $password) {
    $secure = Read-Host 'FTP password (.env.deploy empty)' -AsSecureString
    $password = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    )
}

if (-not $serverDir) { $serverDir = '/' }
$serverDir = $serverDir.Trim()
if (-not $serverDir.EndsWith('/')) { $serverDir += '/' }
if ($serverDir.StartsWith('/')) { $serverDir = $serverDir.TrimStart('/') }

# Match .github/workflows/deploy.yml excludes
$excludePatterns = @(
    '\\\.git',
    '\\node_modules\\',
    '\\backup\\',
    '\\storage\\logs\\',
    '\\storage\\framework\\sessions\\',
    '\\storage\\framework\\cache\\',
    '\\\.env$',
    '\\\.env\.',
    '\\\.ftp-deploy-sync-state\.json$'
)

function Should-Exclude([string]$relativePath) {
    $normalized = $relativePath -replace '/', '\'
    foreach ($pattern in $excludePatterns) {
        if ($normalized -match $pattern) { return $true }
    }
    return $false
}

function Ensure-FtpDirectory([string]$remoteDir) {
    $parts = $remoteDir.Trim('/').Split('/')
    $current = ''
    foreach ($part in $parts) {
        if (-not $part) { continue }
        $current += "/$part"
        $uri = "ftp://$server$current"
        try {
            $req = [System.Net.FtpWebRequest]::Create($uri)
            $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
            $req.Credentials = New-Object System.Net.NetworkCredential($username, $password)
            $req.UsePassive = $true
            $req.UseBinary = $true
            $req.KeepAlive = $false
            $resp = $req.GetResponse()
            $resp.Close()
        } catch {
            # Directory likely already exists.
        }
    }
}

function Upload-File([string]$localPath, [string]$remotePath) {
    $remotePath = $remotePath -replace '\\', '/'
    $remoteDir = Split-Path $remotePath -Parent
    if ($remoteDir) {
        Ensure-FtpDirectory $remoteDir
    }

    $uri = "ftp://$server/$remotePath"
    $req = [System.Net.FtpWebRequest]::Create($uri)
    $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $req.Credentials = New-Object System.Net.NetworkCredential($username, $password)
    $req.UsePassive = $true
    $req.UseBinary = $true
    $req.KeepAlive = $false

    $bytes = [System.IO.File]::ReadAllBytes($localPath)
    $req.ContentLength = $bytes.Length
    $stream = $req.GetRequestStream()
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Close()
    $resp = $req.GetResponse()
    $resp.Close()
}

Write-Host '============================================' -ForegroundColor Cyan
Write-Host ' Direct FTP deploy -> signature.softwaresolutions.pk' -ForegroundColor Cyan
Write-Host ' GitHub Actions down ho to ye use karo' -ForegroundColor Cyan
Write-Host '============================================' -ForegroundColor Cyan
Write-Host ''

$files = Get-ChildItem -Path $root -Recurse -File -ErrorAction SilentlyContinue
$uploaded = 0
$skipped = 0

foreach ($file in $files) {
    $relative = $file.FullName.Substring($root.Length).TrimStart('\', '/')
    if (Should-Exclude $relative) {
        $skipped++
        continue
    }
    $remote = ($serverDir + ($relative -replace '\\', '/'))
    try {
        Upload-File $file.FullName $remote
        $uploaded++
        if ($uploaded % 50 -eq 0) {
            Write-Host "Uploaded $uploaded files..."
        }
    } catch {
        Write-Host "FAIL: $relative -> $($_.Exception.Message)" -ForegroundColor Red
        exit 1
    }
}

Write-Host ''
Write-Host "[OK] FTP deploy complete. Uploaded: $uploaded, skipped: $skipped" -ForegroundColor Green
Write-Host 'Check: https://signature.softwaresolutions.pk' -ForegroundColor Green
