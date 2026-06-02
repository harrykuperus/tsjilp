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
$inviteId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['invite_id'] ?? ''));
$chatId   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['chat_id'] ?? ''));
$fromName = trim((string)($data['from_name'] ?? ''));

if (!user_exists_by_email($email)) {
    echo json_encode(['ok' => false, 'error' => 'No account found for this email']);
    exit;
}

foreach (glob(__DIR__ . '/../data/login_*.json') as $oldFile) {
    $oldData = load_json($oldFile);
    if (!empty($oldData['email']) && normalize_email($oldData['email']) === $email) {
        @unlink($oldFile);
    }
}

$token = bin2hex(random_bytes(16));

$token = bin2hex(random_bytes(16));
$loginFile = __DIR__ . "/../data/login_$token.json";

$ok = save_json($loginFile, [
        'email' => $email,
        'created_at' => time(),
        'invite_id' => $inviteId,
        'chat_id'   => $chatId,
        'from_name' => $fromName
]);

if (!$ok || !file_exists($loginFile)) {
    echo json_encode([
            'ok' => false,
            'error' => 'Could not create sign-in token'
    ]);
    exit;
}

$link = $app_base_url . "/auth/email-verify.php?token=$token";

$subject = 'Your Tsjilp sign-in link';
$textBody = "Open this link to sign in:\n\n$link\n\nThis link expires in 10 minutes.\n\nIf you did not request this, you can ignore this email.";
$htmlBody = "
    <p>Open this link to sign in:</p>
    <p><a href=\"" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "\">Sign in to Tsjilp</a></p>
    <p>This link expires in 10 minutes.</p>
    <p>If you did not request this, you can ignore this email.</p>
";

$sent = send_email($email, '', $subject, $textBody, $htmlBody);

echo json_encode([
        'ok' => $sent === true,
        'error' => $sent === true ? null : 'Could not send sign-in email'
]);
