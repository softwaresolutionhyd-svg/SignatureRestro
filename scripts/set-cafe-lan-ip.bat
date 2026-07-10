@echo off
title Signature - Fixed LAN IP
echo Admin permission chahiye.
echo.

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"\"%~dp0set-cafe-lan-ip.ps1\"\"'"
    exit /b 0
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0set-cafe-lan-ip.ps1"
echo.
pause
