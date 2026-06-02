<?php

/* 
 * PREVENT CACHING, REMOVE IT WHEN LIVE!!!
 * 
 **/

$nocache = "?5";

if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = 30 * 24 * 60 * 60; // 30 days
    ini_set('session.gc_maxlifetime', $sessionLifetime);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/remember-tokens.php';
require_once __DIR__ . '/../config/secrets.php';
if (empty($_SESSION['user_id'])) {
    restore_session_from_remember_cookie();
}

// After a cookie-based restore, user_email may be missing from the session.
// Reload it from the user profile so load_app_config() works correctly.
if (!empty($_SESSION['user_id']) && empty($_SESSION['user_email'])) {
    try {
        $__profilePath = get_user_profile_path_by_id($_SESSION['user_id']);
        $__profile = load_json($__profilePath);
        $__email = trim((string) ($__profile['user']['email'] ?? ''));
        if ($__email !== '') {
            $_SESSION['user_email'] = $__email;
        }
        unset($__profilePath, $__profile, $__email);
    } catch (\Throwable $__e) {
        error_log('[remember] Could not restore user_email: ' . $__e->getMessage());
        unset($__e);
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$app_base_url = "https://www.tsjilp.me";

define('APP_DEBUG', false);

function load_json(string $file): array {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save_json(string $file, array $data): bool {
    $dir = dirname($file);
    
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log('save_json: could not create directory ' . $dir);
        return false;
    }
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ok = file_put_contents($file, $json);
    
    if ($ok === false) {
        error_log('save_json: could not write file ' . $file);
        return false;
    }
    
    return true;
}

function get_app_secret_key(): string
{
    $secret = secret('APP_SECRET');
    if ($secret === '') {
        return '';
    }
    return hash('sha256', $secret, true);
}

function is_encrypted_secret(string $value): bool
{
    $trimmed = trim($value);
    return $trimmed !== '' && substr($trimmed, 0, 4) === 'ENC:';
}

function encrypt_secret_value(string $plaintext): string
{
    $plaintext = trim($plaintext);
    if ($plaintext === '') {
        return '';
    }

    $key = get_app_secret_key();
    if ($key === '') {
        return '';
    }

    $cipher = 'aes-256-cbc';
    $ivLen = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLen);
    $encrypted = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return '';
    }

    return 'ENC:' . base64_encode($iv . $encrypted);
}

function decrypt_secret_value(string $payload): string
{
    $payload = trim($payload);
    if ($payload === '') {
        return '';
    }

    if (!is_encrypted_secret($payload)) {
        return $payload;
    }

    $encoded = substr($payload, 4);
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) {
        return '';
    }

    $cipher = 'aes-256-cbc';
    $key = get_app_secret_key();
    if ($key === '') {
        return '';
    }

    $ivLen = openssl_cipher_iv_length($cipher);
    if (strlen($decoded) <= $ivLen) {
        return '';
    }

    $iv = substr($decoded, 0, $ivLen);
    $cipherText = substr($decoded, $ivLen);
    $decrypted = openssl_decrypt($cipherText, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted === false ? '' : $decrypted;
}

function is_masked_secret_value(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return false;
    }
    return preg_match('/^[\x2A\u2022\u00B7]+$/u', $trimmed) === 1;
}

function load_json_file(string $file, $default = [])
{
    if (!file_exists($file)) {
        return $default;
    }

    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function save_json_file(string $file, array $data): bool
{
    return save_json($file, $data);
}

function generate_id(string $prefix = 'user'): string {
    return $prefix . '_' . bin2hex(random_bytes(6));
}

function build_user_storage_key(string $userId): string
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    if ($userId === '') {
        return '';
    }

    return substr(hash('sha256', $userId), 0, 16);
}

function normalize_email(string $email): string {
    return mb_strtolower(trim($email));
}

function get_data_dir(): string {
    return __DIR__ . '/../data';
}

function get_users_dir(): string {
    return get_data_dir() . '/users';
}

function get_user_json_path_by_id(string $userId): string
{
    return get_user_profile_path_by_id($userId);
}

function get_invites_root(): string
{
    $dir = get_data_dir() . '/invites';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create invites directory');
    }
    return $dir;
}

