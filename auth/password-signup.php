<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = (string)($data['password'] ?? '');
$marketingOptIn = !empty($data['marketing_opt_in']);
$inviteId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['invite_id'] ?? ''));
$chatId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['chat_id'] ?? ''));
$fromName = trim((string)($data['from_name'] ?? ''));

if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'Please enter your name']);
    exit;
}

if (!$email || !$password) {
    echo json_encode(['ok' => false, 'error' => 'Missing email or password']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['ok' => false, 'error' => 'Password must be at least 8 characters']);
    exit;
}

$email = normalize_email($email);

$currentUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SESSION['user_id'] ?? ''));
$currentProfile = $currentUserId !== '' ? load_user_profile_by_id($currentUserId) : [];
$isGuestUpgrade = ((string)($currentProfile['user']['member_kind'] ?? '') === 'invited_member');

if (user_exists_by_email($email)) {
    $existing = load_user_by_email($email);
    $existingId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($existing['user']['id'] ?? ''));

    if (!($isGuestUpgrade && $existingId !== '' && $existingId === $currentUserId)) {
        echo json_encode(['ok' => false, 'error' => 'Account already exists']);
        exit;
    }
}

$existingPending = load_pending_signup_by_email($email);
if (!empty($existingPending['email'])) {
    $createdAt = (int)($existingPending['created_at'] ?? 0);
    if ($createdAt > (time() - 86400)) {
        echo json_encode([
            'ok' => true,
            'status' => 'pending_verification',
            'message' => 'A verification email has already been sent. Please check your inbox.'
        ]);
        exit;
    }

    delete_pending_signups_by_email($email);
}

$token = create_pending_signup(
    $name,
    $email,
    password_hash($password, PASSWORD_DEFAULT),
    $marketingOptIn,
    null,
    $isGuestUpgrade ? $currentUserId : '',
    $inviteId,
    $chatId,
    $fromName
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

$sent = send_email($email, $name, $subject, $textBody, $htmlBody);

if ($sent !== true) {
    echo json_encode(['ok' => false, 'error' => 'Could not send verification email']);
    exit;
}

echo json_encode(['ok' => true]);
