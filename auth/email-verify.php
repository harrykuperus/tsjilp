<?php
require_once __DIR__ . '/common.php';

$token = $_GET['token'] ?? '';
$token = preg_replace('/[^a-f0-9]/i', '', $token);

if ($token === '') {
    die("Missing token.");
}

$file = __DIR__ . "/../data/login_$token.json";

if (!file_exists($file)) {
    die("Invalid or expired link.");
}

$data = load_json($file);
$createdAt = (int)($data['created_at'] ?? filemtime($file));

if ((time() - $createdAt) > 600) {
    @unlink($file);
    die("This sign-in link has expired.");
}

$email = normalize_email($data['email'] ?? '');

if ($email === '') {
    @unlink($file);
    die("Invalid token data.");
}

$user = load_user_by_email($email);

if (empty($user['user']['email']) || normalize_email($user['user']['email']) !== $email) {
    @unlink($file);
    die("No account found for this email.");
}

if (empty($user['user']['email_verified'])) {
    $user['user']['email_verified'] = true;
    save_user_by_email($email, $user);
}

$_SESSION['user_id'] = $user['user']['id'];
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $user['user']['name'] ?? '';

$inviteId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['invite_id'] ?? ''));
$chatId   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['chat_id'] ?? ''));
$fromName = trim((string)($data['from_name'] ?? ''));
$redirectParams = [];
if ($inviteId !== '') $redirectParams['invite'] = $inviteId;
if ($chatId   !== '') $redirectParams['chat']   = $chatId;
if ($fromName !== '') $redirectParams['from']   = $fromName;
$postAuthRedirect = '/' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

@unlink($file);

header('Location: ' . $postAuthRedirect);
exit;
