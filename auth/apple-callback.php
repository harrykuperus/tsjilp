<?php
require_once __DIR__ . '/common.php';

function apple_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function apple_build_client_secret(string $teamId, string $clientId, string $keyId, string $privateKeyPem): string
{
    $header = [
        'alg' => 'ES256',
        'kid' => $keyId,
        'typ' => 'JWT'
    ];

    $now = time();
    $payload = [
        'iss' => $teamId,
        'iat' => $now,
        'exp' => $now + 300,
        'aud' => 'https://appleid.apple.com',
        'sub' => $clientId
    ];

    $encodedHeader = apple_base64url_encode(json_encode($header));
    $encodedPayload = apple_base64url_encode(json_encode($payload));
    $signingInput = $encodedHeader . '.' . $encodedPayload;

    $signature = '';
    $ok = openssl_sign($signingInput, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
    if (! $ok) {
        return '';
    }

    return $signingInput . '.' . apple_base64url_encode($signature);
}

function apple_decode_jwt_payload(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return [];
    }

    $payloadRaw = strtr($parts[1], '-_', '+/');
    $payloadRaw .= str_repeat('=', (4 - strlen($payloadRaw) % 4) % 4);

    $decoded = base64_decode($payloadRaw, true);
    if (! is_string($decoded) || $decoded === '') {
        return [];
    }

    $data = json_decode($decoded, true);
    return is_array($data) ? $data : [];
}

$baseUrl = rtrim((string) (getenv('APP_BASE_URL') ?: $app_base_url), '/');
$clientId = trim((string) getenv('APPLE_CLIENT_ID'));
$teamId = trim((string) getenv('APPLE_TEAM_ID'));
$keyId = trim((string) getenv('APPLE_KEY_ID'));
$privateKey = (string) getenv('APPLE_PRIVATE_KEY');
$redirectUri = trim((string) getenv('APPLE_REDIRECT_URI'));
if ($redirectUri === '') {
    $redirectUri = $baseUrl . '/auth/apple-callback.php';
}

if ($clientId === '' || $teamId === '' || $keyId === '' || trim($privateKey) === '') {
    http_response_code(500);
    echo 'Apple login is not configured (missing APPLE_CLIENT_ID, APPLE_TEAM_ID, APPLE_KEY_ID, or APPLE_PRIVATE_KEY).';
    exit;
}

$privateKey = str_replace("\\n", "\n", $privateKey);

$state = (string) ($_POST['state'] ?? $_GET['state'] ?? '');
$expectedState = (string) ($_SESSION['oauth_apple_state'] ?? '');
unset($_SESSION['oauth_apple_state']);
unset($_SESSION['oauth_apple_nonce']);

if ($expectedState === '' || $state === '' || ! hash_equals($expectedState, $state)) {
    http_response_code(400);
    echo 'Invalid Apple login state. Please try again.';
    exit;
}

$error = (string) ($_POST['error'] ?? $_GET['error'] ?? '');
if ($error !== '') {
    http_response_code(400);
    echo 'Apple login failed: ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    exit;
}

$code = (string) ($_POST['code'] ?? $_GET['code'] ?? '');
if ($code === '') {
    http_response_code(400);
    echo 'Apple login failed: missing authorization code.';
    exit;
}

$clientSecret = apple_build_client_secret($teamId, $clientId, $keyId, $privateKey);
if ($clientSecret === '') {
    http_response_code(500);
    echo 'Apple login failed: could not build client secret.';
    exit;
}

$tokenResponse = file_get_contents('https://appleid.apple.com/auth/token', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded",
        'content' => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ])
    ]
]));

if (! is_string($tokenResponse) || $tokenResponse === '') {
    http_response_code(502);
    echo 'Apple login failed while exchanging the authorization code.';
    exit;
}

$tokenData = json_decode($tokenResponse, true);
$idToken = (string) ($tokenData['id_token'] ?? '');
if ($idToken === '') {
    http_response_code(400);
    echo 'Apple login failed: missing id_token.';
    exit;
}

$payload = apple_decode_jwt_payload($idToken);
$email = normalize_email((string) ($payload['email'] ?? ''));
if ($email === '') {
    http_response_code(400);
    echo 'Apple login failed: no email in token.';
    exit;
}

$name = '';
$userRaw = (string) ($_POST['user'] ?? '');
if ($userRaw !== '') {
    $userObj = json_decode($userRaw, true);
    if (is_array($userObj)) {
        $firstName = trim((string) ($userObj['name']['firstName'] ?? ''));
        $lastName = trim((string) ($userObj['name']['lastName'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);
    }
}

if ($name === '') {
    $name = trim((string) ($payload['name'] ?? ''));
}

$user = create_or_load_user($email, $name);
$_SESSION['user_id'] = (string) ($user['user']['id'] ?? '');
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = (string) ($user['user']['name'] ?? ($name !== '' ? $name : 'User'));

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

header('Location: ' . $postAuthRedirect);
exit;
