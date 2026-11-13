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

function getDbConfig() {
    $default = [
        'host' => '192.168.88.254',
        'port' => 3307,
        'username' => 'root',
        'password' => '',
        'database' => 'test',
        'timeout' => 3
    ];

    $file = getDbConfigFile();
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        $json = @json_decode($content, true);
        if (is_array($json)) {
            return array_merge($default, $json);
        }
    }
    return $default;
}

function saveDbConfig(array $config) {
    $file = getDbConfigFile();
    $current = getDbConfig();
    $merged = array_merge($current, $config);
    return (bool) @file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT));
}

function getDbConnection($redirectOnError = true) {
    // If in Offline Mode, never connect to MySQL and return safe dummy object
    if (isAppOfflineMode()) {
        return new DummyDb();
    }

    $cfg = getDbConfig();
    $host = !empty($cfg['host']) ? $cfg['host'] : '127.0.0.1';
    $port = !empty($cfg['port']) ? (int)$cfg['port'] : 3306;
    $user = isset($cfg['username']) ? $cfg['username'] : 'root';
    $pass = isset($cfg['password']) ? $cfg['password'] : '';
    $db   = isset($cfg['database']) ? $cfg['database'] : 'test';
    $timeout = !empty($cfg['timeout']) ? (int)$cfg['timeout'] : 3;

    // Use mysqli with connect timeout
    $conn = mysqli_init();
    if (!$conn) {
        if ($redirectOnError && php_sapi_name() !== 'cli') {
            redirectToDbSetup("Gagal inisialisasi driver MySQLi");
        }
        return false;
    }

    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, $timeout);

    $connected = @$conn->real_connect($host, $user, $pass, $db, $port);
    if (!$connected || $conn->connect_errno) {
        $errMsg = $conn->connect_error ?: "Tidak dapat terhubung ke MySQL ($host:$port)";
        if ($redirectOnError && php_sapi_name() !== 'cli') {
            redirectToDbSetup($errMsg);
        }
        return false;
    }

    $conn->set_charset('utf8mb4');
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
$koneksi = getDbConnection(true);
