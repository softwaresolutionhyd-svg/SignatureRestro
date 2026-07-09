@echo off
setlocal enabledelayedexpansion
title Import database - Signature

set "MYSQL="
for /d %%d in ("C:\laragon\bin\mysql\mysql-*") do set "MYSQL=%%d\bin\mysql.exe"
if not defined MYSQL (
    echo MySQL not found. Laragon start karo.
    pause
    exit /b 1
)

cd /d "%~dp0"

set "DB=signature_local"
set "SQL="
for /f "delims=" %%f in ('dir /b /o-d backup\database-full-*.sql 2^>nul') do (
    set "SQL=backup\%%f"
    goto :found
)
:found

if not defined SQL (
    echo backup\database-full-*.sql nahi mila.
    echo Pehle make-full-backup.bat is PC par chalao.
    pause
    exit /b 1
)

echo Database: %DB%
echo Import file: %SQL%
echo.

echo Creating database (if missing)...
"%MYSQL%" -uroot -e "CREATE DATABASE IF NOT EXISTS `%DB%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo CREATE DATABASE failed. MySQL Laragon mein START hona chahiye.
    pause
    exit /b 1
)

echo Importing data (1-2 min)...
"%MYSQL%" -uroot %DB% < "%SQL%"
if errorlevel 1 (
    echo Import FAILED.
    pause
    exit /b 1
)

echo.
echo [OK] Database import complete!
echo Ab browser: http://signature.test/login
echo Login: admin@example.com / admin12345
echo.
pause
