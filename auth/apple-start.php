<?php
require_once __DIR__ . '/common.php';

$clientId = trim((string) getenv('APPLE_CLIENT_ID'));
$baseUrl = rtrim((string) (getenv('APP_BASE_URL') ?: $app_base_url), '/');
$redirectUri = trim((string) getenv('APPLE_REDIRECT_URI'));
if ($redirectUri === '') {
    $redirectUri = $baseUrl . '/auth/apple-callback.php';
}

if ($clientId === '') {
    http_response_code(500);
    echo 'Apple login is not configured (missing APPLE_CLIENT_ID).';
    exit;
}

$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));
$_SESSION['oauth_apple_state'] = $state;
$_SESSION['oauth_apple_nonce'] = $nonce;

// Preserve invite context across OAuth round-trip
$_SESSION['post_auth_invite_id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['invite'] ?? ''));
$_SESSION['post_auth_chat_id']   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['chat'] ?? ''));
$_SESSION['post_auth_from_name'] = trim((string)($_GET['from'] ?? ''));

$params = [
    'response_type' => 'code',
    'response_mode' => 'form_post',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => 'name email',
    'state' => $state,
    'nonce' => $nonce
];

$url = 'https://appleid.apple.com/auth/authorize?' . http_build_query($params);
header('Location: ' . $url);
exit;
