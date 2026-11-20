<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SKIP_AUTO_DB_CONNECT', true);
require_once __DIR__ . '/db_config.php';

// Redirect if already authenticated or explicitly remembered
if (isset($_SESSION['username'])) {
    header("Location: " . getDashboardRedirectUrl());
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && tryAutoLoginFromRememberCookie()) {
    header("Location: " . getDashboardRedirectUrl());
    exit;
}

$username = '';
$remember = false;
$isOffline = false;
$feedback = null;
$feedbackType = 'error';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);
    $isOffline = isset($_POST['offline_mode']);

    if ($isOffline) {
        session_regenerate_id(true);
        $_SESSION['app_mode'] = 'offline';
        $_SESSION['username'] = 'Operator (Offline)';
        $_SESSION['role'] = 'user';

        if ($remember) {
            setRememberAuthCookie('Operator (Offline)', 'offline', '');
        } else {
            clearRememberAuthCookies();
        }

        header("Location: userview.php?menu=custom");
        exit;
    }

    if ($username === '' || $password === '') {
        $feedback = 'Username dan password wajib diisi.';
        $feedbackType = 'error';
    } else {
        $activeHost = null;
        $dbErr = '';
        $usedFallback = false;
        $conn = connectDbWithConfig(getDbConfig(), true, null, $activeHost, $dbErr, $usedFallback);

        if ($conn === false) {
            $feedback = 'Gagal terhubung ke database'
                . ($dbErr !== '' ? ': ' . $dbErr : '.')
                . ' Periksa pengaturan database di pojok kanan bawah atau aktifkan mode OFFLINE.';
            $feedbackType = 'error';
        } else {
            $user = null;
            $stmt = $conn->prepare("SELECT username, password, role FROM users WHERE username = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $username);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    $user = $res ? $res->fetch_assoc() : null;
                }
                $stmt->close();
            }
            $conn->close();

            if ($user && password_verify($password, (string) $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['app_mode'] = 'database';
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($remember) {
                    setRememberAuthCookie($user['username'], 'database', (string) $user['password']);
                } else {
                    clearRememberAuthCookies();
                }

                header("Location: " . getDashboardRedirectUrl());
                exit;
            }

            $feedback = 'Username atau password salah!';
            $feedbackType = 'error';
        }
    }
}

$loginCssVer = file_exists(__DIR__ . '/CSS/login.css') ? filemtime(__DIR__ . '/CSS/login.css') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Print Barcode Studio</title>
  <link rel="icon" type="image/png" sizes="32x32" href="IMG/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="IMG/favicon-16x16.png">
  <link rel="shortcut icon" href="IMG/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="IMG/apple-touch-icon.png">
  <link rel="stylesheet" href="CSS/login.css?v=<?= $loginCssVer ?>">
