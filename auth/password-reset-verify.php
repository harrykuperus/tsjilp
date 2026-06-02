<?php
require_once __DIR__ . '/common.php';

$token = $_GET['token'] ?? '';
$token = preg_replace('/[^a-f0-9]/i', '', $token);

$file = __DIR__ . "/../data/password_reset_$token.json";

if (!$token || !file_exists($file)) {
    die('Invalid or expired reset link.');
}

$data = json_decode(file_get_contents($file), true);
$createdAt = (int)($data['created_at'] ?? filemtime($file));

if ((time() - $createdAt) > 1800) {
    @unlink($file);
    die('This reset link has expired.');
}

$email = normalize_email($data['email'] ?? '');
if (!$email) {
    @unlink($file);
    die('Invalid reset link.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password - Tsjilp</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f9fafb; margin:0; padding:24px; }
        .card { max-width:420px; margin:60px auto; background:#fff; border-radius:16px; padding:24px; }
        .title { font-size:24px; font-weight:700; margin-bottom:8px; }
        .sub { color:#6b7280; margin-bottom:18px; }
        .input { width:100%; box-sizing:border-box; padding:12px 14px; margin-bottom:10px; border:1px solid #d1d5db; border-radius:12px; }
        .btn { width:100%; padding:12px 14px; border:0; border-radius:12px; background:#111827; color:#fff; cursor:pointer; }
        .notice { margin-top:12px; color:#6b7280; font-size:14px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Reset password</div>
        <div class="sub">Choose a new password for <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></div>

        <form method="post" action="password-reset-finish.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <input class="input" type="email" name="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" readonly>
            <input class="input" type="password" name="password" placeholder="New password" autocomplete="new-password" required>
            <input class="input" type="password" name="password2" placeholder="Repeat new password" autocomplete="new-password" required>
            <button class="btn" type="submit">Update password</button>
        </form>
    </div>
</body>
</html>