function get_user_base_dir_by_id(string $userId): string
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    if ($userId === '') {
        throw new RuntimeException('Missing user id');
    }

    $legacyBase = get_users_dir() . '/' . $userId;
    $storageKey = build_user_storage_key($userId);
    $base = get_users_dir() . '/' . $storageKey;

    if (is_dir($legacyBase)) {
        $base = $legacyBase;
    }

    if (!is_dir($base) && !mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException('Could not create user directory');
    }

    return $base;
}

function resolve_user_id_from_dir(string $userDir): string
{
    $userDir = rtrim($userDir, '/\\');
    if ($userDir === '' || !is_dir($userDir)) {
        return '';
    }

    foreach (['/user.json', '/profile.json'] as $suffix) {
        $profile = load_json($userDir . $suffix);
        $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($profile['user']['id'] ?? ''));
        if ($userId !== '') {
            return $userId;
        }
    }

    return preg_replace('/[^a-zA-Z0-9_-]/', '', basename($userDir));
}

function ensure_full_user_workspace_by_id(string $userId): void
{
    $base = get_user_base_dir_by_id($userId);

    if (!is_dir($base . '/chats') && !mkdir($base . '/chats', 0777, true) && !is_dir($base . '/chats')) {
        throw new RuntimeException('Could not create chats directory');
    }

    foreach ([
        '/chats-index.json' => [],
        '/contacts.json' => ['contacts' => []]
    ] as $suffix => $defaultPayload) {
        $path = $base . $suffix;
        if (!file_exists($path)) {
            save_json($path, $defaultPayload);
        }
    }
}

function get_user_profile_path_by_id(string $userId): string
{
    return get_user_base_dir_by_id($userId) . '/user.json';
}

function get_user_contacts_path_by_id(string $userId): string
{
    return get_user_base_dir_by_id($userId) . '/contacts.json';
}

function contacts_generate_internal_id(): string
{
    return 'c_' . bin2hex(random_bytes(6));
}

function normalize_contacts_payload(array $payload, bool &$changed = false): array
{
    $changed = false;

    if (!isset($payload['contacts']) || !is_array($payload['contacts'])) {
        $payload['contacts'] = [];
        $changed = true;
        return $payload;
    }

    $normalized = [];

    foreach ($payload['contacts'] as $key => $record) {
        if (!is_array($record)) {
            $changed = true;
            continue;
        }

        $rawKey = trim((string) $key);
        $id = trim((string) ($record['id'] ?? ''));

        if ($id === '' && $rawKey !== '') {
            $id = $rawKey;
        }

        if ($id === '') {
            do {
                $id = contacts_generate_internal_id();
            } while (isset($normalized[$id]));
            $changed = true;
        }

        if ($rawKey !== $id || (string) ($record['id'] ?? '') !== $id) {
            $changed = true;
        }

        if (isset($normalized[$id])) {
            do {
                $id = contacts_generate_internal_id();
            } while (isset($normalized[$id]));
            $changed = true;
        }

        $record['id'] = $id;

        // Keep contact settings schema stable across reads/writes.
        $relationRaw = isset($record['relation']) ? (int) trim((string) $record['relation']) : 3;
        if ($relationRaw < 1 || $relationRaw > 5) {
            $relationRaw = 3;
        }

        $toneSource = $record['tone'] ?? 3;
        if (is_array($toneSource)) {
            $toneSource = $toneSource[0] ?? 3;
            $changed = true;
        }
        $toneRaw = (int) trim((string) $toneSource);
        if ($toneRaw < 1 || $toneRaw > 5) {
            $toneRaw = 3;
        }

        $profileVersionRaw = isset($record['profile_version']) ? (int) $record['profile_version'] : 0;
        if ($profileVersionRaw < 0) {
            $profileVersionRaw = 0;
        }

        $statusRaw = mb_strtolower(trim((string) ($record['status'] ?? 'active')));
        if ($statusRaw !== 'active' && $statusRaw !== 'blocked') {
            $statusRaw = !empty($record['blocked']) ? 'blocked' : 'active';
        }

        if (!isset($record['relation']) || (int) $record['relation'] !== $relationRaw) {
            $changed = true;
        }
        if (!isset($record['tone']) || (int) $record['tone'] !== $toneRaw || is_array($record['tone'] ?? null)) {
            $changed = true;
        }
        if (!isset($record['status']) || (string) $record['status'] !== $statusRaw) {
            $changed = true;
        }
        if (!isset($record['profile_version']) || (int) $record['profile_version'] !== $profileVersionRaw) {
            $changed = true;
        }

        $record['relation'] = $relationRaw;
        $record['tone'] = $toneRaw;
        $record['status'] = $statusRaw;
        $record['profile_version'] = $profileVersionRaw;

        $normalized[$id] = $record;
    }

    if ($changed) {
        $payload['contacts'] = $normalized;
    }

    return $payload;
}

