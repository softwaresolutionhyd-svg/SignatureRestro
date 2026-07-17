' Runs Laravel schedule:run with no visible console window.
' Called by Windows Task Scheduler (SignatureCloudSync).
Option Explicit
Dim sh, php, artisan, cmd, code
Set sh = CreateObject("WScript.Shell")
php = "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
artisan = "C:\laragon\www\signature\artisan"
If WScript.Arguments.Count >= 1 Then php = WScript.Arguments(0)
If WScript.Arguments.Count >= 2 Then artisan = WScript.Arguments(1)
sh.CurrentDirectory = Left(artisan, InStrRev(artisan, "\") - 1)
cmd = """" & php & """ """ & artisan & """ schedule:run"
' 0 = hidden window, True = wait for exit
code = sh.Run(cmd, 0, True)
WScript.Quit code
