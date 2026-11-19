<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Mode Selection
if (isset($_GET['choose'])) {
    $choice = $_GET['choose'];
    $remember = !empty($_GET['remember']);

    if ($choice === 'offline') {
        $_SESSION['app_mode'] = 'offline';
        $_SESSION['username'] = 'Operator (Offline)';
        $_SESSION['role'] = 'user';
        if ($remember) {
            setcookie('app_mode', 'offline', time() + (86400 * 365), '/');
        } else {
            setcookie('app_mode', '', time() - 3600, '/');
        }
        header("Location: userview.php?menu=custom");
        exit;
    } elseif ($choice === 'database') {
        $_SESSION['app_mode'] = 'database';
        if (isset($_SESSION['username']) && $_SESSION['username'] === 'Operator (Offline)') {
            unset($_SESSION['username']);
            unset($_SESSION['role']);
        }
        if ($remember) {
            setcookie('app_mode', 'database', time() + (86400 * 365), '/');
        } else {
            setcookie('app_mode', '', time() - 3600, '/');
        }
        header("Location: Login1.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pilih Mode - PCB Barcode Print</title>
  <link rel="icon" type="image/x-icon" href="IMG/favicon.ico">
  <style>
    :root {
      --bg: #0b1120;
      --card-bg: rgba(17, 24, 39, 0.85);
      --card-border: rgba(255, 255, 255, 0.08);
      --card-hover: rgba(30, 41, 59, 0.95);
      --text: #f3f4f6;
      --text-muted: #94a3b8;
      --primary: #2563eb;
      --primary-hover: #1d4ed8;
      --emerald: #059669;
      --emerald-hover: #047857;
      --cyan: #06b6d4;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: radial-gradient(circle at 50% 20%, #1e1b4b 0%, #0b1120 70%);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      user-select: none;
    }

    .container {
      width: 100%;
      max-width: 860px;
      text-align: center;
      animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .header-logo {
      width: 64px;
      height: 64px;
      margin-bottom: 14px;
      filter: drop-shadow(0 4px 12px rgba(37, 99, 235, 0.35));
    }

    h1 {
      font-size: 28px;
      font-weight: 700;
      letter-spacing: -0.02em;
      margin-bottom: 6px;
      background: linear-gradient(135deg, #ffffff 40%, #94a3b8 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .subtitle {
      font-size: 15px;
      color: var(--text-muted);
      margin-bottom: 32px;
    }

    .cards-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 24px;
    }

    @media (max-width: 640px) {
      .cards-grid {
        grid-template-columns: 1fr;
      }
    }

    .mode-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 28px 24px;
      text-align: left;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      backdrop-filter: blur(16px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      overflow: hidden;
      cursor: pointer;
    }

    .mode-card::before {
      content: "";
      position: absolute;
      top: 0; left: 0; right: 0; height: 3px;
      background: transparent;
      transition: background 0.25s;
    }

    .mode-card.online:hover {
      border-color: rgba(37, 99, 235, 0.4);
      transform: translateY(-4px);
      box-shadow: 0 20px 30px -10px rgba(37, 99, 235, 0.25);
    }
    .mode-card.online::before {
      background: linear-gradient(90deg, #2563eb, #3b82f6);
    }

    .mode-card.offline:hover {
      border-color: rgba(6, 182, 212, 0.4);
      transform: translateY(-4px);
      box-shadow: 0 20px 30px -10px rgba(6, 182, 212, 0.25);
    }
    .mode-card.offline::before {
      background: linear-gradient(90deg, #059669, #06b6d4);
    }

    .card-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }

    .card-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
    }

    .card-icon.online {
      background: rgba(37, 99, 235, 0.15);
      color: #60a5fa;
      border: 1px solid rgba(37, 99, 235, 0.3);
    }

    .card-icon.offline {
      background: rgba(6, 182, 212, 0.15);
      color: #22d3ee;
      border: 1px solid rgba(6, 182, 212, 0.3);
    }

    .card-badge {
      font-size: 11px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 20px;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }

    .card-badge.online {
      background: rgba(37, 99, 235, 0.15);
      color: #93c5fd;
      border: 1px solid rgba(37, 99, 235, 0.3);
    }

    .card-badge.offline {
      background: rgba(5, 150, 105, 0.15);
      color: #6ee7b7;
      border: 1px solid rgba(5, 150, 105, 0.3);
    }

    .card-title {
      font-size: 20px;
      font-weight: 600;
      color: #ffffff;
      margin-bottom: 6px;
    }

    .card-desc {
      font-size: 13.5px;
      color: var(--text-muted);
      line-height: 1.5;
      margin-bottom: 20px;
    }

    .feature-list {
      list-style: none;
      margin-bottom: 24px;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      padding-top: 14px;
    }

    .feature-list li {
      font-size: 13px;
      color: #cbd5e1;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .feature-list li svg {
      flex-shrink: 0;
      width: 15px;
      height: 15px;
    }

    .feature-list li.check svg {
      color: #34d399;
    }

    .feature-list li.lock svg {
      color: #f87171;
    }

    .card-btn {
      width: 100%;
      padding: 12px 18px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      transition: background 0.2s, transform 0.1s;
    }

    .card-btn.online {
      background: var(--primary);
      color: #ffffff;
    }
    .card-btn.online:hover {
      background: var(--primary-hover);
    }

    .card-btn.offline {
      background: linear-gradient(135deg, #059669 0%, #0891b2 100%);
      color: #ffffff;
    }
    .card-btn.offline:hover {
      background: linear-gradient(135deg, #047857 0%, #0e7490 100%);
    }

    .card-footer-link {
      display: block;
      margin-top: 12px;
      font-size: 12.5px;
      color: #94a3b8;
      text-decoration: none;
      text-align: center;
      transition: color 0.15s;
    }
    .card-footer-link:hover {
      color: #60a5fa;
      text-decoration: underline;
    }

    .remember-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 20px;
      font-size: 13px;
      color: #94a3b8;
    }

    .remember-wrap input[type="checkbox"] {
      cursor: pointer;
      width: 16px;
      height: 16px;
      accent-color: var(--primary);
    }

    .system-footer {
      font-size: 12px;
      color: #64748b;
    }
  </style>
</head>
<body>

  <div class="container">
    <img src="IMG/sidebar-logo.svg" alt="Logo" class="header-logo">
    <h1>PCB &amp; Label Barcode Generator</h1>
    <p class="subtitle">Pilih mode operasional sebelum memulai software</p>

    <div class="remember-wrap">
      <input type="checkbox" id="chkRemember">
      <label for="chkRemember">Ingat pilihan saya (bisa diganti kapan saja dari menu software)</label>
    </div>

    <div class="cards-grid">
      <!-- Mode Online / Database -->
      <div class="mode-card online" onclick="selectMode('database')">
        <div>
          <div class="card-top">
            <div class="card-icon online">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
              </svg>
            </div>
            <span class="card-badge online">Fitur Penuh</span>
          </div>

          <h2 class="card-title">Mode Database</h2>
          <p class="card-desc">Terhubung ke database server MySQL. Akses penuh ke seluruh fitur Work Order, Batch, dan sinkronisasi data.</p>

          <ul class="feature-list">
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Work Order &amp; Batch Print PCB
            </li>
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              EV Charger (PCB &amp; SN Print)
            </li>
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Print Nameplate Barcode
            </li>
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Record Print &amp; Export Excel
            </li>
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Custom Print Barcode
            </li>
          </ul>
        </div>

        <div>
          <a href="javascript:void(0)" class="card-btn online" onclick="event.stopPropagation(); selectMode('database')">
            Masuk Mode Database &rarr;
          </a>
          <a href="db_setup.php" class="card-footer-link" onclick="event.stopPropagation()">
            ⚙️ Pengaturan Koneksi Database
          </a>
        </div>
      </div>

      <!-- Mode Offline / Custom Print Saja -->
      <div class="mode-card offline" onclick="selectMode('offline')">
        <div>
          <div class="card-top">
            <div class="card-icon offline">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
              </svg>
            </div>
            <span class="card-badge offline">Standalone / Mandiri</span>
          </div>

          <h2 class="card-title">Mode Offline</h2>
          <p class="card-desc">Bekerja mandiri tanpa server database dan tanpa jaringan internet. Langsung siap cetak barcode custom.</p>

          <ul class="feature-list">
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              100% Berfungsi Tanpa Jaringan / MySQL
            </li>
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Custom Print Barcode (PCB, EV, SN, etc.)
            </li>
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              In-App Print Studio &amp; Pilih Printer
            </li>
            <li class="check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Ukuran Kertas Bebas &amp; HD Preview
            </li>
            <li class="lock">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              Database Menu Dinonaktifkan
            </li>
          </ul>
        </div>

        <div>
          <a href="javascript:void(0)" class="card-btn offline" onclick="event.stopPropagation(); selectMode('offline')">
            Masuk Mode Offline &rarr;
          </a>
          <span class="card-footer-link" style="visibility: hidden;">&nbsp;</span>
        </div>
      </div>
    </div>

    <p class="system-footer">PCB Barcode Print Desktop Edition &bull; Portabel &amp; Mandiri</p>
  </div>

  <script>
    function selectMode(mode) {
      var remember = document.getElementById('chkRemember').checked ? 1 : 0;
      window.location.href = 'mode_select.php?choose=' + encodeURIComponent(mode) + '&remember=' + remember;
    }
  </script>
</body>
</html>
