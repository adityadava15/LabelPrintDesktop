@echo off
echo ==============================================
echo Compiling PCBBarcodePrint.exe ...
echo ==============================================
C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe /target:winexe /win32icon:"IMG\favicon.ico" /win32manifest:"app.manifest" /out:"PCBBarcodePrint.exe" /reference:System.dll,System.Windows.Forms.dll,System.Drawing.dll /optimize+ "Launcher.cs"
if %ERRORLEVEL% EQU 0 (
    echo.
    echo [SUKSES] PCBBarcodePrint.exe berhasil dikompilasi!
) else (
    echo.
    echo [GAGAL] Kompilasi gagal. Periksa pesan error di atas.
)
echo.
