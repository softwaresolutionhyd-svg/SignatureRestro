# Auto commit + push to GitHub after Cursor agent finishes.
# Triggers "Deploy to Hosting" workflow on push to main.

$ErrorActionPreference = 'Continue'

# Consume hook JSON from stdin (stop event payload).
try {
    $null = [Console]::In.ReadToEnd()
} catch {}

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $projectRoot

$logFile = Join-Path $PSScriptRoot 'auto-push.log'

function Write-Log {
    param([string]$Message)
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Add-Content -Path $logFile -Value $line -Encoding UTF8
}

if (-not (Test-Path (Join-Path $projectRoot '.git'))) {
    Write-Log 'Skip: not a git repository.'
    exit 0
}

$branch = (git rev-parse --abbrev-ref HEAD 2>$null)
if ($branch -ne 'main') {
    Write-Log "Skip: on branch '$branch' (only auto-push on main)."
    exit 0
}

$status = git status --porcelain 2>&1
if (-not $status -or ($status | Where-Object { $_.Trim() }).Count -eq 0) {
    Write-Log 'Skip: no changes.'
    exit 0
}

# Ignore noisy runtime-only paths if they appear as untracked.
$lines = @($status | Where-Object { $_.Trim() })
$skipPatterns = @(
    '^\?\? storage[\\/]logs[\\/]',
    '^\?\? \.ftp-deploy-sync-state\.json$',
    '^\?\? backup[\\/].*\.(sql|zip)$'
)
$meaningful = $lines | Where-Object {
    $line = $_
    -not ($skipPatterns | Where-Object { $line -match $_ })
}
if (-not $meaningful -or $meaningful.Count -eq 0) {
    Write-Log 'Skip: only ignored runtime files changed.'
    exit 0
}

git add -A 2>&1 | Out-Null

$staged = git diff --cached --name-only 2>&1
if (-not $staged -or ($staged | Where-Object { $_.Trim() }).Count -eq 0) {
    Write-Log 'Skip: nothing staged after git add.'
    exit 0
}

$msg = "Auto deploy $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
$commitOut = git commit -m $msg 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Log "Commit failed: $commitOut"
    exit 0
}

Write-Log "Committed: $msg"

$pushOut = git push origin main 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Log "Push failed: $pushOut"
    exit 0
}

Write-Log 'Push OK -> GitHub Actions deploy will run.'
exit 0