</head>
<body>
  <main class="login-page">
    <div class="card-stage">
      <div class="dots-grid" aria-hidden="true">
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
      </div>

      <section class="login-card" aria-label="Form Login Print Barcode Studio">
        <div class="brand-panel">
          <svg class="brand-logo" xmlns="http://www.w3.org/2000/svg" viewBox="181 402 1238 395" role="img" aria-label="Print Barcode Studio">
            <g id="barcode-mark">
              <rect x="181" y="402" width="215" height="4.74" fill="#9F926F"/>
              <rect x="181" y="406.39" width="215" height="4.74" fill="#9C9C6C"/>
              <rect x="181" y="410.78" width="215" height="4.74" fill="#616939"/>
              <rect x="181" y="415.17" width="215" height="4.74" fill="#868D6B"/>
              <rect x="181" y="419.56" width="215" height="4.74" fill="#B9B088"/>
              <rect x="181" y="423.94" width="215" height="4.74" fill="#B38845"/>
              <rect x="181" y="428.33" width="215" height="4.74" fill="#CC8A36"/>
              <rect x="181" y="432.72" width="215" height="4.74" fill="#D4882C"/>
              <rect x="181" y="437.11" width="215" height="4.74" fill="#D4843E"/>
              <rect x="181" y="441.5" width="215" height="4.74" fill="#DA8872"/>
              <rect x="181" y="445.89" width="215" height="4.74" fill="#D18A8C"/>
              <rect x="181" y="450.28" width="215" height="4.74" fill="#C99692"/>
              <rect x="181" y="454.67" width="215" height="4.74" fill="#ECC4C3"/>
              <rect x="181" y="459.06" width="215" height="4.74" fill="#67454C"/>
              <rect x="181" y="463.44" width="215" height="4.74" fill="#BDA5A8"/>
              <rect x="181" y="467.83" width="215" height="4.74" fill="#EEE7D4"/>
              <rect x="181" y="472.22" width="215" height="4.74" fill="#EFE0CA"/>
              <rect x="181" y="476.61" width="215" height="4.74" fill="#C99C97"/>
              <rect x="181" y="481" width="215" height="4.74" fill="#DBADB0"/>
              <rect x="181" y="485.39" width="215" height="4.74" fill="#FFFFFF"/>
              <rect x="181" y="489.78" width="215" height="4.74" fill="#FFFFFF"/>
              <rect x="181" y="494.17" width="215" height="4.74" fill="#7BAFCE"/>
              <rect x="181" y="498.56" width="215" height="4.74" fill="#5C8CA0"/>
              <rect x="181" y="502.94" width="215" height="4.74" fill="#FFFFFF"/>
              <rect x="181" y="507.33" width="215" height="4.74" fill="#DDC68B"/>
              <rect x="181" y="511.72" width="215" height="4.74" fill="#DBB35B"/>
              <rect x="181" y="516.11" width="215" height="4.74" fill="#E89D5A"/>
              <rect x="181" y="520.5" width="215" height="4.74" fill="#B63138"/>
              <rect x="181" y="524.89" width="215" height="4.74" fill="#BD1E48"/>
              <rect x="181" y="529.28" width="215" height="4.74" fill="#BC1D4A"/>
              <rect x="181" y="533.67" width="215" height="4.74" fill="#AC3356"/>
              <rect x="181" y="538.06" width="215" height="4.74" fill="#FFDAE6"/>
              <rect x="181" y="542.44" width="215" height="4.74" fill="#FFFFFF"/>
              <rect x="181" y="546.83" width="215" height="4.74" fill="#C5EEFF"/>
              <rect x="181" y="551.22" width="215" height="4.74" fill="#52787C"/>
              <rect x="181" y="555.61" width="215" height="4.74" fill="#A9A368"/>
              <rect x="181" y="560" width="215" height="4.74" fill="#C6C487"/>
              <rect x="181" y="564.39" width="215" height="4.74" fill="#679894"/>
              <rect x="181" y="568.78" width="215" height="4.74" fill="#2F7E9D"/>
              <rect x="181" y="573.17" width="215" height="4.74" fill="#2B83B1"/>
              <rect x="181" y="577.56" width="215" height="4.74" fill="#2C80AE"/>
              <rect x="181" y="581.94" width="215" height="4.74" fill="#4484A7"/>
              <rect x="181" y="586.33" width="215" height="4.74" fill="#E0F4F5"/>
              <rect x="181" y="590.72" width="215" height="4.74" fill="#D3A770"/>
              <rect x="181" y="595.11" width="215" height="4.74" fill="#CE7F2C"/>
              <rect x="181" y="599.5" width="215" height="4.74" fill="#D6822A"/>
              <rect x="181" y="603.89" width="215" height="4.74" fill="#D18741"/>
              <rect x="181" y="608.28" width="215" height="4.74" fill="#A87556"/>
              <rect x="181" y="612.67" width="215" height="4.74" fill="#43241F"/>
              <rect x="181" y="617.06" width="215" height="4.74" fill="#665963"/>
              <rect x="181" y="621.44" width="215" height="4.74" fill="#FFFFFF"/>
              <rect x="181" y="625.83" width="215" height="4.74" fill="#FFFFFF"/>
              <rect x="181" y="630.22" width="215" height="4.74" fill="#C09899"/>
              <rect x="181" y="634.61" width="215" height="4.74" fill="#CC9A9A"/>
              <rect x="181" y="639" width="215" height="4.74" fill="#EA9F9F"/>
              <rect x="181" y="643.39" width="215" height="4.74" fill="#9D2C2F"/>
              <rect x="181" y="647.78" width="215" height="4.74" fill="#A01E20"/>
              <rect x="181" y="652.17" width="215" height="4.74" fill="#A32022"/>
              <rect x="181" y="656.56" width="215" height="4.74" fill="#9A1F10"/>
              <rect x="181" y="660.94" width="215" height="4.74" fill="#BB5022"/>
              <rect x="181" y="665.33" width="215" height="4.74" fill="#DE823F"/>
              <rect x="181" y="669.72" width="215" height="4.74" fill="#CC7D32"/>
              <rect x="181" y="674.11" width="215" height="4.74" fill="#E3A460"/>
              <rect x="181" y="678.5" width="215" height="4.74" fill="#FFF8CB"/>
              <rect x="181" y="682.89" width="215" height="4.74" fill="#DDA689"/>
              <rect x="181" y="687.28" width="215" height="4.74" fill="#A73C2B"/>
              <rect x="181" y="691.67" width="215" height="4.74" fill="#861811"/>
              <rect x="181" y="696.06" width="215" height="4.74" fill="#C98D8F"/>
              <rect x="181" y="700.44" width="215" height="4.74" fill="#BD9188"/>
              <rect x="181" y="704.83" width="215" height="4.74" fill="#A86E45"/>
              <rect x="181" y="709.22" width="215" height="4.74" fill="#C3AC8D"/>
              <rect x="181" y="713.61" width="215" height="4.74" fill="#4A86A0"/>
              <rect x="181" y="718" width="215" height="4.74" fill="#2C74A3"/>
              <rect x="181" y="722.39" width="215" height="4.74" fill="#AEBADA"/>
              <rect x="181" y="726.78" width="215" height="4.74" fill="#C19EA9"/>
              <rect x="181" y="731.17" width="215" height="4.74" fill="#8E4A36"/>
              <rect x="181" y="735.56" width="215" height="4.74" fill="#FFBCA1"/>
              <rect x="181" y="739.94" width="215" height="4.74" fill="#FFFFFF"/>
              <rect x="181" y="744.33" width="215" height="4.74" fill="#E7CFCC"/>
              <rect x="181" y="748.72" width="215" height="4.74" fill="#E9BDBB"/>
              <rect x="181" y="753.11" width="215" height="4.74" fill="#C99080"/>
              <rect x="181" y="757.5" width="215" height="4.74" fill="#CB8A60"/>
              <rect x="181" y="761.89" width="215" height="4.74" fill="#C4935E"/>
              <rect x="181" y="766.28" width="215" height="4.74" fill="#ABA372"/>
              <rect x="181" y="770.67" width="215" height="4.74" fill="#CABC99"/>
              <rect x="181" y="775.06" width="215" height="4.74" fill="#EBA59C"/>
              <rect x="181" y="779.44" width="215" height="4.74" fill="#7F333B"/>
              <rect x="181" y="783.83" width="215" height="4.74" fill="#6B4B5A"/>
              <rect x="181" y="788.22" width="215" height="4.74" fill="#12071D"/>
              <rect x="181" y="792.61" width="215" height="4.39" fill="#2C223D"/>
            </g>
            <g id="typography" fill="#0B1A2E" fill-rule="evenodd">
              <path d="M484,402H609V452H584V427H509V452H584V477H509V527H484Z"/>
              <path d="M619,402H744V452H719V427H644V452H719V502H694V477H644V527H619Z M719,502H744V527H719Z"/>
              <path d="M754,402H879V427H829V502H879V527H754V502H804V427H754Z"/>
              <path d="M889,402H914V427H939V452H914V527H889Z M989,402H1014V527H989V502H964V477H989Z M939,452H964V477H939Z"/>
              <path d="M1024,402H1149V427H1099V527H1074V427H1024Z"/>
              <path d="M484,537H584V587H609V662H484Z M509,562V587H559V562Z M509,612V637H584V612Z"/>
              <path d="M644,537H719V562H644Z M619,562H644V587H719V562H744V662H719V612H644V662H619Z"/>
              <path d="M754,537H879V587H854V562H779V587H854V637H829V612H779V662H754Z M854,637H879V662H854Z"/>
              <path d="M889,537H1014V562H914V637H1014V662H889Z"/>
              <path d="M1024,537H1149V662H1024Z M1049,562V637H1124V562Z"/>
              <path d="M1159,537H1259V562H1184V637H1259V662H1159Z M1259,562H1284V637H1259Z"/>
              <path d="M1294,537H1419V562H1319V587H1394V612H1319V637H1419V662H1294Z"/>
              <path d="M484,672H609V697H509V722H609V797H484V772H584V747H484Z"/>
              <path d="M619,672H744V697H694V797H669V697H619Z"/>
              <path d="M754,672H779V772H854V672H879V797H754Z"/>
              <path d="M889,672H989V697H914V772H989V797H889Z M989,697H1014V772H989Z"/>
              <path d="M1024,672H1149V697H1099V772H1149V797H1024V772H1074V697H1024Z"/>
              <path d="M1159,672H1284V797H1159Z M1184,697V772H1259V697Z"/>
            </g>
          </svg>
        </div>

        <div class="form-panel">
          <div class="form-wrapper">
            <h1 class="form-title">Login</h1>
            <p class="form-subtitle" id="formSubtitle">
              <?= $isOffline ? 'Offline mode &mdash; Custom Print without database' : 'Sign in to your account' ?>
            </p>

            <?php if ($feedback !== null): ?>
              <div class="form-alert<?= $feedbackType === 'info' ? ' is-info' : '' ?>" role="alert">
                <?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>

            <div class="form-alert is-info" id="forgotAlert" role="status" hidden>
              Hubungi Admin (menu <strong>Setting Sistem</strong>) untuk reset password akun Anda, atau <a href="Register1.php">daftar akun baru</a>.
            </div>

            <form method="post" action="Login1.php" id="loginForm" novalidate>
              <label class="mode-toggle" title="Geser untuk memilih mode Online atau Offline" aria-label="Pilih mode koneksi Online atau Offline">
                <input
                  class="mode-input"
                  id="offlineModeToggle"
                  type="checkbox"
                  name="offline_mode"
                  value="1"
                  <?= $isOffline ? 'checked' : '' ?>
                >
                <span class="mode-track">
                  <span class="mode-thumb" aria-hidden="true"></span>
                  <span class="mode-label is-online">ONLINE</span>
                  <span class="mode-label is-offline">OFFLINE</span>
                </span>
              </label>

              <div class="form-group">
                <label class="sr-only" for="username">Username</label>
                <input
                  class="form-input"
                  id="username"
                  name="username"
                  type="text"
                  placeholder="<?= $isOffline ? 'Not required in offline mode' : 'Username' ?>"
                  value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                  autocomplete="username"
                  <?= $isOffline ? 'disabled' : 'required' ?>
                >
              </div>

              <div class="form-group">
                <label class="sr-only" for="password">Password</label>
                <input
                  class="form-input"
                  id="password"
                  name="password"
                  type="password"
                  placeholder="<?= $isOffline ? 'Not required in offline mode' : 'Password' ?>"
                  autocomplete="current-password"
                  <?= $isOffline ? 'disabled' : 'required' ?>
                >
              </div>

              <label class="remember-row">
                <input
                  class="remember-input"
                  type="checkbox"
                  name="remember"
                  value="1"
                  <?= $remember ? 'checked' : '' ?>
                >
                <span class="remember-text">Keep me logged in</span>
              </label>

              <button class="btn-login" id="loginSubmitBtn" type="submit">
                <?= $isOffline ? 'Enter Offline Mode' : 'Log in' ?>
              </button>
              <a class="forgot-link<?= $isOffline ? ' is-disabled' : '' ?>" id="forgotLink" href="Register1.php">Forget password?</a>
            </form>
          </div>

          <a class="settings-btn" href="db_setup.php" title="Pengaturan Database" aria-label="Buka Pengaturan Database">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M14.18,16.31L15.42,17.27L14.85,18.63L13.30,18.39L11.79,19.90L12.03,21.45L10.67,22.02L9.71,20.78L7.49,20.78L6.53,22.02L5.17,21.45L5.41,19.90L3.90,18.39L2.35,18.63L1.78,17.27L3.02,16.31L3.02,14.09L1.78,13.13L2.35,11.77L3.90,12.01L5.41,10.50L5.17,8.95L6.53,8.38L7.49,9.62L9.71,9.62L10.67,8.38L12.03,8.95L11.79,10.50L13.30,12.01L14.85,11.77L15.42,13.13L14.18,14.09Z" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linejoin="round" stroke-linecap="round"/>
              <circle cx="8.6" cy="15.2" r="2.0" fill="none" stroke="currentColor" stroke-width="1.35"/>
              <path d="M21.21,5.84L22.27,6.09L22.27,7.11L21.21,7.36L20.66,8.68L21.24,9.62L20.52,10.34L19.58,9.76L18.26,10.31L18.01,11.37L16.99,11.37L16.74,10.31L15.42,9.76L14.48,10.34L13.76,9.62L14.34,8.68L13.79,7.36L12.73,7.11L12.73,6.09L13.79,5.84L14.34,4.52L13.76,3.58L14.48,2.86L15.42,3.44L16.74,2.89L16.99,1.83L18.01,1.83L18.26,2.89L19.58,3.44L20.52,2.86L21.24,3.58L20.66,4.52Z" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linejoin="round" stroke-linecap="round"/>
              <circle cx="17.4" cy="6.6" r="1.05" fill="none" stroke="currentColor" stroke-width="1.35"/>
            </svg>
          </a>
        </div>
      </section>
    </div>
  </main>

  <script>
    (function () {
      var modeToggle = document.getElementById('offlineModeToggle');
      var subtitleEl = document.getElementById('formSubtitle');
      var usernameInput = document.getElementById('username');
      var passwordInput = document.getElementById('password');
      var submitBtn = document.getElementById('loginSubmitBtn');
      var forgotLink = document.getElementById('forgotLink');
      var forgotAlert = document.getElementById('forgotAlert');

      function syncModeState() {
        var isOffline = !!(modeToggle && modeToggle.checked);
        if (subtitleEl) {
          subtitleEl.textContent = isOffline
            ? 'Offline mode \u2014 Custom Print without database'
            : 'Sign in to your account';
        }
        if (usernameInput) {
          usernameInput.disabled = isOffline;
          usernameInput.required = !isOffline;
          usernameInput.placeholder = isOffline ? 'Not required in offline mode' : 'Username';
        }
        if (passwordInput) {
          passwordInput.disabled = isOffline;
          passwordInput.required = !isOffline;
          passwordInput.placeholder = isOffline ? 'Not required in offline mode' : 'Password';
        }
        if (submitBtn) {
          submitBtn.textContent = isOffline ? 'Enter Offline Mode' : 'Log in';
        }
        if (forgotLink) {
          forgotLink.classList.toggle('is-disabled', isOffline);
          forgotLink.setAttribute('aria-disabled', isOffline ? 'true' : 'false');
          if (isOffline && forgotAlert) {
            forgotAlert.hidden = true;
          }
        }
      }

      if (modeToggle) {
        modeToggle.addEventListener('change', syncModeState);
        syncModeState();
      }

      if (forgotLink && forgotAlert) {
        forgotLink.addEventListener('click', function (e) {
          e.preventDefault();
          if (modeToggle && modeToggle.checked) {
            return;
          }
          forgotAlert.hidden = !forgotAlert.hidden;
        });
      }
    })();
  </script>
</body>
</html>
