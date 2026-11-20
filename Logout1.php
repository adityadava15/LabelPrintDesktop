<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SKIP_AUTO_DB_CONNECT', true);
require_once __DIR__ . '/db_config.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    @setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?: '/',
        $params['domain'] ?: '',
        !empty($params['secure']),
        !empty($params['httponly'])
    );
}
session_destroy();

clearRememberAuthCookies();
header("Location: Login1.php");
exit;
