@echo off
title Signature - Local optimize
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0optimize-local.ps1"
echo.
pause
