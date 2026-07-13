@echo off
title Signature POS - Sync Kitchen Agents to Cloud
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sync-kitchen-agents.ps1"
pause
