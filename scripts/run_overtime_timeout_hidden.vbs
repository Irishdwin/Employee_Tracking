Option Explicit
Dim shell, files, runner, command
Set shell = CreateObject("WScript.Shell")
Set files = CreateObject("Scripting.FileSystemObject")
runner = files.BuildPath(files.GetParentFolderName(WScript.ScriptFullName), "run_overtime_timeout.ps1")
command = "powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File " & Chr(34) & runner & Chr(34)
shell.Run command, 0, True
