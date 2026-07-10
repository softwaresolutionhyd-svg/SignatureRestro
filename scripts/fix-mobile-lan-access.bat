@echo off
title Fix Mobile LAN Access
net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"\"%~dp0fix-mobile-lan-access.ps1\"\"'"
    exit /b 0
)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0fix-mobile-lan-access.ps1"
pause
