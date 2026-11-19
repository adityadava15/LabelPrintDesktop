<?php
/**
 * API Printers - Mengambil daftar printer dari Windows Settings (Printers & Scanners)
 * Mendeteksi status Ready / Offline, menyimpan Default Printer KHUSUS aplikasi ini
 * tanpa mengubah pengaturan Default Printer Windows pengguna.
 */
header('Content-Type: application/json; charset=utf-8');

// Matikan error display agar output JSON tidak rusak oleh warning PHP
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'list';

/**
 * Lokasi file konfigurasi default printer khusus software ini
 */
function getPrinterConfigFile() {
    $dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0777, true);
    }
    return $dataDir . DIRECTORY_SEPARATOR . 'printer_config.json';
}

function loadPrinterConfig() {
    $defaults = [
        'app_default_printer' => '',
        'updated_at' => '',
        'pending_restore_windows_printer' => '',
        'pending_restore_time' => 0
    ];
    $file = getPrinterConfigFile();
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw) {
            $decoded = @json_decode($raw, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }
    }
    return $defaults;
}

function savePrinterConfig(array $config) {
    $file = getPrinterConfigFile();
    $current = loadPrinterConfig();
    $merged = array_merge($current, $config);
    return (bool) @file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Membaca Default Printer Windows saat ini dari Registry (cepat ~15ms)
 */
function getCurrentWindowsDefaultPrinter() {
    $out = @shell_exec('reg query "HKCU\Software\Microsoft\Windows NT\CurrentVersion\Windows" /v Device 2>nul');
    if ($out && preg_match('/\bDevice\s+REG_SZ\s+([^\r\n]+)/i', $out, $m)) {
        $parts = explode(',', trim($m[1]));
        return trim($parts[0]);
    }
    return '';
}

/**
 * Mengatur spooler default printer Windows sementara (hanya saat proses silent print berlangsung)
 */
function setWindowsSpoolerDefaultPrinter($printerName) {
    $printerName = trim((string)$printerName);
    if ($printerName === '') return false;
    $safeArg = str_replace('"', '\"', $printerName);
    @exec('rundll32.exe printui.dll,PrintUIEntry /y /n "' . $safeArg . '"');
    return true;
}

/**
 * Mengembalikan Default Printer Windows ke pengaturan asli pengguna setelah silent print selesai
 */
function restorePendingWindowsDefaultPrinter($force = false) {
    $cfg = loadPrinterConfig();
    $pending = trim((string)($cfg['pending_restore_windows_printer'] ?? ''));
    if ($pending === '') {
        return false;
    }
    $elapsed = time() - (int)($cfg['pending_restore_time'] ?? 0);
    if ($force || $elapsed >= 10) {
        setWindowsSpoolerDefaultPrinter($pending);
        savePrinterConfig([
            'pending_restore_windows_printer' => '',
            'pending_restore_time' => 0
        ]);
        return true;
    }
    return false;
}

/**
 * Sinkronisasi printer terpilih ke preferensi profil Chromium (Edge & Chrome)
 */
function syncProfilePreferences($printerName) {
    $prefFiles = [
        __DIR__ . '/data/profile/Default/Preferences',
        __DIR__ . '/data/profile_chrome/Default/Preferences'
    ];
    $updated = false;

    foreach ($prefFiles as $prefFile) {
        if (!file_exists($prefFile)) continue;

        $raw = @file_get_contents($prefFile);
        if (!$raw) continue;

        $data = @json_decode($raw, true);
        if (!is_array($data)) continue;

        if (!isset($data['printing'])) {
            $data['printing'] = [];
        }
        if (!isset($data['printing']['print_preview_sticky_settings'])) {
            $data['printing']['print_preview_sticky_settings'] = [];
        }

        $appState = [];
        if (!empty($data['printing']['print_preview_sticky_settings']['appState'])) {
            $appState = @json_decode($data['printing']['print_preview_sticky_settings']['appState'], true) ?: [];
        }

        $appState['version'] = 2;
        $appState['recentDestinations'] = [
            [
                'id' => $printerName,
                'origin' => 'local',
                'displayName' => $printerName,
                'printerStatus' => 'printerStatusReady',
                'extensionId' => '',
                'extensionName' => ''
            ]
        ];

        $data['printing']['print_preview_sticky_settings']['appState'] = json_encode($appState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        @file_put_contents($prefFile, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $updated = true;
    }
    return $updated;
}

/**
 * Pastikan browser policy mengizinkan silent kiosk print
 */
function enforceBrowserPrintPolicies() {
    @exec('reg add "HKCU\Software\Policies\Microsoft\Edge" /v PrintPreviewUseSystemDefaultPrinter /t REG_DWORD /d 1 /f');
    @exec('reg add "HKCU\Software\Policies\Google\Chrome" /v PrintPreviewUseSystemDefaultPrinter /t REG_DWORD /d 1 /f');
    @exec('reg add "HKCU\Software\Microsoft\Windows NT\CurrentVersion\Windows" /v LegacyDefaultPrinterMode /t REG_DWORD /d 1 /f');
}

// 1. Set as Default KHUSUS untuk software ini (TIDAK mengubah default printer Windows)
if ($action === 'set_app_default' || $action === 'set_default') {
    $printerName = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
    if (empty($printerName)) {
        echo json_encode(['success' => false, 'error' => 'Nama printer tidak boleh kosong']);
        exit;
    }

    // Pastikan jika ada override sementara sebelumnya, kembalikan dulu ke default Windows asli
    restorePendingWindowsDefaultPrinter(true);

    // Simpan sebagai default printer khusus software ini
    savePrinterConfig([
        'app_default_printer' => $printerName,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    // Update file Preferences profil lokal aplikasi jika ada
    syncProfilePreferences($printerName);

    echo json_encode([
        'success' => true,
        'selected' => $printerName,
        'app_default_printer' => $printerName,
        'windows_default_printer' => getCurrentWindowsDefaultPrinter(),
        'message' => "Printer \"$printerName\" berhasil disimpan sebagai Default khusus software ini (tanpa mengubah default printer Windows)."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Prepare Print: Arahkan spooler sementara sesaat sebelum silent print, lalu simpan default Windows asli untuk di-restore
if ($action === 'prepare_print') {
    $printerName = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
    if (empty($printerName)) {
        echo json_encode(['success' => false, 'error' => 'Nama printer tidak boleh kosong']);
        exit;
    }

    $cfg = loadPrinterConfig();
    $currentWinDef = getCurrentWindowsDefaultPrinter();

    // Simpan default printer Windows asli jika belum ada pending restore dan berbeda dari printer tujuan cetak
    if (empty($cfg['pending_restore_windows_printer'])) {
        if ($currentWinDef !== '' && strcasecmp($currentWinDef, $printerName) !== 0) {
            savePrinterConfig([
                'pending_restore_windows_printer' => $currentWinDef,
                'pending_restore_time' => time()
            ]);
        }
    }

    if ($currentWinDef === '' || strcasecmp($currentWinDef, $printerName) !== 0) {
        setWindowsSpoolerDefaultPrinter($printerName);
    }

    enforceBrowserPrintPolicies();
    syncProfilePreferences($printerName);

    $updatedCfg = loadPrinterConfig();
    echo json_encode([
        'success' => true,
        'selected' => $printerName,
        'preserved_windows_default' => $updatedCfg['pending_restore_windows_printer'] ?: $currentWinDef
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Restore Windows Default Printer segera setelah job cetak terkirim ke spooler
if ($action === 'restore_windows_default') {
    $restored = restorePendingWindowsDefaultPrinter(true);
    echo json_encode([
        'success' => true,
        'restored' => $restored,
        'windows_default_printer' => getCurrentWindowsDefaultPrinter()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Direct Print: Kirim perintah cetak langsung ke printer Windows via DirectPrint.exe tanpa membuka popup print browser
if ($action === 'direct_print') {
    $printerName = isset($_REQUEST['printer']) ? trim($_REQUEST['printer']) : '';
    $htmlContent = isset($_REQUEST['html']) ? (string)$_REQUEST['html'] : '';
    $dryRun = !empty($_REQUEST['dry_run']);

    if ($printerName === '') {
        $cfg = loadPrinterConfig();
        $printerName = trim((string)($cfg['app_default_printer'] ?? ''));
        if ($printerName === '') {
            $printerName = getCurrentWindowsDefaultPrinter();
        }
    }

    if ($printerName === '') {
        echo json_encode(['success' => false, 'error' => 'Nama printer tujuan belum dipilih.']);
        exit;
    }

    if (trim($htmlContent) === '') {
        echo json_encode(['success' => false, 'error' => 'Konten dokumen cetak kosong.']);
        exit;
    }

    $directPrintExe = __DIR__ . DIRECTORY_SEPARATOR . 'DirectPrint.exe';
    $directPrintCs = __DIR__ . DIRECTORY_SEPARATOR . 'DirectPrint.cs';
    $cscPath = 'C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe';

    if (!file_exists($directPrintExe) && file_exists($directPrintCs) && file_exists($cscPath)) {
        $compileCmd = '"' . $cscPath . '" /nologo /target:exe /out:"' . $directPrintExe . '" ' .
            '/r:System.dll /r:System.Drawing.dll ' .
            '/r:"C:\Windows\Microsoft.NET\Framework64\v4.0.30319\System.Runtime.dll" ' .
            '/r:"C:\Windows\Microsoft.NET\Framework64\v4.0.30319\System.Runtime.WindowsRuntime.dll" ' .
            '/r:"C:\Windows\Microsoft.NET\Framework64\v4.0.30319\System.Runtime.InteropServices.WindowsRuntime.dll" ' .
            '/r:"C:\Windows\System32\WinMetadata\Windows.Data.winmd" ' .
            '/r:"C:\Windows\System32\WinMetadata\Windows.Foundation.winmd" ' .
            '/r:"C:\Windows\System32\WinMetadata\Windows.Storage.winmd" ' .
            '/optimize+ "' . $directPrintCs . '"';
        @shell_exec($compileCmd);
    }

    if (!file_exists($directPrintExe)) {
        echo json_encode(['success' => false, 'error' => 'Modul DirectPrint.exe tidak ditemukan.']);
        exit;
    }

    $dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0777, true);
    }

    // Sisipkan <base href> agar path relatif gambar/aset tetap terbaca dari root aplikasi
    $baseHref = 'file:///' . str_replace('\\', '/', realpath(__DIR__)) . '/';
    if (stripos($htmlContent, '<base ') === false) {
        if (stripos($htmlContent, '<head>') !== false) {
            $htmlContent = preg_replace('/<head>/i', '<head><base href="' . htmlspecialchars($baseHref, ENT_QUOTES) . '">', $htmlContent, 1);
        } else {
            $htmlContent = '<base href="' . htmlspecialchars($baseHref, ENT_QUOTES) . '">' . $htmlContent;
        }
    }

    $jobId = uniqid('printjob_', true);
    $tempHtmlFile = $dataDir . DIRECTORY_SEPARATOR . $jobId . '.html';
    @file_put_contents($tempHtmlFile, $htmlContent);

    $safeExe = '"' . $directPrintExe . '"';
    $safeHtml = '"' . $tempHtmlFile . '"';
    $safePrinter = '"' . str_replace('"', '\"', $printerName) . '"';
    $extraFlag = $dryRun ? ' --dry-run' : '';

    $cmdOutput = @shell_exec("$safeExe $safeHtml $safePrinter 600$extraFlag 2>&1");
    @unlink($tempHtmlFile);

    $parsed = @json_decode(trim((string)$cmdOutput), true);
    if (is_array($parsed) && isset($parsed['success'])) {
        echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Gagal mengirim perintah cetak: ' . trim((string)$cmdOutput)
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 4. Action: 'list' (Default)
restorePendingWindowsDefaultPrinter(false);

$printerCfg = loadPrinterConfig();
$savedAppDefault = trim((string)($printerCfg['app_default_printer'] ?? ''));

$psScript = <<<'POWERSHELL'
$ProgressPreference = 'SilentlyContinue';
$def = '';
try {
    $dev = (Get-ItemProperty 'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\Windows' -ErrorAction SilentlyContinue).Device;
    if ($dev) { $def = ($dev -split ',')[0]; }
} catch {}

$printers = @();
try {
    Get-Printer | ForEach-Object {
        $st = $_.PrinterStatus;
        $stStr = [string]$st;
        $isReady = ($st -eq 0 -or $stStr -eq 'Normal' -or $stStr -eq 'TonerLow');
        $label = 'Ready';
        if ($stStr -eq 'Offline' -or $st -eq 128) {
            $label = 'Offline';
            $isReady = $false;
        } elseif ($stStr -eq 'TonerLow' -or $st -eq 131072) {
            $label = 'Toner Low';
        } elseif ($stStr -eq 'Error') {
            $label = 'Error';
            $isReady = $false;
        }

        $printers += [PSCustomObject]@{
            name = $_.Name;
            status = $label;
            raw_status = $stStr;
            is_default = ($_.Name -eq $def);
            is_ready = $isReady;
            port = [string]$_.PortName;
            driver = [string]$_.DriverName;
        }
    }
} catch {}

$printers | ConvertTo-Json -Depth 3
POWERSHELL;

// Escape powershell script untuk shell_exec
$encodedCmd = base64_encode(mb_convert_encoding($psScript, 'UTF-16LE', 'UTF-8'));
$cmd = "powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand $encodedCmd";

$jsonRaw = @shell_exec($cmd);

// Bersihkan jika ada output non-JSON (seperti CLIXML)
$startJson = strpos($jsonRaw, '[');
$startObj = strpos($jsonRaw, '{');
$cleanJson = $jsonRaw;
if ($startJson !== false && ($startObj === false || $startJson < $startObj)) {
    $cleanJson = substr($jsonRaw, $startJson);
} elseif ($startObj !== false) {
    $cleanJson = substr($jsonRaw, $startObj);
}

$decoded = json_decode($cleanJson, true);

// Jika hanya ada 1 printer, PowerShell ConvertTo-Json mengembalikan single object bukannya array
$printerList = [];
if (is_array($decoded)) {
    if (isset($decoded['name'])) {
        $printerList = [$decoded];
    } else {
        $printerList = $decoded;
    }
}

$windowsDefaultPrinter = '';
$matchedAppDefault = '';

foreach ($printerList as &$p) {
    $isWinDef = !empty($p['is_default']);
    if ($isWinDef && $windowsDefaultPrinter === '') {
        $windowsDefaultPrinter = $p['name'];
    }
    $isAppDef = ($savedAppDefault !== '' && strcasecmp($p['name'], $savedAppDefault) === 0);
    if ($isAppDef) {
        $matchedAppDefault = $p['name'];
    }
    $p['is_windows_default'] = $isWinDef;
    $p['is_app_default'] = $isAppDef;
    $p['is_default'] = ($savedAppDefault !== '') ? $isAppDef : $isWinDef;
}
unset($p);

// Jika printer default software disimpan tapi sedang tidak terdeteksi di list, tetap laporkan namanya
$reportedAppDefault = $matchedAppDefault !== '' ? $matchedAppDefault : $savedAppDefault;

// Urutkan: Default Software pertama, lalu Ready, lalu Default Windows, lalu abjad
usort($printerList, function ($a, $b) {
    if (!empty($a['is_app_default']) && empty($b['is_app_default'])) return -1;
    if (empty($a['is_app_default']) && !empty($b['is_app_default'])) return 1;
    if (!empty($a['is_ready']) && empty($b['is_ready'])) return -1;
    if (empty($a['is_ready']) && !empty($b['is_ready'])) return 1;
    if (!empty($a['is_windows_default']) && empty($b['is_windows_default'])) return -1;
    if (empty($a['is_windows_default']) && !empty($b['is_windows_default'])) return 1;
    return strcasecmp($a['name'], $b['name']);
});

$effectiveDefaultPrinter = $matchedAppDefault !== '' ? $matchedAppDefault : $windowsDefaultPrinter;
if (empty($effectiveDefaultPrinter) && !empty($printerList)) {
    $effectiveDefaultPrinter = $printerList[0]['name'];
}

echo json_encode([
    'success' => true,
    'app_default_printer' => $reportedAppDefault,
    'windows_default_printer' => $windowsDefaultPrinter,
    'default_printer' => $effectiveDefaultPrinter,
    'total' => count($printerList),
    'printers' => $printerList
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

