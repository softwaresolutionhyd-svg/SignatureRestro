#Requires -Version 5.1
<#
.SYNOPSIS
  Force-upload all recent offline code changes to live hosting (correct FTP root).
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
if ($dir -eq 'public_html/signature' -or $dir -eq 'public_html\signature') {
    Write-Host 'NOTE: FTP chroot is project root — ignoring public_html/signature' -ForegroundColor Yellow
    $dir = ''
}
$prefix = if ($dir) { "$dir/" } else { '' }

if ([string]::IsNullOrWhiteSpace($pass)) {
    Write-Host 'FTP_PASSWORD empty in .env.deploy' -ForegroundColor Red
    exit 1
}

$files = @(
    # Analytics + admin shell / mobile
    'app/Http/Controllers/AnalyticsController.php',
    'resources/views/analytics/index.blade.php',
    'resources/views/layouts/admin.blade.php',
    'resources/views/partials/admin/topbar.blade.php',
    'resources/views/partials/locale-switcher.blade.php',
    'public/css/admin-shell.css',
    'public/css/admin-shell-v14.css',
    'lang/ur.json',
    # POS / receipt / closing / reports
    'app/Http/Controllers/Pos/PosController.php',
    'app/Http/Controllers/ReportsController.php',
    'app/Services/NetworkPrinterService.php',
    'app/Services/OrderTakerService.php',
    'app/Services/KitchenService.php',
    'public/js/restaurant-pos-app.js',
    'public/css/restaurant-pos.css',
    'resources/views/pos/restaurant.blade.php',
    'resources/views/pos/receipt.blade.php',
    'resources/views/pos/kitchen-slip.blade.php',
    'resources/views/pos/open-session.blade.php',
    'resources/views/pos/closing/index.blade.php',
    'resources/views/pos/closing/print.blade.php',
    'resources/views/reports/sales-show.blade.php',
    'resources/views/reports/pos-sessions.blade.php',
    # HR / payroll / loans / attendance
    'app/Http/Controllers/Employee/AttendanceController.php',
    'app/Http/Controllers/Employee/EmployeeController.php',
    'app/Http/Controllers/Employee/EmployeeLoanController.php',
    'app/Models/Employee.php',
    'app/Services/AttendancePayrollService.php',
    'app/Services/PayrollSalaryService.php',
    'resources/views/employees/index.blade.php',
    'resources/views/employees/attendance-index.blade.php',
    'resources/views/employees/loans-index.blade.php',
    'resources/views/employees/payroll-index.blade.php',
    'resources/views/employees/payroll-slip.blade.php',
    # PWA / LAN / auth
    'app/Http/Controllers/Auth/LoginController.php',
    'app/Http/Controllers/Auth/TotpVerificationController.php',
    'app/Http/Controllers/LanCaController.php',
    'app/Http/Controllers/LanInstallGuideController.php',
    'app/Providers/AppServiceProvider.php',
    'app/Support/AdminBreadcrumbs.php',
    'app/Support/WebAuthSession.php',
    'public/manifest.webmanifest',
    'public/sw.js',
    'resources/views/auth/login.blade.php',
    'resources/views/lan-install.blade.php',
    'resources/views/partials/pwa-head.blade.php',
    'resources/views/partials/pwa-register.blade.php',
    'resources/views/order-taker/pos.blade.php',
    'resources/views/kitchen/partials/today-consumption.blade.php',
    'routes/web.php'
)

Write-Host "Uploading $($files.Count) files to ftp://$hostName/$prefix ..." -ForegroundColor Cyan

$ok = 0
$fail = 0
foreach ($localRel in $files) {
    $localPath = Join-Path $root ($localRel -replace '/', '\')
    if (-not (Test-Path $localPath)) {
        Write-Host "SKIP missing $localRel" -ForegroundColor Yellow
        continue
    }
    $remote = "ftp://$hostName/$prefix$localRel"
    & curl.exe -sS --ftp-create-dirs -T $localPath --user "${user}:${pass}" $remote
    if ($LASTEXITCODE -ne 0) {
        Write-Host "FAIL $localRel (exit $LASTEXITCODE)" -ForegroundColor Red
        $fail++
        continue
    }
    $ok++
    Write-Host "OK $localRel"
}

Write-Host ''
Write-Host "Uploaded OK=$ok FAIL=$fail" -ForegroundColor $(if ($fail -gt 0) { 'Yellow' } else { 'Green' })

# Bust Laravel compiled views if endpoint exists
try {
    $code = & curl.exe -s -o NUL -w '%{http_code}' --max-time 15 'https://signature.softwaresolutions.pk/clear-config.php'
    Write-Host "clear-config.php => HTTP $code"
} catch {}

Write-Host 'Done. Hard-refresh online (Ctrl+F5).' -ForegroundColor Green
