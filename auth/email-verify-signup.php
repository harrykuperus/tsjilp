<?php
require_once __DIR__ . '/common.php';

function build_post_signup_redirect(array $data, string $appBaseUrl): string
{
    $baseUrl = rtrim($appBaseUrl, '/');
    $params = [];

    $inviteId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['invite_id'] ?? ''));
    $chatId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['chat_id'] ?? ''));
    $fromName = trim((string)($data['from_name'] ?? ''));

    if ($inviteId !== '') {
        $params['invite'] = $inviteId;
    }

    if ($chatId !== '') {
        $params['chat'] = $chatId;
    }

    if ($fromName !== '') {
        $params['from'] = $fromName;
    }

    $params['signup_complete'] = '1';

    return $baseUrl . '/?' . http_build_query($params);
}

$token = $_GET['token'] ?? '';
$token = preg_replace('/[^a-f0-9]/i', '', $token);

if ($token === '') {
    die('Missing token');
}

$file = __DIR__ . "/../data/pending_signup_$token.json";

if (!file_exists($file)) {
    die('Invalid verification link');
}

$data = load_json($file);
$createdAt = (int)($data['created_at'] ?? filemtime($file));

if ((time() - $createdAt) > 86400) {
    @unlink($file);
    die('Verification link expired');
}

$email = normalize_email($data['email'] ?? '');
$name = trim($data['name'] ?? '');
$passwordHash = $data['password_hash'] ?? '';
$marketingOptIn = !empty($data['marketing_opt_in']);
$upgradeUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['upgrade_user_id'] ?? ''));
$postSignupRedirect = build_post_signup_redirect($data, $app_base_url);

if ($email === '' || $passwordHash === '') {
    @unlink($file);
    die('Invalid signup data');
}

if ($upgradeUserId !== '') {
    $upgradeProfile = load_user_profile_by_id($upgradeUserId);
    $upgradeUser = is_array($upgradeProfile['user'] ?? null) ? $upgradeProfile['user'] : [];

    if (empty($upgradeUser['id'])) {
        @unlink($file);
        die('Invalid upgrade account');
    }

    $existing = load_user_by_email($email);
    $existingId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($existing['user']['id'] ?? ''));
    if ($existingId !== '' && $existingId !== $upgradeUserId) {
        @unlink($file);
        die('Email already belongs to another account');
    }

    $upgradeProfile['user']['id'] = $upgradeUserId;
    $upgradeProfile['user']['name'] = $name !== '' ? $name : (trim((string)($upgradeUser['name'] ?? '')) ?: 'User');
    $upgradeProfile['user']['initials'] = make_initials((string)$upgradeProfile['user']['name']);
    $upgradeProfile['user']['email'] = $email;
    $upgradeProfile['user']['password_hash'] = $passwordHash;
    $upgradeProfile['user']['email_verified'] = true;
    $upgradeProfile['user']['marketing_opt_in'] = $marketingOptIn;

    unset($upgradeProfile['user']['member_kind']);
    unset($upgradeProfile['user']['invite_token']);

    save_user_by_email($email, $upgradeProfile);

    $_SESSION['user_id'] = $upgradeUserId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $upgradeProfile['user']['name'];
    unset($_SESSION['guest_member_token']);

    issue_remember_token($_SESSION['user_id']);

    @unlink($file);
    header('Location: ' . $postSignupRedirect);
    exit;
}

if (user_exists_by_email($email)) {
    $existing = load_user_by_email($email);
    $_SESSION['user_id'] = $existing['user']['id'] ?? '';
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $existing['user']['name'] ?? '';
    unset($_SESSION['guest_member_token']);
    issue_remember_token($_SESSION['user_id']);
    @unlink($file);
    header('Location: ' . $postSignupRedirect);
    exit;
}

$newUser = [
    "user" => [
        "id" => generate_id('user'),
        "name" => $name !== '' ? $name : 'User',
        "initials" => make_initials($name),
        "email" => $email,
        "password_hash" => $passwordHash,
        "email_verified" => true,
        "marketing_opt_in" => $marketingOptIn,
        "language" => "en",
        "languages" => ["en"],
        "country" => "",
        "age_range" => "",
        "gender" => "",
        "domain" => "general",
        "communication_style" => "practical",
        "assistant" => "luca",
        "assistant_profile_prompt" => ""
    ]
];

save_user_by_email($email, $newUser);

$_SESSION['user_id'] = $newUser['user']['id'];
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $newUser['user']['name'];
unset($_SESSION['guest_member_token']);

issue_remember_token($_SESSION['user_id']);

@unlink($file);

header('Location: ' . $postSignupRedirect);
exit;
