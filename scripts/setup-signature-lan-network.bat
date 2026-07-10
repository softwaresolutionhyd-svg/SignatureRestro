@echo off
title Signature LAN / Extender Setup
echo.
echo Admin permission chahiye (firewall + .env update).
echo.

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"\"%~dp0setup-signature-lan-network.ps1\"\"'"
    exit /b 0
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-signature-lan-network.ps1"
echo.
pause
