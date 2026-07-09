@echo off
set PATH=C:\laragon\bin\nodejs\node-v18;C:\laragon\bin\nodejs\node-v18\node_modules\npm\bin;%PATH%
cd /d "%~dp0"
echo.
echo === Afro Fresh Store - Vercel Deploy ===
echo.
if not exist "node_modules\vercel" (
  echo Installing Vercel CLI...
  call npm install vercel --no-save
)
echo.
echo Step 1: Login to Vercel (browser will open)
call node_modules\.bin\vercel.cmd login
echo.
echo Step 2: Deploying to production...
call node_modules\.bin\vercel.cmd deploy --prod
echo.
pause
