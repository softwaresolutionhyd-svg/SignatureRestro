@echo off
setlocal enabledelayedexpansion
echo.
echo PC par yeh URLs try karo (Laragon Apache GREEN):
echo.
echo   http://Softwaresolution.test/
echo   http://127.0.0.1/
echo.
for /f "usebackq tokens=2 delims=:" %%a in (`ipconfig ^| findstr /c:"IPv4"`) do (
    set "ip=%%a"
    set "ip=!ip:~1!"
    echo !ip! | findstr /r "^192\.168\." >nul && echo   http://!ip!/
)
echo.
echo Mat use karo: localhost:8000 ya softwaresolution.cafe
echo.
start http://Softwaresolution.test/
pause
