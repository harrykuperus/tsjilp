<?php
require_once __DIR__ . '/common.php';

$clientId = trim((string) getenv('GOOGLE_CLIENT_ID'));
$baseUrl = rtrim((string) (getenv('APP_BASE_URL') ?: $app_base_url), '/');

if ($clientId === '') {
    http_response_code(500);
    echo 'Google login is not configured (missing GOOGLE_CLIENT_ID).';
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_google_state'] = $state;

// Preserve invite context across OAuth round-trip
$_SESSION['post_auth_invite_id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['invite'] ?? ''));
$_SESSION['post_auth_chat_id']   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['chat'] ?? ''));
$_SESSION['post_auth_from_name'] = trim((string)($_GET['from'] ?? ''));

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $baseUrl . '/auth/google-callback.php',
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'state' => $state
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header("Location: $url");
exit;
