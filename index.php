<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SKIP_AUTO_DB_CONNECT', true);
require_once __DIR__ . '/db_config.php';

// User requested to switch mode manually or reset login state
if (isset($_GET['switch_mode'])) {
    unset($_SESSION['app_mode'], $_SESSION['username'], $_SESSION['role']);
    clearRememberAuthCookies();
    header("Location: Login1.php");
    exit;
}

// Already logged in during current active session
if (isset($_SESSION['username'])) {
    header("Location: " . getDashboardRedirectUrl());
    exit;
}

// Auto-login only when user explicitly selected "Keep me logged in"
if (tryAutoLoginFromRememberCookie()) {
    header("Location: " . getDashboardRedirectUrl());
    exit;
}

// Default: always open straight to Login form
clearRememberAuthCookies();
header("Location: Login1.php");
exit;
