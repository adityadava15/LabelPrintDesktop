<?php
// Central Database Configuration for PCB Barcode Print Desktop

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

class DummyDbResult {
    public $num_rows = 0;
    public function fetch_assoc() { return null; }
    public function fetch_row() { return null; }
    public function fetch_all($mode = MYSQLI_NUM) { return []; }
    public function free() {}
    public function close() {}
}

class DummyDb extends mysqli {
    public $connect_errno = 0;
    public $connect_error = '';
    public $errno = 0;
    public $error = '';
    public $insert_id = 0;
    public $affected_rows = 0;

    public function __construct() {}

    public function query($sql, $resultmode = MYSQLI_STORE_RESULT) {
        return new DummyDbResult();
    }
    public function real_escape_string($str) {
        return addslashes((string)$str);
    }
    public function escape_string($str) {
        return addslashes((string)$str);
    }
    public function set_charset($charset) {
        return true;
    }
    public function close() {
        return true;
    }
}

function isAppOfflineMode() {
    if (isset($_GET['mode'])) {
        return $_GET['mode'] === 'offline';
    }
    if (isset($_SESSION['app_mode'])) {
        return $_SESSION['app_mode'] === 'offline';
    }
    if (isset($_COOKIE['app_mode'])) {
        return $_COOKIE['app_mode'] === 'offline';
    }
    return false;
}

function getDbConfigFile() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'db_config.json';
}

function getDefaultDbConfig() {
    return [
        'host' => '192.168.88.254',
        'fallback_host' => '192.168.2.254',
        'port' => 3307,
        'username' => 'root',
        'password' => '',
        'database' => 'test',
        'timeout' => 3
    ];
}

function getDbConfig() {
    $default = getDefaultDbConfig();

    $file = getDbConfigFile();
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        $json = @json_decode($content, true);
        if (is_array($json)) {
            $merged = array_merge($default, $json);
            // Preserve non-office configs from older db_config.json without injecting office fallback
            if (!array_key_exists('fallback_host', $json)) {
                $hostVal = trim((string)($merged['host'] ?? ''));
                if ($hostVal === '192.168.88.254') {
                    $merged['fallback_host'] = '192.168.2.254';
                } elseif ($hostVal === '192.168.2.254') {
                    $merged['fallback_host'] = '192.168.88.254';
                } else {
                    $merged['fallback_host'] = '';
                }
            }
            return $merged;
        }
    }
    return $default;
}

function getDbCandidateHosts(array $cfg) {
    $rawHosts = [];
    $primary = trim((string)($cfg['host'] ?? ''));
    $rawHosts[] = $primary !== '' ? $primary : '127.0.0.1';

    $fallback = $cfg['fallback_host'] ?? '';
    if (is_array($fallback)) {
        foreach ($fallback as $fb) {
            $fbTrim = trim((string)$fb);
            if ($fbTrim !== '') {
                $rawHosts[] = $fbTrim;
            }
        }
    } else {
        foreach (explode(',', (string)$fallback) as $fb) {
            $fbTrim = trim($fb);
            if ($fbTrim !== '') {
                $rawHosts[] = $fbTrim;
            }
        }
    }

    return array_values(array_unique($rawHosts));
}

function getDbConfigSignature(array $cfg) {
    return md5(json_encode([
        getDbCandidateHosts($cfg),
        !empty($cfg['port']) ? (int)$cfg['port'] : 3306,
        isset($cfg['username']) ? (string)$cfg['username'] : 'root',
        isset($cfg['password']) ? (string)$cfg['password'] : '',
        isset($cfg['database']) ? (string)$cfg['database'] : 'test'
    ]));
}

function clearResolvedDbHostCache() {
    if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['resolved_db_key'], $_SESSION['resolved_db_host']);
    }
}

function cacheResolvedDbHost(array $cfg, $host) {
    if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE && $host !== null && $host !== '') {
        $_SESSION['resolved_db_key'] = getDbConfigSignature($cfg);
        $_SESSION['resolved_db_host'] = $host;
    }
}

