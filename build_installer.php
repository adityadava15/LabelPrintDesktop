<?php
/**
 * Master Distribution & Installer Builder
 * PCB Barcode Print v2.4 (Studio Edition)
 */

$root = __DIR__;
$dist = $root . DIRECTORY_SEPARATOR . 'dist';
$scratch = $root . DIRECTORY_SEPARATOR . 'scratch';

if (!is_dir($dist)) {
    mkdir($dist, 0777, true);
}
if (!is_dir($scratch)) {
    mkdir($scratch, 0777, true);
}

$cscPath = 'C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe';

echo "=========================================================\n";
echo "    PCB BARCODE PRINT v2.4 - BUILDER DISTRIBUSI & SETUP   \n";
echo "=========================================================\n\n";

// 0. Kompilasi PCBBarcodePrint.exe dari Launcher.cs
echo "---------------------------------------------------------\n";
echo "0. Mengompilasi PCBBarcodePrint.exe dari Launcher.cs...\n";
echo "---------------------------------------------------------\n";

$launcherCs = $root . DIRECTORY_SEPARATOR . 'Launcher.cs';
$appExe = $root . DIRECTORY_SEPARATOR . 'PCBBarcodePrint.exe';
$appIcon = $root . DIRECTORY_SEPARATOR . 'IMG' . DIRECTORY_SEPARATOR . 'favicon.ico';
$manifest = $root . DIRECTORY_SEPARATOR . 'app.manifest';

if (file_exists($cscPath) && file_exists($launcherCs)) {
    $cscAppCmd = "\"$cscPath\" /nologo /target:winexe /win32icon:\"$appIcon\" /win32manifest:\"$manifest\" " .
                 "/out:\"$appExe\" /reference:System.dll,System.Windows.Forms.dll,System.Drawing.dll /optimize+ \"$launcherCs\"";
    shell_exec($cscAppCmd);
    if (file_exists($appExe)) {
        $appSizeKb = round(filesize($appExe) / 1024, 1);
        echo "   [✓] PCBBarcodePrint.exe berhasil dikompilasi ({$appSizeKb} KB)\n";
    } else {
        echo "   [!] Kompilasi Launcher.cs gagal, memeriksa file exe yang sudah ada...\n";
    }
} else {
    echo "   [!] csc.exe atau Launcher.cs tidak ditemukan, melompati kompilasi launcher.\n";
}

$directPrintCs = $root . DIRECTORY_SEPARATOR . 'DirectPrint.cs';
$directPrintExe = $root . DIRECTORY_SEPARATOR . 'DirectPrint.exe';
if (file_exists($cscPath) && file_exists($directPrintCs)) {
    $cscDirectCmd = "\"$cscPath\" /nologo /target:exe /out:\"$directPrintExe\" " .
                    "/r:System.dll /r:System.Drawing.dll " .
                    "/r:\"C:\\Windows\\Microsoft.NET\\Framework64\\v4.0.30319\\System.Runtime.dll\" " .
                    "/r:\"C:\\Windows\\Microsoft.NET\\Framework64\\v4.0.30319\\System.Runtime.WindowsRuntime.dll\" " .
                    "/r:\"C:\\Windows\\Microsoft.NET\\Framework64\\v4.0.30319\\System.Runtime.InteropServices.WindowsRuntime.dll\" " .
                    "/r:\"C:\\Windows\\System32\\WinMetadata\\Windows.Data.winmd\" " .
                    "/r:\"C:\\Windows\\System32\\WinMetadata\\Windows.Foundation.winmd\" " .
                    "/r:\"C:\\Windows\\System32\\WinMetadata\\Windows.Storage.winmd\" " .
                    "/optimize+ \"$directPrintCs\"";
    shell_exec($cscDirectCmd);
    if (file_exists($directPrintExe)) {
        $dpSizeKb = round(filesize($directPrintExe) / 1024, 1);
        echo "   [✓] DirectPrint.exe berhasil dikompilasi ({$dpSizeKb} KB)\n\n";
    }
}

