@echo off
title Full backup ZIP
set "PHP="
for /d %%d in ("C:\laragon\bin\php\php-*") do set "PHP=%%d\php.exe"
if not defined PHP (
    echo PHP not found. Start Laragon first.
    pause
    exit /b 1
)
cd /d "%~dp0"
echo Creating full backup (code + database + data)...
echo.
"%PHP%" scripts\make-full-backup.php
echo.
pause
