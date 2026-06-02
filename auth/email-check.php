<?php
require_once __DIR__ . '/common.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!$email) {
    echo json_encode(['ok' => false, 'error' => 'Missing email']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address']);
    exit;
}

$email = normalize_email($email);

echo json_encode([
    'ok' => true,
    'exists' => user_exists_by_email($email),
    'pending_signup' => find_pending_signup_file_by_email($email) !== ''
]);
