@echo off
title Signature POS - Clear Caches
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0dev-clear.ps1"
pause
