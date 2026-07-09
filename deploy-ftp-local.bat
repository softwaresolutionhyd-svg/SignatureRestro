@echo off
setlocal enabledelayedexpansion
title Deploy FTP - Signature (direct to hosting)

cd /d "%~dp0"

if not exist ".env.deploy" (
    echo.
    echo  .env.deploy file nahi mila.
    echo  Copy karo: .env.deploy.example -^> .env.deploy
    echo  GitHub Secrets wali same FTP values daalo.
    echo.
    pause
    exit /b 1
)

for /f "usebackq tokens=1,* delims==" %%A in (".env.deploy") do (
    set "line=%%A"
    if not "!line:~0,1!"=="#" if not "%%A"=="" set "%%A=%%B"
)

if "%FTP_SERVER%"=="" goto :missing
if "%FTP_USERNAME%"=="" goto :missing
if "%FTP_PASSWORD%"=="" goto :missing
if "%FTP_SERVER_DIR%"=="" goto :missing

where node >nul 2>&1
if errorlevel 1 (
  if exist "C:\laragon\bin\nodejs\node-v*\node.exe" (
    for /d %%n in ("C:\laragon\bin\nodejs\node-v*") do set "PATH=%%n;%%n\node_modules\npm\bin;!PATH!"
  )
)

where npx >nul 2>&1
if errorlevel 1 (
    echo Node/npx not found. Laragon mein Node install karo.
    pause
    exit /b 1
)

echo ============================================
echo  Direct FTP deploy - signature.softwaresolutions.pk
echo  (GitHub Actions down ho to ye use karo)
echo ============================================
echo.

call npx --yes @samkirkland/ftp-deploy@1.0.0 --server "%FTP_SERVER%" --username "%FTP_USERNAME%" --password "%FTP_PASSWORD%" --server-dir "%FTP_SERVER_DIR%" --local-dir "./" --exclude ".git/** .git*/** node_modules/** .env .env.* backup/** storage/logs/** storage/framework/sessions/** storage/framework/cache/** .cursor/**"
if errorlevel 1 (
    echo FTP deploy failed.
    pause
    exit /b 1
)

echo.
echo [OK] Live site par code upload ho gaya.
echo Check: https://signature.softwaresolutions.pk
echo.
pause
exit /b 0

:missing
echo .env.deploy incomplete. FTP_SERVER, FTP_USERNAME, FTP_PASSWORD, FTP_SERVER_DIR required.
pause
exit /b 1