// 1. Mempersiapkan dan mengompresi payload aplikasi
echo "---------------------------------------------------------\n";
echo "1. Mempersiapkan dan mengompresi payload aplikasi v2.4...\n";
echo "   (Mengabaikan cache browser, git, pdb, dan file tes)\n";
echo "---------------------------------------------------------\n";

$payloadZip = $scratch . DIRECTORY_SEPARATOR . 'payload.zip';
if (file_exists($payloadZip)) @unlink($payloadZip);

$zip = new ZipArchive();
if ($zip->open($payloadZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("GAGAL: Tidak dapat membuat arsip payload.\n");
}

$essentialFiles = [
    'PCBBarcodePrint.exe',
    'DirectPrint.exe',
    'DirectPrint.cs',
    'app.manifest',
    'index.php',
    'mode_select.php',
    'export_record_excel.php',
    'export_record_modal.php',
    'Login1.php',
    'Logout1.php',
    'Register1.php',
    'view.php',
    'userview.php',
    'api_printers.php',
    'db_config.php',
    'db_config.json',
    'db_setup.php',
    'db_check.php',
    'print-barcode.php',
    'test.sql',
    'type_meter_options.json',
    'ev_charger_type_options.json',
    'favicon.ico',
    'PETUNJUK_PENGGUNAAN_DESKTOP.md',
    'Buat Shortcut Desktop.bat',
    'DAFTAR_PERBAIKAN.md',
    'README.md'
];

$essentialDirs = ['CSS', 'JS', 'JS2', 'IMG', 'php'];

foreach ($essentialFiles as $f) {
    $full = $root . DIRECTORY_SEPARATOR . $f;
    if (file_exists($full)) {
        $zip->addFile($full, str_replace('\\', '/', $f));
    }
}

foreach ($essentialDirs as $d) {
    $dirFull = $root . DIRECTORY_SEPARATOR . $d;
    if (!is_dir($dirFull)) continue;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirFull, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $relPath = substr($filePath, strlen($root . DIRECTORY_SEPARATOR));

        // Skip unnecessary files
        if (strpos($relPath, 'tests') !== false) continue;
        if (strpos($relPath, '.bak') !== false) continue;
        if (strpos($relPath, '.phpt') !== false) continue;
        if (strpos($relPath, '.pdb') !== false) continue;

        // ZIP standard strictly requires forward slash for directories
        $zipEntryName = str_replace('\\', '/', $relPath);
        $zip->addFile($filePath, $zipEntryName);
    }
}

$zip->close();
$zipSizeMb = round(filesize($payloadZip) / (1024 * 1024), 2);
echo "   [✓] Payload terkompresi: {$zipSizeMb} MB\n\n";

// 2. Paket Portable (v2.4 dan v2.0 untuk kompatibilitas)
echo "---------------------------------------------------------\n";
echo "2. Membuat paket Portable (dist/PCBBarcodePrint_v2.4_Portable.zip)...\n";
echo "---------------------------------------------------------\n";
$portableZip24 = $dist . DIRECTORY_SEPARATOR . 'PCBBarcodePrint_v2.4_Portable.zip';
$portableZip20 = $dist . DIRECTORY_SEPARATOR . 'PCBBarcodePrint_v2.0_Portable.zip';

copy($payloadZip, $portableZip24);
copy($payloadZip, $portableZip20); // Alias untuk link lama
echo "   [✓] Selesai: " . basename($portableZip24) . " ({$zipSizeMb} MB)\n";
echo "   [✓] Selesai (alias kompatibilitas): " . basename($portableZip20) . "\n\n";

// 3. Mengompilasi Installer Mandiri (dist/Setup_PCBBarcodePrint.exe)
echo "---------------------------------------------------------\n";
echo "3. Mengompilasi Installer Mandiri GUI (dist/Setup_PCBBarcodePrint.exe)...\n";
echo "---------------------------------------------------------\n";
$setupExe = $dist . DIRECTORY_SEPARATOR . 'Setup_PCBBarcodePrint.exe';
$installerCs = $root . DIRECTORY_SEPARATOR . 'Installer.cs';
$iconPath = $root . DIRECTORY_SEPARATOR . 'IMG' . DIRECTORY_SEPARATOR . 'favicon.ico';
$logoPng = $root . DIRECTORY_SEPARATOR . 'IMG' . DIRECTORY_SEPARATOR . 'apple-touch-icon.png';

