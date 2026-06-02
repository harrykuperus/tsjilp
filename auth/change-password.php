<?php
require_once __DIR__ . '/auth/common.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_email'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$current = $data['current_password'] ?? '';
$new = $data['new_password'] ?? '';

if (!$new) {
    echo json_encode(['ok' => false, 'error' => 'Missing new password']);
    exit;
}

if (mb_strlen($new) < 6) {
    echo json_encode(['ok' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
}

$email = normalize_email($_SESSION['user_email']);
$user = load_user_by_email($email);

if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}

$hash = $user['user']['password_hash'] ?? '';

// User has no password yet (magic-link-only user)
if (!$hash) {
    $user['user']['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
    save_user_by_email($email, $user);
    
    echo json_encode([
            'ok' => true,
            'message' => 'Password created.'
    ]);
    exit;
}

// User already has a password, so current password is required
if (!$current) {
    echo json_encode(['ok' => false, 'error' => 'Please enter your current password']);
    exit;
}

if (!password_verify($current, $hash)) {
    echo json_encode(['ok' => false, 'error' => 'Incorrect current password']);
    exit;
}

$user['user']['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
save_user_by_email($email, $user);

echo json_encode([
        'ok' => true,
        'message' => 'Password updated.'
]);