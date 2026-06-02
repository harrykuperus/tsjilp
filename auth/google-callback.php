<?php
require_once __DIR__ . '/common.php';

$baseUrl = rtrim((string) (getenv('APP_BASE_URL') ?: $app_base_url), '/');
$clientId = trim((string) getenv('GOOGLE_CLIENT_ID'));
$clientSecret = trim((string) getenv('GOOGLE_CLIENT_SECRET'));

$state = (string) ($_GET['state'] ?? '');
$expectedState = (string) ($_SESSION['oauth_google_state'] ?? '');
unset($_SESSION['oauth_google_state']);

if ($expectedState === '' || $state === '' || ! hash_equals($expectedState, $state)) {
    http_response_code(400);
    echo 'Invalid Google login state. Please try again.';
    exit;
}

$error = (string) ($_GET['error'] ?? '');
if ($error !== '') {
    http_response_code(400);
    echo 'Google login failed: ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    exit;
}

$code = $_GET['code'] ?? '';

if (!$code) {
    http_response_code(400);
    echo 'Google login failed: missing authorization code.';
    exit;
}

if ($clientId === '' || $clientSecret === '') {
    http_response_code(500);
    echo 'Google login is not configured (missing client credentials).';
    exit;
}

$tokenResponse = file_get_contents("https://oauth2.googleapis.com/token", false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded",
        'content' => http_build_query([
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $baseUrl . '/auth/google-callback.php',
            'grant_type' => 'authorization_code'
        ])
    ]
]));

if (! is_string($tokenResponse) || $tokenResponse === '') {
    http_response_code(502);
    echo 'Google login failed while exchanging the authorization code.';
    exit;
}

$tokenData = json_decode($tokenResponse, true);

$idToken = $tokenData['id_token'] ?? '';

if (!$idToken) {
    http_response_code(400);
    echo 'Google login failed: missing id_token.';
    exit;
}

$parts = explode('.', (string) $idToken);
if (count($parts) < 2) {
    http_response_code(400);
    echo 'Google login failed: invalid token format.';
    exit;
}

$payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

$email = $payload['email'] ?? '';
$name = $payload['name'] ?? '';

if (!$email) {
    http_response_code(400);
    echo 'Google login failed: no email in token.';
    exit;
}

$user = create_or_load_user($email, $name);

$_SESSION['user_id'] = $user['user']['id'];
$_SESSION['user_email'] = normalize_email($email);
$_SESSION['user_name'] = $user['user']['name'] ?? $name;

$inviteId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SESSION['post_auth_invite_id'] ?? ''));
$chatId   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SESSION['post_auth_chat_id'] ?? ''));
$fromName = trim((string)($_SESSION['post_auth_from_name'] ?? ''));
unset($_SESSION['post_auth_invite_id'], $_SESSION['post_auth_chat_id'], $_SESSION['post_auth_from_name']);
$redirectParams = [];
if ($inviteId !== '') $redirectParams['invite'] = $inviteId;
if ($chatId   !== '') $redirectParams['chat']   = $chatId;
if ($fromName !== '') $redirectParams['from']   = $fromName;
$postAuthRedirect = $baseUrl . '/' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

issue_remember_token($_SESSION['user_id']);

header("Location: " . $postAuthRedirect);
exit;
