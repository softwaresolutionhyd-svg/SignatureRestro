@echo off
title Signature Emergency Network Fix
net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"\"%~dp0emergency-cafe-network-fix.ps1\"\"'"
    exit /b 0
)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0emergency-cafe-network-fix.ps1"
echo.
pause
