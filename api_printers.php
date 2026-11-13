<?php
/**
 * API Printers - Mengambil daftar printer dari Windows Settings (Printers & Scanners)
 * Mendeteksi status Ready / Offline dan printer default Windows.
 * Mengatur printer default Windows & browser profile secara instan.
 */
header('Content-Type: application/json; charset=utf-8');

// Matikan error display agar output JSON tidak rusak oleh warning PHP
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'list';

/**
 * Sinkronisasi printer terpilih ke preferensi profil Chromium (Edge / Chrome)
 */
function syncProfilePreferences($printerName) {
    $prefFile = __DIR__ . '/data/profile/Default/Preferences';
    if (!file_exists($prefFile)) return false;
    
    $raw = @file_get_contents($prefFile);
    if (!$raw) return false;
    
    $data = @json_decode($raw, true);
    if (!is_array($data)) return false;
    
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
    return true;
}

/**
 * Pastikan browser policy selalu mengarahkan silent print ke default printer Windows
 */
function enforceBrowserPrintPolicies() {
    @exec('reg add "HKCU\Software\Policies\Microsoft\Edge" /v PrintPreviewUseSystemDefaultPrinter /t REG_DWORD /d 1 /f');
    @exec('reg add "HKCU\Software\Policies\Google\Chrome" /v PrintPreviewUseSystemDefaultPrinter /t REG_DWORD /d 1 /f');
    @exec('reg add "HKCU\Software\Microsoft\Windows NT\CurrentVersion\Windows" /v LegacyDefaultPrinterMode /t REG_DWORD /d 1 /f');
}

if ($action === 'set_default') {
    $printerName = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
    if (empty($printerName)) {
        echo json_encode(['success' => false, 'error' => 'Nama printer tidak boleh kosong']);
        exit;
    }

    // 1. Set Windows default printer secara instan dengan rundll32 printui.dll (~50ms)
    $safeArg = str_replace('"', '\"', $printerName);
    @exec('rundll32.exe printui.dll,PrintUIEntry /y /n "' . $safeArg . '"');

    // 2. Set Windows default printer via COM WScript.Network sebagai backup
    $safePsName = str_replace(["'", "\\"], ["''", "\\\\"], $printerName);
    $psCmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "$ProgressPreference = \'SilentlyContinue\'; try { (New-Object -ComObject WScript.Network).SetDefaultPrinter(\'' . $safePsName . '\') } catch {}"';
    @exec($psCmd);

    // 3. Pastikan Edge / Chrome selalu mengarahkan print ke system default printer
    enforceBrowserPrintPolicies();

    // 4. Update file Preferences profil browser jika ada
    syncProfilePreferences($printerName);

    echo json_encode([
        'success' => true,
        'selected' => $printerName,
        'message' => "Printer default berhasil diubah ke: $printerName"
    ]);
    exit;
}

// Action: 'list' (Default)
enforceBrowserPrintPolicies();

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

// Urutkan: Default pertama, lalu Ready, lalu Offline
usort($printerList, function ($a, $b) {
    if (!empty($a['is_default']) && empty($b['is_default'])) return -1;
    if (empty($a['is_default']) && !empty($b['is_default'])) return 1;
    if (!empty($a['is_ready']) && empty($b['is_ready'])) return -1;
    if (empty($a['is_ready']) && !empty($b['is_ready'])) return 1;
    return strcasecmp($a['name'], $b['name']);
});

$defaultPrinter = '';
foreach ($printerList as $p) {
    if (!empty($p['is_default'])) {
        $defaultPrinter = $p['name'];
        break;
    }
}
if (empty($defaultPrinter) && !empty($printerList)) {
    $defaultPrinter = $printerList[0]['name'];
}

echo json_encode([
    'success' => true,
    'default_printer' => $defaultPrinter,
    'total' => count($printerList),
    'printers' => $printerList
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
