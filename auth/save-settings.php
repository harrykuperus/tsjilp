<?php
require_once __DIR__ . '/common.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];

$marketingOptIn = !empty($data['marketing_opt_in']);
$assistantEnabled = !empty($data['assistant_enabled']);
$assistantProvider = trim($data['assistant_provider'] ?? 'ChatGPT');
$assistantApiKey = trim($data['assistant_api_key'] ?? '');
$language = trim((string)($data['language'] ?? 'en'));
$allowedLanguages = ['en', 'nl', 'it', 'de', 'fr', 'es', 'pt', 'zh'];

if (!in_array($language, $allowedLanguages, true)) {
    $language = 'en';
}

$quickLanguages = $data['quick_languages'] ?? [];

if (!is_array($quickLanguages)) {
    $quickLanguages = [];
}

$quickLanguages = array_values(array_unique(array_filter(
    array_map(static fn($v) => trim((string)$v), $quickLanguages),
    static fn($v) => in_array($v, $allowedLanguages, true)
    )));

$email = normalize_email($_SESSION['user_email']);
$user = load_user_by_email($email);

if (!$user || empty($user['user'])) {
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}

$allowedProviders = ['ChatGPT'];
if (!in_array($assistantProvider, $allowedProviders, true)) {
    $assistantProvider = 'ChatGPT';
}

$providerKeys = [];
if (isset($user['user']['assistant_api_keys']) && is_array($user['user']['assistant_api_keys'])) {
    foreach ($user['user']['assistant_api_keys'] as $provider => $key) {
        $providerName = trim((string)$provider);
        if (!in_array($providerName, $allowedProviders, true)) {
            continue;
        }
        $providerKeys[$providerName] = decrypt_secret_value(trim((string)$key));
    }
}

if ($providerKeys === []) {
    $legacyProvider = trim((string)($user['user']['assistant_provider'] ?? 'ChatGPT'));
    if (!in_array($legacyProvider, $allowedProviders, true)) {
        $legacyProvider = 'ChatGPT';
    }
    $legacyKey = decrypt_secret_value(trim((string)($user['user']['assistant_api_key'] ?? '')));
    if ($legacyKey !== '') {
        $providerKeys[$legacyProvider] = $legacyKey;
    }
}

if ($assistantApiKey !== '' && !is_masked_secret_value($assistantApiKey)) {
    $providerKeys[$assistantProvider] = $assistantApiKey;
}

$activeKeyPlain = trim((string)($providerKeys[$assistantProvider] ?? ''));

$user['user']['marketing_opt_in'] = $marketingOptIn;
$user['user']['assistant_enabled'] = $assistantEnabled;
$user['user']['assistant_provider'] = $assistantProvider;

foreach ($providerKeys as $provider => $key) {
    $trimmedKey = trim((string)$key);
    if ($trimmedKey === '') {
        $providerKeys[$provider] = '';
        continue;
    }
    $encrypted = encrypt_secret_value($trimmedKey);
    $providerKeys[$provider] = $encrypted !== '' ? $encrypted : $trimmedKey;
}

$user['user']['assistant_api_keys'] = $providerKeys;
$user['user']['assistant_api_key'] = $activeKeyPlain !== '' ? (encrypt_secret_value($activeKeyPlain) ?: $activeKeyPlain) : '';
$user['user']['language'] = $language;
$user['user']['quick_languages'] = $quickLanguages;
$user['user']['default_language'] = $language;
$user['user']['known_languages'] = $quickLanguages;

$communicationProfile = trim((string)($data['communication_profile'] ?? ''));
$communicationPrompt = trim((string)($data['communication_custom_prompt'] ?? ''));

$user['user']['communication_profile'] = [
    'preset' => $communicationProfile,
    'custom_prompt' => $communicationPrompt
];

$_SESSION['assistant_enabled'] = $user['user']['assistant_enabled'];
$_SESSION['assistant_provider'] = $user['user']['assistant_provider'];
$_SESSION['assistant_api_key'] = $activeKeyPlain;

if (!save_user_by_email($email, $user)) {
    echo json_encode(['ok' => false, 'error' => 'Could not persist settings']);
    exit;
}

$storedKey = $activeKeyPlain;
function mask_api_key(string $key): string {
    $len = strlen($key);
    if ($len <= 8) {
        return str_repeat('•', $len);
    }
    $first = substr($key, 0, 4);
    $last = substr($key, -4);
    $middleLen = $len - 8;
    return $first . str_repeat('•', $middleLen) . $last;
}
$maskedKey = $storedKey !== '' ? mask_api_key($storedKey) : '';
        
echo json_encode([
    'ok' => true,
    'initials' => $user['user']['initials'] ?? make_initials((string)($user['user']['name'] ?? '')),
    'assistant_enabled' => !empty($user['user']['assistant_enabled']),
    'assistant_has_key' => $storedKey !== '',
    'assistant_key_masked' => $maskedKey,
    'communication_profile' => $user['user']['communication_profile'] ?? [],
    'language' => $user['user']['language'] ?? 'en',
    'quick_languages' => $user['user']['quick_languages'] ?? [],
    'default_language' => $user['user']['default_language'] ?? 'en',
    'known_languages' => $user['user']['known_languages'] ?? []
]);
