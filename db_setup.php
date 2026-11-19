<?php
define('SKIP_AUTO_DB_CONNECT', true);
require_once __DIR__ . '/db_config.php';

$message = '';
$messageType = '';
$tested = false;
$connected = false;
$connectedHost = '';
$connectedViaFallback = false;

$config = getDbConfig();

if (isset($_GET['error']) && !empty($_GET['error'])) {
    $message = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
    $messageType = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $host = trim($_POST['host'] ?? '127.0.0.1');
    $fallbackHost = trim($_POST['fallback_host'] ?? '');
    $port = (int)($_POST['port'] ?? 3307);
    if ($port <= 0 || $port > 65535) {
        $port = 3307;
    }
    $user = trim($_POST['username'] ?? 'root');
    $pass = $_POST['password'] ?? '';
    $db   = trim($_POST['database'] ?? 'test');
    $timeout = !empty($config['timeout']) ? (int)$config['timeout'] : 3;

    $newConfig = [
        'host' => $host,
        'fallback_host' => $fallbackHost,
        'port' => $port,
        'username' => $user,
        'password' => $pass,
        'database' => $db,
        'timeout' => $timeout
    ];

    if ($action === 'test' || $action === 'save') {
        $activeHost = null;
        $errMsg = '';
        $usedFallback = false;
        $testConn = connectDbWithConfig($newConfig, false, 3, $activeHost, $errMsg, $usedFallback);

        $tested = true;
        $config = $newConfig;
        if ($testConn !== false) {
            $testConn->close();
            $connected = true;
            $connectedHost = (string)$activeHost;
            $connectedViaFallback = $usedFallback;

            if ($action === 'save') {
                saveDbConfig($newConfig);
                cacheResolvedDbHost($newConfig, $activeHost);
                if ($usedFallback) {
                    $message = "Konfigurasi disimpan! Server utama (" . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . ":{$port}) belum merespons, saat ini terhubung melalui IP cadangan (" . htmlspecialchars($connectedHost, ENT_QUOTES, 'UTF-8') . ":{$port}).";
                } else {
                    $message = "Konfigurasi database berhasil disimpan dan terhubung ke " . htmlspecialchars($connectedHost, ENT_QUOTES, 'UTF-8') . ":{$port}!";
                }
                $messageType = 'success';
            } else {
                if ($usedFallback) {
                    $message = "Server utama (" . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . ":{$port}) tidak merespons, namun berhasil terhubung ke IP cadangan (" . htmlspecialchars($connectedHost, ENT_QUOTES, 'UTF-8') . ":{$port})!";
                } else {
                    $message = "Koneksi berhasil ke " . htmlspecialchars($connectedHost, ENT_QUOTES, 'UTF-8') . ":{$port}! Database '" . htmlspecialchars($db, ENT_QUOTES, 'UTF-8') . "' ditemukan.";
                }
                $messageType = 'success';
            }
        } else {
            $err = $errMsg ?: "Koneksi gagal atau database '$db' belum ada.";
            $message = "Gagal terhubung: " . htmlspecialchars($err, ENT_QUOTES, 'UTF-8');
            $messageType = 'danger';
        }
    } elseif ($action === 'import') {
        $activeHost = null;
        $errMsg = '';
        $usedFallback = false;
        $testConn = connectDbWithConfig($newConfig, false, 3, $activeHost, $errMsg, $usedFallback);
        $config = $newConfig;

        if ($testConn !== false) {
            $sqlFile = __DIR__ . '/test.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                $importOk = $testConn->multi_query($sqlContent);
                $importErr = $importOk ? '' : $testConn->error;
                if ($importOk) {
                    do {
                        if ($res = $testConn->store_result()) {
                            $res->free();
                        }
                        if ($testConn->errno) {
                            $importOk = false;
                            $importErr = $testConn->error;
                            break;
                        }
                    } while ($testConn->more_results() && $testConn->next_result());
                }
                $testConn->close();

                $connected = true;
                $connectedHost = (string)$activeHost;
                $connectedViaFallback = $usedFallback;

                if ($importOk) {
                    $message = "Database berhasil diimport dari test.sql ke " . htmlspecialchars($connectedHost, ENT_QUOTES, 'UTF-8') . ":{$port}!";
                    $messageType = 'success';
                } else {
                    $message = "Import test.sql selesai dengan error: " . htmlspecialchars($importErr, ENT_QUOTES, 'UTF-8');
                    $messageType = 'danger';
                }
            } else {
                $testConn->close();
                $message = "File test.sql tidak ditemukan.";
                $messageType = 'danger';
            }
        } else {
            $message = "Gagal terhubung sebelum import: " . htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8');
            $messageType = 'danger';
        }
    }
} else {
    // Check initial connection silently
    $activeHost = null;
    $errMsg = '';
    $usedFallback = false;
    $initConn = connectDbWithConfig($config, true, 2, $activeHost, $errMsg, $usedFallback);
    if ($initConn !== false) {
        $initConn->close();
        $connected = true;
        $connectedHost = (string)$activeHost;
        $connectedViaFallback = $usedFallback;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengaturan Database - PCB Barcode Print</title>
  <link rel="icon" type="image/png" sizes="32x32" href="IMG/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="IMG/favicon-16x16.png">
  <link rel="shortcut icon" href="IMG/favicon.ico">
  <style>
    :root {
      --bg: #081a32;
      --card: #ffffff;
      --text: #102847;
      --muted: #5d7597;
      --line: #c8d7eb;
      --accent: #123968;
      --accent-hover: #1a4b84;
      --success: #1f7a4d;
      --success-bg: #e8f7ee;
      --danger: #b3261e;
      --danger-bg: #fcecef;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
    body {
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      color: var(--text);
    }
    .container {
      background: var(--card);
      width: 100%;
      max-width: 620px;
      border-radius: 14px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .header {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 22px;
      padding-bottom: 16px;
      border-bottom: 2px solid #eef2f8;
    }
    .header img {
      width: 48px;
      height: 48px;
    }
    .header h1 {
      font-size: 20px;
      color: var(--accent);
      font-weight: 700;
    }
    .header p {
      font-size: 13px;
      color: var(--muted);
      margin-top: 3px;
    }
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 13.5px;
      line-height: 1.4;
    }
    .alert-danger {
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid #f5c2c7;
    }
    .alert-success {
      background: var(--success-bg);
      color: var(--success);
      border: 1px solid #c3e6cb;
    }
    .presets {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-bottom: 20px;
    }
    @media (max-width: 520px) {
      .presets {
        grid-template-columns: 1fr;
      }
    }
    .preset-btn {
      background: #eef3f9;
      border: 1px solid #d0dbe9;
      color: #1a4b84;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 12px;
      cursor: pointer;
      font-weight: 500;
      text-align: left;
      transition: all 0.2s;
    }
    .preset-btn:hover {
      background: #e0eaf5;
      border-color: #1a4b84;
    }
    .preset-btn.active {
      background: #dbe8f8;
      border-color: #123968;
      color: #0b2547;
      font-weight: 600;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-row {
      display: flex;
      gap: 12px;
    }
    .form-row .form-group {
      flex: 1;
    }
    label {
      display: block;
      font-size: 12.5px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 6px;
    }
    input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--line);
      border-radius: 6px;
      font-size: 14px;
      outline: none;
      transition: border 0.2s;
    }
    input:focus {
      border-color: var(--accent-hover);
      box-shadow: 0 0 0 3px rgba(26, 75, 132, 0.15);
    }
    .field-hint {
      margin-top: 5px;
      font-size: 11.5px;
      color: var(--muted);
    }
    .actions {
      display: flex;
      gap: 10px;
      margin-top: 22px;
      flex-wrap: wrap;
    }
    .btn {
      padding: 10px 18px;
      border-radius: 6px;
      font-size: 13.5px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
    }
    .btn-primary {
      background: var(--accent);
      color: #fff;
      flex: 1;
    }
    .btn-primary:hover {
      background: var(--accent-hover);
    }
    .btn-secondary {
      background: #f0f4f8;
      color: #1a4b84;
      border: 1px solid #c8d7eb;
    }
    .btn-secondary:hover {
      background: #e2eaf2;
    }
    .btn-success {
      background: var(--success);
      color: #fff;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 20px;
    }
    .btn-success:hover {
      background: #17633e;
    }
    .footer-note {
      margin-top: 20px;
      padding-top: 15px;
      border-top: 1px solid #edf2f7;
      font-size: 12px;
      color: var(--muted);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .footer-note a {
      color: var(--accent-hover);
      text-decoration: none;
      font-weight: 500;
    }
    .footer-note a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="IMG/hexlogo.png" alt="Logo">
      <div>
        <h1>Pengaturan Database</h1>
        <p>PCB Barcode Print Desktop</p>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?= $messageType ?>">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <div>
      <div style="font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--muted);">PRESET CEPAT:</div>
      <div class="presets">
        <button type="button" class="preset-btn" data-host="192.168.88.254" data-port="3307" onclick="applyPreset('192.168.88.254', '192.168.2.254', 3307, 'root', '', 'test')">🏢 Server Kantor (192.168.88.254:3307)</button>
        <button type="button" class="preset-btn" data-host="192.168.2.254" data-port="3307" onclick="applyPreset('192.168.2.254', '192.168.88.254', 3307, 'root', '', 'test')">🏠 Server Kantor Lokal (192.168.2.254:3307)</button>
        <button type="button" class="preset-btn" data-host="127.0.0.1" data-port="3306" onclick="applyPreset('127.0.0.1', '', 3306, 'root', '', 'test')">💻 Localhost (127.0.0.1:3306)</button>
        <button type="button" class="preset-btn" data-host="127.0.0.1" data-port="3307" onclick="applyPreset('127.0.0.1', '', 3307, 'root', '', 'test')">⚡ Localhost Port 3307</button>
      </div>
    </div>

    <form method="POST" id="dbForm">
      <div class="form-row">
        <div class="form-group" style="flex: 2;">
          <label for="host">Host / IP Server Utama</label>
          <input type="text" id="host" name="host" value="<?= htmlspecialchars($config['host'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-group" style="flex: 1;">
          <label for="port">Port</label>
          <input type="number" id="port" name="port" min="1" max="65535" value="<?= (int)$config['port'] ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="fallback_host">IP Server Cadangan / Fallback <span style="font-weight:400; color:var(--muted);">(Opsional)</span></label>
        <input type="text" id="fallback_host" name="fallback_host" value="<?= htmlspecialchars($config['fallback_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: 192.168.2.254 (kosongkan jika tidak perlu)">
        <div class="field-hint">Otomatis digunakan jika IP Server Utama tidak dapat dijangkau (port sama).</div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="username">Username MySQL</label>
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($config['username'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" value="<?= htmlspecialchars($config['password'], ENT_QUOTES, 'UTF-8') ?>" placeholder="(Kosong jika tanpa password)">
        </div>
      </div>

      <div class="form-group">
        <label for="database">Nama Database</label>
        <input type="text" id="database" name="database" value="<?= htmlspecialchars($config['database'], ENT_QUOTES, 'UTF-8') ?>" required>
      </div>

      <div class="actions">
        <button type="submit" name="action" value="test" class="btn btn-secondary">🔍 Test Koneksi</button>
        <button type="submit" name="action" value="save" class="btn btn-primary">💾 Simpan Konfigurasi</button>
        <button type="submit" name="action" value="import" class="btn btn-secondary" title="Import struktur & data awal dari test.sql" onclick="return confirm('Import struktur dan data dari test.sql ke database ini?');">📥 Import test.sql</button>
        <?php if ($connected): ?>
          <a href="Login1.php" class="btn btn-success">➡️ Buka Aplikasi</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="footer-note">
      <span>
        Status:
        <?php if ($connected): ?>
          <b style="color:var(--success)">
            ● Terhubung
            <?php if ($connectedHost !== ''): ?>
              (<?= $connectedViaFallback ? 'Cadangan: ' : '' ?><?= htmlspecialchars($connectedHost, ENT_QUOTES, 'UTF-8') ?>:<?= (int)$config['port'] ?>)
            <?php endif; ?>
          </b>
        <?php else: ?>
          <b style="color:var(--danger)">● Belum Terhubung</b>
        <?php endif; ?>
      </span>
      <a href="Login1.php">Kembali ke Halaman Login</a>
    </div>
  </div>

  <script>
    function updateActivePreset() {
      var host = document.getElementById('host').value.trim();
      var port = document.getElementById('port').value.trim();
      var buttons = document.querySelectorAll('.preset-btn');
      buttons.forEach(function (btn) {
        var match = btn.getAttribute('data-host') === host && btn.getAttribute('data-port') === port;
        btn.classList.toggle('active', match);
      });
    }

    function applyPreset(host, fallbackHost, port, user, pass, db) {
      document.getElementById('host').value = host;
      document.getElementById('fallback_host').value = fallbackHost;
      document.getElementById('port').value = port;
      document.getElementById('username').value = user;
      document.getElementById('password').value = pass;
      document.getElementById('database').value = db;
      updateActivePreset();
    }

    document.getElementById('host').addEventListener('input', updateActivePreset);
    document.getElementById('port').addEventListener('input', updateActivePreset);
    updateActivePreset();
  </script>
</body>
</html>
