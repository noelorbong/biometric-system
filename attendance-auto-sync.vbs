Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "C:\xampp\biometric-system"
WshShell.Run """C:\xampp\php\php.exe"" artisan attendance:auto-sync:daemon --sleep=1", 0, False