function saveDbConfig(array $config) {
    $file = getDbConfigFile();
    $current = getDbConfig();
    $merged = array_merge($current, $config);
    clearResolvedDbHostCache();
    return (bool) @file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT));
}

function tryConnectDbHost($host, $user, $pass, $db, $port, $timeout, &$errorMsg = '') {
    $conn = mysqli_init();
    if (!$conn) {
        $errorMsg = "Gagal inisialisasi driver MySQLi";
        return false;
    }

    $timeout = max(1, (int)$timeout);
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, $timeout);

    $connected = @$conn->real_connect($host, $user, $pass, $db, $port);
    if (!$connected || $conn->connect_errno) {
        $errorMsg = $conn->connect_error ?: "Tidak dapat terhubung ke MySQL ($host:$port)";
        @$conn->close();
        return false;
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function connectDbWithConfig(array $cfg, $useSessionCache = true, $timeoutOverride = null, &$activeHost = null, &$errorMsg = '', &$usedFallback = false) {
    $candidates = getDbCandidateHosts($cfg);
    $primaryHost = $candidates[0];
    $port = !empty($cfg['port']) ? (int)$cfg['port'] : 3306;
    $user = isset($cfg['username']) ? (string)$cfg['username'] : 'root';
    $pass = isset($cfg['password']) ? (string)$cfg['password'] : '';
    $db   = isset($cfg['database']) ? (string)$cfg['database'] : 'test';
    $timeout = $timeoutOverride !== null
        ? max(1, (int)$timeoutOverride)
        : (!empty($cfg['timeout']) ? max(1, (int)$cfg['timeout']) : 3);

    $sig = getDbConfigSignature($cfg);
    $tryOrder = $candidates;

    if ($useSessionCache && php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        if (isset($_SESSION['resolved_db_key'], $_SESSION['resolved_db_host'])
            && $_SESSION['resolved_db_key'] === $sig
            && in_array($_SESSION['resolved_db_host'], $candidates, true)
        ) {
            $cached = $_SESSION['resolved_db_host'];
            if ($cached !== $candidates[0]) {
                $tryOrder = array_values(array_unique(array_merge([$cached], $candidates)));
            }
        }
    }

    $lastErr = '';
    foreach ($tryOrder as $candidate) {
        $hostErr = '';
        $conn = tryConnectDbHost($candidate, $user, $pass, $db, $port, $timeout, $hostErr);
        if ($conn !== false) {
            $activeHost = $candidate;
            $usedFallback = ($candidate !== $primaryHost);
            $errorMsg = '';
            if ($useSessionCache) {
                cacheResolvedDbHost($cfg, $candidate);
            }
            return $conn;
        }
        $lastErr = $hostErr;
    }

    if ($useSessionCache) {
        clearResolvedDbHostCache();
    }

    $activeHost = null;
    $usedFallback = false;
    if (count($candidates) > 1) {
        $errorMsg = "Tidak dapat terhubung ke MySQL (" . implode(' / ', $candidates) . " port $port): " . $lastErr;
    } else {
        $errorMsg = $lastErr;
    }
    return false;
}

function getDbConnection($redirectOnError = true) {
    // If in Offline Mode, never connect to MySQL and return safe dummy object
    if (isAppOfflineMode()) {
        return new DummyDb();
    }

    $cfg = getDbConfig();
    $activeHost = null;
    $errMsg = '';
    $usedFallback = false;
    $conn = connectDbWithConfig($cfg, true, null, $activeHost, $errMsg, $usedFallback);
    if ($conn === false) {
        if ($redirectOnError && php_sapi_name() !== 'cli') {
            redirectToDbSetup($errMsg);
        }
        return false;
    }

    return $conn;
}

function redirectToDbSetup($error = '') {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === 'db_setup.php') {
        return;
    }
    $url = 'db_setup.php' . ($error ? '?error=' . urlencode($error) : '');
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo "<script>window.location.href = " . json_encode($url) . ";</script>";
    }
    exit;
}

// Global variable for backward compatibility with existing code
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!defined('SKIP_AUTO_DB_CONNECT') && $currentScript !== 'db_setup.php') {
    $koneksi = getDbConnection(true);
} else {
    $koneksi = null;
}
