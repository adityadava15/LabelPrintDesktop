<?php
require_once __DIR__ . '/db_config.php';

$message = '';
$messageType = '';
$tested = false;
$connected = false;

$config = getDbConfig();

if (isset($_GET['error']) && !empty($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $messageType = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $host = trim($_POST['host'] ?? '127.0.0.1');
    $port = (int)($_POST['port'] ?? 3306);
    $user = trim($_POST['username'] ?? 'root');
    $pass = $_POST['password'] ?? '';
    $db   = trim($_POST['database'] ?? 'test');
    
    $newConfig = [
        'host' => $host,
        'port' => $port,
        'username' => $user,
        'password' => $pass,
        'database' => $db,
        'timeout' => 3
    ];

    if ($action === 'test' || $action === 'save') {
        $testConn = mysqli_init();
        $testConn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        $ok = @$testConn->real_connect($host, $user, $pass, $db, $port);
        
        $tested = true;
        if ($ok && !$testConn->connect_errno) {
            $connected = true;
            if ($action === 'save') {
                saveDbConfig($newConfig);
                $config = $newConfig;
                $message = "Konfigurasi database berhasil disimpan dan terhubung!";
                $messageType = 'success';
            } else {
                $message = "Koneksi berhasil! Database ditemukan.";
                $messageType = 'success';
            }
        } else {
            $err = $testConn->connect_error ?: "Koneksi gagal atau database '$db' belum ada.";
            $message = "Gagal terhubung: " . $err;
            $messageType = 'danger';
        }
    } elseif ($action === 'import') {
        $testConn = mysqli_init();
        $testConn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        $ok = @$testConn->real_connect($host, $user, $pass, $db, $port);
        
        if ($ok && !$testConn->connect_errno) {
            $sqlFile = __DIR__ . '/test.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                $testConn->multi_query($sqlContent);
                do {
                    if ($res = $testConn->store_result()) {
                        $res->free();
                    }
                } while ($testConn->more_results() && $testConn->next_result());
                
                $message = "Database berhasil diimport dari test.sql!";
                $messageType = 'success';
                $connected = true;
            } else {
                $message = "File test.sql tidak ditemukan.";
                $messageType = 'danger';
            }
        } else {
            $message = "Gagal terhubung sebelum import.";
            $messageType = 'danger';
        }
    }
} else {
    // Check initial connection silently
    $initConn = mysqli_init();
    $initConn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    $ok = @$initConn->real_connect($config['host'], $config['username'], $config['password'], $config['database'], (int)$config['port']);
    if ($ok && !$initConn->connect_errno) {
        $connected = true;
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
      max-width: 580px;
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
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .preset-btn {
      background: #eef3f9;
      border: 1px solid #d0dbe9;
      color: #1a4b84;
      padding: 7px 12px;
      border-radius: 6px;
      font-size: 12px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s;
    }
    .preset-btn:hover {
      background: #e0eaf5;
      border-color: #1a4b84;
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
        <button type="button" class="preset-btn" onclick="applyPreset('192.168.88.254', 3307, 'root', '', 'test')">🏢 Server Kantor (192.168.88.254:3307)</button>
        <button type="button" class="preset-btn" onclick="applyPreset('127.0.0.1', 3306, 'root', '', 'test')">💻 Localhost (127.0.0.1:3306)</button>
        <button type="button" class="preset-btn" onclick="applyPreset('127.0.0.1', 3307, 'root', '', 'test')">⚡ Localhost Port 3307</button>
      </div>
    </div>

    <form method="POST" id="dbForm">
      <div class="form-row">
        <div class="form-group" style="flex: 2;">
          <label for="host">Host / IP Server</label>
          <input type="text" id="host" name="host" value="<?= htmlspecialchars($config['host']) ?>" required>
        </div>
        <div class="form-group" style="flex: 1;">
          <label for="port">Port</label>
          <input type="number" id="port" name="port" value="<?= (int)$config['port'] ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="username">Username MySQL</label>
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($config['username']) ?>" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" value="<?= htmlspecialchars($config['password']) ?>" placeholder="(Kosong jika tanpa password)">
        </div>
      </div>

      <div class="form-group">
        <label for="database">Nama Database</label>
        <input type="text" id="database" name="database" value="<?= htmlspecialchars($config['database']) ?>" required>
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
      <span>Status: <?= $connected ? '<b style="color:var(--success)">● Terhubung</b>' : '<b style="color:var(--danger)">● Belum Terhubung</b>' ?></span>
      <a href="Login1.php">Kembali ke Halaman Login</a>
    </div>
  </div>

  <script>
    function applyPreset(host, port, user, pass, db) {
      document.getElementById('host').value = host;
      document.getElementById('port').value = port;
      document.getElementById('username').value = user;
      document.getElementById('password').value = pass;
      document.getElementById('database').value = db;
    }
  </script>
</body>
</html>
