<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$wasOffline = (isset($_SESSION['app_mode']) && $_SESSION['app_mode'] === 'offline');
session_unset();
session_destroy();

if ($wasOffline) {
    header("Location: mode_select.php");
} else {
    header("Location: Login1.php");
}
exit;