function load_user_contacts_by_id(string $userId): array
{
    $path = get_user_contacts_path_by_id($userId);
    $payload = load_json($path);
    $changed = false;
    $payload = normalize_contacts_payload($payload, $changed);

    if ($changed) {
        save_json($path, $payload);
    }

    return $payload;
}

function save_user_contacts_by_id(string $userId, array $payload): bool
{
    $changed = false;
    $payload = normalize_contacts_payload($payload, $changed);
    return save_json(get_user_contacts_path_by_id($userId), $payload);
}

function load_user_profile_by_id(string $userId): array
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    if ($userId === '') {
        return [];
    }

    $baseDir = get_user_base_dir_by_id($userId);
    $primaryPath = $baseDir . '/user.json';
    $legacyProfilePath = $baseDir . '/profile.json';

    if (file_exists($primaryPath)) {
        $profile = load_json($primaryPath);
        if (is_array($profile) && $profile !== []) {
            return $profile;
        }
    }

    if (file_exists($legacyProfilePath)) {
        $profile = load_json($legacyProfilePath);
        if (is_array($profile) && $profile !== []) {
            return $profile;
        }
    }

    foreach (glob(get_users_dir() . '/*.json') as $userFile) {
        $profile = load_json($userFile);
        if (!is_array($profile) || empty($profile['user']['id'])) {
            continue;
        }

        if (preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $profile['user']['id']) === $userId) {
            return $profile;
        }
    }

    return [];
}

function get_user_open_issues_path_by_id(string $userId): string
{
    return get_user_base_dir_by_id($userId) . '/open-issues.json';
}

function load_current_open_issues(): array
{
    $userId = get_current_user_id();
    if ($userId === '') {
        return [];
    }

    $issues = load_json_file(get_user_open_issues_path_by_id($userId), []);
    return is_array($issues) ? $issues : [];
}

function save_current_open_issues(array $issues): bool
{
    $userId = get_current_user_id();
    if ($userId === '') {
        return false;
    }

    return save_json_file(get_user_open_issues_path_by_id($userId), $issues);
}

function get_user_open_issue_entry(string $userId, string $chatId, string $messageId): ?array
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    $chatId = preg_replace('/[^a-zA-Z0-9_-]/', '', $chatId);
    $messageId = trim($messageId);

    if ($userId === '' || $chatId === '' || $messageId === '') {
        return null;
    }

    $issues = load_json_file(get_user_open_issues_path_by_id($userId), []);
    if (!is_array($issues) || !isset($issues[$chatId][$messageId]) || !is_array($issues[$chatId][$messageId])) {
        return null;
    }

    return $issues[$chatId][$messageId];
}

function set_user_open_issue(string $userId, string $chatId, string $messageId, array $entry): bool
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    $chatId = preg_replace('/[^a-zA-Z0-9_-]/', '', $chatId);
    $messageId = trim($messageId);

    if ($userId === '' || $chatId === '' || $messageId === '') {
        return false;
    }

    $issues = load_json_file(get_user_open_issues_path_by_id($userId), []);
    if (!is_array($issues)) {
        $issues = [];
    }
    if (!isset($issues[$chatId]) || !is_array($issues[$chatId])) {
        $issues[$chatId] = [];
    }

    $issues[$chatId][$messageId] = $entry;
    return save_json_file(get_user_open_issues_path_by_id($userId), $issues);
}

function remove_user_open_issue(string $userId, string $chatId, string $messageId): bool
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    $chatId = preg_replace('/[^a-zA-Z0-9_-]/', '', $chatId);
    $messageId = trim($messageId);

    if ($userId === '' || $chatId === '' || $messageId === '') {
        return false;
    }

    $issues = load_json_file(get_user_open_issues_path_by_id($userId), []);
    if (!is_array($issues) || !isset($issues[$chatId][$messageId])) {
        return false;
    }

    unset($issues[$chatId][$messageId]);
    if (empty($issues[$chatId])) {
        unset($issues[$chatId]);
    }

    return save_json_file(get_user_open_issues_path_by_id($userId), $issues);
}

