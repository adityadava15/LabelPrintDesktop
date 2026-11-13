<?php
/**
 * Master Distribution & Installer Builder
 * PCB Barcode Print v2.0
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

echo "---------------------------------------------------------\n";
echo "1. Mempersiapkan dan mengompresi payload aplikasi...\n";
echo "   (Mengabaikan cache browser 491 MB, git, dan file tes)\n";
echo "---------------------------------------------------------\n";

$payloadZip = $scratch . DIRECTORY_SEPARATOR . 'payload.zip';
if (file_exists($payloadZip)) @unlink($payloadZip);

$zip = new ZipArchive();
if ($zip->open($payloadZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("GAGAL: Tidak dapat membuat arsip payload.\n");
}

$essentialFiles = [
    'PCBBarcodePrint.exe',
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
    'Buat Shortcut Desktop.bat'
];

$essentialDirs = ['CSS', 'JS', 'JS2', 'IMG', 'php'];

foreach ($essentialFiles as $f) {
    $full = $root . DIRECTORY_SEPARATOR . $f;
    if (file_exists($full)) {
        $zip->addFile($full, $f);
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

        $zip->addFile($filePath, $relPath);
    }
}

$zip->close();
$zipSizeMb = round(filesize($payloadZip) / (1024 * 1024), 2);
echo "   [✓] Payload terkompresi: {$zipSizeMb} MB (turun drastis dari 565 MB!)\n\n";

echo "---------------------------------------------------------\n";
echo "2. Membuat paket Portable (dist/PCBBarcodePrint_v2.0_Portable.zip)...\n";
echo "---------------------------------------------------------\n";
$portableZip = $dist . DIRECTORY_SEPARATOR . 'PCBBarcodePrint_v2.0_Portable.zip';
copy($payloadZip, $portableZip);
echo "   [✓] Selesai: " . basename($portableZip) . " ({$zipSizeMb} MB)\n\n";

echo "---------------------------------------------------------\n";
echo "3. Mengompilasi Installer Mandiri (dist/Setup_PCBBarcodePrint.exe)...\n";
echo "---------------------------------------------------------\n";
$cscPath = 'C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe';
$setupExe = $dist . DIRECTORY_SEPARATOR . 'Setup_PCBBarcodePrint.exe';
$installerCs = $root . DIRECTORY_SEPARATOR . 'Installer.cs';
$iconPath = $root . DIRECTORY_SEPARATOR . 'favicon.ico';

$cscCmd = "\"$cscPath\" /nologo /target:winexe /out:\"$setupExe\" " .
          "/win32icon:\"$iconPath\" " .
          "/r:System.dll /r:System.Windows.Forms.dll /r:System.Drawing.dll " .
          "/r:System.IO.Compression.dll /r:System.IO.Compression.FileSystem.dll /r:Microsoft.CSharp.dll " .
          "/resource:\"$payloadZip\",payload.zip " .
          "/resource:\"$iconPath\",favicon.ico " .
          "\"$installerCs\"";

shell_exec($cscCmd);

if (file_exists($setupExe)) {
    $setupSizeMb = round(filesize($setupExe) / (1024 * 1024), 2);
    echo "   [✓] Selesai: " . basename($setupExe) . " ({$setupSizeMb} MB)\n\n";
} else {
    echo "   [-] Gagal mengompilasi Installer C#.\n\n";
}

echo "---------------------------------------------------------\n";
echo "4. Membuat Installer Ultra-Compact 7z SFX (~17 MB)...\n";
echo "---------------------------------------------------------\n";
$sevenZip = 'C:\Program Files\7-Zip\7z.exe';
$sfxModule = 'C:\Program Files\7-Zip\7z.sfx';
if (file_exists($sevenZip) && file_exists($sfxModule)) {
    $temp7z = $scratch . DIRECTORY_SEPARATOR . 'compact_payload.7z';
    if (file_exists($temp7z)) @unlink($temp7z);

    $filesToArchive = implode(' ', array_map(function ($f) { return "\"$f\""; }, array_merge($essentialFiles, $essentialDirs)));
    $cmd7z = "\"$sevenZip\" a -mx9 \"$temp7z\" $filesToArchive -xr!tests -xr!*.bak -xr!*.phpt >nul 2>&1";
    shell_exec($cmd7z);

    if (file_exists($temp7z)) {
        $sfxConfig = $scratch . DIRECTORY_SEPARATOR . 'sfx_config.txt';
        file_put_contents($sfxConfig, ";!@Install@!UTF-8!\r\nTitle=\"PCB Barcode Print Setup\"\r\nBeginPrompt=\"Instal PCB Barcode Print di komputer ini?\"\r\nExtractTitle=\"Mengekstrak PCB Barcode Print...\"\r\nExtractPathText=\"Pilih folder lokasi instalasi:\"\r\nExtractPathWidth=\"280\"\r\nGUIFlags=\"32\"\r\n;!@InstallEnd@!\r\n");

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
    printf(" * %-42s : %6.2f MB\n", basename($f), filesize($f) / (1024 * 1024));
}
echo "=========================================================\n";
