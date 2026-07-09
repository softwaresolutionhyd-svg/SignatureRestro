@echo off
setlocal enabledelayedexpansion
title Publish - Signature (local + online)

set "PHP="
for /d %%d in ("C:\laragon\bin\php\php-*") do set "PHP=%%d\php.exe"
if not defined PHP (
    echo PHP not found. Laragon start karo.
    pause
    exit /b 1
)

cd /d "%~dp0"

echo ============================================
echo  Signature - Publish to localhost + online
echo ============================================
echo.
echo  Code  -^> GitHub -^> signature.softwaresolutions.pk
echo  Data  -^> sync   -^> hosting MySQL
echo.

echo [1/3] Database sync (local -^> hosting)...
"%PHP%" artisan sync:cloud --status
"%PHP%" artisan sync:cloud
if errorlevel 1 (
    echo Sync warning: check internet / SYNC_TOKEN / hosting .env
)
echo.

echo [2/3] Git status...
git status -sb 2>nul
if errorlevel 1 (
    echo Git not initialized. Run: git init
    pause
    exit /b 1
)
echo.

set "MSG=%~1"
if "%MSG%"=="" (
    set /p MSG=Commit message: 
)
if "!MSG!"=="" (
    echo Commit message required.
    pause
    exit /b 1
)

echo [3/3] Push code to GitHub...
git add .
git commit -m "!MSG!"
if errorlevel 1 (
    echo Nothing new to commit, or commit failed.
) else (
    git push origin main
    if errorlevel 1 (
        echo Push failed. GitHub Desktop se push try karo.
        pause
        exit /b 1
    )
)

echo.
echo [OK] Done!
echo  - Data: hosting DB par sync ho gaya (agar net tha)
echo  - Code: GitHub Actions 2-5 min mein live site par deploy karega
echo  - Agar Actions "Queued" par atka ho: deploy-ftp-local.bat use karo
echo  - Check: https://signature.softwaresolutions.pk
echo.
pause
