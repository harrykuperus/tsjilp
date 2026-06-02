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
$user = load_user_by_email($email);

if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'No account found for this email']);
    exit;
}

// remove older reset files for this email
foreach (glob(__DIR__ . '/../data/password_reset_*.json') as $oldFile) {
    $oldData = json_decode(file_get_contents($oldFile), true);
    if (!empty($oldData['email']) && normalize_email($oldData['email']) === $email) {
        @unlink($oldFile);
    }
}

$token = bin2hex(random_bytes(16));

file_put_contents(
    __DIR__ . "/../data/password_reset_$token.json",
    json_encode([
        'email' => $email,
        'created_at' => time()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

$link = $app_base_url . "/auth/password-reset-verify.php?token=$token";

$subject = 'Reset your Tsjilp password';
$textBody = "Open this link to reset your password:\n\n$link\n\nThis link expires in 30 minutes.\n\nIf you did not request this, you can ignore this email.";
$htmlBody = "
    <p>Open this link to reset your password:</p>
    <p><a href=\"" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "\">Reset password</a></p>
    <p>This link expires in 30 minutes.</p>
    <p>If you did not request this, you can ignore this email.</p>
";

$sent = send_email($email, '', $subject, $textBody, $htmlBody);

echo json_encode([
    'ok' => $sent === true,
    'error' => $sent === true ? null : 'Could not send reset email'
]);