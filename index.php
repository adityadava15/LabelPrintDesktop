<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// User requested to switch mode manually
if (isset($_GET['switch_mode'])) {
    unset($_SESSION['app_mode']);
    setcookie('app_mode', '', time() - 3600, '/');
    header("Location: mode_select.php");
    exit;
}

// Check session mode first
if (isset($_SESSION['app_mode'])) {
    if ($_SESSION['app_mode'] === 'offline') {
        header("Location: userview.php?menu=custom");
        exit;
    } elseif ($_SESSION['app_mode'] === 'database') {
        header("Location: Login1.php");
        exit;
    }
}

// Check remembered cookie mode
if (isset($_COOKIE['app_mode'])) {
    if ($_COOKIE['app_mode'] === 'offline') {
        $_SESSION['app_mode'] = 'offline';
        $_SESSION['username'] = 'Operator (Offline)';
        $_SESSION['role'] = 'user';
        header("Location: userview.php?menu=custom");
        exit;
    } elseif ($_COOKIE['app_mode'] === 'database') {
        $_SESSION['app_mode'] = 'database';
        header("Location: Login1.php");
        exit;
    }
}

// Otherwise show mode selection screen
header("Location: mode_select.php");
exit;
