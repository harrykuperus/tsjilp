<?php
require_once __DIR__ . '/common.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = (string)($data['password'] ?? '');

if (!$email || !$password) {
    echo json_encode(['ok' => false, 'error' => 'Missing email or password']);
    exit;
}

$email = normalize_email($email);
$user = load_user_by_email($email);

if (empty($user['user']['email']) || normalize_email($user['user']['email']) !== $email) {
    echo json_encode(['ok' => false, 'error' => 'No account found for this email']);
    exit;
}

$hash = $user['user']['password_hash'] ?? '';
if (!$hash || !password_verify($password, $hash)) {
    echo json_encode(['ok' => false, 'error' => 'Incorrect password']);
    exit;
}

if (empty($user['user']['email_verified'])) {
    echo json_encode(['ok' => false, 'error' => 'Please verify your email before logging in']);
    exit;
}

$_SESSION['user_id'] = $user['user']['id'];
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $user['user']['name'] ?? '';

issue_remember_token($_SESSION['user_id']);

echo json_encode(['ok' => true]);