function find_chat_owner_for_chat_id(string $chatId): array
{
    $chatId = preg_replace('/[^a-zA-Z0-9_-]/', '', $chatId);
    if ($chatId === '') {
        return [];
    }

    foreach (glob(get_users_dir() . '/*') as $userDir) {
        if (!is_dir($userDir)) {
            continue;
        }

        $ownerUserId = resolve_user_id_from_dir($userDir);
        if ($ownerUserId === '') {
            continue;
        }

        $contacts = load_user_contacts_by_id($ownerUserId);
        foreach (($contacts['guest_memberships'] ?? []) as $membership) {
            if (!is_array($membership)) {
                continue;
            }

            if (preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($membership['chat_id'] ?? '')) === $chatId) {
                return [
                    'owner_user_id' => $ownerUserId,
                    'membership' => $membership
                ];
            }
        }

        $chatMetaPath = $userDir . '/chats/' . $chatId . '/chat-meta.json';
        $legacyChatPath = $userDir . '/chats/' . $chatId . '.json';

        if (is_file($chatMetaPath) || is_file($legacyChatPath)) {
            return [
                'owner_user_id' => $ownerUserId,
                'chat_id' => $chatId,
                'path' => is_file($chatMetaPath) ? $chatMetaPath : $legacyChatPath
            ];
        }
    }

    return [];
}

function upsert_guest_membership_for_owner(string $ownerUserId, array $membership): bool
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $token = trim((string) ($membership['guest_token'] ?? ''));
    if ($ownerUserId === '' || $token === '') {
        return false;
    }

    $payload = load_user_contacts_by_id($ownerUserId);
    if (!isset($payload['guest_memberships']) || !is_array($payload['guest_memberships'])) {
        $payload['guest_memberships'] = [];
    }

    $membership['owner_user_id'] = $ownerUserId;
    $membership['guest_token'] = $token;
    $payload['guest_memberships'][$token] = $membership;

    return save_user_contacts_by_id($ownerUserId, $payload);
}

function get_user_file_by_email(string $email): string {
    $normalized = normalize_email($email);
    $hash = hash('sha256', $normalized);
    return get_users_dir() . '/' . $hash . '.json';
}

function load_user_by_email(string $email): array {
    $email = normalize_email($email);
    if ($email === '') {
        return [];
    }

    foreach (glob(get_users_dir() . '/*') as $userDir) {
        if (!is_dir($userDir)) {
            continue;
        }

        foreach (['/user.json', '/profile.json'] as $suffix) {
            $payload = load_json($userDir . $suffix);
            if (!is_array($payload) || empty($payload['user'])) {
                continue;
            }

            $candidateEmail = normalize_email((string)($payload['user']['email'] ?? ''));
            if ($candidateEmail !== '' && $candidateEmail === $email) {
                return $payload;
            }
        }
    }

    // Fallback for legacy flat files in /data/users/*.json
    return load_json(get_user_file_by_email($email));
}

function save_user_by_email(string $email, array $data): bool {
    $email = normalize_email($email);
    if ($email === '') {
        return false;
    }

    if (!isset($data['user']) || !is_array($data['user'])) {
        $data['user'] = [];
    }

    $data['user']['email'] = $email;

    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['user']['id'] ?? ''));
    if ($userId === '') {
        $userId = generate_id('user');
        $data['user']['id'] = $userId;
    }

    $name = trim((string)($data['user']['name'] ?? 'User'));
    $data['user']['name'] = $name;
    if (empty($data['user']['initials'])) {
        $data['user']['initials'] = make_initials($name);
    }

    $isLightweight = ((string)($data['user']['member_kind'] ?? '') === 'invited_member');
    if (!$isLightweight) {
        ensure_full_user_workspace_by_id($userId);
    }

    $saved = save_json(get_user_profile_path_by_id($userId), $data);
    if (!$saved) {
        return false;
    }

    // Cleanup obsolete duplicate profile file when present.
    $legacyProfilePath = get_user_base_dir_by_id($userId) . '/profile.json';
    if (is_file($legacyProfilePath)) {
        @unlink($legacyProfilePath);
    }

    return true;
}

function user_exists_by_email(string $email): bool {
    return !empty(load_user_by_email($email));
}

