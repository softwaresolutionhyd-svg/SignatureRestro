Set shell = CreateObject("Shell.Application")
shell.ShellExecute "cmd.exe", "/c ""cd /d """ & CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName) & """ && setup-mobile-dns.bat""", "", "runas", 1
