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

function getAuthSecretKey() {
    $dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    $keyFile = $dataDir . DIRECTORY_SEPARATOR . 'auth_secret.key';
    if (file_exists($keyFile)) {
        $existing = trim((string) @file_get_contents($keyFile));
        if (strlen($existing) >= 32) {
            return $existing;
        }
    }
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0775, true);
    }
    $secret = bin2hex(random_bytes(32));
    @file_put_contents($keyFile, $secret);
    return $secret;
}

function getDashboardRedirectUrl() {
    if (isset($_SESSION['app_mode']) && $_SESSION['app_mode'] === 'offline') {
        return 'userview.php?menu=custom';
    }
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return 'view.php';
    }
    return 'userview.php';
}

function clearRememberAuthCookies() {
    $past = time() - 3600;
    @setcookie('pcb_remember', '', $past, '/');
    @setcookie('app_mode', '', $past, '/');
    unset($_COOKIE['pcb_remember'], $_COOKIE['app_mode']);
}

function setRememberAuthCookie($username, $mode, $passwordHash = '') {
    $exp = time() + (86400 * 30);
    $payload = base64_encode(json_encode([
        'u' => (string) $username,
        'm' => (string) $mode,
        'e' => $exp
    ]));
    $sig = hash_hmac('sha256', $payload . '|' . (string) $passwordHash, getAuthSecretKey());
    $token = $payload . '.' . $sig;
    @setcookie('pcb_remember', $token, $exp, '/', '', false, true);
    $_COOKIE['pcb_remember'] = $token;
}

function tryAutoLoginFromRememberCookie() {
    $raw = (string) ($_COOKIE['pcb_remember'] ?? '');
    if ($raw === '' || strpos($raw, '.') === false) {
        return false;
    }

    list($payload, $sig) = explode('.', $raw, 2);
    $decoded = json_decode((string) base64_decode($payload, true), true);
    if (!is_array($decoded) || empty($decoded['m']) || empty($decoded['e']) || (int) $decoded['e'] < time()) {
        clearRememberAuthCookies();
        return false;
    }

    $mode = (string) $decoded['m'];
    $username = (string) ($decoded['u'] ?? '');

    if ($mode === 'offline') {
        $expectedSig = hash_hmac('sha256', $payload . '|', getAuthSecretKey());
        if (!hash_equals($expectedSig, $sig)) {
            clearRememberAuthCookies();
            return false;
        }
        $_SESSION['app_mode'] = 'offline';
        $_SESSION['username'] = 'Operator (Offline)';
        $_SESSION['role'] = 'user';
        return true;
    }

    if ($mode === 'database' && $username !== '') {
        $activeHost = null;
        $errMsg = '';
        $usedFallback = false;
        $conn = connectDbWithConfig(getDbConfig(), true, 2, $activeHost, $errMsg, $usedFallback);
        if ($conn === false) {
            return false;
        }

        $userRow = null;
        $stmt = $conn->prepare("SELECT username, password, role FROM users WHERE username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $username);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $userRow = $res ? $res->fetch_assoc() : null;
            }
            $stmt->close();
        }
        $conn->close();

        if (!$userRow) {
            clearRememberAuthCookies();
            return false;
        }

        $expectedSig = hash_hmac('sha256', $payload . '|' . (string) $userRow['password'], getAuthSecretKey());
        if (!hash_equals($expectedSig, $sig)) {
            clearRememberAuthCookies();
            return false;
        }

        $_SESSION['app_mode'] = 'database';
        $_SESSION['username'] = $userRow['username'];
        $_SESSION['role'] = $userRow['role'];
        return true;
    }

    clearRememberAuthCookies();
    return false;
}

// Global variable for backward compatibility with existing code
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!defined('SKIP_AUTO_DB_CONNECT') && $currentScript !== 'db_setup.php') {
    $koneksi = getDbConnection(true);
} else {
    $koneksi = null;
}
