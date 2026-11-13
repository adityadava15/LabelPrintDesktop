@echo off
setlocal
echo Membuat Shortcut Desktop untuk PCB Barcode Print...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ws = New-Object -ComObject WScript.Shell; $desktop = [System.Environment]::GetFolderPath('Desktop'); $s = $ws.CreateShortcut((Join-Path $desktop 'PCB Barcode Print.lnk')); $s.TargetPath = '%~dp0PCBBarcodePrint.exe'; $s.WorkingDirectory = '%~dp0'; $s.IconLocation = '%~dp0IMG\favicon.ico'; $s.Description = 'Sistem Cetak Barcode PCB Desktop'; $s.Save();"
echo Shortcut berhasil dibuat di Desktop!
pause