function is_user_hidden(array $userProfile): bool {
    return trim((string)(($userProfile['user'] ?? [])['visibility'] ?? 'visible')) === 'hidden';
}

// ─── Private beta helpers ────────────────────────────────────────────────────

define('BETA_DAILY_TOKEN_LIMIT', 50000);
define('BETA_TOTAL_TOKEN_LIMIT', 250000);
define('BETA_ACCESS_DAYS', 10);
define('BETA_MAX_INPUT_TOKENS', 1000);

function get_beta_usage(array $userProfile): array {
    $u = $userProfile['user'] ?? [];
    $betaStartedAt  = trim((string)($u['beta_started_at']    ?? ''));
    $dailyResetDate = trim((string)($u['daily_reset_date']   ?? ''));
    $dailyUsed      = (int)($u['daily_tokens_used']  ?? 0);
    $totalUsed      = (int)($u['total_tokens_used']  ?? 0);

    $today = date('Y-m-d');
    if ($dailyResetDate !== $today) {
        $dailyUsed = 0;
    }

    $daysUsed = 0;
    if ($betaStartedAt !== '') {
        $startTs = strtotime($betaStartedAt);
        if ($startTs !== false) {
            $daysUsed = (int)floor((time() - $startTs) / 86400);
        }
    }

    return [
        'beta_started_at'  => $betaStartedAt,
        'daily_tokens_used' => $dailyUsed,
        'daily_reset_date'  => $dailyResetDate,
        'total_tokens_used' => $totalUsed,
        'days_used'         => $daysUsed,
        'today'             => $today,
    ];
}

function check_beta_limits(array $betaUsage): array {
    $dailyUsed = $betaUsage['daily_tokens_used'];
    $totalUsed = $betaUsage['total_tokens_used'];
    $daysUsed  = $betaUsage['days_used'];

    if ($daysUsed >= BETA_ACCESS_DAYS || $totalUsed >= BETA_TOTAL_TOKEN_LIMIT) {
        return ['allowed' => false, 'reason' => 'expired'];
    }
    if ($dailyUsed >= BETA_DAILY_TOKEN_LIMIT) {
        return ['allowed' => false, 'reason' => 'daily_limit'];
    }
    return ['allowed' => true, 'reason' => ''];
}

function record_beta_token_usage(string $userId, int $tokensUsed): void {
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    if ($userId === '' || $tokensUsed <= 0) {
        return;
    }

    $profile = load_user_profile_by_id($userId);
    if (empty($profile['user'])) {
        return;
    }

    $betaUsage = get_beta_usage($profile);
    $today = date('Y-m-d');

    if ($betaUsage['beta_started_at'] === '') {
        $profile['user']['beta_started_at'] = date('Y-m-d H:i:s');
    }

    $daily = ($betaUsage['daily_reset_date'] === $today) ? $betaUsage['daily_tokens_used'] : 0;
    $profile['user']['daily_tokens_used'] = $daily + $tokensUsed;
    $profile['user']['daily_reset_date']  = $today;
    $profile['user']['total_tokens_used'] = $betaUsage['total_tokens_used'] + $tokensUsed;

    save_user_by_email(normalize_email((string)($profile['user']['email'] ?? '')), $profile);
}

// ─────────────────────────────────────────────────────────────────────────────

function make_initials(string $name = ''): string {
    $name = trim($name);
    if ($name === '') return 'DU';

    $parts = preg_split('/\s+/', $name);
    $initials = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if (mb_strlen($initials) >= 2) break;
    }

    if (mb_strlen($initials) === 1) {
        $first = $parts[0] ?? '';
        if (mb_strlen($first) >= 2) {
            $initials .= mb_strtoupper(mb_substr($first, 1, 1));
        }
    }

    return str_pad($initials, 2, 'X');
}

