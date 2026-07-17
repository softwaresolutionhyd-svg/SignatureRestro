@echo off
title Signature - LAN HTTPS (Not secure fix)
echo Admin permission chahiye.
echo.

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"\"%~dp0enable-signature-lan-https.ps1\"\"'"
    exit /b 0
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0enable-signature-lan-https.ps1"
echo.
pause
