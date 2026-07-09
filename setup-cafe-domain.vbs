Set shell = CreateObject("Shell.Application")
shell.ShellExecute "cmd.exe", "/c ""cd /d """ & CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName) & """ && setup-cafe-domain.bat""", "", "runas", 1
