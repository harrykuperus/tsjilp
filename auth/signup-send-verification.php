<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/mailer.php';

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

if (user_exists_by_email($email)) {
    $user = load_user_by_email($email);
    if (!empty($user['user']['email_verified'])) {
        echo json_encode(['ok' => false, 'error' => 'Email is already verified']);
        exit;
    }
}

$pending = load_pending_signup_by_email($email);

if (empty($pending['email'])) {
    echo json_encode(['ok' => false, 'error' => 'No pending signup found']);
    exit;
}

delete_pending_signups_by_email($email);

$token = create_pending_signup(
    $pending['name'] ?? '',
    $email,
    $pending['password_hash'] ?? '',
    !empty($pending['marketing_opt_in']),
    null,
    (string)($pending['upgrade_user_id'] ?? ''),
    (string)($pending['invite_id'] ?? ''),
    (string)($pending['chat_id'] ?? ''),
    (string)($pending['from_name'] ?? '')
);

$link = $app_base_url . "/auth/email-verify-signup.php?token=$token";

$subject = 'Verify your email for Tsjilp';
$textBody = "Open this link to verify your email:\n\n$link\n\nThis link expires in 24 hours.\n\nIf you did not request this, you can ignore this email.";
$htmlBody = "
    <p>Open this link to verify your email:</p>
    <p><a href=\"" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "\">Verify your email</a></p>
    <p>This link expires in 24 hours.</p>
    <p>If you did not request this, you can ignore this email.</p>
";

$sent = send_email($email, $pending['name'] ?? '', $subject, $textBody, $htmlBody);

echo json_encode([
    'ok' => $sent === true,
    'error' => $sent === true ? null : 'Could not send verification email'
]);
