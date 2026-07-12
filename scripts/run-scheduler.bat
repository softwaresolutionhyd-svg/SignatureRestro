@echo off
REM Laragon: run this in a minimized window so sync runs even when browser is closed.
REM Double-click or add to Windows Startup / Laragon "Run at startup".

cd /d "%~dp0.."

:loop
php artisan schedule:run --no-interaction >nul 2>&1
timeout /t 60 /nobreak >nul
goto loop