$logoRes = file_exists($logoPng) ? "/resource:\"$logoPng\",app_logo.png " : "";

$cscCmd = "\"$cscPath\" /nologo /target:winexe /out:\"$setupExe\" " .
          "/win32icon:\"$iconPath\" " .
          "/r:System.dll /r:System.Windows.Forms.dll /r:System.Drawing.dll " .
          "/r:System.IO.Compression.dll /r:System.IO.Compression.FileSystem.dll /r:Microsoft.CSharp.dll " .
          "/resource:\"$payloadZip\",payload.zip " .
          "/resource:\"$iconPath\",favicon.ico " .
          $logoRes .
          "\"$installerCs\"";

shell_exec($cscCmd);

if (file_exists($setupExe)) {
    $setupSizeMb = round(filesize($setupExe) / (1024 * 1024), 2);
    echo "   [✓] Selesai: " . basename($setupExe) . " ({$setupSizeMb} MB)\n\n";
} else {
    echo "   [-] Gagal mengompilasi Installer C#.\n\n";
}

// 4. Membuat Installer Ultra-Compact 7z SFX
echo "---------------------------------------------------------\n";
echo "4. Membuat Installer Ultra-Compact 7z SFX (~17 MB)...\n";
echo "---------------------------------------------------------\n";
$sevenZip = 'C:\Program Files\7-Zip\7z.exe';
$sfxModule = 'C:\Program Files\7-Zip\7z.sfx';
if (file_exists($sevenZip) && file_exists($sfxModule)) {
    $temp7z = $scratch . DIRECTORY_SEPARATOR . 'compact_payload.7z';
    if (file_exists($temp7z)) @unlink($temp7z);

    $filesToArchive = implode(' ', array_map(function ($f) { return "\"$f\""; }, array_merge($essentialFiles, $essentialDirs)));
    $cmd7z = "\"$sevenZip\" a -mx9 \"$temp7z\" $filesToArchive -xr!tests -xr!*.bak -xr!*.phpt -xr!*.pdb >nul 2>&1";
    shell_exec($cmd7z);

    if (file_exists($temp7z)) {
        $sfxConfig = $scratch . DIRECTORY_SEPARATOR . 'sfx_config.txt';
        $sfxContent = ";!@Install@!UTF-8!\r\n" .
                      "Title=\"PCB Barcode Print v2.4 Setup\"\r\n" .
                      "BeginPrompt=\"Instal PCB Barcode Print v2.4 (Studio Edition) di komputer ini?\"\r\n" .
                      "ExtractTitle=\"Mengekstrak PCB Barcode Print v2.4...\"\r\n" .
                      "ExtractPathText=\"Pilih folder lokasi instalasi:\"\r\n" .
                      "ExtractPathWidth=\"280\"\r\n" .
                      "GUIFlags=\"32\"\r\n" .
                      ";!@InstallEnd@!\r\n";
        file_put_contents($sfxConfig, $sfxContent);

        $sfxExe = $dist . DIRECTORY_SEPARATOR . 'Setup_PCBBarcodePrint_UltraCompact.exe';
        copy($sfxModule, $sfxExe);
        $fp = fopen($sfxExe, 'ab');
        fwrite($fp, file_get_contents($sfxConfig));
        fwrite($fp, file_get_contents($temp7z));
        fclose($fp);

        if (file_exists($sfxExe)) {
            $sfxSizeMb = round(filesize($sfxExe) / (1024 * 1024), 2);
            echo "   [✓] Selesai: " . basename($sfxExe) . " ({$sfxSizeMb} MB)\n\n";
        }
        @unlink($temp7z);
        @unlink($sfxConfig);
    }
}

// Cleanup payload temp
@unlink($payloadZip);

echo "=========================================================\n";
echo "PAKET INSTALASI BERHASIL DIBUAT DI FOLDER 'dist/':\n";
foreach (glob($dist . '/*') as $f) {
    printf(" * %-44s : %6.2f MB\n", basename($f), filesize($f) / (1024 * 1024));
}
echo "=========================================================\n";
