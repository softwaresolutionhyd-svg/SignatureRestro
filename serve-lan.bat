@echo off
title Signature POS - LAN Server
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0serve-lan.ps1"
pause
