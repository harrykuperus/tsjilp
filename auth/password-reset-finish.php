<?php
require_once __DIR__ . '/common.php';

$token = $_POST['token'] ?? '';
$token = preg_replace('/[^a-f0-9]/i', '', $token);

$password = $_POST['password'] ?? '';
$password2 = $_POST['password2'] ?? '';

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
    die('Invalid reset data.');
}

if (!$password || !$password2) {
    die('Please fill both password fields.');
}

if ($password !== $password2) {
    die('Passwords do not match.');
}

if (mb_strlen($password) < 6) {
    die('Password must be at least 6 characters.');
}

$user = load_user_by_email($email);
if (!$user) {
    @unlink($file);
    die('User not found.');
}

$user['user']['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
save_user_by_email($email, $user);

@unlink($file);

$_SESSION['user_id'] = $user['user']['id'];
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $user['user']['name'] ?? '';

header('Location: /');
exit;