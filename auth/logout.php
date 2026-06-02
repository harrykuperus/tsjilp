<?php
session_start();
require_once __DIR__ . '/remember-tokens.php';

revoke_remember_cookie();

// clear session data
$_SESSION = [];

// destroy session
session_destroy();

// delete session cookie (CRITICAL)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
            );
}

// return JSON (since you're using fetch)
echo json_encode(['ok' => true]);
exit;