@echo off
echo ==============================================
echo Compiling PCBBarcodePrint.exe ^& DirectPrint.exe ...
echo ==============================================
C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe /target:winexe /win32icon:"IMG\favicon.ico" /win32manifest:"app.manifest" /out:"PCBBarcodePrint.exe" /reference:System.dll,System.Windows.Forms.dll,System.Drawing.dll /optimize+ "Launcher.cs"
C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe /nologo /target:exe /out:"DirectPrint.exe" /r:System.dll /r:System.Drawing.dll /r:"C:\Windows\Microsoft.NET\Framework64\v4.0.30319\System.Runtime.dll" /r:"C:\Windows\Microsoft.NET\Framework64\v4.0.30319\System.Runtime.WindowsRuntime.dll" /r:"C:\Windows\Microsoft.NET\Framework64\v4.0.30319\System.Runtime.InteropServices.WindowsRuntime.dll" /r:"C:\Windows\System32\WinMetadata\Windows.Data.winmd" /r:"C:\Windows\System32\WinMetadata\Windows.Foundation.winmd" /r:"C:\Windows\System32\WinMetadata\Windows.Storage.winmd" /optimize+ "DirectPrint.cs"
if %ERRORLEVEL% EQU 0 (
    echo.
    echo [SUKSES] PCBBarcodePrint.exe ^& DirectPrint.exe berhasil dikompilasi!
) else (
    echo.
    echo [GAGAL] Kompilasi gagal. Periksa pesan error di atas.
)
echo.
