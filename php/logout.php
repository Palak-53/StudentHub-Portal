<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['user_id'], $_SESSION['csrf_token'])
    || !is_string($submittedToken)
    || !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    header('Location: login.php?reason=login_required');
    exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => 'Lax',
    ]);
}
session_destroy();

header('Location: login.php?reason=logout');
exit;