function create_or_load_user(string $email, string $name = ''): array {
    $email = normalize_email($email);
    $existing = load_user_by_email($email);

    if (!empty($existing['user'])) {
        if ($name !== '' && empty($existing['user']['name'])) {
            $existing['user']['name'] = $name;
            $existing['user']['initials'] = make_initials($name);
            save_user_by_email($email, $existing);
        }
        return $existing;
    }

    $name = trim($name);
    if ($name === '') {
        $name = 'User';
    }

    $user = [
        "user" => [
            "id" => generate_id('user'),
            "name" => $name,
            "initials" => make_initials($name),
            "email" => $email,
            "email_verified" => true,
            "marketing_opt_in" => false,
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

    save_user_by_email($email, $user);

    return $user;
}

function find_pending_signup_file_by_email(string $email): string {
    $email = normalize_email($email);

    foreach (glob(get_data_dir() . '/pending_signup_*.json') as $pendingFile) {
        $pendingData = load_json($pendingFile);
        if (!empty($pendingData['email']) && normalize_email($pendingData['email']) === $email) {
            return $pendingFile;
        }
    }

    return '';
}

function load_pending_signup_by_email(string $email): array {
    $file = find_pending_signup_file_by_email($email);
    return $file !== '' ? load_json($file) : [];
}

function delete_pending_signups_by_email(string $email): void {
    $email = normalize_email($email);

    foreach (glob(get_data_dir() . '/pending_signup_*.json') as $pendingFile) {
        $pendingData = load_json($pendingFile);
        if (!empty($pendingData['email']) && normalize_email($pendingData['email']) === $email) {
            @unlink($pendingFile);
        }
    }
}

function create_pending_signup(
    string $name,
    string $email,
    string $passwordHash,
    bool $marketingOptIn,
    ?string $token = null,
    string $upgradeUserId = '',
    string $inviteId = '',
    string $chatId = '',
    string $fromName = ''
): string {
    $email = normalize_email($email);
    $upgradeUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $upgradeUserId);
    $inviteId = preg_replace('/[^a-zA-Z0-9_-]/', '', $inviteId);
    $chatId = preg_replace('/[^a-zA-Z0-9_-]/', '', $chatId);
    $fromName = trim($fromName);
    if ($token === null || $token === '') {
        $token = bin2hex(random_bytes(16));
    }

    $pendingSignup = [
        'name' => trim($name),
        'email' => $email,
        'password_hash' => $passwordHash,
        'marketing_opt_in' => $marketingOptIn,
        'created_at' => time()
    ];

    if ($upgradeUserId !== '') {
        $pendingSignup['upgrade_user_id'] = $upgradeUserId;
    }

    if ($inviteId !== '') {
        $pendingSignup['invite_id'] = $inviteId;
    }

    if ($chatId !== '') {
        $pendingSignup['chat_id'] = $chatId;
    }

    if ($fromName !== '') {
        $pendingSignup['from_name'] = $fromName;
    }

    save_json(get_data_dir() . "/pending_signup_$token.json", $pendingSignup);

    return $token;
}

function cleanup_auth_files(string $dir = __DIR__ . '/../data', int $ttl = 86400): void {
    if (!is_dir($dir)) return;

    $now = time();

    foreach (glob($dir . '/*.json') as $file) {
        if (!is_file($file)) continue;

        if (($now - filemtime($file)) > $ttl) {
            @unlink($file);
        }
    }
}

function getCurrentUserHash(): ?string {
    return $_SESSION['user_id'] ?? null;
}

function getUserBaseDir(): string {
    $userHash = getCurrentUserHash();
    if (!$userHash) {
        http_response_code(401);
        exit('Not logged in');
    }
    
    $base = __DIR__ . '/../data/users/' . $userHash;
    if (!is_dir($base)) mkdir($base, 0777, true);
    if (!is_dir($base . '/chats')) mkdir($base . '/chats', 0777, true);
    
    return $base;
}

function getChatsIndexPath(): string {
    return getUserBaseDir() . '/chats-index.json';
}

function loadChatsIndex(): array {
    $path = getChatsIndexPath();
    if (!file_exists($path)) {
        file_put_contents($path, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveChatsIndex(array $index): void {
    file_put_contents(
            getChatsIndexPath(),
            json_encode(array_values($index), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
}

function getChatPath(string $chatId): string {
    return getUserBaseDir() . '/chats/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $chatId) . '.json';
}

function now(): string {
    return date('Y-m-d H:i:s');
}

function get_users_root(): string
{
    return get_users_dir();
}

function get_current_user_id(): string
{
    return (string) ($_SESSION['user_id'] ?? '');
}

function get_current_user_email(): string
{
    return (string) ($_SESSION['user_email'] ?? '');
}

function get_current_user_name(): string
{
    return (string) ($_SESSION['user_name'] ?? '');
}

function require_login(): void
{
    if (get_current_user_id() === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}
