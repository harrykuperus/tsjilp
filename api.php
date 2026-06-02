<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth/common.php';
require_once __DIR__ . '/src/promptbuilder.php';
require_once __DIR__ . '/auth/contacts-common.php';
require_once __DIR__ . '/config/secrets.php';

if (!function_exists('contacts_hydrate_record')) {
    function contacts_hydrate_record(array $input, array $existing = []): array
    {
        $now = date('Y-m-d H:i:s');

        $record = [
                'id' => (string)($existing['id'] ?? $input['id'] ?? contacts_generate_id()),
                'display_name' => trim((string)($input['display_name'] ?? $existing['display_name'] ?? '')),
                'email' => trim((string)($input['email'] ?? $existing['email'] ?? '')),
                'preferred_language' => trim((string)($input['preferred_language'] ?? $existing['preferred_language'] ?? '')),
                'avatar' => trim((string)($input['avatar'] ?? $existing['avatar'] ?? '')),
                'invite_chat_id' => trim((string)($input['invite_chat_id'] ?? $existing['invite_chat_id'] ?? '')),
                'guest_token' => trim((string)($existing['guest_token'] ?? $input['guest_token'] ?? '')),
                'relation' => trim((string)($input['relation'] ?? $existing['relation'] ?? '')),
                'tone' => contacts_normalize_list($input['tone'] ?? $existing['tone'] ?? []),
                'topics' => contacts_normalize_list($input['topics'] ?? $existing['topics'] ?? []),
                'notes' => trim((string)($input['notes'] ?? $existing['notes'] ?? '')),
                'last_seen_at' => trim((string)($input['last_seen_at'] ?? $existing['last_seen_at'] ?? '')),
                'created_at' => (string)($existing['created_at'] ?? $input['created_at'] ?? $now),
                'updated_at' => $now,
                'status' => trim((string)($input['status'] ?? $existing['status'] ?? 'active')) ?: 'active'
        ];

        if ($record['guest_token'] === '' && $record['invite_chat_id'] !== '') {
            $record['guest_token'] = contacts_generate_guest_token();
        }

        return $record;
    }
}

function json_response(array $data, int $status = 200): void
{
    if (is_debug_mode()) {
        $debug = [];
        
        if (isset($GLOBALS['payload'])) {
            $debug['prompt'] = $GLOBALS['payload'];
        }
        
        if (isset($GLOBALS['data'])) {
            $debug['raw_response'] = $GLOBALS['data'];
        }
        
        if (isset($GLOBALS['payload'])) {
            $debug['payload'] = $GLOBALS['payload'];
        }
        
        if (isset($GLOBALS['system_prompt'])) {
            $debug['system_prompt'] = $GLOBALS['system_prompt'];
        }
        
        if (isset($GLOBALS['prompt_context'])) {
            $debug['prompt_context'] = $GLOBALS['prompt_context'];
        }
        
        if (isset($GLOBALS['sanitized_messages'])) {
            $debug['messages'] = $GLOBALS['sanitized_messages'];
        }
        
        if (isset($GLOBALS['sanitized_context_messages'])) {
            $debug['context_messages'] = $GLOBALS['sanitized_context_messages'];
        }
        
        if (isset($GLOBALS['data'])) {
            $debug['raw_response'] = $GLOBALS['data'];
        }
        
        if (! empty($debug)) {
            $data['_debug'] = redact_secrets($debug);
        }
    }
    
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function get_server_assistant_api_key(): string
{
    return trim(secret('OPENAI_API_KEY'));
}

function is_debug_mode(): bool
{
    return APP_DEBUG ? true : false;
}

function debug_error_payload(string $message, array $extra = []): array
{
    if (! is_debug_mode()) {
        return [
                'ok' => false,
                'silent' => true
        ];
    }
    return array_merge([
            'error' => $message
    ], $extra);
}

function maybe_chat_not_found(string $message = 'Chat not found', array $extra = [], int $status = 404): void
{
    if (is_debug_mode()) {
        json_response(array_merge([
                'error' => $message
        ], $extra), $status);
    }
    json_response(array_merge([
            'ok' => false,
            'silent' => true
    ], $extra), 200);
}

ini_set('display_errors', is_debug_mode() ? '1' : '0');

function now_str(): string
{
    return date('Y-m-d H:i:s');
}

function sanitize_chat_id(string $chatId): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $chatId);
}

function sanitize_invite_id(string $inviteId): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $inviteId);
}

function normalize_email_local(string $email): string
{
    return strtolower(trim($email));
}

function find_guest_membership_by_token(string $token): array
{
    $token = trim($token);
    if ($token === '') {
        return [];
    }

    foreach (glob(get_users_root() . '/*') as $userDir) {
        if (! is_dir($userDir)) {
            continue;
        }

        $ownerUserId = resolve_user_id_from_dir($userDir);
        if ($ownerUserId === '') {
            continue;
        }

        $payload = load_contacts_payload_for_owner($ownerUserId);
        $memberships = $payload['guest_memberships'] ?? [];
        if (! is_array($memberships) || empty($memberships[$token])) {
            continue;
        }

        $membership = is_array($memberships[$token]) ? $memberships[$token] : [];
        $chatId = sanitize_chat_id((string) ($membership['chat_id'] ?? ''));
        $chat = $chatId !== '' ? load_chat_for_owner($ownerUserId, $chatId) : null;

        return [
                'owner_user_id' => $ownerUserId,
                'membership' => $membership,
                'contact' => resolve_contact_by_id_for_owner($ownerUserId, (string) ($membership['contact_id'] ?? '')),
                'chat' => $chat,
                'chat_id' => $chatId
        ];
    }

    return [];
}

function build_guest_user_id_for_token(string $token): string
{
    return 'user_' . substr(hash('sha256', trim($token)), 0, 12);
}

function build_guest_user_email_for_token(string $token): string
{
    return 'member+' . substr(hash('sha256', trim($token)), 0, 12) . '@member.local';
}

function build_member_path(string $token): string
{
    return '/?member=' . rawurlencode(trim($token));
}

function is_invited_member_profile(array $profile): bool
{
    return ((string) (($profile['user'] ?? [])['member_kind'] ?? '') === 'invited_member');
}

function is_invited_member_user_id(string $userId): bool
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    if ($userId === '') {
        return false;
    }

    $profile = load_user_profile_by_id($userId);
    return is_invited_member_profile($profile);
}

function create_or_load_invited_member_for_token(string $token, string $displayName): array
{
    $token = trim($token);
    $displayName = trim($displayName);
    $email = build_guest_user_email_for_token($token);

    $profile = load_user_by_email($email);
    if (! is_array($profile) || empty($profile['user'])) {
        $profile = [
                'user' => [
                        'id' => build_guest_user_id_for_token($token),
                        'name' => $displayName !== '' ? $displayName : 'Member',
                        'initials' => make_initials($displayName !== '' ? $displayName : 'Member'),
                        'email' => $email,
                        'email_verified' => true,
                        'marketing_opt_in' => false,
                        'language' => 'en',
                        'languages' => ['en'],
                        'country' => '',
                        'age_range' => '',
                        'gender' => '',
                        'domain' => 'general',
                        'communication_style' => 'practical',
                        'assistant' => 'luca',
                        'assistant_profile_prompt' => '',
                        'member_kind' => 'invited_member',
                        'can_create_chats' => false,
                        'sso_link' => build_member_path($token),
                        'member_tokens' => [$token]
                ]
        ];
    }

    if (! isset($profile['user']) || ! is_array($profile['user'])) {
        $profile['user'] = [];
    }

    if ($displayName !== '') {
        $profile['user']['name'] = $displayName;
        $profile['user']['initials'] = make_initials($displayName);
    }

    $profile['user']['email'] = $email;
    $profile['user']['email_verified'] = true;
    $profile['user']['member_kind'] = 'invited_member';
    $profile['user']['can_create_chats'] = false;
    $profile['user']['sso_link'] = build_member_path($token);

    $tokens = $profile['user']['member_tokens'] ?? [];
    if (! is_array($tokens)) {
        $tokens = [];
    }
    if (! in_array($token, $tokens, true)) {
        $tokens[] = $token;
    }
    $profile['user']['member_tokens'] = array_values(array_unique(array_filter(array_map('strval', $tokens))));

    save_user_by_email($email, $profile);

    return load_user_by_email($email);
}

function persist_guest_session_from_membership(array $resolved): array
{
    $membership = is_array($resolved['membership'] ?? null) ? $resolved['membership'] : [];
    $contact = is_array($resolved['contact'] ?? null) ? $resolved['contact'] : [];

    $token = trim((string) ($membership['guest_token'] ?? ''));
    $chatId = sanitize_chat_id((string) ($resolved['chat_id'] ?? ''));
    $displayName = trim((string) ($contact['display_name'] ?? $membership['display_name'] ?? 'Guest'));
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($resolved['owner_user_id'] ?? ''));

    if ($token === '' || $chatId === '' || $ownerUserId === '') {
        return [];
    }

    $memberProfile = create_or_load_invited_member_for_token($token, $displayName);
    $memberUser = is_array($memberProfile['user'] ?? null) ? $memberProfile['user'] : [];
    $memberUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($memberUser['id'] ?? build_guest_user_id_for_token($token)));
    $memberEmail = normalize_email_local((string) ($memberUser['email'] ?? build_guest_user_email_for_token($token)));

    $contactId = trim((string) ($contact['id'] ?? ''));
    if ($contactId !== '') {
        $payload = load_contacts_payload_for_owner($ownerUserId);
        $existing = is_array($payload['contacts'][$contactId] ?? null) ? $payload['contacts'][$contactId] : [];
        $payload['contacts'][$contactId] = contacts_hydrate_record([
                'id' => $contactId,
                'display_name' => $displayName,
                'guest_token' => $token,
                'invite_chat_id' => $chatId,
                'user_id' => $memberUserId,
                'verified' => true
        ], $existing);
        save_contacts_payload_for_owner($ownerUserId, $payload);
    }

    $_SESSION['user_id'] = $memberUserId;
    $_SESSION['user_email'] = $memberEmail;
    $_SESSION['user_name'] = $displayName;
    $_SESSION['current_chat_id'] = $chatId;
    $_SESSION['guest_member_token'] = $token;
    $_SESSION['guest_owner_user_id'] = $ownerUserId;
    $_SESSION['guest_contact_id'] = (string) ($contact['id'] ?? '');

    return $memberProfile;
}

function ensure_dir(string $dir): void
{
    $dir = rtrim($dir, '/\\');
    if ($dir === '') {
        return;
    }
    if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
        json_response([
                'error' => 'Could not create directory'
        ], 500);
    }
}

function remove_chat_ref_from_owner_index(string $ownerUserId, string $chatId): bool
{
    return remove_chat_index_entry_for_user($ownerUserId, $chatId, $ownerUserId);
}

function remove_chat_ref_from_member_profile(string $memberUserId, string $ownerUserId, string $chatId): bool
{
    $memberUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $memberUserId);
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $chatId = sanitize_chat_id($chatId);
    if ($memberUserId === '' || $ownerUserId === '' || $chatId === '') {
        return false;
    }

    return remove_chat_index_entry_for_user($memberUserId, $chatId, $ownerUserId);
}

function normalize_membership_period_timestamp($value, string $fallback = ''): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return $fallback;
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return $fallback;
    }

    return date('Y-m-d H:i:s', $ts);
}

function normalize_membership_periods($periods): array
{
    if (!is_array($periods)) {
        return [];
    }

    $normalized = [];
    foreach ($periods as $period) {
        if (!is_array($period)) {
            continue;
        }

        $startAt = normalize_membership_period_timestamp($period['start_at'] ?? $period['from'] ?? '', '');
        if ($startAt === '') {
            continue;
        }

        $endAt = normalize_membership_period_timestamp($period['end_at'] ?? $period['to'] ?? '', '');
        if ($endAt !== '' && strtotime($endAt) !== false && strtotime($startAt) !== false && strtotime($endAt) < strtotime($startAt)) {
            $endAt = $startAt;
        }

        $normalized[] = [
            'start_at' => $startAt,
            'end_at' => $endAt !== '' ? $endAt : null,
        ];
    }

    usort($normalized, static function ($a, $b) {
        return strcmp((string)($a['start_at'] ?? ''), (string)($b['start_at'] ?? ''));
    });

    return $normalized;
}

function is_membership_periods_active(array $periods): bool
{
    foreach ($periods as $period) {
        if (!is_array($period)) {
            continue;
        }
        if (($period['end_at'] ?? null) === null) {
            return true;
        }
    }
    return false;
}

function close_open_membership_periods(array $periods, string $closedAt): array
{
    $closedAt = normalize_membership_period_timestamp($closedAt, now_str());
    if ($closedAt === '') {
        $closedAt = now_str();
    }

    $result = [];
    foreach ($periods as $period) {
        if (!is_array($period)) {
            continue;
        }
        $startAt = normalize_membership_period_timestamp($period['start_at'] ?? $period['from'] ?? '', '');
        if ($startAt === '') {
            continue;
        }
        $endAt = $period['end_at'] ?? $period['to'] ?? null;
        if ($endAt === null || trim((string) $endAt) === '') {
            $endAtNorm = normalize_membership_period_timestamp($closedAt, $startAt);
            if (strtotime($endAtNorm) !== false && strtotime($startAt) !== false && strtotime($endAtNorm) < strtotime($startAt)) {
                $endAtNorm = $startAt;
            }
            $result[] = ['start_at' => $startAt, 'end_at' => $endAtNorm];
            continue;
        }

        $endAtNorm = normalize_membership_period_timestamp($endAt, $startAt);
        $result[] = ['start_at' => $startAt, 'end_at' => $endAtNorm];
    }

    return normalize_membership_periods($result);
}

function ensure_member_periods_from_legacy(array $participant, array $snapshot, string $chatCreatedAt): array
{
    $existing = normalize_membership_periods(
        $participant['membership_periods']
        ?? $snapshot['membership_periods']
        ?? $participant['periods']
        ?? $snapshot['periods']
        ?? []
    );
    if ($existing !== []) {
        return $existing;
    }

    $role = trim((string) ($participant['role'] ?? $snapshot['role'] ?? 'member')) ?: 'member';
    $from = normalize_membership_period_timestamp(
        $participant['added_at'] ?? $snapshot['added_at'] ?? $chatCreatedAt,
        normalize_membership_period_timestamp($chatCreatedAt, now_str())
    );

    if ($role === 'owner') {
        return [[
            'start_at' => $from,
            'end_at' => null,
        ]];
    }

    $inactiveAt = normalize_membership_period_timestamp($participant['inactive_at'] ?? $snapshot['inactive_at'] ?? '', '');
    $removedAt = normalize_membership_period_timestamp($participant['removed_at'] ?? $snapshot['removed_at'] ?? '', '');
    $legacyHasInactiveMarker = ($inactiveAt !== '' || $removedAt !== '');
    $legacyActive = !$legacyHasInactiveMarker;

    if (array_key_exists('member_active', $participant) || array_key_exists('member_active', $snapshot)) {
        $legacyActive = (!empty($participant['member_active']) || !empty($snapshot['member_active']));
    }

    if ($legacyActive) {
        return [[
            'start_at' => $from,
            'end_at' => null,
        ]];
    }

    return [[
        'start_at' => $from,
        'end_at' => ($removedAt !== '' ? $removedAt : ($inactiveAt !== '' ? $inactiveAt : $from)),
    ]];
}

function migrate_legacy_profile_contacts_to_contacts(string $userId, array $legacyContacts): void
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    if ($userId === '' || $legacyContacts === []) {
        return;
    }

    $payload = load_user_contacts_by_id($userId);
    $changed = false;

    foreach ($legacyContacts as $legacyContact) {
        if (! is_array($legacyContact)) {
            continue;
        }

        $candidate = [
                'id' => trim((string) ($legacyContact['id'] ?? '')),
                'display_name' => trim((string) ($legacyContact['display_name'] ?? $legacyContact['name'] ?? '')),
                'email' => trim((string) ($legacyContact['email'] ?? '')),
                'preferred_language' => trim((string) ($legacyContact['preferred_language'] ?? '')),
                'avatar' => trim((string) ($legacyContact['avatar'] ?? '')),
                'user_id' => trim((string) ($legacyContact['user_id'] ?? '')),
                'verified' => ! empty($legacyContact['verified']),
                'relation' => trim((string) ($legacyContact['relation'] ?? '')),
                'tone' => $legacyContact['tone'] ?? [],
                'topics' => $legacyContact['topics'] ?? [],
                'notes' => trim((string) ($legacyContact['notes'] ?? '')),
                'status' => trim((string) ($legacyContact['status'] ?? 'active')) ?: 'active'
        ];

        if ($candidate['display_name'] === '' && $candidate['email'] === '' && $candidate['user_id'] === '') {
            continue;
        }

        $existing = find_matching_contact_record($payload, $candidate);
        $record = contacts_hydrate_record($candidate, $existing);
        if ($record['display_name'] === '') {
            $record['display_name'] = $candidate['display_name'] !== '' ? $candidate['display_name'] : ($candidate['email'] !== '' ? $candidate['email'] : 'Contact');
        }
        $payload['contacts'][$record['id']] = $record;
        $changed = true;
    }

    if ($changed) {
        save_user_contacts_by_id($userId, $payload);
    }
}

function normalize_user_profile_payload(array $profile, string $userId = ''): array
{
    if ($userId === '' && ! empty($profile['user']['id'])) {
        $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $profile['user']['id']);
    }

    $profile['pending_invites'] = array_values($profile['pending_invites'] ?? []);

    if ($userId !== '' && ! empty($profile['contacts']) && is_array($profile['contacts'])) {
        migrate_legacy_profile_contacts_to_contacts($userId, $profile['contacts']);
    }

    unset($profile['contacts']);

    return $profile;
}

function get_chats_index_path(): string
{
    return get_user_base_dir() . '/chats-index.json';
}

function get_chats_index_path_by_owner(string $ownerUserId): string
{
    return get_user_base_dir_by_id($ownerUserId) . '/chats-index.json';
}

function get_chat_meta_path_for_owner(string $ownerUserId, string $chatId): string
{
    $chatId = sanitize_chat_id($chatId);
    return get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chatId . '/chat-meta.json';
}

function ensure_chat_folder_for_owner(string $ownerUserId, string $chatId): string
{
    $chatDir = get_user_base_dir_by_id($ownerUserId) . '/chats/' . sanitize_chat_id($chatId);
    if (! is_dir($chatDir) && ! mkdir($chatDir, 0777, true) && ! is_dir($chatDir)) {
        json_response([
                'error' => 'Could not create chat directory'
        ], 500);
    }
    return $chatDir;
}

function normalize_chat_index_item(array $item, string $ownerUserId = ''): array
{
    $chatId = sanitize_chat_id((string) ($item['id'] ?? $item['chat_id'] ?? ''));
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $ownerUserId);
    $storedOwnerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['owner_user_id'] ?? ''));
    $effectiveOwnerUserId = $storedOwnerUserId !== '' ? $storedOwnerUserId : $ownerUserId;
    $item['id'] = $chatId;
    $item['chat_id'] = $chatId;
    if ($effectiveOwnerUserId !== '' && $effectiveOwnerUserId !== $ownerUserId) {
        $item['owner_user_id'] = $effectiveOwnerUserId;
    } else {
        unset($item['owner_user_id']);
    }
    $item['title'] = trim((string) ($item['title'] ?? '')) ?: 'New chat';
    $item['updated_at'] = trim((string) ($item['updated_at'] ?? ''));
    $item['state'] = in_array((string) ($item['state'] ?? 'active'), ['active', 'archived', 'left'], true) ? (string) $item['state'] : 'active';
    $item['unread_count'] = max(0, (int) ($item['unread_count'] ?? 0));
    $item['preview'] = trim((string) ($item['preview'] ?? ''));
    return $item;
}

function load_chats_index(): array
{
    return load_chats_index_by_owner((string) get_current_user_id());
}

function load_chats_index_by_owner(string $ownerUserId): array
{
    $path = get_chats_index_path_by_owner($ownerUserId);
    $oldPath = get_user_base_dir_by_id($ownerUserId) . '/chat-index.json';
    $legacyPath = get_user_base_dir_by_id($ownerUserId) . '/chats-index.json';

    if (! file_exists($path) && file_exists($oldPath)) {
        $oldIndex = load_json_file($oldPath, []);
        $normalized = [];
        foreach ((array) $oldIndex as $item) {
            if (! is_array($item)) {
                continue;
            }
            $normalized[] = normalize_chat_index_item($item, $ownerUserId);
        }
        save_json_file($path, $normalized);
    }

    if (! file_exists($path) && ! file_exists($legacyPath) && is_invited_member_user_id($ownerUserId)) {
        return [];
    }

    if (! file_exists($path)) {
        save_json_file($path, []);
    }

    $index = load_json_file($path, []);
    $normalized = [];
    foreach ((array) $index as $item) {
        if (! is_array($item)) {
            continue;
        }
        $normalized[] = normalize_chat_index_item($item, $ownerUserId);
    }

    return $normalized;
}

function save_chats_index(array $index): void
{
    save_json_file(get_chats_index_path(), array_values(array_map(function ($item) {
        return is_array($item) ? normalize_chat_index_item($item) : $item;
    }, $index)));
}

function save_chats_index_by_owner(string $ownerUserId, array $index): void
{
    $normalized = [];
    foreach ($index as $item) {
        if (! is_array($item)) {
            continue;
        }
        $normalized[] = normalize_chat_index_item($item, $ownerUserId);
    }

    $values = array_values($normalized);
    save_json_file(get_chats_index_path_by_owner($ownerUserId), $values);
    $oldPath = get_user_base_dir_by_id($ownerUserId) . '/chat-index.json';
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

function get_chat_open_issues_path_for_owner(string $ownerUserId, string $chatId): string
{
    $chatId = sanitize_chat_id($chatId);
    return ensure_chat_folder_for_owner($ownerUserId, $chatId) . '/open-issues.json';
}

function load_chat_open_issues_for_owner(string $ownerUserId, string $chatId): array
{
    $path = get_chat_open_issues_path_for_owner($ownerUserId, $chatId);
    $issues = load_json_file($path, []);
    return is_array($issues) ? $issues : [];
}

function save_chat_open_issues_for_owner(string $ownerUserId, string $chatId, array $issues): bool
{
    return save_json_file(get_chat_open_issues_path_for_owner($ownerUserId, $chatId), $issues);
}

function get_chat_path_for_owner(string $ownerUserId, string $chatId): string
{
    return get_chat_meta_path_for_owner($ownerUserId, $chatId);
}

function get_chat_path(string $chatId): string
{
    return get_chat_path_for_owner((string) get_current_user_id(), $chatId);
}


function get_chat_chunk_max_messages(): int
{
    return 200;
}

function get_chat_initial_load_chunk_count(): int
{
    return 2;
}

function get_chat_chunk_basename(string $chatId, int $index): string
{
    $index = max(1, $index);
    return 'messages-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT) . '.json';
}

function normalize_chat_chunk_file(string $chatId, string $chunkFile): string
{
    $chatId = sanitize_chat_id($chatId);
    $chunkFile = basename(trim($chunkFile));

    if ($chunkFile === '') {
        return '';
    }

    if ($chatId !== '') {
        $legacyPrefix = $chatId . '.';
        if (strpos($chunkFile, $legacyPrefix) === 0) {
            $chunkFile = substr($chunkFile, strlen($legacyPrefix));
        }
    }

    return basename($chunkFile);
}

function get_chat_chunk_path_for_owner(string $ownerUserId, string $chatId, string $chunkFile): string
{
    $chatId = sanitize_chat_id($chatId);
    $chunkFile = normalize_chat_chunk_file($chatId, $chunkFile);
    return get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chatId . '/' . $chunkFile;
}

function get_chat_chunk_legacy_path_for_owner(string $ownerUserId, string $chatId, string $chunkFile): string
{
    $chatId = sanitize_chat_id($chatId);
    $chunkFile = basename(trim($chunkFile));

    if ($chunkFile === '') {
        return get_user_base_dir_by_id($ownerUserId) . '/chats/';
    }

    if ($chatId !== '' && strpos($chunkFile, $chatId . '.') !== 0) {
        $chunkFile = $chatId . '.' . normalize_chat_chunk_file($chatId, $chunkFile);
    }

    return get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chunkFile;
}

function ensure_chat_message_chunks(array &$chat): array
{
    $chatId = sanitize_chat_id((string) ($chat['id'] ?? ''));

    if (! isset($chat['message_chunks']) || ! is_array($chat['message_chunks']) || ! $chat['message_chunks']) {
        $chat['message_chunks'] = [get_chat_chunk_basename($chatId, 1)];
    }

    $chat['message_chunks'] = array_values(array_filter(array_map(function ($file) use ($chatId) {
        $file = normalize_chat_chunk_file($chatId, (string) $file);
        return $file !== '' ? $file : null;
    }, $chat['message_chunks'])));

    if (! $chat['message_chunks']) {
        $chat['message_chunks'] = [get_chat_chunk_basename($chatId, 1)];
    }

    return $chat['message_chunks'];
}

function cleanup_chat_chunk_files_for_owner(string $ownerUserId, string $chatId, array $keepChunkFiles = []): void
{
    $chatId = sanitize_chat_id($chatId);
    if ($chatId === '') {
        return;
    }

    $chatDir = get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chatId;
    $keep = array_fill_keys(array_map(function ($chunkFile) use ($chatId) {
        return normalize_chat_chunk_file($chatId, (string) $chunkFile);
    }, $keepChunkFiles), true);

    // Clean up messages-*.json in the chat folder
    $pattern = $chatDir . '/messages-*.json';
    foreach (glob($pattern) ?: [] as $file) {
        $base = basename($file);
        if (! isset($keep[$base]) && is_file($file)) {
            @unlink($file);
        }
    }

    // Clean up legacy flat format files
    $legacyPattern = get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chatId . '.messages-*.json';
    foreach (glob($legacyPattern) ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function load_chat_messages_for_owner(string $ownerUserId, array $chat, ?array $chunkFiles = null): array
{
    $messages = [];
    $chunks = $chunkFiles ?? ($chat['message_chunks'] ?? []);
    
    if (! is_array($chunks)) {
        return [];
    }
    
    foreach ($chunks as $chunkFile) {
        $chatId = (string) ($chat['id'] ?? '');
        $path = get_chat_chunk_path_for_owner($ownerUserId, $chatId, (string) $chunkFile);
        if (! is_file($path)) {
            $legacyPath = get_chat_chunk_legacy_path_for_owner($ownerUserId, $chatId, (string) $chunkFile);
            $path = is_file($legacyPath) ? $legacyPath : $path;
        }

        if (! is_file($path)) {
            continue;
        }

        $chunkMessages = load_json_file($path, []);
        if (is_array($chunkMessages)) {
            $messages = array_merge($messages, $chunkMessages);
        }
    }
    
    return $messages;
}

function get_message_timestamp_for_membership(array $message): string
{
    $raw = (string) ($message['created_at'] ?? $message['time'] ?? $message['sent_at'] ?? '');
    return normalize_membership_period_timestamp($raw, '');
}

function is_message_visible_in_membership_periods(array $periods, string $messageAt): bool
{
    if ($messageAt === '') {
        return true;
    }

    $messageTs = strtotime($messageAt);
    if ($messageTs === false) {
        return true;
    }

    foreach ($periods as $period) {
        if (!is_array($period)) {
            continue;
        }

        $startAt = normalize_membership_period_timestamp($period['start_at'] ?? $period['from'] ?? '', '');
        if ($startAt === '') {
            continue;
        }

        $startTs = strtotime($startAt);
        if ($startTs === false || $messageTs < $startTs) {
            continue;
        }

        $endAtRaw = $period['end_at'] ?? $period['to'] ?? null;
        if ($endAtRaw === null || trim((string) $endAtRaw) === '') {
            return true;
        }

        $endAt = normalize_membership_period_timestamp($endAtRaw, '');
        if ($endAt === '') {
            continue;
        }

        $endTs = strtotime($endAt);
        if ($endTs === false || $messageTs <= $endTs) {
            return true;
        }
    }

    return false;
}

function resolve_membership_periods_for_user(array $chat, string $viewerUserId): array
{
    $viewerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $viewerUserId);
    if ($viewerUserId === '') {
        return [];
    }

    $chatCreatedAt = normalize_membership_period_timestamp($chat['created_at'] ?? now_str(), now_str());
    $participants = is_array($chat['participants'] ?? null) ? $chat['participants'] : [];
    $snapshots = is_array($chat['participant_snapshots'] ?? null) ? $chat['participant_snapshots'] : [];

    foreach ($participants as $participant) {
        if (!is_array($participant)) {
            continue;
        }

        $memberUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($participant['user_id'] ?? ''));
        if ($memberUserId === '' || $memberUserId !== $viewerUserId) {
            continue;
        }

        $contactId = trim((string) ($participant['contact_id'] ?? ''));
        $snapshot = is_array($snapshots[$contactId] ?? null) ? $snapshots[$contactId] : [];
        return ensure_member_periods_from_legacy($participant, $snapshot, $chatCreatedAt);
    }

    foreach ($snapshots as $snapshot) {
        if (!is_array($snapshot)) {
            continue;
        }
        $memberUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($snapshot['user_id'] ?? ''));
        if ($memberUserId === '' || $memberUserId !== $viewerUserId) {
            continue;
        }
        return ensure_member_periods_from_legacy([], $snapshot, $chatCreatedAt);
    }

    return [];
}

function filter_chat_messages_for_user_by_membership(array $chat, array $messages, string $viewerUserId, string $ownerUserId): array
{
    $viewerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $viewerUserId);
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);

    if ($viewerUserId === '' || $viewerUserId === $ownerUserId) {
        return array_values($messages);
    }

    $periods = resolve_membership_periods_for_user($chat, $viewerUserId);
    if ($periods === []) {
        return [];
    }

    return array_values(array_filter($messages, static function ($message) use ($periods) {
        if (!is_array($message)) {
            return false;
        }
        $messageAt = get_message_timestamp_for_membership($message);
        return is_message_visible_in_membership_periods($periods, $messageAt);
    }));
}

function enforce_chat_send_access_or_fail(array &$chat, string $ownerUserId, string $senderUserId): void
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $senderUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $senderUserId);

    if ($ownerUserId === '' || $senderUserId === '') {
        json_response([
                'ok' => false,
                'error' => 'not_joined',
                'message' => 'Not joined'
        ], 403);
    }

    $participantsChanged = normalize_chat_participants_for_owner($chat, $ownerUserId);
    $chat = hydrate_chat_participants_for_owner($chat, $ownerUserId);

    if ($participantsChanged) {
        save_chat_for_owner($chat, $ownerUserId);
    }

    // Owner is always allowed to send.
    if ($senderUserId === $ownerUserId) {
        return;
    }

    $periods = resolve_membership_periods_for_user($chat, $senderUserId);
    if (!is_membership_periods_active($periods)) {
        json_response([
                'ok' => false,
                'error' => 'not_joined',
                'message' => 'Not joined'
        ], 403);
    }
}

function get_chat_recent_chunk_files(array $chat, ?int $maxChunks = null): array
{
    $chunks = $chat['message_chunks'] ?? [];
    if (! is_array($chunks) || ! $chunks) {
        return [];
    }
    
    $maxChunks = $maxChunks ?? get_chat_initial_load_chunk_count();
    $maxChunks = max(1, $maxChunks);
    
    return array_values(array_slice($chunks, -$maxChunks));
}

function load_chat_recent_messages_for_owner(string $ownerUserId, array $chat, ?int $maxChunks = null): array
{
    $chunkFiles = get_chat_recent_chunk_files($chat, $maxChunks);
    return load_chat_messages_for_owner($ownerUserId, $chat, $chunkFiles);
}

function get_chat_previous_chunk_file(array $chat, string $beforeChunkFile): ?string
{
    $chunks = array_values($chat['message_chunks'] ?? []);
    if (! is_array($chunks) || ! $chunks) {
        return null;
    }
    
    $beforeChunkFile = basename($beforeChunkFile);
    $index = array_search($beforeChunkFile, $chunks, true);
    
    if ($index === false || $index <= 0) {
        return null;
    }
    
    return $chunks[$index - 1] ?? null;
}

function get_chat_chunk_file_by_message_id(string $ownerUserId, array $chat, string $messageId): ?string
{
    $messageId = trim($messageId);
    if ($messageId === '') {
        return null;
    }
    
    $chunks = array_values($chat['message_chunks'] ?? []);
    if (! is_array($chunks) || ! $chunks) {
        return null;
    }
    
    foreach ($chunks as $chunkFile) {
        $chatId = (string) ($chat['id'] ?? '');
        $path = get_chat_chunk_path_for_owner($ownerUserId, $chatId, (string) $chunkFile);
        if (! is_file($path)) {
            $legacyPath = get_chat_chunk_legacy_path_for_owner($ownerUserId, $chatId, (string) $chunkFile);
            $path = is_file($legacyPath) ? $legacyPath : $path;
        }

        if (! is_file($path)) {
            continue;
        }

        $chunkMessages = load_json_file($path, []);
        if (! is_array($chunkMessages) || ! $chunkMessages) {
            continue;
        }
        
        foreach ($chunkMessages as $msg) {
            if (trim((string) ($msg['id'] ?? '')) === $messageId) {
                return basename((string) $chunkFile);
            }
        }
    }

    return null;
}

function build_user_prompt_profile(array $userData): array
{
    $communicationProfileRaw = $userData['communication_profile'] ?? '';
    $communicationProfile = is_array($communicationProfileRaw) ? '' : trim((string) $communicationProfileRaw);
    if ($communicationProfile === '') {
        $communicationStyleRaw = $userData['communication_style'] ?? '';
        $communicationProfile = is_array($communicationStyleRaw) ? '' : trim((string) $communicationStyleRaw);
    }

    return [
            'id' => trim((string) ($userData['id'] ?? '')),
            'name' => trim((string) ($userData['name'] ?? '')),
            'email' => trim((string) ($userData['email'] ?? '')),
            'language' => trim((string) ($userData['language'] ?? '')),
            'default_language' => trim((string) ($userData['default_language'] ?? $userData['language'] ?? 'en')) ?: 'en',
            'known_languages' => array_values(array_filter(array_map('strval', $userData['known_languages'] ?? $userData['quick_languages'] ?? []))),
            'languages' => array_values($userData['languages'] ?? []),
            'country' => trim((string) ($userData['country'] ?? '')),
            'age_range' => trim((string) ($userData['age_range'] ?? '')),
            'gender' => trim((string) ($userData['gender'] ?? '')),
            'domain' => trim((string) ($userData['domain'] ?? '')),
            'communication_profile' => $communicationProfile
    ];
}

function build_conversation_prompt_context(array $chat, array $messages, array $userProfile, string $mode, array $input = []): array
{
    $userData = $userProfile['user'] ?? $userProfile;
    $userPromptProfile = build_user_prompt_profile($userData);

    $senderName = trim((string) ($input['incoming_sender_name'] ?? ''));

    if ($senderName === '') {
        for ($i = count($messages) - 1; $i >= 0; $i --) {
            $msg = $messages[$i] ?? [];
            $role = trim((string) ($msg['role'] ?? ''));
            $name = trim((string) ($msg['name'] ?? ''));

            if ($name !== '' && in_array($role, ['other', 'assistant', 'user'], true)) {
                $senderName = $name;
                break;
            }
        }
    }

    return [
            'user' => $userPromptProfile,
            'sender' => [
                    'name' => $senderName
            ],
            'mode' => $mode,
            'freetext' => [
                    'scope' => trim((string) ($input['freetext_scope'] ?? '')),
                    'current_message_text' => trim((string) ($input['current_message_text'] ?? ''))
            ],
            'chat' => [
                    'writing_personality' => trim((string) ($chat['writing_personality'] ?? ''))
            ],
            'selected_intent' => trim((string) ($input['selected_intent'] ?? '')),
            'message_direction' => trim((string) ($input['message_direction'] ?? 'incoming_reply'))
    ];
}

function save_user_profile_by_id(string $userId, array $profile): void
{
    if (! isset($profile['user']) || ! is_array($profile['user'])) {
        $profile['user'] = [];
    }

    $profile = normalize_user_profile_payload($profile, $userId);
    save_json_file(get_user_json_path_by_id($userId), $profile);
}

function load_current_user_profile(): array
{
    $userId = get_current_user_id();
    if (! $userId) {
        json_response([
                'error' => 'Not logged in'
        ], 401);
    }
    
    $profile = load_user_profile_by_id($userId);
    if (! $profile && function_exists('load_user_by_email')) {
        $email = get_current_user_email();
        if ($email) {
            $profile = load_user_by_email($email);
        }
    }
    if (! isset($profile['user']) || ! is_array($profile['user'])) {
        $profile['user'] = [
                'id' => $userId,
                'email' => get_current_user_email(),
                'name' => $_SESSION['user_name'] ?? 'User'
        ];
    }

    return normalize_user_profile_payload($profile, $userId);
}

function save_current_user_profile(array $profile): void
{
    $userId = get_current_user_id();
    if (! $userId) {
        json_response([
                'error' => 'Not logged in'
        ], 401);
    }
    save_user_profile_by_id($userId, $profile);
}

function load_user_profile_by_email_local(string $email): ?array
{
    $email = normalize_email_local($email);
    if (function_exists('load_user_by_email')) {
        $profile = load_user_by_email($email);
        if (is_array($profile) && ! empty($profile['user']['id'])) {
            return normalize_user_profile_payload($profile, (string) $profile['user']['id']);
        }
    }

    foreach (glob(get_users_root() . '/*') as $userDir) {
        if (! is_dir($userDir)) {
            continue;
        }

        $profile = load_json_file($userDir . '/user.json', []);
        if (! is_array($profile) || $profile === []) {
            $profile = load_json_file($userDir . '/profile.json', []);
        }
        if (normalize_email_local((string) ($profile['user']['email'] ?? '')) === $email) {
            return normalize_user_profile_payload($profile, (string) ($profile['user']['id'] ?? ''));
        }
    }

    return null;
}

function add_or_update_contact(array &$profile, string $name, string $email, string $userId = '', bool $verified = false): void
{
    $ownerUserId = trim((string) ($profile['user']['id'] ?? get_current_user_id() ?? ''));
    if ($ownerUserId === '') {
        return;
    }

    $payload = load_user_contacts_by_id($ownerUserId);
    $candidate = [
            'display_name' => $name,
            'email' => normalize_email_local($email),
            'user_id' => $userId,
            'verified' => $verified
    ];
    $existing = find_matching_contact_record($payload, $candidate);
    $contact = contacts_hydrate_record($candidate, $existing);
    if ($contact['display_name'] === '') {
        $contact['display_name'] = $name !== '' ? $name : $contact['email'];
    }

    $payload['contacts'][$contact['id']] = $contact;
    save_user_contacts_by_id($ownerUserId, $payload);

    unset($profile['contacts']);
}

function load_contacts_payload_for_owner(string $ownerUserId): array
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    if ($ownerUserId === '') {
        return contacts_empty_payload();
    }

    return load_user_contacts_by_id($ownerUserId);
}

function save_contacts_payload_for_owner(string $ownerUserId, array $payload): void
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    if ($ownerUserId === '') {
        return;
    }

    save_user_contacts_by_id($ownerUserId, $payload);
}

function resolve_contact_for_owner(string $ownerUserId, array $candidate, bool $createIfMissing = true): array
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    if ($ownerUserId === '') {
        return [];
    }

    $payload = load_contacts_payload_for_owner($ownerUserId);
    $existing = find_matching_contact_record($payload, $candidate);

    if (!empty($existing['id'])) {
        $resolved = contacts_hydrate_record($candidate, $existing);
        if ($resolved !== $existing) {
            $payload['contacts'][$resolved['id']] = $resolved;
            save_contacts_payload_for_owner($ownerUserId, $payload);
        }
        return $resolved;
    }

    if (!$createIfMissing) {
        return [];
    }

    $resolved = contacts_hydrate_record($candidate, []);
    if ($resolved['display_name'] === '') {
        $resolved['display_name'] = trim((string) ($candidate['display_name'] ?? $candidate['name'] ?? ''));
    }
    if ($resolved['display_name'] === '') {
        $resolved['display_name'] = $resolved['email'] !== '' ? $resolved['email'] : 'Contact';
    }
    $payload['contacts'][$resolved['id']] = $resolved;
    save_contacts_payload_for_owner($ownerUserId, $payload);

    return $resolved;
}

function resolve_contact_by_id_for_owner(string $ownerUserId, string $contactId): array
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $contactId = trim($contactId);
    if ($ownerUserId === '' || $contactId === '') {
        return [];
    }

    $payload = load_contacts_payload_for_owner($ownerUserId);
    $record = $payload['contacts'][$contactId] ?? [];

    // If not found by contact ID, try looking up by user_id
    if (empty($record['id'])) {
        foreach ($payload['contacts'] as $c) {
            if (is_array($c) && ($c['user_id'] ?? '') === $contactId) {
                $record = $c;
                break;
            }
        }
    }

    return is_array($record) ? $record : [];
}

function normalize_chat_participants_for_owner(array &$chat, string $ownerUserId): bool
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $changed = false;
    $participantIds = [];
    $snapshots = [];
    $chatCreatedAt = normalize_membership_period_timestamp($chat['created_at'] ?? now_str(), now_str());

    $rawParticipants = [];
    if (isset($chat['participants']) && is_array($chat['participants'])) {
        $rawParticipants = array_values($chat['participants']);
    } elseif (isset($chat['participant_ids']) && is_array($chat['participant_ids'])) {
        foreach (array_values($chat['participant_ids']) as $participantId) {
            $rawParticipants[] = [
                    'contact_id' => (string) $participantId,
                    'role' => 'member'
            ];
        }
    } elseif (isset($chat['participant_snapshots']) && is_array($chat['participant_snapshots'])) {
        foreach (array_keys($chat['participant_snapshots']) as $participantId) {
            $rawParticipants[] = [
                    'contact_id' => (string) $participantId,
                    'role' => (string) (($chat['participant_snapshots'][$participantId]['role'] ?? 'member')),
                    'membership_periods' => $chat['participant_snapshots'][$participantId]['membership_periods']
                        ?? $chat['participant_snapshots'][$participantId]['periods']
                        ?? []
            ];
        }
    }

    foreach ($rawParticipants as $participant) {
        if (! is_array($participant)) {
            continue;
        }

        $role = trim((string) ($participant['role'] ?? 'member')) ?: 'member';
        $contactId = trim((string) ($participant['contact_id'] ?? $participant['id'] ?? ''));
        $existingSnapshot = is_array($chat['participant_snapshots'][$contactId] ?? null)
            ? $chat['participant_snapshots'][$contactId]
            : [];
        $candidate = [
                'id' => $contactId,
                'display_name' => trim((string) ($participant['display_name'] ?? $participant['name'] ?? '')),
                'email' => trim((string) ($participant['email'] ?? '')),
                'user_id' => trim((string) ($participant['user_id'] ?? '')),
            'added_by_user_id' => trim((string) ($participant['added_by_user_id'] ?? '')),
                'verified' => ! empty($participant['verified']),
                'avatar' => trim((string) ($participant['avatar'] ?? '')),
                'preferred_language' => trim((string) ($participant['preferred_language'] ?? '')),
                'relation' => trim((string) ($participant['relation'] ?? '')),
                'tone' => $participant['tone'] ?? [],
                'topics' => $participant['topics'] ?? [],
                'notes' => trim((string) ($participant['notes'] ?? '')),
                'membership_periods' => $participant['membership_periods'] ?? $participant['periods'] ?? [],
                'status' => 'active'
        ];

        if ($contactId !== '') {
            $contact = resolve_contact_by_id_for_owner($ownerUserId, $contactId);
            if (empty($contact['id'])) {
                $contact = resolve_contact_for_owner($ownerUserId, $candidate, true);
            }
        } else {
            $contact = resolve_contact_for_owner($ownerUserId, $candidate, true);
            $changed = true;
        }

        if (empty($contact['id'])) {
            continue;
        }

        $resolvedId = (string) $contact['id'];
        $displayName = trim((string) ($contact['display_name'] ?? $contact['name'] ?? $candidate['display_name'] ?? $candidate['email'] ?? ''));
        $membershipPeriods = ensure_member_periods_from_legacy($participant, $existingSnapshot, $chatCreatedAt);
        if ($role === 'owner' && !is_membership_periods_active($membershipPeriods)) {
            $membershipPeriods[] = [
                'start_at' => $chatCreatedAt,
                'end_at' => null,
            ];
            $membershipPeriods = normalize_membership_periods($membershipPeriods);
        }
        $existingMembershipPeriods = normalize_membership_periods(
            $existingSnapshot['membership_periods'] ?? $existingSnapshot['periods'] ?? []
        );
        if ($existingMembershipPeriods !== $membershipPeriods) {
            $changed = true;
        }
        $snapshot = [
                'id' => $resolvedId,
                'display_name' => $displayName,
                'name' => $displayName,
                'initials' => trim((string) ($contact['initials'] ?? make_initials($displayName !== '' ? $displayName : $candidate['email']))),
                'email' => trim((string) ($contact['email'] ?? $candidate['email'] ?? '')),
                'avatar' => trim((string) ($contact['avatar'] ?? $candidate['avatar'] ?? '')),
            'profile_version' => (int) ($contact['profile_version'] ?? $candidate['profile_version'] ?? 0),
            'user_id' => trim((string) ($contact['user_id'] ?? $candidate['user_id'] ?? '')),
            'added_by_user_id' => trim((string) ($contact['added_by_user_id'] ?? $existingSnapshot['added_by_user_id'] ?? $candidate['added_by_user_id'] ?? '')),
            'verified' => ! empty($contact['verified']) || ! empty($candidate['verified']),
            'membership_periods' => $membershipPeriods,
                'role' => $role
        ];

        $participantIds[] = $resolvedId;
        $snapshots[$resolvedId] = $snapshot;

        if ($contactId !== $resolvedId) {
            $changed = true;
        }
    }

    $chat['participant_ids'] = array_values(array_unique($participantIds));
    $chat['participant_snapshots'] = $snapshots;
    unset($chat['participants']);
    unset($chat['participant_names']);

    return $changed;
}

function hydrate_chat_participants_for_owner(array $chat, string $ownerUserId): array
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $chatCreatedAt = normalize_membership_period_timestamp($chat['created_at'] ?? now_str(), now_str());
    $participants = [];
    $participantNames = [];
    $participantIds = array_values(array_filter(array_map('trim', array_map('strval', $chat['participant_ids'] ?? [])), function ($value) {
        return $value !== '';
    }));
    $snapshots = is_array($chat['participant_snapshots'] ?? null) ? $chat['participant_snapshots'] : [];

    foreach ($participantIds as $participantId) {
        $snapshot = is_array($snapshots[$participantId] ?? null) ? $snapshots[$participantId] : [];
        $contact = $participantId !== '' ? resolve_contact_by_id_for_owner($ownerUserId, $participantId) : [];
        if ($participantId !== '' && empty($contact['id']) && ! empty($snapshot)) {
            $contact = resolve_contact_for_owner($ownerUserId, [
                    'id' => $participantId,
                    'display_name' => trim((string) ($snapshot['display_name'] ?? $snapshot['name'] ?? '')),
                    'email' => trim((string) ($snapshot['email'] ?? '')),
                    'avatar' => trim((string) ($snapshot['avatar'] ?? '')),
                    'user_id' => trim((string) ($snapshot['user_id'] ?? '')),
                    'verified' => ! empty($snapshot['verified'])
            ], false);
        }

        $displayName = trim((string) ($snapshot['display_name'] ?? $snapshot['name'] ?? $contact['display_name'] ?? $contact['name'] ?? ''));
        $email = trim((string) ($snapshot['email'] ?? $contact['email'] ?? ''));
        $initials = trim((string) ($snapshot['initials'] ?? '')) ?: make_initials($displayName !== '' ? $displayName : $email);

        $hydrated = [
                'contact_id' => $participantId,
                'role' => trim((string) ($snapshot['role'] ?? 'member')) ?: 'member',
                'display_name' => $displayName,
                'name' => $displayName,
                'email' => $email,
                'initials' => $initials,
                'avatar' => trim((string) ($snapshot['avatar'] ?? $contact['avatar'] ?? '')),
            'profile_version' => (int) ($snapshot['profile_version'] ?? $contact['profile_version'] ?? 0),
                'user_id' => trim((string) ($contact['user_id'] ?? $snapshot['user_id'] ?? '')),
                'added_by_user_id' => trim((string) ($snapshot['added_by_user_id'] ?? $contact['added_by_user_id'] ?? '')),
                'verified' => ! empty($contact['verified']) || ! empty($snapshot['verified']),
                'membership_periods' => normalize_membership_periods($snapshot['membership_periods'] ?? $snapshot['periods'] ?? []),
                'preferred_language' => trim((string) ($contact['preferred_language'] ?? $snapshot['preferred_language'] ?? '')),
                'relation' => trim((string) ($contact['relation'] ?? $snapshot['relation'] ?? '')),
                'tone' => $contact['tone'] ?? $snapshot['tone'] ?? [],
                'topics' => $contact['topics'] ?? $snapshot['topics'] ?? [],
                'notes' => trim((string) ($contact['notes'] ?? $snapshot['notes'] ?? ''))
        ];

        if (($hydrated['role'] ?? '') === 'owner' && !is_membership_periods_active($hydrated['membership_periods'] ?? [])) {
            $hydrated['membership_periods'][] = [
                'start_at' => $chatCreatedAt,
                'end_at' => null,
            ];
            $hydrated['membership_periods'] = normalize_membership_periods($hydrated['membership_periods']);
            if (isset($snapshots[$participantId]) && is_array($snapshots[$participantId])) {
                $snapshots[$participantId]['membership_periods'] = $hydrated['membership_periods'];
            }
        }

        $participants[] = $hydrated;

        if ($displayName !== '') {
            $participantNames[] = $displayName;
        }
    }

    $chat['participants'] = $participants;
    $chat['participant_ids'] = $participantIds;
    $chat['participant_snapshots'] = $snapshots;
    $chat['participant_names'] = array_values(array_unique($participantNames));

    return $chat;
}

function get_chat_member_added_by_user_id(array $chat, string $memberId): string
{
    $memberId = trim($memberId);
    if ($memberId === '') {
        return '';
    }

    $participants = is_array($chat['participants'] ?? null) ? $chat['participants'] : [];
    foreach ($participants as $participant) {
        if (! is_array($participant)) {
            continue;
        }

        $candidateIds = [
                trim((string) ($participant['contact_id'] ?? '')),
                trim((string) ($participant['id'] ?? '')),
                trim((string) ($participant['user_id'] ?? ''))
        ];

        if (! in_array($memberId, $candidateIds, true)) {
            continue;
        }

        return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($participant['added_by_user_id'] ?? ''));
    }

    $snapshots = is_array($chat['participant_snapshots'] ?? null) ? $chat['participant_snapshots'] : [];
    $snapshot = is_array($snapshots[$memberId] ?? null) ? $snapshots[$memberId] : [];
    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($snapshot['added_by_user_id'] ?? ''));
}

function current_user_can_add_members_to_chat(array $chat): bool
{
    $profile = load_current_user_profile();
    if (is_invited_member_profile($profile)) {
        return false;
    }

    $chatId = sanitize_chat_id((string) ($chat['id'] ?? ''));
    if ($chatId === '') {
        return false;
    }

    return (bool) resolve_chat_reference_for_current_user($chatId);
}

function current_user_can_manage_chat_member(array $chat, string $memberId): bool
{
    $profile = load_current_user_profile();
    if (is_invited_member_profile($profile)) {
        return false;
    }

    $currentUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) get_current_user_id());
    if ($currentUserId === '') {
        return false;
    }

    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($chat['owner_user_id'] ?? ''));
    if ($ownerUserId !== '' && $ownerUserId === $currentUserId) {
        return true;
    }

    $addedByUserId = get_chat_member_added_by_user_id($chat, $memberId);
    return $addedByUserId !== '' && $addedByUserId === $currentUserId;
}

function upsert_member_chat_ref(array &$profile, array $ref): void
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($profile['user']['id'] ?? get_current_user_id() ?? ''));
    $chatId = sanitize_chat_id((string) ($ref['chat_id'] ?? $ref['id'] ?? ''));
    if ($userId === '' || $chatId === '') {
        return;
    }
    $ref['chat_id'] = $chatId;
    $ref['id'] = $chatId;
    $ref['updated_at'] = $ref['updated_at'] ?? now_str();
    $ref = ensure_chat_ref_defaults(normalize_chat_index_item($ref, $userId));

    $index = load_chats_index_by_owner($userId);
    $found = false;
    foreach ($index as &$item) {
        if (($item['id'] ?? '') === $chatId && ($item['owner_user_id'] ?? '') === ($ref['owner_user_id'] ?? '')) {
            $item = array_merge($item, $ref);
            $found = true;
            break;
        }
    }
    unset($item);
    if (! $found) {
        $index[] = $ref;
    }

    save_chats_index_by_owner($userId, $index);
}

function remove_pending_invite_from_profile(array &$profile, string $inviteId): void
{
    $inviteId = sanitize_invite_id($inviteId);
    $profile['pending_invites'] = array_values(array_filter($profile['pending_invites'] ?? [], function ($item) use ($inviteId) {
        return (($item['invite_id'] ?? '') !== $inviteId);
    }));
}

function add_pending_invite_to_profile(array &$profile, array $pending): void
{
    $pendingId = sanitize_invite_id((string) ($pending['invite_id'] ?? ''));
    if ($pendingId === '') {
        return;
    }
    foreach ($profile['pending_invites'] ?? [] as $item) {
        if (($item['invite_id'] ?? '') === $pendingId) {
            return;
        }
    }
    $profile['pending_invites'][] = $pending;
}

function ensure_chat_ref_defaults(array $item): array
{
    $item['id'] = sanitize_chat_id((string) ($item['id'] ?? $item['chat_id'] ?? ''));
    if ($item['id'] === '') {
        $item['id'] = sanitize_chat_id((string) ($item['chat_id'] ?? ''));
    }
    $item['chat_id'] = $item['id'];
    if (! isset($item['unread_count'])) {
        $item['unread_count'] = 0;
    } else {
        $item['unread_count'] = max(0, (int) $item['unread_count']);
    }
    
    if (! isset($item['last_read_at'])) {
        $item['last_read_at'] = '';
    }
    
    if (empty($item['state']) || ! in_array((string) $item['state'], [
            'active',
            'archived',
            'left'
    ], true)) {
        $item['state'] = 'active';
    }
    
    return $item;
}

function load_chat_index_for_user(string $userId): array
{
    return load_chats_index_by_owner($userId);
}

function save_chat_index_for_user(string $userId, array $index): void
{
    save_chats_index_by_owner($userId, $index);
}

function remove_chat_index_entry_for_user(string $userId, string $chatId, string $ownerUserId = ''): bool
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    $chatId = sanitize_chat_id($chatId);
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    if ($userId === '' || $chatId === '') {
        return false;
    }

    $index = load_chat_index_for_user($userId);
    $before = count($index);
    $index = array_values(array_filter($index, function ($item) use ($chatId, $ownerUserId) {
        $item = ensure_chat_ref_defaults(is_array($item) ? $item : []);
        if (($item['id'] ?? '') !== $chatId) {
            return true;
        }
        if ($ownerUserId !== '' && ($item['owner_user_id'] ?? '') !== $ownerUserId) {
            return true;
        }
        return false;
    }));

    if ($before !== count($index)) {
        save_chat_index_for_user($userId, $index);
        return true;
    }

    return false;
}

function find_chat_index_entry_for_user(string $userId, string $chatId): ?array
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    $chatId = sanitize_chat_id($chatId);
    if ($userId === '' || $chatId === '') {
        return null;
    }

    foreach (load_chat_index_for_user($userId) as $item) {
        $item = ensure_chat_ref_defaults(is_array($item) ? $item : []);
        if (($item['id'] ?? '') === $chatId) {
            return $item;
        }
    }

    return null;
}

function mark_chat_as_read_for_current_user(string $chatId): void
{
    $chatId = sanitize_chat_id($chatId);
    if ($chatId === '') {
        return;
    }
    $currentUserId = (string) get_current_user_id();
    if ($currentUserId === '') {
        return;
    }

    $index = load_chat_index_for_user($currentUserId);
    $changed = false;
    
    foreach ($index as &$item) {
        if (($item['id'] ?? '') === $chatId) {
            $item = ensure_chat_ref_defaults($item);
            if ((int) ($item['unread_count'] ?? 0) !== 0 || ($item['last_read_at'] ?? '') === '') {
                $item['unread_count'] = 0;
                $item['last_read_at'] = now_str();
                $changed = true;
            }
            break;
        }
    }
    unset($item);
    
    if ($changed) {
        save_chat_index_for_user($currentUserId, $index);
    }
}

function increment_unread_for_other_participants(array $chat, string $senderUserId): void
{
    $chatId = sanitize_chat_id((string) ($chat['id'] ?? ''));
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($chat['owner_user_id'] ?? ''));
    $senderUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $senderUserId);
    
    if ($chatId === '' || $ownerUserId === '' || $senderUserId === '') {
        return;
    }
    
    if ($ownerUserId !== $senderUserId) {
        $ownerIndex = load_chats_index_by_owner($ownerUserId);
        $changed = false;
        
        foreach ($ownerIndex as &$item) {
            if (($item['id'] ?? '') === $chatId) {
                $item = ensure_chat_ref_defaults($item);
                $item['unread_count'] = (int) ($item['unread_count'] ?? 0) + 1;
                $changed = true;
                break;
            }
        }
        unset($item);
        
        if ($changed) {
            save_chats_index_by_owner($ownerUserId, $ownerIndex);
        }
    }
    
    $seen = [];
    foreach (($chat['participants'] ?? []) as $participant) {
        $participantContactId = trim((string) ($participant['contact_id'] ?? ''));
        $participantContact = $participantContactId !== '' ? resolve_contact_by_id_for_owner($ownerUserId, $participantContactId) : [];
        $participantUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($participantContact['user_id'] ?? ''));
        if ($participantUserId === '' || $participantUserId === $senderUserId || $participantUserId === $ownerUserId || isset($seen[$participantUserId])) {
            continue;
        }
        $seen[$participantUserId] = true;

        $index = load_chat_index_for_user($participantUserId);
        $changed = false;

        foreach ($index as &$refItem) {
            if ((($refItem['id'] ?? '') === $chatId) && (($refItem['owner_user_id'] ?? '') === $ownerUserId)) {
                $refItem = ensure_chat_ref_defaults($refItem);
                $refItem['unread_count'] = (int) ($refItem['unread_count'] ?? 0) + 1;
                $changed = true;
                break;
            }
        }
        unset($refItem);

        if ($changed) {
            save_chat_index_for_user($participantUserId, $index);
        }
    }
}

function get_chats_for_current_user_by_state(string $wantedState = 'active'): array
{
    $wantedState = in_array($wantedState, [
            'active',
            'archived'
    ], true) ? $wantedState : 'active';
    $currentUserId = (string) get_current_user_id();
    $sessionToken = trim((string) ($_SESSION['guest_member_token'] ?? ''));
    $isInvitedMemberUser = is_invited_member_user_id($currentUserId);

    if (! $isInvitedMemberUser) {
        unset($_SESSION['guest_member_token'], $_SESSION['guest_owner_user_id'], $_SESSION['guest_contact_id']);
        $sessionToken = '';
    }

    $effectiveOwnerForItem = static function (array $item, string $fallbackOwnerUserId): string {
        $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['owner_user_id'] ?? ''));
        return $ownerUserId !== '' ? $ownerUserId : $fallbackOwnerUserId;
    };

    $owned = load_chat_index_for_user($currentUserId);
    
    $result = [];
    
    foreach ($owned as $item) {
        $item = ensure_chat_ref_defaults($item);
        $chatId = sanitize_chat_id((string) ($item['id'] ?? ''));
        $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['owner_user_id'] ?? $currentUserId));
        $chat = ($chatId !== '' && $ownerUserId !== '') ? load_chat_for_owner($ownerUserId, $chatId) : null;
        if ($chatId === '' || ! is_array($chat)) {
            cleanup_orphan_chat_reference_for_current_user($chatId);
            continue;
        }
        $item['is_owner'] = $ownerUserId === $currentUserId;
        $item['member_of'] = $item['is_owner'] ? '' : $ownerUserId;
        $item['participant_ids'] = $chat['participant_ids'] ?? [];
        $item['participant_snapshots'] = $chat['participant_snapshots'] ?? [];
        $item['participants'] = $chat['participants'] ?? [];
        $item['title'] = (string) ($chat['title'] ?? $item['title'] ?? 'New chat');
        $item['updated_at'] = (string) ($chat['updated_at'] ?? $item['updated_at'] ?? '');
        remove_persisted_assistant_messages($chat);
        $item['preview'] = build_chat_preview($chat['messages'] ?? []);
        $item['participant_names'] = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $chat['participant_names'] ?? [])))));
        $item = ensure_chat_ref_defaults($item);
        if (($item['state'] ?? 'active') !== $wantedState) {
            continue;
        }
        $result[] = $item;
    }

    if ($isInvitedMemberUser && $sessionToken !== '') {
        $resolved = find_guest_membership_by_token($sessionToken);
        $resolvedChatId = sanitize_chat_id((string) ($resolved['chat_id'] ?? ''));
        $resolvedOwnerId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($resolved['owner_user_id'] ?? ''));

        if ($resolvedChatId !== '' && $resolvedOwnerId !== '') {
            $exists = false;
            foreach ($result as $item) {
                $itemChatId = sanitize_chat_id((string) ($item['id'] ?? ''));
                $itemOwnerId = $effectiveOwnerForItem($item, $currentUserId);
                if ($itemChatId === $resolvedChatId && $itemOwnerId === $resolvedOwnerId) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $chat = load_chat_for_owner($resolvedOwnerId, $resolvedChatId);
                if (is_array($chat)) {
                    $item = ensure_chat_ref_defaults([
                            'id' => $resolvedChatId,
                            'chat_id' => $resolvedChatId,
                            'owner_user_id' => $resolvedOwnerId,
                            'title' => (string) ($chat['title'] ?? 'Shared chat'),
                            'updated_at' => (string) ($chat['updated_at'] ?? now_str()),
                            'last_read_at' => '',
                            'unread_count' => 0,
                            'state' => 'active',
                            'preview' => build_chat_preview($chat['messages'] ?? [])
                    ]);

                    $item['is_owner'] = false;
                    $item['member_of'] = $resolvedOwnerId;
                    $item['participant_ids'] = $chat['participant_ids'] ?? [];
                    $item['participant_snapshots'] = $chat['participant_snapshots'] ?? [];
                    $item['participants'] = $chat['participants'] ?? [];
                    $item['participant_names'] = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $chat['participant_names'] ?? [])))));

                    if (($item['state'] ?? 'active') === $wantedState) {
                        $result[] = $item;
                    }
                }
            }
        }
    }
    
    usort($result, function ($a, $b) {
        return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
    });
        
    return $result;
}

function remove_persisted_assistant_messages(array &$chat): void
{
    $chat['messages'] = array_values(array_filter(($chat['messages'] ?? []), function ($msg) {
        return (($msg['role'] ?? '') !== 'assistant');
    }));
}

function build_chat_preview(array $messages): string
{
    $messages = array_values($messages);
    
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        $msg = $messages[$i] ?? [];
        $role = (string) ($msg['role'] ?? '');
        $text = trim((string) ($msg['content'] ?? $msg['text'] ?? ''));
        
        if ($text === '') {
            continue;
        }
        
        if (! in_array($role, ['user', 'other'], true)) {
            continue;
        }
        
        $text = preg_replace('/\s+/', ' ', $text);
        return mb_strlen($text) > 80 ? mb_substr($text, 0, 80) . '…' : $text;
    }
    
    return 'No messages yet';
}

function get_visible_chats_for_current_user(): array
{
    return get_chats_for_current_user_by_state('active');
}

function get_archived_chats_for_current_user(): array
{
    return get_chats_for_current_user_by_state('archived');
}

function resolve_chat_reference_for_current_user(string $chatId): ?array
{
    $chatId = sanitize_chat_id($chatId);
    if ($chatId === '') {
        return null;
    }
    
    $currentUserId = (string) get_current_user_id();
    $ref = find_chat_index_entry_for_user($currentUserId, $chatId);
    if (! $ref) {
        $ownerUserId = '';

        $sessionToken = trim((string) ($_SESSION['guest_member_token'] ?? ''));
        if ($sessionToken !== '') {
            $resolved = find_guest_membership_by_token($sessionToken);
            $resolvedChatId = sanitize_chat_id((string) ($resolved['chat_id'] ?? ''));
            if ($resolvedChatId === $chatId) {
                $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($resolved['owner_user_id'] ?? ''));
            }
        }

        if ($ownerUserId === '') {
            $ownerRef = find_chat_owner_for_chat_id($chatId);
            $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($ownerRef['owner_user_id'] ?? ''));
        }

        if ($ownerUserId === '') {
            return null;
        }

        $path = get_chat_path_for_owner($ownerUserId, $chatId);
        if (! file_exists($path)) {
            $path = get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chatId . '.json';
        }

        if (! file_exists($path)) {
            return null;
        }

        return [
                'owner_user_id' => $ownerUserId,
                'path' => $path,
                'is_owner' => $ownerUserId === $currentUserId
        ];
    }

    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($ref['owner_user_id'] ?? ''));
    if ($ownerUserId === '') {
        $ownerUserId = $currentUserId;
    }

    $path = get_chat_path_for_owner($ownerUserId, $chatId);
    if (! file_exists($path)) {
        return null;
    }

    return [
            'owner_user_id' => $ownerUserId,
            'path' => $path,
            'is_owner' => $ownerUserId === $currentUserId
    ];
}

function load_visible_chat_for_current_user(string $chatId): ?array
{
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        return null;
    }
    return load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
}

function load_chat_for_owner(string $ownerUserId, string $chatId): ?array
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $chatId = sanitize_chat_id($chatId);

    if ($ownerUserId === '' || $chatId === '') {
        return null;
    }

    $path = get_chat_path_for_owner($ownerUserId, $chatId);
    $legacyPath = get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chatId . '.json';

    $chat = null;
    $isLegacy = false;

    if (is_file($path)) {
        $chat = load_json_file($path, []);
    } elseif (is_file($legacyPath)) {
        $chat = load_json_file($legacyPath, []);
        $isLegacy = true;
    }

    if (! is_array($chat) || $chat === []) {
        return null;
    }

    $chat['id'] = sanitize_chat_id((string) ($chat['id'] ?? $chatId));
    $chat['owner_user_id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($chat['owner_user_id'] ?? $ownerUserId));
    $chat['type'] = (string) ($chat['type'] ?? 'assistant');
    $chat['title'] = trim((string) ($chat['title'] ?? '')) ?: 'New chat';
    $chat['created_at'] = trim((string) ($chat['created_at'] ?? now_str()));
    $chat['updated_at'] = trim((string) ($chat['updated_at'] ?? now_str()));

    $participantsChanged = normalize_chat_participants_for_owner($chat, $ownerUserId);
    $chat = hydrate_chat_participants_for_owner($chat, $ownerUserId);

    if (isset($chat['messages']) && is_array($chat['messages']) && $chat['messages']) {
        if ($isLegacy || empty($chat['message_chunks']) || $participantsChanged) {
            save_chat_for_owner($chat, $ownerUserId);
        }
        return $chat;
    }

    $recentMessages = load_chat_messages_for_owner($ownerUserId, $chat);
    $chat['messages'] = is_array($recentMessages) ? $recentMessages : [];

    ensure_chat_message_chunks($chat);

    if ($isLegacy || $participantsChanged) {
        save_chat_for_owner($chat, $ownerUserId);
    }

    return $chat;
}

function save_chat_for_owner(array $chat, string $ownerUserId): void
{
    if (empty($chat['id'])) {
        json_response([
                'error' => 'Missing chat id'
        ], 500);
    }
    
    $chat['id'] = sanitize_chat_id((string) $chat['id']);
    $chat['updated_at'] = now_str();
    if (! isset($chat['messages']) || ! is_array($chat['messages'])) {
        $chat['messages'] = [];
    }
    if (empty($chat['title'])) {
        $chat['title'] = 'New chat';
    }
    if (empty($chat['owner_user_id'])) {
        $chat['owner_user_id'] = $ownerUserId;
    }
    if (! isset($chat['participants']) || ! is_array($chat['participants'])) {
        $chat['participants'] = [];
    }

    normalize_chat_participants_for_owner($chat, $ownerUserId);
    
    $messages = array_values($chat['messages']);
    $chunkSize = max(1, get_chat_chunk_max_messages());
    $messageChunks = [];
    $chunkGroups = array_chunk($messages, $chunkSize);
    
    if (! $chunkGroups) {
        $chunkGroups = [[]];
    }
    
    foreach ($chunkGroups as $index => $chunkMessages) {
        $chunkFile = get_chat_chunk_basename($chat['id'], $index + 1);
        save_json_file(get_chat_chunk_path_for_owner($ownerUserId, $chat['id'], $chunkFile), array_values($chunkMessages));
        $messageChunks[] = $chunkFile;
    }
    
    cleanup_chat_chunk_files_for_owner($ownerUserId, $chat['id'], $messageChunks);
    
    $meta = $chat;
    unset($meta['messages']);
    unset($meta['participant_names']);
    $meta['message_chunks'] = $messageChunks;
    
    save_json_file(get_chat_path_for_owner($ownerUserId, $chat['id']), $meta);
    
    $index = load_chats_index_by_owner($ownerUserId);
    $found = false;
    foreach ($index as &$item) {
        if (($item['id'] ?? '') === $chat['id']) {
            $item = ensure_chat_ref_defaults($item);
            $item['title'] = $chat['title'];
            $item['updated_at'] = $chat['updated_at'];
            $item['participant_ids'] = $chat['participant_ids'] ?? [];
            $item['participant_snapshots'] = $chat['participant_snapshots'] ?? [];
            $found = true;
            break;
        }
    }
    unset($item);
    if (! $found) {
        $index[] = ensure_chat_ref_defaults([
                'id' => $chat['id'],
                'chat_id' => $chat['id'],
                'title' => $chat['title'],
                'updated_at' => $chat['updated_at'],
                'last_read_at' => now_str(),
                'unread_count' => 0,
                'state' => $chat['state'],
                'preview' => build_chat_preview($messages),
                'participant_ids' => $chat['participant_ids'] ?? [],
                'participant_snapshots' => $chat['participant_snapshots'] ?? []
        ]);
    }

    usort($index, function ($a, $b) {
        return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
    });
    save_chat_index_for_user($ownerUserId, $index);
}

function cleanup_orphan_chat_reference_for_current_user(string $chatId): bool
{
    $chatId = sanitize_chat_id($chatId);
    if ($chatId === '') {
        return false;
    }
    
    $currentUserId = (string) get_current_user_id();
    if ($currentUserId === '') {
        return false;
    }
    
    if (remove_chat_ref_from_owner_index($currentUserId, $chatId)) {
        return true;
    }
    
    $entry = find_chat_index_entry_for_user($currentUserId, $chatId);
    if (is_array($entry) && ! empty($entry['owner_user_id'])) {
        return remove_chat_ref_from_member_profile($currentUserId, (string) $entry['owner_user_id'], $chatId);
    }
    
    return false;
}

function cleanup_orphan_chat_reference_for_user(string $ownerUserId, string $chatId): bool
{
    $chatId = sanitize_chat_id($chatId);
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    if ($chatId === '' || $ownerUserId === '') {
        return false;
    }
    
    $changed = remove_chat_ref_from_owner_index($ownerUserId, $chatId);
    
    foreach (glob(get_users_root() . '/*') as $userDir) {
        if (! is_dir($userDir)) {
            continue;
        }
        $memberUserId = resolve_user_id_from_dir($userDir);
        if ($memberUserId === '') {
            continue;
        }
        $changed = remove_chat_ref_from_member_profile($memberUserId, $ownerUserId, $chatId) || $changed;
    }
    
    return $changed;
}

function create_new_chat($title = ''): array
{
    global $app_base_url;
    
    require_login();

        $currentProfile = load_current_user_profile();
        if (is_invited_member_profile($currentProfile)) {
            json_response([
                    'error' => 'Invited members cannot create new chats until they sign up.'
            ], 403);
        }
    
    $chatId = bin2hex(random_bytes(8));
    $time = now_str();
    $currentProfile = load_current_user_profile();
    $currentUser = $currentProfile['user'] ?? [];
        $ownerContact = resolve_contact_for_owner((string) get_current_user_id(), [
            'display_name' => (string) ($currentUser['name'] ?? ($_SESSION['user_name'] ?? 'User')),
            'email' => (string) ($currentUser['email'] ?? get_current_user_email()),
            'user_id' => (string) get_current_user_id(),
            'verified' => !empty($currentUser['email_verified'])
        ], true);
    
    $chat = [
            'id' => $chatId,
            'type' => 'assistant',
            'title' => $title ?: 'New chat',
            'created_at' => $time,
            'updated_at' => $time,
            'owner_user_id' => (string) get_current_user_id(),
            'participants' => [
                    [
                    'contact_id' => (string) ($ownerContact['id'] ?? ''),
                            'role' => 'owner'
                    ]
            ],
            'message_chunks' => [get_chat_chunk_basename($chatId, 1)]
    ];
    
    save_chat_for_owner($chat, (string) get_current_user_id());
    $_SESSION['current_chat_id'] = $chatId;

    $hydrated = load_chat_for_owner((string) get_current_user_id(), $chatId);
    return is_array($hydrated) ? $hydrated : $chat;
}

function archive_chat_by_id(string $chatId): ?string
{
    $chatId = sanitize_chat_id($chatId);
    $ref = resolve_chat_reference_for_current_user($chatId);
    
    if (! $ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            $visible = get_visible_chats_for_current_user();
            $newCurrent = $visible[0]['id'] ?? null;
            $_SESSION['current_chat_id'] = $newCurrent;
            return $newCurrent;
        }
        maybe_chat_not_found('Chat not found');
    }
    
    if (! empty($ref['is_owner'])) {
        $ownerUserId = (string) $ref['owner_user_id'];
        $index = load_chats_index_by_owner($ownerUserId);
        
        foreach ($index as &$item) {
            if (($item['id'] ?? '') === $chatId) {
                $item = ensure_chat_ref_defaults($item);
                $item['state'] = 'archived';
                $item['unread_count'] = 0;
                $item['last_read_at'] = now_str();
                break;
            }
        }
        unset($item);
        
        save_chats_index_by_owner($ownerUserId, $index);
    } else {
        $currentUserId = (string) get_current_user_id();
        $index = load_chat_index_for_user($currentUserId);
        foreach ($index as &$item) {
            if ((($item['id'] ?? '') === $chatId) && (($item['owner_user_id'] ?? '') === ($ref['owner_user_id'] ?? ''))) {
                $item = ensure_chat_ref_defaults($item);
                $item['state'] = 'archived';
                $item['unread_count'] = 0;
                $item['last_read_at'] = now_str();
                break;
            }
        }
        unset($item);
        save_chat_index_for_user($currentUserId, $index);
    }
    
    $visible = get_visible_chats_for_current_user();
    $newCurrent = $visible[0]['id'] ?? null;
    $_SESSION['current_chat_id'] = $newCurrent;
    return $newCurrent;
}

function permanently_delete_chat_by_id(string $chatId): ?string
{
    $chatId = sanitize_chat_id($chatId);
    if ($chatId === '') {
        maybe_chat_not_found('Chat not found');
    }
    
    $currentUserId = (string) get_current_user_id();
    
    $ownerIndex = load_chats_index_by_owner($currentUserId);
    $ownerFound = false;
    
    foreach ($ownerIndex as $k => $item) {
        $item = ensure_chat_ref_defaults($item);
        if (($item['id'] ?? '') === $chatId) {
            $ownerFound = true;
            unset($ownerIndex[$k]);
            break;
        }
    }
    
    if ($ownerFound) {
        $chatPath = get_chat_path_for_owner($currentUserId, $chatId);
        if (file_exists($chatPath)) {
            unlink($chatPath);
        }
        cleanup_chat_chunk_files_for_owner($currentUserId, $chatId, []);
        
        save_chats_index_by_owner($currentUserId, array_values($ownerIndex));
        
        $visible = get_visible_chats_for_current_user();
        $newCurrent = $visible[0]['id'] ?? null;
        $_SESSION['current_chat_id'] = $newCurrent;
        return $newCurrent;
    }
    
    $currentUserId = (string) get_current_user_id();
    $memberIndex = load_chat_index_for_user($currentUserId);
    $before = count($memberIndex);
    $memberIndex = array_values(array_filter($memberIndex, function ($item) use ($chatId) {
        $item = ensure_chat_ref_defaults(is_array($item) ? $item : []);
        return ($item['id'] ?? '') !== $chatId;
    }));

    if ($before !== count($memberIndex)) {
        save_chat_index_for_user($currentUserId, $memberIndex);

        $visible = get_visible_chats_for_current_user();
        $newCurrent = $visible[0]['id'] ?? null;
        $_SESSION['current_chat_id'] = $newCurrent;
        return $newCurrent;
    }
    
    if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
        $visible = get_visible_chats_for_current_user();
        $newCurrent = $visible[0]['id'] ?? null;
        $_SESSION['current_chat_id'] = $newCurrent;
        return $newCurrent;
    }
    
    maybe_chat_not_found('Chat not found');
}

function leave_chat_by_id(string $chatId): ?string
{
    $chatId = sanitize_chat_id($chatId);
    $ref = resolve_chat_reference_for_current_user($chatId);
    
    if (! $ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            $visible = get_visible_chats_for_current_user();
            $newCurrent = $visible[0]['id'] ?? null;
            $_SESSION['current_chat_id'] = $newCurrent;
            return $newCurrent;
        }
        maybe_chat_not_found('Chat not found');
    }
    
    if (! empty($ref['is_owner'])) {
        json_response([
                'error' => 'Owner cannot leave their own chat'
        ], 403);
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    
    if ($chat) {
        $currentUserId = (string) get_current_user_id();
        $memberName = $_SESSION['user_name'] ?? 'Someone';
        $ownerUserId = (string) $ref['owner_user_id'];
        
        $chat['participants'] = array_values(array_filter($chat['participants'] ?? [], function ($participant) use ($currentUserId, $ownerUserId) {
            $contactId = trim((string) ($participant['contact_id'] ?? ''));
            if ($contactId === '') {
                return true;
            }

            $contact = resolve_contact_by_id_for_owner($ownerUserId, $contactId);
            return (string) ($contact['user_id'] ?? '') !== $currentUserId;
        }));
            
            $chat['messages'][] = [
                    'role' => 'join_note',
                    'content' => $memberName . ' left this chat',
                    'time' => now_str()
            ];
            
            save_chat_for_owner($chat, (string) $ref['owner_user_id']);
    }
    
    $currentUserId = (string) get_current_user_id();
    $index = load_chat_index_for_user($currentUserId);
    $index = array_values(array_filter($index, function ($item) use ($chatId, $ref) {
        $item = ensure_chat_ref_defaults(is_array($item) ? $item : []);
        return ! ((($item['id'] ?? '') === $chatId) && (($item['owner_user_id'] ?? '') === ($ref['owner_user_id'] ?? '')));
    }));
    save_chat_index_for_user($currentUserId, $index);

    $visible = get_visible_chats_for_current_user();
    $newCurrent = $visible[0]['id'] ?? null;
    $_SESSION['current_chat_id'] = $newCurrent;
    return $newCurrent;
}

function remove_chat_participant_by_contact_id(string $chatId, string $contactId): array
{
    $chatId = sanitize_chat_id($chatId);
    $contactId = trim($contactId);

    if ($chatId === '' || $contactId === '') {
        json_response([
                'error' => 'Missing parameters'
        ], 400);
    }

    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        maybe_chat_not_found('Chat not found');
    }

    if (empty($ref['is_owner'])) {
        json_response([
                'error' => 'Only the owner can remove participants from this chat.'
        ], 403);
    }

    $ownerUserId = (string) $ref['owner_user_id'];
    $chat = load_chat_for_owner($ownerUserId, $chatId);
    if (! is_array($chat)) {
        maybe_chat_not_found('Chat not found');
    }

    if (! current_user_can_manage_chat_member($chat, $contactId)) {
        json_response([
                'error' => 'You can only manage members you added.'
        ], 403);
    }

    $contact = resolve_contact_by_id_for_owner($ownerUserId, $contactId);
    $removedUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($contact['user_id'] ?? ''));
    $removedName = trim((string) ($contact['display_name'] ?? $contact['name'] ?? 'Member'));

    if ($removedUserId !== '' && $removedUserId === $ownerUserId) {
        json_response([
                'error' => 'Owner cannot be removed from their own chat.'
        ], 403);
    }

    $beforeParticipants = is_array($chat['participants'] ?? null) ? count($chat['participants']) : 0;

    $chat['participants'] = array_values(array_filter($chat['participants'] ?? [], function ($participant) use ($contactId) {
        if (! is_array($participant)) {
            return false;
        }
        return trim((string) ($participant['contact_id'] ?? '')) !== $contactId;
    }));

    $afterParticipants = count($chat['participants']);
    if ($afterParticipants >= $beforeParticipants) {
        json_response([
                'error' => 'Participant not found in chat.'
        ], 404);
    }

    if ($removedUserId !== '') {
        remove_chat_ref_from_member_profile($removedUserId, $ownerUserId, $chatId);
    }

    $chat['messages'][] = [
            'role' => 'join_note',
            'content' => ($removedName !== '' ? $removedName : 'Member') . ' left this chat',
            'time' => now_str()
    ];

    save_chat_for_owner($chat, $ownerUserId);

    return [
            'chat_id' => $chatId,
            'contact_id' => $contactId,
            'removed_user_id' => $removedUserId
    ];
}

function toggle_chat_participant_access_by_member_id(string $chatId, string $memberId, int $memberActive): array
{
    $chatId = sanitize_chat_id($chatId);
    $memberId = trim($memberId);
    $memberActive = $memberActive === 0 ? 0 : 1;

    if ($chatId === '' || $memberId === '') {
        json_response([
                'error' => 'Missing parameters'
        ], 400);
    }

    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        maybe_chat_not_found('Chat not found');
    }

    if (empty($ref['is_owner'])) {
        json_response([
                'error' => 'Only the owner can update member access.'
        ], 403);
    }

    $ownerUserId = (string) $ref['owner_user_id'];
    $chat = load_chat_for_owner($ownerUserId, $chatId);
    if (! is_array($chat)) {
        maybe_chat_not_found('Chat not found');
    }

    if (! current_user_can_manage_chat_member($chat, $memberId)) {
        json_response([
                'error' => 'You can only manage members you added.'
        ], 403);
    }

    $participants = is_array($chat['participants'] ?? null) ? array_values($chat['participants']) : [];
    $snapshots = is_array($chat['participant_snapshots'] ?? null) ? $chat['participant_snapshots'] : [];
    $participantIds = array_values(array_filter(array_map('trim', array_map('strval', $chat['participant_ids'] ?? []))));
    $chatCreatedAt = normalize_membership_period_timestamp($chat['created_at'] ?? now_str(), now_str());
    $now = now_str();
    $targetIndex = -1;
    $resolvedMemberId = '';

    if (in_array($memberId, $participantIds, true)) {
        $resolvedMemberId = $memberId;
    } elseif (is_array($snapshots[$memberId] ?? null)) {
        $resolvedMemberId = $memberId;
    }

    foreach ($participants as $idx => $participant) {
        if (!is_array($participant)) {
            continue;
        }
        $participantContactId = trim((string) ($participant['contact_id'] ?? ''));
        $participantId = trim((string) ($participant['id'] ?? ''));
        $participantUserId = trim((string) ($participant['user_id'] ?? ''));
        if (
            ($resolvedMemberId !== '' && ($participantContactId === $resolvedMemberId || $participantId === $resolvedMemberId))
            || $participantContactId === $memberId
            || $participantId === $memberId
            || $participantUserId === $memberId
        ) {
            $targetIndex = $idx;
            if ($resolvedMemberId === '') {
                $resolvedMemberId = $participantContactId !== ''
                    ? $participantContactId
                    : ($participantId !== '' ? $participantId : $memberId);
            }
            break;
        }
    }

    if ($resolvedMemberId === '') {
        json_response([
                'error' => 'Participant not found in chat.'
        ], 404);
    }

    if ($targetIndex < 0 && is_array($snapshots[$resolvedMemberId] ?? null)) {
        $target = [
                'contact_id' => $resolvedMemberId,
                'id' => $resolvedMemberId,
                'role' => (string) ($snapshots[$resolvedMemberId]['role'] ?? 'member'),
                'user_id' => (string) ($snapshots[$resolvedMemberId]['user_id'] ?? '')
        ];
    } else {
        $target = is_array($participants[$targetIndex] ?? null) ? $participants[$targetIndex] : [];
    }

    $targetRole = trim((string) ($target['role'] ?? 'member')) ?: 'member';
    $targetUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($target['user_id'] ?? ''));

    if ($targetRole === 'owner' || ($targetUserId !== '' && $targetUserId === $ownerUserId)) {
        json_response([
                'error' => 'Owner access cannot be changed.'
        ], 403);
    }

    $existingSnapshot = is_array($snapshots[$resolvedMemberId] ?? null) ? $snapshots[$resolvedMemberId] : [];
    $membershipPeriods = ensure_member_periods_from_legacy($target, $existingSnapshot, $chatCreatedAt);
    $isCurrentlyActive = is_membership_periods_active($membershipPeriods);

    if ($memberActive === 1 && !$isCurrentlyActive) {
        $membershipPeriods[] = [
            'start_at' => $now,
            'end_at' => null,
        ];
    } elseif ($memberActive === 0 && $isCurrentlyActive) {
        $membershipPeriods = close_open_membership_periods($membershipPeriods, $now);
    }

    $membershipPeriods = normalize_membership_periods($membershipPeriods);
    $resolvedActive = is_membership_periods_active($membershipPeriods) ? 1 : 0;

    if ($targetIndex >= 0) {
        $participants[$targetIndex]['membership_periods'] = $membershipPeriods;
        unset($participants[$targetIndex]['member_active']);
    }
    $chat['participants'] = $participants;

    if (!in_array($resolvedMemberId, $participantIds, true)) {
        $participantIds[] = $resolvedMemberId;
    }
    $chat['participant_ids'] = array_values(array_unique($participantIds));

    $snapshots[$resolvedMemberId] = array_merge($existingSnapshot, [
            'id' => $resolvedMemberId,
            'display_name' => (string) ($existingSnapshot['display_name'] ?? $target['display_name'] ?? $target['name'] ?? ''),
            'name' => (string) ($existingSnapshot['name'] ?? $target['name'] ?? $target['display_name'] ?? ''),
            'avatar' => (string) ($existingSnapshot['avatar'] ?? $target['avatar'] ?? ''),
            'initials' => (string) ($existingSnapshot['initials'] ?? $target['initials'] ?? ''),
            'email' => (string) ($existingSnapshot['email'] ?? $target['email'] ?? ''),
            'user_id' => (string) ($existingSnapshot['user_id'] ?? $target['user_id'] ?? ''),
            'verified' => !empty($existingSnapshot['verified']) || !empty($target['verified']),
            'role' => $targetRole,
            'membership_periods' => $membershipPeriods,
    ]);
    unset($snapshots[$resolvedMemberId]['member_active']);
    $chat['participant_snapshots'] = $snapshots;

    save_chat_for_owner($chat, $ownerUserId);

    $chat = load_chat_for_owner($ownerUserId, $chatId);

    $snapshot = is_array(($chat['participant_snapshots'] ?? [])[$resolvedMemberId] ?? null)
        ? $chat['participant_snapshots'][$resolvedMemberId]
        : [];
    if ($targetUserId === '') {
        $targetUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($snapshot['user_id'] ?? ''));
    }

    if ($targetUserId !== '') {
        if ($resolvedActive === 1) {
            upsert_chat_ref_for_user($targetUserId, [
                    'chat_id' => (string) ($chat['id'] ?? $chatId),
                    'owner_user_id' => $ownerUserId,
                    'title' => (string) ($chat['title'] ?? 'Shared chat'),
                    'updated_at' => (string) ($chat['updated_at'] ?? now_str()),
                    'last_read_at' => '',
                    'unread_count' => 0,
                    'state' => 'active',
                    'participant_ids' => $chat['participant_ids'] ?? [],
                    'participant_snapshots' => $chat['participant_snapshots'] ?? []
            ]);
        } else {
            remove_chat_ref_from_member_profile($targetUserId, $ownerUserId, $chatId);
        }
    }

    return [
            'chat_id' => $chatId,
            'member_id' => $resolvedMemberId,
            'participant_id' => $resolvedMemberId,
            'contact_id' => $resolvedMemberId,
            'is_active' => $resolvedActive,
            'chat' => $chat
    ];
}

function upsert_chat_ref_for_user(string $userId, array $ref): void
{
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    if ($userId === '') {
        return;
    }

    $chatId = sanitize_chat_id((string) ($ref['chat_id'] ?? $ref['id'] ?? ''));
    if ($chatId === '') {
        return;
    }

    $ref['chat_id'] = $chatId;
    $ref['id'] = $chatId;
    $ref = ensure_chat_ref_defaults(normalize_chat_index_item($ref, $userId));

    $index = load_chat_index_for_user($userId);
    $found = false;

    foreach ($index as &$item) {
        $item = ensure_chat_ref_defaults(is_array($item) ? $item : []);
        if (($item['id'] ?? '') === $chatId && ($item['owner_user_id'] ?? '') === ($ref['owner_user_id'] ?? '')) {
            $item = array_merge($item, $ref);
            $found = true;
            break;
        }
    }
    unset($item);

    if (! $found) {
        $index[] = $ref;
    }

    usort($index, function ($a, $b) {
        return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
    });

    save_chat_index_for_user($userId, $index);
}

function find_direct_chat_id_for_contact_pair(string $ownerUserId, string $ownerContactId, string $targetContactId): string
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $ownerContactId = trim($ownerContactId);
    $targetContactId = trim($targetContactId);

    if ($ownerUserId === '' || $ownerContactId === '' || $targetContactId === '') {
        return '';
    }

    $expectedParticipantIds = array_values(array_unique(array_filter([$ownerContactId, $targetContactId])));
    if ($expectedParticipantIds === []) {
        return '';
    }

    foreach (load_chats_index_by_owner($ownerUserId) as $item) {
        $chatId = sanitize_chat_id((string) ($item['id'] ?? ''));
        if ($chatId === '') {
            continue;
        }

        $chat = load_chat_for_owner($ownerUserId, $chatId);
        if (! is_array($chat)) {
            continue;
        }

        $participantIds = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $chat['participant_ids'] ?? [])))));
        if (count($participantIds) !== count($expectedParticipantIds)) {
            continue;
        }

        sort($participantIds);
        $expected = $expectedParticipantIds;
        sort($expected);

        if ($participantIds === $expected) {
            return $chatId;
        }
    }

    return '';
}

function open_or_create_private_chat_for_contact_id(string $contactId): array
{
    require_login();

    $contactId = trim($contactId);
    if ($contactId === '') {
        json_response([
                'error' => 'Missing contact_id'
        ], 400);
    }

    $ownerUserId = (string) get_current_user_id();
    $ownerProfile = load_current_user_profile();
    if (is_invited_member_profile($ownerProfile)) {
        json_response([
                'error' => 'Invited members cannot create new chats until they sign up.'
        ], 403);
    }

    $targetContact = resolve_contact_by_id_for_owner($ownerUserId, $contactId);
    if (empty($targetContact['id'])) {
        json_response([
                'error' => 'Contact not found'
        ], 404);
    }

    $ownerUser = is_array($ownerProfile['user'] ?? null) ? $ownerProfile['user'] : [];
    $ownerContact = resolve_contact_for_owner($ownerUserId, [
            'display_name' => (string) ($ownerUser['name'] ?? ($_SESSION['user_name'] ?? 'User')),
            'email' => normalize_email_local((string) ($ownerUser['email'] ?? get_current_user_email())),
            'user_id' => $ownerUserId,
            'verified' => ! empty($ownerUser['email_verified'])
    ], true);

    $ownerContactId = trim((string) ($ownerContact['id'] ?? ''));
    $targetContactId = trim((string) ($targetContact['id'] ?? ''));

    if ($ownerContactId === '' || $targetContactId === '') {
        json_response([
                'error' => 'Could not resolve chat participants'
        ], 500);
    }

    $isSelfChat = ($ownerContactId === $targetContactId);
    $chatId = find_direct_chat_id_for_contact_pair($ownerUserId, $ownerContactId, $targetContactId);
    $created = false;

    if ($chatId !== '') {
        $chat = load_chat_for_owner($ownerUserId, $chatId);
        if (! is_array($chat)) {
            $chatId = '';
        }
    }

    if ($chatId === '') {
        $title = trim((string) ($targetContact['display_name'] ?? $targetContact['name'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($targetContact['email'] ?? ''));
        }
        if ($title === '') {
            $title = 'Private chat';
        }

        $chat = create_new_chat($title);
        $chatId = sanitize_chat_id((string) ($chat['id'] ?? ''));
        if ($chatId === '') {
            json_response([
                    'error' => 'Could not create chat'
            ], 500);
        }

        if (! $isSelfChat) {
            $chat['participants'][] = [
                    'contact_id' => $targetContactId,
                    'role' => 'member'
            ];
        }
        save_chat_for_owner($chat, $ownerUserId);
        $chat = load_chat_for_owner($ownerUserId, $chatId);
        $created = true;
    }

    if (! is_array($chat)) {
        json_response([
                'error' => 'Chat not found'
        ], 404);
    }

    $targetUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($targetContact['user_id'] ?? ''));
    if ($targetUserId !== '' && $targetUserId !== $ownerUserId) {
        upsert_chat_ref_for_user($targetUserId, [
                'chat_id' => (string) $chat['id'],
                'owner_user_id' => $ownerUserId,
                'title' => (string) ($chat['title'] ?? 'Shared chat'),
                'updated_at' => (string) ($chat['updated_at'] ?? now_str()),
                'last_read_at' => '',
                'unread_count' => 0,
                'state' => 'active',
                'participant_ids' => $chat['participant_ids'] ?? [],
                'participant_snapshots' => $chat['participant_snapshots'] ?? []
        ]);
    }

    $_SESSION['current_chat_id'] = (string) $chat['id'];

    return [
            'chat_id' => (string) $chat['id'],
            'chat' => $chat,
            'created' => $created
    ];
}

function delete_chat_by_id(string $chatId): ?string
{
    return archive_chat_by_id($chatId);
}

function restore_chat_by_id(string $chatId): ?string
{
    $chatId = sanitize_chat_id($chatId);
    if ($chatId === '') {
        maybe_chat_not_found('Chat not found');
    }
    
    $currentUserId = (string) get_current_user_id();
    
    $ownerIndex = load_chats_index_by_owner($currentUserId);
    $ownerFound = false;
    foreach ($ownerIndex as &$item) {
        $item = ensure_chat_ref_defaults($item);
        if (($item['id'] ?? '') === $chatId) {
            if (($item['state'] ?? 'active') !== 'archived') {
                json_response([
                        'error' => 'Only archived chats can be restored'
                ], 400);
            }
            $item['state'] = 'active';
            $ownerFound = true;
            break;
        }
    }
    unset($item);
    
    if ($ownerFound) {
        save_chats_index_by_owner($currentUserId, $ownerIndex);
        $_SESSION['current_chat_id'] = $chatId;
        return $chatId;
    }
    
    $currentUserId = (string) get_current_user_id();
    $memberIndex = load_chat_index_for_user($currentUserId);
    $memberFound = false;
    foreach ($memberIndex as &$item) {
        $item = ensure_chat_ref_defaults($item);
        if (($item['id'] ?? '') === $chatId) {
            if (($item['state'] ?? 'active') !== 'archived') {
                json_response([
                        'error' => 'Only archived chats can be restored'
                ], 400);
            }
            $item['state'] = 'active';
            $memberFound = true;
            break;
        }
    }
    unset($item);

    if ($memberFound) {
        save_chat_index_for_user($currentUserId, $memberIndex);
        $_SESSION['current_chat_id'] = $chatId;
        return $chatId;
    }
    
    if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
        $visible = get_visible_chats_for_current_user();
        $newCurrent = $visible[0]['id'] ?? null;
        $_SESSION['current_chat_id'] = $newCurrent;
        return $newCurrent;
    }
    
    maybe_chat_not_found('Chat not found');
}

function make_chat_title_from_text(string $text): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return 'New chat';
    }
    return mb_substr($text, 0, 40);
}

function rename_chat_by_id(string $chatId, string $title): array
{
    $chatId = sanitize_chat_id($chatId);
    $title = make_chat_title_from_text($title);
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            maybe_chat_not_found('Chat not found', [
                    'removed_orphan' => true
            ]);
        }
        maybe_chat_not_found('Chat not found');
    }
    
    if (empty($ref['is_owner'])) {
        json_response([
                'error' => 'Only the owner can rename this chat.'
        ], 403);
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    if (! $chat) {
        cleanup_orphan_chat_reference_for_user((string) $ref['owner_user_id'], $chatId);
        maybe_chat_not_found('Chat not found');
    }
    
    $chat['title'] = $title;
    save_chat_for_owner($chat, (string) $ref['owner_user_id']);
    
    foreach (glob(get_users_root() . '/*') as $userDir) {
        if (! is_dir($userDir)) {
            continue;
        }

        $userId = resolve_user_id_from_dir($userDir);
        if ($userId === '') {
            continue;
        }
        $index = load_chat_index_for_user($userId);
        $changed = false;

        foreach ($index as &$item) {
            $item = ensure_chat_ref_defaults($item);
            if ((($item['id'] ?? '') === $chatId) && (($item['owner_user_id'] ?? '') === (string) $ref['owner_user_id'])) {
                $item['title'] = $title;
                $item['updated_at'] = $chat['updated_at'] ?? now_str();
                $changed = true;
            }
        }
        unset($item);

        if ($changed) {
            save_chat_index_for_user($userId, $index);
        }
    }
    
    return [
            'chat_id' => $chatId,
            'title' => $title,
            'updated_at' => $chat['updated_at'] ?? now_str()
    ];
}

function extract_output_text(array $data): string
{
    $output = '';
    if (! empty($data['output_text']) && is_string($data['output_text'])) {
        return trim($data['output_text']);
    }
    foreach ($data['output'] ?? [] as $item) {
        foreach ($item['content'] ?? [] as $c) {
            if (($c['type'] ?? '') === 'output_text' && ! empty($c['text'])) {
                $output .= $c['text'];
            }
        }
    }
    return trim($output);
}

function extract_first_json_object_str(string $raw): string
{
    $start = strpos($raw, '{');
    if ($start === false) {
        return '';
    }

    $depth = 0;
    $inString = false;
    $escapeNext = false;
    $len = strlen($raw);

    for ($i = $start; $i < $len; $i++) {
        $c = $raw[$i];

        if ($escapeNext) {
            $escapeNext = false;
            continue;
        }
        if ($c === '\\' && $inString) {
            $escapeNext = true;
            continue;
        }
        if ($c === '"') {
            $inString = ! $inString;
            continue;
        }
        if ($inString) {
            continue;
        }

        if ($c === '{') {
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($raw, $start, $i - $start + 1);
            }
        }
    }

    return '';
}

function extract_structured_output(array $data, string $fallbackReplyType = 'draft'): array
{
    $raw = trim(extract_output_text($data));
    
    if ($raw === '') {
        return [
                'reply_type' => 'passive',
                'message_key' => 'no_edits_suggested',
                'content' => ''
        ];
    }
    
    $parsed = json_decode($raw, true);

    if (! is_array($parsed)) {
        $jsonStr = extract_first_json_object_str($raw);
        if ($jsonStr !== '') {
            $parsed = json_decode($jsonStr, true);
        }
    }

    if (! is_array($parsed)) {
        // Try regex extraction for malformed JSON-like strings (e.g. single quotes, wrong separators)
        if (preg_match('/["\']?content["\']?\s*[=:]\s*["\']([^"\']+)["\']/', $raw, $m)) {
            $extracted = trim($m[1]);
            if ($extracted !== '') {
                return [
                        'reply_type' => $fallbackReplyType,
                        'message_key' => '',
                        'content' => $extracted
                ];
            }
        }
        // Model returned plain text instead of JSON — use it directly as content
        return [
                'reply_type' => $fallbackReplyType,
                'message_key' => '',
                'content' => $raw
        ];
    }
    
    $replyType = (string) ($parsed['reply_type'] ?? '');

    // Infer reply_type when the model omits it
    if ($replyType === '') {
        if (isset($parsed['options']) && is_array($parsed['options']) && count($parsed['options']) > 0) {
            $replyType = 'options';
        } elseif (isset($parsed['content']) && trim((string) $parsed['content']) !== '') {
            $replyType = 'draft';
        } else {
            $replyType = $fallbackReplyType;
        }
    }
    $messageKey = (string) ($parsed['message_key'] ?? '');
    $content = trim((string) ($parsed['content'] ?? ''));
    $options = [];
    if (isset($parsed['options']) && is_array($parsed['options'])) {
        foreach ($parsed['options'] as $opt) {
            if (is_array($opt)) {
                // AI returned an object — try common string keys
                $raw = $opt['text'] ?? $opt['content'] ?? $opt['label'] ?? $opt['value'] ?? reset($opt);
                $clean = is_string($raw) ? trim($raw) : '';
            } else {
                $clean = trim((string) $opt);
            }
            if ($clean !== '') {
                $options[] = $clean;
            }
        }
    }

    if (! in_array($replyType, [
            'draft',
            'options',
            'passive',
            'private',
            'error',
            'explain',
            'translate'
    ], true)) {
        $replyType = $fallbackReplyType;
    }
    
    if ($replyType === 'passive') {
        return [
                'reply_type' => 'passive',
                'message_key' => $messageKey !== '' ? $messageKey : 'no_edits_suggested',
                'content' => '',
                'options' => []
        ];
    }

    // 'options' reply type: needs at least 2 options
    if ($replyType === 'options') {
        if (count($options) >= 2) {
            return [
                    'reply_type' => 'options',
                    'message_key' => $messageKey,
                    'content' => '',
                    'options' => array_values($options)
            ];
        }
        // Fallback: if AI returned options but only 1, treat as draft
        if (count($options) === 1) {
            return [
                    'reply_type' => 'draft',
                    'message_key' => $messageKey,
                    'content' => $options[0],
                    'options' => []
            ];
        }
        return [
                'reply_type' => 'passive',
                'message_key' => 'message_unclear',
                'content' => '',
                'options' => []
        ];
    }

    if (in_array($replyType, [
            'draft',
            'private',
            'explain',
            'translate'
    ], true) && $content === '') {
        return [
                'reply_type' => 'passive',
                'message_key' => 'message_unclear',
                'content' => '',
                'options' => []
        ];
    }
    
    if ($replyType === 'error' && $content === '') {
        return [
                'reply_type' => 'error',
                'message_key' => $messageKey !== '' ? $messageKey : 'assist_unavailable',
                'content' => '',
                'options' => []
        ];
    }
    
    return [
            'reply_type' => $replyType,
            'message_key' => $messageKey,
            'content' => $content,
            'options' => $options
    ];
}

function assist_json_response(string $content = '', string $replyType = 'draft', string $messageKey = '', array $extra = [], int $status = 200): void
{
    $payload = array_merge([
            'reply' => $content,
            'reply_type' => $replyType,
            'message_key' => $messageKey
    ], $extra);
    
    $GLOBALS['payload'] = $payload;
    
    json_response($payload, $status);
}

function create_invite_record(string $chatId, string $name, string $email): array
{
    $chatId = sanitize_chat_id($chatId);
    $inviteId = 'inv_' . substr(bin2hex(random_bytes(9)), 0, 18);
    $email = normalize_email_local($email);
    $ownerProfile = load_current_user_profile();
    $owner = $ownerProfile['user'] ?? [];
    $record = [
            'invite_id' => $inviteId,
            'chat_id' => $chatId,
            'owner_user_id' => (string) get_current_user_id(),
            'owner_email' => (string) ($owner['email'] ?? get_current_user_email()),
            'owner_name' => (string) ($owner['name'] ?? ($_SESSION['user_name'] ?? '')),
            'name' => $name,
            'email' => $email,
            'invite_kind' => 'guest',
            'created_at' => now_str(),
            'status' => 'pending',
            'used_at' => '',
            'used_user_id' => '',
            'used_contact_id' => '',
            'used_guest_token' => ''
    ];
    save_json_file(get_invites_root() . '/' . $inviteId . '.json', $record);
    return $record;
}

function load_invite_record(string $inviteId): ?array
{
    $inviteId = sanitize_invite_id($inviteId);
    $path = get_invites_root() . '/' . $inviteId . '.json';
    if (! file_exists($path)) {
        return null;
    }
    return load_json_file($path, null);
}

function save_invite_record(array $invite): void
{
    $inviteId = sanitize_invite_id((string) ($invite['invite_id'] ?? ''));
    if ($inviteId === '') {
        json_response([
                'error' => 'Missing invite_id'
        ], 500);
    }
    save_json_file(get_invites_root() . '/' . $inviteId . '.json', $invite);
}

function build_invite_path(string $inviteId, string $fromName = ''): string
{
    return '/?invite=' . rawurlencode(sanitize_invite_id($inviteId));
}

function send_invite_email(array $invite): array
{
    global $app_base_url;
    require_once __DIR__ . '/auth/mailer.php';
    
    $absolute = $app_base_url . build_invite_path((string) ($invite['invite_id'] ?? ''));
    
    $subject = 'You are invited to a Tsjilp chat';
    $ownerName = trim((string) ($invite['owner_name'] ?? 'Someone'));
    $recipientName = trim((string) ($invite['name'] ?? ''));
    $recipientEmail = trim((string) ($invite['email'] ?? ''));
    
    if ($recipientEmail === '') {
        return [
                'sent' => false,
                'debug_link' => $absolute,
                'error' => 'Missing recipient email'
        ];
    }
    
    $textBody = $ownerName . " invited you to a Tsjilp chat.\n\nOpen this link:\n" . $absolute;
    
    $htmlBody = '
        <p>' . htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8') . ' invited you to a Tsjilp chat.</p>
        <p><a href="' . htmlspecialchars($absolute, ENT_QUOTES, 'UTF-8') . '">Open this link</a></p>
    ';
    
    $sent = send_email($recipientEmail, $recipientName, $subject, $textBody, $htmlBody);
    
    return [
            'sent' => $sent === true,
            'debug_link' => $absolute,
            'error' => $sent === true ? null : 'Could not send invite email'
    ];
}

function add_participant_to_chat_if_missing(array &$chat, array $userProfile): bool
{
    $ownerUserId = (string) ($chat['owner_user_id'] ?? get_current_user_id());
    $contact = resolve_contact_for_owner($ownerUserId, [
            'display_name' => (string) ($userProfile['user']['name'] ?? 'Member'),
            'email' => normalize_email_local((string) ($userProfile['user']['email'] ?? '')),
            'user_id' => (string) ($userProfile['user']['id'] ?? ''),
            'verified' => !empty($userProfile['user']['email_verified'])
    ], true);

    if (empty($contact['id'])) {
        return false;
    }

    foreach (($chat['participants'] ?? []) as $participant) {
        if (($participant['contact_id'] ?? '') === (string) $contact['id']) {
            return false;
        }
    }

    $chat['participants'][] = [
            'contact_id' => (string) $contact['id'],
            'role' => 'member'
    ];
    
    $chat['messages'][] = [
            'role' => 'join_note',
            'content' => (string) ($userProfile['user']['name'] ?? 'Someone') . ' joined the chat'
    ];
    
    return true;
}

function add_guest_participant_to_chat_if_missing(array &$chat, string $displayName, string $contactId): bool
{
    $displayName = trim($displayName);
    $contactId = trim($contactId);

    if ($contactId === '') {
        return false;
    }

    foreach (($chat['participants'] ?? []) as $participant) {
        if (($participant['contact_id'] ?? '') === $contactId) {
            return false;
        }
    }

    $chat['participants'][] = [
            'contact_id' => $contactId,
            'role' => 'member'
    ];

    if ($displayName !== '') {
        $chat['messages'][] = [
                'role' => 'join_note',
                'content' => $displayName . ' joined the chat'
        ];
    }

    return true;
}

function resolve_contact_by_uid_for_owner(string $ownerUserId, string $uid): array
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    $uid = trim($uid);
    if ($ownerUserId === '' || $uid === '') {
        return [];
    }

    $contact = resolve_contact_by_id_for_owner($ownerUserId, $uid);
    if (!empty($contact['id'])) {
        return $contact;
    }

    $payload = load_contacts_payload_for_owner($ownerUserId);
    foreach (($payload['contacts'] ?? []) as $record) {
        if (!is_array($record)) {
            continue;
        }
        if (trim((string) ($record['user_id'] ?? '')) === $uid) {
            return $record;
        }
    }

    return [];
}

function ensure_chat_participant_for_uid(string $chatId, string $uid, bool $allowOwnerAttach = false, string $addedByUserId = ''): array
{
    $chatId = sanitize_chat_id($chatId);
    $uid = trim($uid);
    $addedByUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $addedByUserId);

    if ($chatId === '' || $uid === '') {
        return [
                'ok' => false,
                'error' => 'Missing chat_id or uid'
        ];
    }

    $owner = find_chat_owner_for_chat_id($chatId);
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($owner['owner_user_id'] ?? ''));
    if ($ownerUserId === '') {
        return [
                'ok' => false,
                'error' => 'Chat not found'
        ];
    }

    $chat = load_chat_for_owner($ownerUserId, $chatId);
    if (!is_array($chat)) {
        return [
                'ok' => false,
                'error' => 'Chat not found'
        ];
    }

    $contact = resolve_contact_by_uid_for_owner($ownerUserId, $uid);
    if (empty($contact['id'])) {
        return [
                'ok' => false,
                'error' => 'UID not found'
        ];
    }

    $contactId = trim((string) ($contact['id'] ?? ''));
    $participantIds = array_values(array_filter(array_map('trim', array_map('strval', $chat['participant_ids'] ?? []))));
    $isInChat = in_array($contactId, $participantIds, true);
    $added = false;

    if (!$isInChat && $allowOwnerAttach) {
        if ($addedByUserId === '') {
            $addedByUserId = $ownerUserId;
        }
        $chat['participants'][] = [
                'contact_id' => $contactId,
            'role' => 'member',
            'added_by_user_id' => $addedByUserId
        ];
        save_chat_for_owner($chat, $ownerUserId);
        $chat = load_chat_for_owner($ownerUserId, $chatId);
        $participantIds = array_values(array_filter(array_map('trim', array_map('strval', $chat['participant_ids'] ?? []))));
        $isInChat = in_array($contactId, $participantIds, true);
        $added = $isInChat;
    }

    return [
            'ok' => true,
            'owner_user_id' => $ownerUserId,
            'chat' => $chat,
            'chat_id' => (string) ($chat['id'] ?? $chatId),
            'contact' => $contact,
            'contact_id' => $contactId,
            'is_in_chat' => $isInChat,
            'added' => $added
    ];
}

function normalize_display_name_for_compare(string $value): string
{
    return mb_strtolower(trim($value), 'UTF-8');
}

function get_chat_participant_display_names(array $chat, string $ownerUserId): array
{
    $hydrated = hydrate_chat_participants_for_owner($chat, $ownerUserId);
    $names = [];

    foreach (($hydrated['participants'] ?? []) as $participant) {
        if (!is_array($participant)) continue;
        $name = trim((string) ($participant['display_name'] ?? $participant['name'] ?? ''));
        if ($name === '') continue;
        $names[] = $name;
    }

    return array_values(array_unique($names));
}

function is_chat_display_name_taken(array $chat, string $ownerUserId, string $displayName): bool
{
    $needle = normalize_display_name_for_compare($displayName);
    if ($needle === '') return false;

    foreach (get_chat_participant_display_names($chat, $ownerUserId) as $name) {
        if (normalize_display_name_for_compare((string) $name) === $needle) {
            return true;
        }
    }

    return false;
}

function create_guest_join_response(string $chatId, string $displayName, string $uid = '', string $inviteId = ''): array
{
    global $app_base_url;

    $chatId = sanitize_chat_id($chatId);
    $displayName = trim($displayName);
    $uid = trim($uid);
    $inviteId = sanitize_invite_id($inviteId);

    $currentUserId = trim((string) get_current_user_id());
    $currentProfile = [];
    $currentUser = [];
    $isCurrentInvitedMember = false;
    if ($currentUserId !== '') {
        $currentProfile = load_current_user_profile();
        $currentUser = is_array($currentProfile['user'] ?? null) ? $currentProfile['user'] : [];
        $currentUserId = trim((string) ($currentUser['id'] ?? $currentUserId));
        $isCurrentInvitedMember = is_invited_member_profile($currentProfile);
    }

    if ($displayName === '' && $isCurrentInvitedMember) {
        $displayName = trim((string) ($currentUser['name'] ?? $_SESSION['user_name'] ?? ''));
    }

    $invite = [];
    if ($inviteId !== '') {
        $invite = load_invite_record($inviteId) ?: [];
        if (empty($invite) || (string) ($invite['invite_kind'] ?? 'guest') !== 'guest') {
            return [
                    'ok' => false,
                    'error' => 'Invite not found'
            ];
        }

        if ((string) ($invite['status'] ?? 'pending') !== 'pending') {
            return [
                    'ok' => false,
                    'error' => 'This invite link has already been used.'
            ];
        }

        $inviteChatId = sanitize_chat_id((string) ($invite['chat_id'] ?? ''));
        if ($inviteChatId === '') {
            return [
                    'ok' => false,
                    'error' => 'Invite not found'
            ];
        }

        $chatId = $inviteChatId;
    }

    if ($chatId === '' || ($displayName === '' && $uid === '')) {
        return [
                'ok' => false,
                'error' => 'Missing chat_id and invite identity'
        ];
    }

    if ($uid !== '') {
        $resolvedUid = ensure_chat_participant_for_uid($chatId, $uid, true, '');
        if (empty($resolvedUid['ok'])) {
            return $resolvedUid;
        }

        if (empty($resolvedUid['is_in_chat'])) {
            return [
                    'ok' => false,
                    'error' => 'Participant not found in chat.'
            ];
        }

        $contact = is_array($resolvedUid['contact'] ?? null) ? $resolvedUid['contact'] : [];
        $ownerUserId = (string) ($resolvedUid['owner_user_id'] ?? '');
        $chat = is_array($resolvedUid['chat'] ?? null) ? $resolvedUid['chat'] : [];
        $contactId = trim((string) ($contact['id'] ?? ''));
        $token = trim((string) ($contact['guest_token'] ?? ''));
        if ($token === '') {
            $token = contacts_generate_guest_token();
            $payload = load_contacts_payload_for_owner($ownerUserId);
            $existing = $payload['contacts'][$contactId] ?? [];
            $payload['contacts'][$contactId] = contacts_hydrate_record([
                    'id' => $contactId,
                    'display_name' => (string) ($contact['display_name'] ?? $contact['name'] ?? 'Member'),
                    'email' => (string) ($contact['email'] ?? ''),
                    'guest_token' => $token,
                    'invite_chat_id' => $chatId
            ], is_array($existing) ? $existing : []);
            save_contacts_payload_for_owner($ownerUserId, $payload);
        }

        upsert_guest_membership_for_owner($ownerUserId, [
                'guest_token' => $token,
                'contact_id' => $contactId,
                'chat_id' => $chatId,
                'display_name' => (string) ($contact['display_name'] ?? $contact['name'] ?? 'Member'),
                'owner_user_id' => $ownerUserId,
                'created_at' => now_str(),
                'updated_at' => now_str()
        ]);

        $resolved = find_guest_membership_by_token($token);
        persist_guest_session_from_membership($resolved);

        if ($inviteId !== '') {
            $invite['status'] = 'used';
            $invite['used_at'] = now_str();
            $invite['used_user_id'] = (string) ($_SESSION['user_id'] ?? '');
            $invite['used_contact_id'] = $contactId;
            $invite['used_guest_token'] = $token;
            save_invite_record($invite);
        }

        return [
                'ok' => true,
                'chat_id' => $chatId,
                'chat_title' => (string) ($chat['title'] ?? 'Shared chat'),
                'user_id' => (string) ($_SESSION['user_id'] ?? ''),
            'contact_id' => $contactId,
            'display_name' => (string) ($contact['display_name'] ?? $contact['name'] ?? ''),
                'member_kind' => (string) (((load_current_user_profile()['user'] ?? [])['member_kind'] ?? '')),
                'guest_token' => $token,
                'member_path' => build_member_path($token),
                'member_link' => $app_base_url . '/?member=' . rawurlencode($token)
        ];
    }

    $owner = find_chat_owner_for_chat_id($chatId);
    if (empty($owner['owner_user_id'])) {
        return [
                'ok' => false,
                'error' => 'Chat not found'
        ];
    }

    $chat = load_chat_for_owner((string) $owner['owner_user_id'], $chatId);
    if (! is_array($chat)) {
        return [
                'ok' => false,
                'error' => 'Chat not found'
        ];
    }

    if (is_chat_display_name_taken($chat, (string) $owner['owner_user_id'], $displayName)) {
        return [
                'ok' => false,
                'error' => 'This name is already used in this chat. Please use another name.'
        ];
    }

    $candidate = [
            'display_name' => $displayName,
            'email' => '',
            'user_id' => $isCurrentInvitedMember ? $currentUserId : '',
            'invite_chat_id' => $chatId,
            'status' => 'active'
    ];
    $contact = resolve_contact_for_owner((string) $owner['owner_user_id'], $candidate, true);
    if (empty($contact['id'])) {
        return [
                'ok' => false,
                'error' => 'Could not create guest contact'
        ];
    }

    $added = add_guest_participant_to_chat_if_missing($chat, $displayName, (string) $contact['id']);
    if ($added) {
        save_chat_for_owner($chat, (string) $owner['owner_user_id']);
    }

    $token = trim((string) ($contact['guest_token'] ?? ''));
    if ($token === '') {
        $token = contacts_generate_guest_token();
        $payload = load_contacts_payload_for_owner((string) $owner['owner_user_id']);
        $existing = $payload['contacts'][$contact['id']] ?? [];
        $payload['contacts'][$contact['id']] = contacts_hydrate_record([
                'id' => (string) $contact['id'],
                'display_name' => $displayName,
                'email' => '',
                'invite_chat_id' => $chatId,
                'guest_token' => $token
        ], is_array($existing) ? $existing : []);
        save_contacts_payload_for_owner((string) $owner['owner_user_id'], $payload);
    }

    upsert_guest_membership_for_owner((string) $owner['owner_user_id'], [
            'guest_token' => $token,
            'contact_id' => (string) $contact['id'],
            'chat_id' => $chatId,
            'display_name' => $displayName,
            'owner_user_id' => (string) $owner['owner_user_id'],
            'created_at' => now_str(),
            'updated_at' => now_str()
    ]);

    $resolved = find_guest_membership_by_token($token);
    persist_guest_session_from_membership($resolved);

    if ($inviteId !== '') {
        $invite['status'] = 'used';
        $invite['used_at'] = now_str();
        $invite['used_user_id'] = (string) ($_SESSION['user_id'] ?? '');
        $invite['used_contact_id'] = (string) ($contact['id'] ?? '');
        $invite['used_guest_token'] = $token;
        save_invite_record($invite);
    }

    return [
            'ok' => true,
            'chat_id' => $chatId,
            'chat_title' => (string) ($chat['title'] ?? 'Shared chat'),
            'user_id' => (string) ($_SESSION['user_id'] ?? ''),
            'contact_id' => (string) ($contact['id'] ?? ''),
            'display_name' => (string) ($contact['display_name'] ?? $displayName),
            'member_kind' => (string) (((load_current_user_profile()['user'] ?? [])['member_kind'] ?? '')),
            'guest_token' => $token,
            'member_path' => build_member_path($token),
            'member_link' => $app_base_url . '/?member=' . rawurlencode($token)
    ];
}

function resolve_member_token_response(string $token): array
{
    $token = trim($token);
    if ($token === '') {
        return [
                'ok' => false,
                'error' => 'Missing member token'
        ];
    }

    $resolved = find_guest_membership_by_token($token);
    if (empty($resolved['owner_user_id'])) {
        return [
                'ok' => false,
                'error' => 'Member token not found'
        ];
    }

    $chat = is_array($resolved['chat'] ?? null) ? $resolved['chat'] : [];
    $contact = is_array($resolved['contact'] ?? null) ? $resolved['contact'] : [];

    persist_guest_session_from_membership($resolved);

    return [
            'ok' => true,
            'owner_user_id' => $resolved['owner_user_id'],
            'chat_id' => (string) ($resolved['chat_id'] ?? ''),
            'chat_title' => (string) ($chat['title'] ?? 'Shared chat'),
            'user_id' => (string) ($_SESSION['user_id'] ?? ''),
            'member_kind' => (string) (((load_current_user_profile()['user'] ?? [])['member_kind'] ?? '')),
            'contact' => [
                    'id' => (string) ($contact['id'] ?? ''),
                    'display_name' => (string) ($contact['display_name'] ?? ''),
                    'email' => (string) ($contact['email'] ?? ''),
                    'guest_token' => $token
            ],
            'membership' => $resolved['membership'] ?? [],
            'member_path' => build_member_path($token)
    ];
}

function ensure_current_user_contacts_for_owner_user(array &$profile, string $ownerUserId): void
{
    $currentUser = is_array($profile['user'] ?? null) ? $profile['user'] : [];
    $currentUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($currentUser['id'] ?? get_current_user_id()));
    if ($currentUserId !== '') {
        resolve_contact_for_owner($currentUserId, [
                'display_name' => (string)($currentUser['name'] ?? ($_SESSION['user_name'] ?? 'User')),
                'email' => (string)($currentUser['email'] ?? get_current_user_email()),
                'user_id' => $currentUserId,
                'verified' => !empty($currentUser['email_verified'])
        ], true);
    }

    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    if ($ownerUserId === '' || $ownerUserId === $currentUserId) {
        return;
    }

    $ownerProfile = load_user_profile_by_id($ownerUserId);
    $ownerUser = is_array($ownerProfile['user'] ?? null) ? $ownerProfile['user'] : [];
    if (empty($ownerUser)) {
        return;
    }

    add_or_update_contact(
        $profile,
        trim((string)($ownerUser['name'] ?? '')),
        normalize_email_local((string)($ownerUser['email'] ?? '')),
        $ownerUserId,
        !empty($ownerUser['email_verified'])
    );
}

function join_shared_chat_for_current_user(string $chatId, string $uid = ''): array
{
    require_login();
    $chatId = sanitize_chat_id($chatId);
    $uid = trim($uid);
    if ($chatId === '') {
        return [
                'ok' => false,
                'error' => 'Missing chat_id'
        ];
    }

    $profile = load_current_user_profile();
    if (is_invited_member_profile($profile)) {
        return [
                'ok' => false,
                'error' => 'Guest members must continue through the guest join flow.'
        ];
    }

    if ($uid !== '') {
        $ownerAttachAllowed = false;
        $chatRef = resolve_chat_reference_for_current_user($chatId);
        if ($chatRef && !empty($chatRef['is_owner'])) {
            $ownerAttachAllowed = true;
        }

        $resolvedUid = ensure_chat_participant_for_uid($chatId, $uid, $ownerAttachAllowed, $ownerAttachAllowed ? (string) get_current_user_id() : '');
        if (empty($resolvedUid['ok'])) {
            return $resolvedUid;
        }
        if (empty($resolvedUid['is_in_chat'])) {
            return [
                    'ok' => false,
                    'error' => 'Participant not found in chat.'
            ];
        }
    }

    $owner = find_chat_owner_for_chat_id($chatId);
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($owner['owner_user_id'] ?? ''));
    if ($ownerUserId === '') {
        return [
                'ok' => false,
                'error' => 'Chat not found'
        ];
    }

    $chat = load_chat_for_owner($ownerUserId, $chatId);
    if (!is_array($chat)) {
        return [
                'ok' => false,
                'error' => 'Chat not found'
        ];
    }

    $added = false;
    if ($ownerUserId !== (string)get_current_user_id()) {
        $added = add_participant_to_chat_if_missing($chat, $profile);
        if ($added) {
            save_chat_for_owner($chat, $ownerUserId);
        }

        upsert_member_chat_ref($profile, [
                'chat_id' => (string)$chat['id'],
                'owner_user_id' => $ownerUserId,
                'title' => (string)($chat['title'] ?? 'Shared chat'),
                'updated_at' => now_str(),
                'last_read_at' => now_str(),
                'unread_count' => 0,
                'state' => 'active'
        ]);
    }

    ensure_current_user_contacts_for_owner_user($profile, $ownerUserId);
    save_current_user_profile($profile);

    $_SESSION['current_chat_id'] = (string)$chat['id'];

    return [
            'ok' => true,
            'chat_id' => (string)$chat['id'],
            'chat_title' => (string)($chat['title'] ?? 'Shared chat'),
            'joined' => $added
    ];
}

function accept_invite_for_current_user(string $inviteId): array
{
    require_login();
    $invite = load_invite_record($inviteId);
    if (! $invite) {
        return [
                'ok' => false,
                'error' => 'Invite not found'
        ];
    }

    if ((string) ($invite['invite_kind'] ?? 'account') === 'guest') {
        return [
                'ok' => false,
                'error' => 'This invite uses the guest join flow.'
        ];
    }
    
    $currentEmail = get_current_user_email();
    if ($currentEmail !== normalize_email_local((string) ($invite['email'] ?? ''))) {
        return [
                'ok' => false,
                'error' => 'This invite was sent to ' . ($invite['email'] ?? 'another account') . '.',
                'expected_email' => $invite['email'] ?? ''
        ];
    }
    
    $profile = load_current_user_profile();
    if (empty($profile['user']['email_verified'])) {
        add_pending_invite_to_profile($profile, [
                'invite_id' => $invite['invite_id'],
                'chat_id' => $invite['chat_id'],
                'owner_user_id' => $invite['owner_user_id'],
                'email' => $invite['email'],
                'name' => $invite['name'],
                'created_at' => $invite['created_at'] ?? now_str(),
                'status' => 'pending'
        ]);
        save_current_user_profile($profile);
        $_SESSION['pending_invite_id'] = $invite['invite_id'];
        return [
                'ok' => false,
                'needs_verification' => true,
                'error' => 'Please verify your email first.'
        ];
    }
    
    $chat = load_chat_for_owner((string) $invite['owner_user_id'], (string) $invite['chat_id']);
    if (! $chat) {
        return [
                'ok' => false,
                'error' => 'Chat not found'
        ];
    }
    
    $added = add_participant_to_chat_if_missing($chat, $profile);
    if ($added) {
        save_chat_for_owner($chat, (string) $invite['owner_user_id']);
    }
    
    upsert_member_chat_ref($profile, [
            'chat_id' => (string) $chat['id'],
            'owner_user_id' => (string) $invite['owner_user_id'],
            'title' => (string) ($chat['title'] ?? 'Shared chat'),
            'updated_at' => now_str(),
            'last_read_at' => now_str(),
            'unread_count' => 0,
            'state' => 'active'
    ]);
            ensure_current_user_contacts_for_owner_user($profile, (string)$invite['owner_user_id']);
    remove_pending_invite_from_profile($profile, (string) $invite['invite_id']);
    save_current_user_profile($profile);
    
    $invite['status'] = 'accepted';
    $invite['accepted_at'] = now_str();
    $invite['accepted_user_id'] = (string) get_current_user_id();
    save_invite_record($invite);
    
    $_SESSION['current_chat_id'] = $chat['id'];
    unset($_SESSION['pending_invite_id']);
    
    return [
            'ok' => true,
            'chat_id' => $chat['id'],
            'chat_title' => $chat['title'] ?? 'Shared chat'
    ];
}

function consume_pending_invites_for_current_user(): array
{
    require_login();
    $profile = load_current_user_profile();
    $accepted = [];
    foreach (($profile['pending_invites'] ?? []) as $pending) {
        $result = accept_invite_for_current_user((string) ($pending['invite_id'] ?? ''));
        if (! empty($result['ok']) && ! empty($result['chat_id'])) {
            $accepted[] = $result;
        }
    }
    return $accepted;
}

function normalize_assist_payload(array $decoded, string $fallbackType = 'draft'): array
{
    $text = trim(extract_output_text($decoded));
    
    if ($text === '') {
        return [
                'reply_type' => 'passive',
                'message_key' => 'no_edits_suggested',
                'content' => '',
                'items' => []
        ];
    }
    
    $parsed = json_decode($text, true);
    
    if (! is_array($parsed) && preg_match('/\{.*\}/s', $text, $m)) {
        $parsed = json_decode($m[0], true);
    }
    
    if (is_array($parsed)) {
        return [
                'reply_type' => (string) ($parsed['reply_type'] ?? $fallbackType),
                'message_key' => (string) ($parsed['message_key'] ?? ''),
                'content' => (string) ($parsed['content'] ?? ''),
                'items' => array_values($parsed['items'] ?? [])
        ];
    }
    
    return [
            'reply_type' => $fallbackType,
            'message_key' => '',
            'content' => $text,
            'items' => []
    ];
}

/*
 * THE TSJILP MEMORY BUILDING BLOCK
 */
function get_chat_memory_path_for_owner(string $ownerUserId, string $chatId): string
{
    $chatId = sanitize_chat_id($chatId);
    return get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chatId . '.memory.json';
}

function load_chat_memory_for_owner(string $ownerUserId, string $chatId): array
{
    $path = get_chat_memory_path_for_owner($ownerUserId, $chatId);
    
    $memory = load_json_file($path, [
            'version' => 1,
            'updated_at' => '',
            'turn_summary_upto' => 0,
            'summary_blocks' => []
    ]);
    
    if (! isset($memory['stable_memory']) || ! is_array($memory['stable_memory'])) {
        $memory['stable_memory'] = [
                'people' => [],
                'preferences' => [],
                'facts' => [],
                'open_loops' => [],
                'priority' => []
        ];
    }
    
    foreach ([
            'people',
            'preferences',
            'facts',
            'open_loops',
            'priority'
    ] as $key) {
        if (! isset($memory['stable_memory'][$key])) {
            $memory['stable_memory'][$key] = [];
        }
    }
    
    $memory['stable_memory']['people'] = prepare_memory_bucket($memory['stable_memory']['people'] ?? [], 120);
    $memory['stable_memory']['preferences'] = prepare_memory_bucket($memory['stable_memory']['preferences'] ?? [], 90);
    $memory['stable_memory']['facts'] = prepare_memory_bucket($memory['stable_memory']['facts'] ?? [], 120);
    $memory['stable_memory']['open_loops'] = prepare_memory_bucket($memory['stable_memory']['open_loops'] ?? [], 30);
    $memory['stable_memory']['priority'] = prepare_memory_bucket($memory['stable_memory']['priority'] ?? [], 21);
    
    return $memory;
}

function save_chat_memory_for_owner(string $ownerUserId, string $chatId, array $memory): void
{
    $memory['version'] = 1;
    $memory['updated_at'] = now_str();
    
    $path = get_chat_memory_path_for_owner($ownerUserId, $chatId);
    save_json_file($path, $memory);
}

function maybe_summarize_chat(array $chat, string $ownerUserId): void
{
    $assistantApiKey = get_server_assistant_api_key();
    
    if ($assistantApiKey === '') {
        return;
    }
    
    $count = count($chat['messages'] ?? []);
    
    if ($count < 8) {
        return;
    }
    
    if ($count % 4 !== 0) {
        return;
    }
    
    summarize_old_turns_if_needed($chat, $ownerUserId, $assistantApiKey);
}

function build_compact_memory_summary(array $summaryBlocks, int $maxBlocks = 3): string
{
    if (empty($summaryBlocks)) {
        return '';
    }
    
    $summaryBlocks = array_slice($summaryBlocks, - $maxBlocks);
    
    $topics = [];
    $facts = [];
    $openPoints = [];
    $tones = [];
    $summaries = [];
    
    foreach ($summaryBlocks as $block) {
        foreach (($block['topics'] ?? []) as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $topics[mb_strtolower($item)] = $item;
            }
        }
        
        foreach (($block['facts'] ?? []) as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $facts[mb_strtolower($item)] = $item;
            }
        }
        
        foreach (($block['open_points'] ?? []) as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $openPoints[mb_strtolower($item)] = $item;
            }
        }
        
        $tone = trim((string) ($block['tone'] ?? ''));
        if ($tone !== '') {
            $tones[mb_strtolower($tone)] = $tone;
        }
        
        $summary = trim((string) ($block['summary'] ?? ''));
        if ($summary !== '') {
            $summaries[] = $summary;
        }
    }
    
    $out = "Earlier conversation memory:\n";
    
    if ($topics) {
        $out .= 'Topics: ' . implode(', ', array_values($topics)) . "\n";
    }
    
    if ($facts) {
        $out .= "Facts:\n";
        foreach (array_slice(array_values($facts), 0, 8) as $fact) {
            $out .= '- ' . $fact . "\n";
        }
    }
    
    if ($openPoints) {
        $out .= "Open points:\n";
        foreach (array_slice(array_values($openPoints), 0, 6) as $point) {
            $out .= '- ' . $point . "\n";
        }
    }
    
    if ($tones) {
        $out .= 'Tone: ' . implode(', ', array_values($tones)) . "\n";
    }
    
    if ($summaries) {
        $out .= 'Short recap: ' . implode(' ', array_slice($summaries, - 2)) . "\n";
    }
    
    return trim($out);
}

function extract_stable_memory_with_openai(array $turns, string $assistantApiKey): ?array
{
    if (! $turns || $assistantApiKey === '') {
        return null;
    }
    
    $turnLines = [];
    foreach ($turns as $turn) {
        $speaker = trim((string) ($turn['speaker'] ?? 'Unknown'));
        $text = trim((string) ($turn['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $turnLines[] = $speaker . ': ' . $text;
    }
    
    if (! $turnLines) {
        return null;
    }
    
    $parsed = run_structured_mode_on_turn_lines($turnLines, $assistantApiKey, 'stable_memory', 400);
    if (! is_array($parsed)) {
        return null;
    }
    
    return [
            'people' => array_values($parsed['people'] ?? []),
            'preferences' => array_values($parsed['preferences'] ?? []),
            'facts' => array_values($parsed['facts'] ?? []),
            'open_loops' => array_values($parsed['open_loops'] ?? [])
    ];
}

function merge_stable_memory(array $existing, array $new): array
{
    foreach ([
            'people',
            'preferences',
            'facts'
    ] as $key) {
        $existing[$key] = merge_memory_items($existing[$key] ?? [], $new[$key] ?? []);
    }
    
    return $existing;
}

function stable_memory_to_text(array $stable): string
{
    $out = "Stable conversation memory:\n";
    
    $sections = [
            'priority' => [
                    'title' => "\nImportant context:\n",
                    'limit' => 5
            ],
            'people' => [
                    'title' => "People:\n",
                    'limit' => 8
            ],
            'preferences' => [
                    'title' => "Preferences:\n",
                    'limit' => 8
            ],
            'facts' => [
                    'title' => "Facts:\n",
                    'limit' => 8
            ],
            'open_loops' => [
                    'title' => "Open loops:\n",
                    'limit' => 8
            ]
    ];
    
    foreach ($sections as $key => $config) {
        if (empty($stable[$key])) {
            continue;
        }
        
        $out .= $config['title'];
        
        foreach (array_slice($stable[$key], 0, $config['limit']) as $item) {
            $text = trim((string) ($item['text'] ?? $item ?? ''));
            if ($text === '')
                continue;
                
                $out .= '- ' . $text . "\n";
        }
    }
    
    return trim($out) === 'Stable conversation memory:' ? '' : trim($out);
}

function extract_priority_memory(array $turns): array
{
    $priority = [];
    
    foreach ($turns as $turn) {
        $text = strtolower($turn['text'] ?? '');
        
        if (strpos($text, 'important') !== false || strpos($text, 'remember') !== false || strpos($text, 'don\'t forget') !== false || strpos($text, 'deadline') !== false || strpos($text, 'meeting') !== false) {
            $priority[] = trim($turn['text']);
        }
    }
    
    return array_slice($priority, 0, 5);
}

function trim_stable_memory(array $stableMemory): array
{
    $limits = [
            'priority' => 5,
            'people' => 8,
            'preferences' => 8,
            'facts' => 10,
            'open_loops' => 8
    ];
    
    foreach ($limits as $key => $limit) {
        if (! isset($stableMemory[$key]) || ! is_array($stableMemory[$key])) {
            $stableMemory[$key] = [];
            continue;
        }
        
        $stableMemory[$key] = array_values(array_slice($stableMemory[$key], - $limit));
    }
    
    return $stableMemory;
}

function normalize_memory_items(array $items): array
{
    $out = [];
    
    foreach ($items as $item) {
        if (is_string($item)) {
            $text = trim($item);
            if ($text !== '') {
                $out[] = [
                        'text' => $text,
                        'updated_at' => now_str()
                ];
            }
            continue;
        }
        
        if (is_array($item)) {
            $text = trim((string) ($item['text'] ?? ''));
            if ($text !== '') {
                $out[] = [
                        'text' => $text,
                        'updated_at' => $item['updated_at'] ?? now_str()
                ];
            }
        }
    }
    
    return $out;
}

function prune_old_memory_items(array $items, int $maxAgeDays = 30): array
{
    $cutoff = time() - ($maxAgeDays * 86400);
    $out = [];
    
    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }
        
        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        
        $updatedAt = trim((string) ($item['updated_at'] ?? ''));
        $ts = $updatedAt !== '' ? strtotime($updatedAt) : false;
        
        if ($ts !== false && $ts < $cutoff) {
            continue;
        }
        
        $out[] = [
                'text' => $text,
                'updated_at' => $updatedAt !== '' ? $updatedAt : now_str()
        ];
    }
    
    return array_values($out);
}

function sort_memory_items_newest_first(array $items): array
{
    usort($items, function ($a, $b) {
        return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
    });
        
        return array_values($items);
}

function merge_memory_items(array $existing, array $new): array
{
    $existingItems = normalize_memory_items($existing);
    $newItems = normalize_memory_items($new);
    
    $merged = [];
    
    foreach ($existingItems as $item) {
        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '')
            continue;
            
            $merged[mb_strtolower($text)] = [
                    'text' => $text,
                    'updated_at' => $item['updated_at'] ?? now_str()
            ];
    }
    
    foreach ($newItems as $item) {
        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '')
            continue;
            
            $merged[mb_strtolower($text)] = [
                    'text' => $text,
                    'updated_at' => now_str()
            ];
    }
    
    return array_values($merged);
}

function prepare_memory_bucket(array $items, int $maxAgeDays): array
{
    $items = normalize_memory_items($items);
    $items = prune_old_memory_items($items, $maxAgeDays);
    return sort_memory_items_newest_first($items);
}

function cleanup_resolved_open_loops(array $stableMemory, array $recentTurns): array
{
    if (empty($stableMemory['open_loops'])) {
        return $stableMemory;
    }
    
    $recentTexts = [];
    
    foreach ($recentTurns as $turn) {
        $text = strtolower(trim((string) ($turn['text'] ?? '')));
        if ($text !== '') {
            $recentTexts[] = $text;
        }
    }
    
    $filtered = [];
    
    foreach ($stableMemory['open_loops'] as $loop) {
        $loopText = '';
        
        if (is_array($loop)) {
            $loopText = trim((string) ($loop['text'] ?? ''));
        } else {
            $loopText = trim((string) $loop);
        }
        
        if ($loopText === '') {
            continue;
        }
        
        $loopLower = strtolower($loopText);
        $resolved = false;
        
        foreach ($recentTexts as $recentText) {
            $hasResolutionWord = strpos($recentText, 'done') !== false || strpos($recentText, 'paid') !== false || strpos($recentText, 'finished') !== false || strpos($recentText, 'completed') !== false || strpos($recentText, 'resolved') !== false;
            
            if (! $hasResolutionWord) {
                continue;
            }
            
            $loopWords = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', '', $loopLower));
            $loopWords = array_filter($loopWords, fn ($w) => mb_strlen($w) >= 4);
            
            foreach ($loopWords as $word) {
                if (strpos($recentText, $word) !== false) {
                    $resolved = true;
                    break 2;
                }
            }
        }
        
        if (! $resolved) {
            $filtered[] = is_array($loop) ? [
                    'text' => $loopText,
                    'updated_at' => $loop['updated_at'] ?? now_str()
            ] : [
                    'text' => $loopText,
                    'updated_at' => now_str()
            ];
        }
    }
    
    $stableMemory['open_loops'] = array_values($filtered);
    
    return $stableMemory;
}

function build_conversation_turns(array $messages, int $maxGapSeconds = 180): array
{
    $turns = [];
    $current = null;
    $turnIndex = 0;
    
    foreach ($messages as $msg) {
        $text = trim((string) ($msg['content'] ?? ''));
        if ($text === '') {
            continue;
        }
        
        $speaker = trim((string) ($msg['name'] ?? ''));
        $userId = trim((string) ($msg['user_id'] ?? ''));
        $role = trim((string) ($msg['role'] ?? 'user'));
        $time = trim((string) ($msg['time'] ?? ''));
        $messageId = trim((string) ($msg['id'] ?? ''));
        
        $sameSpeaker = false;
        $withinGap = false;
        
        if ($current) {
            $sameSpeaker = $current['speaker'] === $speaker && $current['user_id'] === $userId && $current['role'] === $role;
            
            if ($sameSpeaker && $current['end_time'] !== '' && $time !== '') {
                $prevTs = strtotime($current['end_time']);
                $currTs = strtotime($time);
                if ($prevTs !== false && $currTs !== false && ($currTs - $prevTs) <= $maxGapSeconds) {
                    $withinGap = true;
                }
            }
        }
        
        if ($current && $sameSpeaker && $withinGap) {
            $current['text'] .= ' ' . $text;
            $current['end_time'] = $time ?: $current['end_time'];
            if ($messageId !== '') {
                $current['message_ids'][] = $messageId;
            }
            continue;
        }
        
        if ($current) {
            $turns[] = $current;
        }
        
        $turnIndex ++;
        
        $current = [
                'turn_index' => $turnIndex,
                'speaker' => $speaker !== '' ? $speaker : 'Unknown',
                'user_id' => $userId,
                'role' => $role,
                'start_time' => $time,
                'end_time' => $time,
                'message_ids' => $messageId !== '' ? [
                        $messageId
                ] : [],
                'text' => $text
        ];
    }
    
    if ($current) {
        $turns[] = $current;
    }
    
    return $turns;
}

function get_recent_turns(array $chat, int $limit = 6): array
{
    $messages = $chat['messages'] ?? [];
    $turns = build_conversation_turns($messages);
    return array_slice($turns, - $limit);
}

function format_turns_for_prompt(array $turns): string
{
    if (! $turns) {
        return '';
    }
    
    $out = "Recent conversation turns:\n";
    
    foreach ($turns as $turn) {
        $speaker = $turn['speaker'] ?? 'Unknown';
        $text = trim((string) ($turn['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        
        $out .= $speaker . ': ' . $text . "\n";
    }
    
    return trim($out);
}

function run_structured_mode(array $messages, string $assistantApiKey, string $mode, array $context = [], string $incomingIntent = '', int $maxOutputTokens = 400): ?array
{
    if (! $messages || $assistantApiKey === '') {
        return null;
    }
    
    $sanitizedMessages = sanitize_openai_input_messages($messages);
    $systemPrompt = build_prompt($sanitizedMessages, $mode, $context, $incomingIntent);
    
    $payload = [
            'model' => 'gpt-5-nano',
            'input' => array_merge(
                    [[
                            'role' => 'system',
                            'content' => $systemPrompt
                    ]],
                    $sanitizedMessages
                    ),
            'max_output_tokens' => $maxOutputTokens,
            'reasoning' => [
                    'effort' => 'minimal'
            ],
            'text' => [
                    'format' => [
                            'type' => 'text'
                    ]
            ]
    ];
    
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $assistantApiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return null;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode < 200 || $httpCode >= 300) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (! is_array($data)) {
        return null;
    }
    
    $raw = extract_output_text($data);
    if ($raw === '') {
        return null;
    }
    
    $parsed = json_decode($raw, true);
    if (! is_array($parsed)) {
        $jsonStr = extract_first_json_object_str($raw);
        if ($jsonStr !== '') {
            $parsed = json_decode($jsonStr, true);
        }
    }

    return is_array($parsed) ? $parsed : null;
}

function run_structured_mode_on_turn_lines(array $turnLines, string $assistantApiKey, string $mode, int $maxOutputTokens = 400): ?array
{
    if (! $turnLines || $assistantApiKey === '') {
        return null;
    }
    
    return run_structured_mode(
            [[
                    'role' => 'user',
                    'content' => implode("\n", $turnLines)
            ]],
            $assistantApiKey,
            $mode,
            ['mode' => $mode],
            '',
            $maxOutputTokens
            );
}

function summarize_turn_block_with_openai(array $turns, string $assistantApiKey): ?array
{
    if (! $turns || $assistantApiKey === '') {
        return null;
    }
    
    $turnLines = [];
    foreach ($turns as $turn) {
        $speaker = trim((string) ($turn['speaker'] ?? 'Unknown'));
        $text = trim((string) ($turn['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $turnLines[] = $speaker . ': ' . $text;
    }
    
    if (! $turnLines) {
        return null;
    }
    
    $parsed = run_structured_mode_on_turn_lines($turnLines, $assistantApiKey, 'turn_block_summary', 500);
    if (! is_array($parsed)) {
        return null;
    }
    
    return [
            'topics' => array_values($parsed['topics'] ?? []),
            'facts' => array_values($parsed['facts'] ?? []),
            'open_points' => array_values($parsed['open_points'] ?? []),
            'tone' => trim((string) ($parsed['tone'] ?? '')),
            'summary' => trim((string) ($parsed['summary'] ?? ''))
    ];
}

function summarize_old_turns_if_needed(array $chat, string $ownerUserId, string $assistantApiKey): array
{
    $chatId = sanitize_chat_id((string) ($chat['id'] ?? ''));
    if ($chatId === '' || $ownerUserId === '' || $assistantApiKey === '') {
        return [
                'updated' => false,
                'reason' => 'missing_requirements'
        ];
    }
    
    $memory = load_chat_memory_for_owner($ownerUserId, $chatId);
    $turns = build_conversation_turns($chat['messages'] ?? []);
    
    if (! $turns) {
        return [
                'updated' => false,
                'reason' => 'no_turns'
        ];
    }
    
    $alreadySummarizedUpto = (int) ($memory['turn_summary_upto'] ?? 0);
    $totalTurns = count($turns);
    
    $keepRecentTurns = 6;
    $minTurnsToSummarize = 5;
    
    $maxSummarizableTurnIndex = $totalTurns - $keepRecentTurns;
    if ($maxSummarizableTurnIndex <= $alreadySummarizedUpto) {
        return [
                'updated' => false,
                'reason' => 'nothing_new_to_summarize'
        ];
    }
    
    $unsummarizedCount = $maxSummarizableTurnIndex - $alreadySummarizedUpto;
    if ($unsummarizedCount < $minTurnsToSummarize) {
        return [
                'updated' => false,
                'reason' => 'below_threshold'
        ];
    }
    
    $blockTurns = [];
    foreach ($turns as $turn) {
        $turnIndex = (int) ($turn['turn_index'] ?? 0);
        if ($turnIndex > $alreadySummarizedUpto && $turnIndex <= $maxSummarizableTurnIndex) {
            $blockTurns[] = $turn;
        }
    }
    
    $maxBlockTurns = 12;
    $blockTurns = array_slice($blockTurns, 0, $maxBlockTurns);
    
    if (! $blockTurns) {
        return [
                'updated' => false,
                'reason' => 'empty_block'
        ];
    }
    
    $summary = summarize_turn_block_with_openai($blockTurns, $assistantApiKey);
    if (! is_array($summary)) {
        return [
                'updated' => false,
                'reason' => 'summary_failed'
        ];
    }
    
    $stable = extract_stable_memory_with_openai($blockTurns, $assistantApiKey);
    
    if (!empty($stable['open_loops'])) {
        $memory['stable_memory']['open_loops'] = merge_memory_items(
                $memory['stable_memory']['open_loops'] ?? [],
                $stable['open_loops']
                );
    }
    
    $priority = extract_priority_memory($blockTurns);
    
    if (!empty($priority)) {
        $memory['stable_memory']['priority'] = merge_memory_items(
                $memory['stable_memory']['priority'] ?? [],
                $priority
                );
    }
    
    if (is_array($stable)) {
        $memory['stable_memory'] = merge_stable_memory(
                $memory['stable_memory'] ?? [
                        'people' => [],
                        'preferences' => [],
                        'facts' => [],
                        'open_loops' => [],
                        'priority' => []
                ],
                $stable
                );
        
        $memory['stable_memory'] = cleanup_resolved_open_loops(
                $memory['stable_memory'],
                $blockTurns
                );
    }
    
    $memory['stable_memory'] = trim_stable_memory($memory['stable_memory']);
    
    $firstTurn = reset($blockTurns);
    $lastTurn = end($blockTurns);
    
    $memory['summary_blocks'][] = [
            'id' => 'sum_' . substr(bin2hex(random_bytes(6)), 0, 12),
            'turn_range' => [
                    (int) ($firstTurn['turn_index'] ?? 0),
                    (int) ($lastTurn['turn_index'] ?? 0)
            ],
            'created_at' => now_str(),
            'topics' => $summary['topics'],
            'facts' => $summary['facts'],
            'open_points' => $summary['open_points'],
            'tone' => $summary['tone'],
            'summary' => $summary['summary']
    ];
    
    $memory['turn_summary_upto'] = (int) ($lastTurn['turn_index'] ?? $alreadySummarizedUpto);
    
    save_chat_memory_for_owner($ownerUserId, $chatId, $memory);
    
    return [
            'updated' => true,
            'turn_summary_upto' => $memory['turn_summary_upto'],
            'summary_block_count' => count($memory['summary_blocks'])
    ];
}

function build_assistant_context(array $chat, array $memory = []): array
{
    return [
            'memory_summary' => $memory['summary_blocks'] ?? [],
            'recent_turns' => get_recent_turns($chat, 6),
            'stable_memory' => $memory['stable_memory'] ?? []
    ];
}

function context_to_prompt_messages(array $context): array
{
    $messages = [];
    
    // 1. Stable memory FIRST
    $stableText = stable_memory_to_text($context['stable_memory'] ?? []);
    if ($stableText !== '') {
        $messages[] = [
                'role' => 'system',
                'content' => $stableText
        ];
    }
    
    // 2. Compact summary SECOND
    $summaryText = build_compact_memory_summary($context['memory_summary'] ?? [], 3);
    if ($summaryText !== '') {
        $messages[] = [
                'role' => 'system',
                'content' => $summaryText
        ];
    }
    
    // 3. Recent turns LAST
    $turnText = format_turns_for_prompt($context['recent_turns'] ?? []);
    if ($turnText !== '') {
        $messages[] = [
                'role' => 'system',
                'content' => $turnText
        ];
    }
    
    return $messages;
}

function sanitize_openai_input_messages(array $messages): array
{
    $clean = [];
    
    foreach ($messages as $msg) {
        if (! is_array($msg)) {
            continue;
        }
        
        $role = trim((string) ($msg['role'] ?? 'user'));
        $content = '';
        
        if (isset($msg['content']) && is_string($msg['content'])) {
            $content = trim($msg['content']);
        } elseif (isset($msg['text']) && is_string($msg['text'])) {
            $content = trim($msg['text']);
        }
        
        if ($content === '') {
            continue;
        }
        
        if (! in_array($role, [
                'system',
                'user',
                'assistant',
                'developer'
        ], true)) {
            $role = 'user';
        }
        
        $clean[] = [
                'role' => $role,
                'content' => $content
        ];
    }
    
    return $clean;
}

function get_chat_search_index_path_for_owner(string $ownerUserId, string $chatId): string
{
    $chatId = sanitize_chat_id($chatId);
    return get_user_base_dir_by_id($ownerUserId) . '/chats/' . $chatId . '.search.csv';
}

function ensure_chat_search_index_exists(string $ownerUserId, string $chatId): string
{
    $path = get_chat_search_index_path_for_owner($ownerUserId, $chatId);
    
    if (!file_exists($path)) {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            json_response(['error' => 'Could not create search index'], 500);
        }
        
        fputcsv($fh, ['message_id', 'chunk_file', 'time', 'text']);
        fclose($fh);
    }
    
    return $path;
}

function normalize_search_text(string $text): string
{
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text ?? '');
    
    if ($text === '') {
        return '';
    }
    
    return mb_strtolower($text, 'UTF-8');
}

function append_message_to_chat_search_index(string $ownerUserId, array $chat, array $message): void
{
    $chatId = sanitize_chat_id((string) ($chat['id'] ?? ''));
    if ($chatId === '') {
        return;
    }
    
    $messageId = trim((string) ($message['id'] ?? ''));
    $text = normalize_search_text((string) ($message['content'] ?? $message['text'] ?? ''));
    $time = trim((string) ($message['time'] ?? ''));
    $chunks = array_values($chat['message_chunks'] ?? []);
    $chunkFile = $chunks ? basename((string) end($chunks)) : '';
    
    if ($messageId === '' || $text === '') {
        return;
    }
    
    $path = ensure_chat_search_index_exists($ownerUserId, $chatId);
    $fh = fopen($path, 'ab');
    if ($fh === false) {
        return;
    }
    
    fputcsv($fh, [$messageId, $chunkFile, $time, $text]);
    fclose($fh);
}

function extract_message_labels_from_input(array $input): array
{
    $rawMeta = $input['meta'] ?? [];
    $messageMeta = is_array($rawMeta) ? $rawMeta : [];

    $labels = $messageMeta['labels'] ?? [];
    if (! is_array($labels)) {
        $labels = [];
    }

    return array_values(array_filter(array_map(function ($label) {
        if (! is_array($label)) {
            return null;
        }

        $type = trim((string) ($label['type'] ?? ''));
        $text = trim((string) ($label['text'] ?? ''));

        if ($type === '' || $text === '') {
            return null;
        }

        return [
                'type' => $type,
                'text' => $text
        ];
    }, $labels)));
}

function get_latest_user_message_content(array $messages): string
{
    for ($i = count($messages) - 1; $i >= 0; $i --) {
        if (($messages[$i]['role'] ?? '') === 'user') {
            return trim((string) ($messages[$i]['content'] ?? ''));
        }
    }

    return '';
}

function persist_latest_user_message_for_chat(array &$chat, string $ownerUserId, bool $isMultiChat, array $messages, array $input): ?array
{
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerUserId);
    if ($ownerUserId === '' || ! is_array($chat) || empty($chat['id'])) {
        return null;
    }

    $lastUserMessage = get_latest_user_message_content($messages);
    if ($lastUserMessage === '') {
        return null;
    }

    $labels = extract_message_labels_from_input($input);

    $newChatMessage = [
            'id' => bin2hex(random_bytes(6)),
            'role' => $isMultiChat ? 'other' : 'user',
            'user_id' => (string) get_current_user_id(),
            'name' => (string) ($_SESSION['user_name'] ?? 'User'),
            'content' => $lastUserMessage,
            'time' => now_str(),
            'recipient_label' => trim((string) ($input['recipient_label'] ?? '')),
            'reply_to' => trim((string) ($input['reply_to'] ?? '')),
            'meta' => [
                    'labels' => $labels
            ]
    ];

    $chat['messages'][] = $newChatMessage;

    if (($chat['title'] ?? 'New chat') === 'New chat' && $lastUserMessage !== '') {
        $chat['title'] = make_chat_title_from_text($lastUserMessage);
    }

    append_message_to_chat_search_index($ownerUserId, $chat, $newChatMessage);
    save_chat_for_owner($chat, $ownerUserId);

    if ($isMultiChat) {
        increment_unread_for_other_participants($chat, (string) get_current_user_id());
    }

    maybe_summarize_chat($chat, $ownerUserId);

    return $newChatMessage;
}

function build_search_result_snippet(string $text, string $query, int $radius = 70): string
{
    $text = trim($text);
    $query = trim($query);
    
    if ($text === '') {
        return '';
    }
    
    $pos = mb_stripos($text, $query, 0, 'UTF-8');
    
    if ($pos === false) {
        return mb_strlen($text, 'UTF-8') > 140
        ? mb_substr($text, 0, 140, 'UTF-8') . '…'
                : $text;
    }
    
    $start = max(0, $pos - $radius);
    $length = mb_strlen($query, 'UTF-8') + ($radius * 2);
    $snippet = mb_substr($text, $start, $length, 'UTF-8');
    
    if ($start > 0) {
        $snippet = '…' . $snippet;
    }
    
    if (($start + $length) < mb_strlen($text, 'UTF-8')) {
        $snippet .= '…';
    }
    
    return $snippet;
}

function search_chat_messages_in_csv_for_owner(string $ownerUserId, string $chatId, string $query, int $limit = 30): array
{
    $chatId = sanitize_chat_id($chatId);
    $query = normalize_search_text($query);
    
    if ($chatId === '' || $query === '') {
        return [];
    }
    
    $path = get_chat_search_index_path_for_owner($ownerUserId, $chatId);
    if (!file_exists($path)) {
        return [];
    }
    
    $chat = load_chat_for_owner($ownerUserId, $chatId);
    if (!is_array($chat)) {
        return [];
    }
    
    $messagesById = [];
    foreach (($chat['messages'] ?? []) as $msg) {
        $id = trim((string) ($msg['id'] ?? ''));
        if ($id !== '') {
            $messagesById[$id] = $msg;
        }
    }
    
    $results = [];
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return [];
    }
    
    $header = fgetcsv($fh);
    
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) < 4) {
            continue;
        }
        
        [$messageId, $chunkFile, $time, $text] = $row;
        
        if ($text === '' || mb_stripos($text, $query, 0, 'UTF-8') === false) {
            continue;
        }
        
        $msg = $messagesById[$messageId] ?? null;
        
        $results[] = [
                'message_id' => (string) $messageId,
                'chunk_file' => (string) $chunkFile,
                'time' => (string) $time,
                'snippet' => build_search_result_snippet((string) $text, $query),
                'name' => trim((string) ($msg['name'] ?? '')),
                'role' => trim((string) ($msg['role'] ?? '')),
                'is_me' => ((string) ($msg['user_id'] ?? '') === (string) get_current_user_id())
        ];
    }
    
    fclose($fh);
    
    usort($results, function ($a, $b) {
        return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
    });
        
        return array_slice($results, 0, max(1, min(100, $limit)));
}

function search_sidebar_chats_for_current_user(string $query): array
{
    $query = normalize_search_text($query);
    
    if ($query === '') {
        return [];
    }
    
    $chatRefs = array_merge(
            get_chats_for_current_user_by_state('active'),
            get_chats_for_current_user_by_state('archived')
            );
    
    $results = [];
    $seenChatIds = [];
    
    foreach ($chatRefs as $chat) {
        $chatId = sanitize_chat_id((string) ($chat['id'] ?? ''));
        if ($chatId === '' || isset($seenChatIds[$chatId])) {
            continue;
        }
        $seenChatIds[$chatId] = true;
        
        $ownerUserId = (string) (($chat['is_owner'] ?? false) ? get_current_user_id() : ($chat['member_of'] ?? ''));
        if ($ownerUserId === '') {
            continue;
        }
        
        $path = get_chat_search_index_path_for_owner($ownerUserId, $chatId);
        if (!file_exists($path)) {
            continue;
        }
        
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            continue;
        }
        
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) {
                continue;
            }
            
            [$messageId, $chunkFile, $time, $text] = $row;
            
            $text = (string) $text;
            if ($text === '' || mb_stripos($text, $query, 0, 'UTF-8') === false) {
                continue;
            }
            
            $chatItem = ensure_chat_ref_defaults($chat);
            $chatItem['preview'] = build_search_result_snippet($text, $query);
            $chatItem['updated_at'] = (string) $time;
            $chatItem['search_message_id'] = (string) $messageId;
            
            $results[] = $chatItem;
        }
        
        fclose($fh);
    }
    
    usort($results, function ($a, $b) {
        return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
    });
        
        return $results;
}

function normalize_open_issue_type($value): int
{
    $type = (int) $value;
    return in_array($type, [1, 2, 3, 4, 5], true) ? $type : 1;
}

function should_auto_resolve_open_issue_type($value): bool
{
    $type = (int) $value;
    return in_array($type, [2, 3], true);
}

function fallback_open_issue_text(string $text): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    
    if ($text === '') {
        return 'Needs attention';
    }
    
    if (mb_strlen($text) <= 60) {
        return $text;
    }
    
    $short = mb_substr($text, 0, 60);
    $lastSpace = mb_strrpos($short, ' ');
    
    if ($lastSpace !== false && $lastSpace > 20) {
        $short = mb_substr($short, 0, $lastSpace);
    }
    
    return rtrim($short, " ,.;:-") . '…';
}

function build_open_issue_text(array $chat, array $message): string
{
    $text = trim((string) ($message['content'] ?? $message['text'] ?? ''));
    if ($text === '') {
        return 'Needs attention';
    }
    
    $assistantApiKey = get_server_assistant_api_key();
    if ($assistantApiKey === '') {
        return fallback_open_issue_text($text);
    }
    
    $messages = [[
            'role' => 'user',
            'content' => $text
    ]];
    
    $promptUserProfile = load_current_user_profile();
    $promptContext = build_conversation_prompt_context($chat, $messages, $promptUserProfile, 'open_issue', []);
    $parsed = run_structured_mode($messages, $assistantApiKey, 'open_issue', $promptContext, '', 80);
    
    $content = trim((string) ($parsed['content'] ?? ''));
    return $content !== '' ? $content : fallback_open_issue_text($text);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (! is_array($input)) {
    $input = [];
}

$action = $_GET['action'] ?? $input['action'] ?? 'send_message';

if ($action === 'create_chat') {
    require_login();
    $title = trim((string) ($input['title'] ?? ''));
    $chat = create_new_chat($title);
    json_response([
            'ok' => true,
            'chat_id' => $chat['id'],
            'chat' => $chat
    ]);
}

if ($action === 'open_or_create_private_chat') {
    require_login();
    $contactId = trim((string) ($input['contact_id'] ?? $_GET['contact_id'] ?? ''));
    $result = open_or_create_private_chat_for_contact_id($contactId);

    json_response([
            'ok' => true,
            'chat_id' => $result['chat_id'],
            'chat' => $result['chat'],
            'created' => ! empty($result['created'])
    ]);
}

if ($action === 'list_chats') {
    require_login();
    json_response([
            'ok' => true,
            'current_chat_id' => $_SESSION['current_chat_id'] ?? null,
            'chats' => get_visible_chats_for_current_user(),
            'archived_chats' => get_archived_chats_for_current_user()
    ]);
}

if ($action === 'load_chat') {
    require_login();
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat_id'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            json_response([
                    'ok' => false,
                    'removed_orphan' => true,
                    'current_chat_id' => $_SESSION['current_chat_id'] ?? null,
                    'silent' => true
            ]);
        }
        maybe_chat_not_found('Chat not found');
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    if (! $chat) {
        cleanup_orphan_chat_reference_for_user((string) $ref['owner_user_id'], $chatId);
        json_response([
                'ok' => false,
                'removed_orphan' => true,
                'current_chat_id' => $_SESSION['current_chat_id'] ?? null,
                'silent' => true
        ]);
    }
    
    $allChunkFiles = array_values($chat['message_chunks'] ?? []);
    $recentChunkFiles = get_chat_recent_chunk_files($chat, get_chat_initial_load_chunk_count());
    $chat['messages'] = load_chat_messages_for_owner((string) $ref['owner_user_id'], $chat, $recentChunkFiles);
    $chat['messages'] = filter_chat_messages_for_user_by_membership(
        $chat,
        $chat['messages'],
        (string) get_current_user_id(),
        (string) $ref['owner_user_id']
    );
    $chat['loaded_chunk_files'] = $recentChunkFiles;
    $chat['has_older_chunks'] = count($allChunkFiles) > count($recentChunkFiles);
    
    remove_persisted_assistant_messages($chat);
    
    mark_chat_as_read_for_current_user($chatId);
    $_SESSION['current_chat_id'] = $chatId;
    
    json_response([
            'ok' => true,
            'chat' => $chat
    ]);
}

if ($action === 'load_chat_chunk') {
    require_login();
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    $beforeChunkFile = basename((string) ($_GET['before_chunk_file'] ?? $input['before_chunk_file'] ?? ''));
    
    if ($chatId === '' || $beforeChunkFile === '') {
        json_response([
                'error' => 'Missing parameters'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            json_response([
                    'ok' => false,
                    'removed_orphan' => true,
                    'silent' => true
            ]);
        }
        maybe_chat_not_found('Chat not found');
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    if (! $chat) {
        cleanup_orphan_chat_reference_for_user((string) $ref['owner_user_id'], $chatId);
        json_response([
                'ok' => false,
                'removed_orphan' => true,
                'silent' => true
        ]);
    }
    
    $chunkFile = get_chat_previous_chunk_file($chat, $beforeChunkFile);
    if (! $chunkFile) {
        json_response([
                'ok' => true,
                'chat_id' => $chatId,
                'loaded_chunk_file' => null,
                'messages' => [],
                'has_older_chunks' => false
        ]);
    }
    
    $messages = load_chat_messages_for_owner((string) $ref['owner_user_id'], $chat, [$chunkFile]);
    $messages = filter_chat_messages_for_user_by_membership(
        $chat,
        $messages,
        (string) get_current_user_id(),
        (string) $ref['owner_user_id']
    );
    $hasOlderChunks = get_chat_previous_chunk_file($chat, $chunkFile) !== null;
    
    remove_persisted_assistant_messages($chat);
    $messages = array_values(array_filter($messages, function ($msg) {
        return (($msg['role'] ?? '') !== 'assistant');
    }));

    $visibleTarget = false;
    foreach ($messages as $msg) {
        if (trim((string) ($msg['id'] ?? '')) === $messageId) {
            $visibleTarget = true;
            break;
        }
    }

    if (!$visibleTarget) {
        json_response([
                'ok' => false,
                'error' => 'Message not found'
        ], 404);
    }
        
        json_response([
                'ok' => true,
                'chat_id' => $chatId,
                'loaded_chunk_file' => $chunkFile,
                'messages' => $messages,
                'has_older_chunks' => $hasOlderChunks
        ]);
}

if ($action === 'load_chat_chunk_by_message_id') {
    require_login();
    
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    $messageId = trim((string) ($_GET['message_id'] ?? $input['message_id'] ?? ''));
    
    if ($chatId === '' || $messageId === '') {
        json_response([
                'error' => 'Missing parameters'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            json_response([
                    'ok' => false,
                    'removed_orphan' => true,
                    'silent' => true
            ]);
        }
        maybe_chat_not_found('Chat not found');
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    if (! $chat) {
        cleanup_orphan_chat_reference_for_user((string) $ref['owner_user_id'], $chatId);
        json_response([
                'ok' => false,
                'removed_orphan' => true,
                'silent' => true
        ]);
    }
    
    $chunkFile = get_chat_chunk_file_by_message_id((string) $ref['owner_user_id'], $chat, $messageId);
    
    if (! $chunkFile) {
        json_response([
                'ok' => false,
                'error' => 'Message not found'
        ], 404);
    }
    
    $messages = load_chat_messages_for_owner((string) $ref['owner_user_id'], $chat, [$chunkFile]);
    $messages = filter_chat_messages_for_user_by_membership(
        $chat,
        $messages,
        (string) get_current_user_id(),
        (string) $ref['owner_user_id']
    );
    $hasOlderChunks = get_chat_previous_chunk_file($chat, $chunkFile) !== null;
    
    $messages = array_values(array_filter($messages, function ($msg) {
        return (($msg['role'] ?? '') !== 'assistant');
    }));
        
        json_response([
                'ok' => true,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'loaded_chunk_file' => $chunkFile,
                'messages' => $messages,
                'has_older_chunks' => $hasOlderChunks
        ]);
}

if ($action === 'search_sidebar_chats') {
    require_login();
    $q = trim((string) ($_GET['q'] ?? $input['q'] ?? ''));
    
    json_response([
            'ok' => true,
            'items' => search_sidebar_chats_for_current_user($q)
    ]);
}

if ($action === 'search_chat_messages') {
    require_login();
    
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? $_GET['chat_id'] ?? ''));
    $query = trim((string) ($input['q'] ?? $_GET['q'] ?? ''));
    $limit = (int) ($input['limit'] ?? $_GET['limit'] ?? 30);
    
    if ($chatId === '') {
        json_response(['error' => 'Missing chat_id'], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (!$ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            json_response([
                    'ok' => false,
                    'removed_orphan' => true,
                    'silent' => true
            ]);
        }
        maybe_chat_not_found('Chat not found');
    }
    
    $results = search_chat_messages_in_csv_for_owner(
            (string) $ref['owner_user_id'],
            $chatId,
            $query,
            $limit
            );
    
    json_response([
            'ok' => true,
            'chat_id' => $chatId,
            'items' => $results
    ]);
}

if ($action === 'delete_chat') {
    require_login();
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat_id'
        ], 400);
    }
    $newCurrent = delete_chat_by_id($chatId);
    json_response([
            'ok' => true,
            'current_chat_id' => $newCurrent
    ]);
}

if ($action === 'restore_chat') {
    require_login();
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat_id'
        ], 400);
    }
    $currentChat = restore_chat_by_id($chatId);
    json_response([
            'ok' => true,
            'current_chat_id' => $currentChat
    ]);
}

if ($action === 'archive_chat') {
    require_login();
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat_id'
        ], 400);
    }
    $newCurrent = archive_chat_by_id($chatId);
    json_response([
            'ok' => true,
            'current_chat_id' => $newCurrent
    ]);
}

if ($action === 'leave_chat') {
    require_login();
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat_id'
        ], 400);
    }
    $newCurrent = leave_chat_by_id($chatId);
    json_response([
            'ok' => true,
            'current_chat_id' => $newCurrent
    ]);
}

if ($action === 'remove_chat_participant') {
    require_login();
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? $_GET['chat_id'] ?? ''));
    $contactId = trim((string) ($input['contact_id'] ?? $_GET['contact_id'] ?? ''));

    $result = remove_chat_participant_by_contact_id($chatId, $contactId);

    json_response([
            'ok' => true,
            'chat_id' => $result['chat_id'],
            'contact_id' => $result['contact_id']
    ]);
}

if ($action === 'toggle_chat_participant_access') {
    require_login();
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? $_GET['chat_id'] ?? ''));
    $memberId = trim((string) ($input['member_id'] ?? $input['participant_id'] ?? $_GET['member_id'] ?? $_GET['participant_id'] ?? $input['contact_id'] ?? $_GET['contact_id'] ?? ''));
    $memberActive = array_key_exists('target_active', $input)
        ? (!empty($input['target_active']) ? 1 : 0)
        : (!empty($input['member_active']) ? 1 : 0);

    $result = toggle_chat_participant_access_by_member_id($chatId, $memberId, $memberActive);

    json_response([
            'ok' => true,
            'chat_id' => $result['chat_id'],
            'member_id' => $result['member_id'],
            'participant_id' => $result['participant_id'],
            'contact_id' => $result['contact_id'],
            'is_active' => (int) ($result['is_active'] ?? 0),
            'member_active' => (int) ($result['is_active'] ?? 0),
            'chat' => $result['chat']
    ]);
}

if ($action === 'delete_chat_permanently') {
    require_login();
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat_id'
        ], 400);
    }
    
    $newCurrent = permanently_delete_chat_by_id($chatId);
    
    json_response([
            'ok' => true,
            'current_chat_id' => $newCurrent
    ]);
}

if ($action === 'rename_chat') {
    require_login();
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? $_GET['chat_id'] ?? ''));
    $title = (string) ($input['title'] ?? $_GET['title'] ?? '');
    
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat_id'
        ], 400);
    }
    
    $renamed = rename_chat_by_id($chatId, $title);
    json_response([
            'ok' => true,
            'chat_id' => $renamed['chat_id'],
            'title' => $renamed['title'],
            'updated_at' => $renamed['updated_at']
    ]);
}

if ($action === 'update_chat_writing_personality') {
    require_login();
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? ''));
    $personality = trim((string) ($input['writing_personality'] ?? ''));

    $validPersonalities = ['corporate_friendly', 'corporate_direct', 'polite_thoughtful', 'neutral_practical', 'casual_friendly', 'casual_direct', 'playful_light', 'bold_confident'];

    if ($chatId === '') {
        json_response(['error' => 'Missing chat_id'], 400);
    }

    if ($personality !== '' && !in_array($personality, $validPersonalities, true)) {
        json_response(['error' => 'Invalid writing personality'], 400);
    }

    $ref = resolve_chat_reference_for_current_user($chatId);
    if (!$ref || empty($ref['is_owner'])) {
        json_response(['error' => 'Not found or not owner'], 403);
    }

    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    if (!is_array($chat)) {
        maybe_chat_not_found('Chat not found');
    }

    if ($personality !== '') {
        $chat['writing_personality'] = $personality;
    } else {
        unset($chat['writing_personality']);
    }

    save_chat_for_owner($chat, (string) $ref['owner_user_id']);

    json_response([
            'ok' => true,
            'chat_id' => $chatId,
            'writing_personality' => $personality
    ]);
}

if ($action === 'edit_message') {
    require_login();
    
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? ''));
    $messageId = trim((string) ($input['message_id'] ?? ''));
    $content = trim((string) ($input['content'] ?? ''));
    
    if ($chatId === '' || $messageId === '') {
        json_response([
                'error' => 'Missing parameters'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            json_response([
                    'ok' => false,
                    'removed_orphan' => true,
                    'silent' => true
            ]);
        }
        maybe_chat_not_found('Chat not found');
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    $ownerUserId = (string) $ref['owner_user_id'];
    
    foreach ($chat['messages'] as &$msg) {
        if (($msg['id'] ?? '') === $messageId) {
            $msg['content'] = $content;
            $msg['edited_at'] = now_str();
            break;
        }
    }
    unset($msg);
    
    save_chat_for_owner($chat, $ownerUserId);
    
    maybe_summarize_chat($chat, $ownerUserId);
    
    json_response([
            'ok' => true,
            'chat_id' => $chatId
    ]);
}

if ($action === 'delete_message') {
    require_login();
    
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? ''));
    $messageId = trim((string) ($input['message_id'] ?? ''));
    
    if ($chatId === '' || $messageId === '') {
        json_response([
                'error' => 'Missing parameters'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        if (cleanup_orphan_chat_reference_for_current_user($chatId)) {
            json_response([
                    'ok' => false,
                    'removed_orphan' => true,
                    'silent' => true
            ]);
        }
        maybe_chat_not_found('Chat not found');
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    $ownerUserId = (string) $ref['owner_user_id'];
    
    $chat['messages'] = array_values(array_filter($chat['messages'] ?? [], function ($msg) use ($messageId) {
        return (($msg['id'] ?? '') !== $messageId);
    }));
        
        save_chat_for_owner($chat, $ownerUserId);
        
        maybe_summarize_chat($chat, $ownerUserId);
        
        json_response([
                'ok' => true,
                'chat_id' => $chatId
        ]);
}

if ($action === 'list_contacts') {
    require_login();
    $userId = (string) get_current_user_id();
    $payload = load_user_contacts_by_id($userId);
    json_response([
            'ok' => true,
            'contacts' => contacts_records_for_response($payload)
    ]);
}

if ($action === 'get_public_profiles') {
    require_login();

    $rawIds = (string) ($_GET['ids'] ?? $input['ids'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map(
        static function ($value) {
            return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value);
        },
        preg_split('/\s*,\s*/', $rawIds) ?: []
    ), static function ($value) {
        return $value !== '';
    })));

    $profiles = [];
    foreach ($ids as $id) {
        $profile = load_user_profile_by_id((string) $id);
        $user = is_array($profile['user'] ?? null) ? $profile['user'] : [];
        if (!$user) {
            continue;
        }

        $name = trim((string) ($user['name'] ?? ''));
        $avatar = trim((string) ($user['avatar'] ?? ''));
        $profileVersion = (int) ($user['profile_version'] ?? 0);
        if ($profileVersion < 0) {
            $profileVersion = 0;
        }

        $profiles[$id] = [
            'user_id' => (string) $id,
            'name' => $name,
            'avatar' => $avatar,
            'initials' => trim((string) ($user['initials'] ?? make_initials($name !== '' ? $name : 'User'))),
            'profile_version' => $profileVersion,
        ];
    }

    json_response([
        'ok' => true,
        'profiles' => $profiles,
    ]);
}

if ($action === 'create_invite') {
    require_login();
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $email = normalize_email_local((string) ($input['email'] ?? ''));
    
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat id.'
        ], 400);
    }

    if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response([
                'error' => 'Please enter a valid email address.'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref || empty($ref['is_owner'])) {
        json_response([
                'error' => 'Only the owner can invite people to this chat.'
        ], 403);
    }
    
    $ownerProfile = load_current_user_profile();
    $existing = $email !== '' ? load_user_profile_by_email_local($email) : [];

    // Block inviting hidden users
    if ($existing && is_user_hidden($existing)) {
        json_response([
            'ok' => false,
            'error' => 'This user is not available.'
        ], 403);
    }

    if ($email !== '') {
        add_or_update_contact($ownerProfile, $name !== '' ? $name : ($existing['user']['name'] ?? 'Guest'), $email, (string) ($existing['user']['id'] ?? ''), ! empty($existing['user']['email_verified']));
        save_current_user_profile($ownerProfile);
    }
    
    $invite = create_invite_record($chatId, $name !== '' ? $name : 'Guest', $email);
    if ($email !== '' && $existing && ! empty($existing['user']['id'])) {
        add_pending_invite_to_profile($existing, [
                'invite_id' => $invite['invite_id'],
                'chat_id' => $chatId,
                'owner_user_id' => (string) get_current_user_id(),
                'email' => $email,
                'name' => $name,
                'created_at' => $invite['created_at'],
                'status' => 'pending'
        ]);
        save_user_profile_by_id((string) $existing['user']['id'], $existing);
    }
    
    $mail = [
            'sent' => false,
            'debug_link' => $app_base_url . build_invite_path((string) $invite['invite_id']),
            'error' => null
    ];
    if ($email !== '') {
        $mail = send_invite_email($invite);
    }
    
    json_response([
            'ok' => true,
            'invite_id' => $invite['invite_id'],
            'invite_path' => build_invite_path((string) ($invite['invite_id'] ?? '')),
            'invite_link' => $app_base_url . build_invite_path((string) ($invite['invite_id'] ?? '')),
            'debug_link' => $mail['debug_link'],
            'email_sent' => ! empty($mail['sent']),
            'mail_error' => $mail['error'] ?? null
    ]);
}

if ($action === 'accept_guest_join') {
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    $displayName = trim((string) ($input['display_name'] ?? ''));
    $uid = trim((string) ($_GET['uid'] ?? $input['uid'] ?? $input['member_id'] ?? $input['participant_id'] ?? ''));
    $inviteId = sanitize_invite_id((string) ($_GET['invite_id'] ?? $input['invite_id'] ?? ''));

    $result = create_guest_join_response($chatId, $displayName, $uid, $inviteId);
    if (! empty($result['ok'])) {
        json_response($result);
    }

    json_response($result, 400);
}

if ($action === 'join_shared_chat') {
    require_login();
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    $uid = trim((string) ($_GET['uid'] ?? $input['uid'] ?? $input['member_id'] ?? $input['participant_id'] ?? ''));
    $result = join_shared_chat_for_current_user($chatId, $uid);
    if (! empty($result['ok'])) {
        json_response($result);
    }

    json_response($result, 400);
}

if ($action === 'submit_feedback') {
    $rating  = trim((string) ($input['rating']  ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    $url     = trim((string) ($input['url']     ?? ''));
    $ua      = trim((string) ($input['ua']      ?? $_SERVER['HTTP_USER_AGENT'] ?? ''));
    $userId  = trim((string) ($input['user_id'] ?? get_current_user_id() ?? ''));
    $ts      = date('Y-m-d H:i:s');

    if ($rating !== 'up' && $rating !== 'down') {
        json_response(['ok' => false, 'error' => 'Invalid rating.'], 400);
    }

    $ratingLabel = $rating === 'up' ? '👍 Positive' : '👎 Negative';
    $textBody = implode("\n", [
        "Rating:    {$ratingLabel}",
        "Message:   " . ($message !== '' ? $message : '(none)'),
        "User:      " . ($userId !== '' ? $userId : 'guest'),
        "URL:       {$url}",
        "Browser:   {$ua}",
        "Time:      {$ts}",
    ]);
    $htmlBody = '<pre style="font-family:monospace;font-size:14px">' . htmlspecialchars($textBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';

    require_once __DIR__ . '/auth/mailer.php';
    $feedbackEmail = secret('FEEDBACK_TO_EMAIL');
    $feedbackName  = secret('FEEDBACK_TO_NAME') ?: 'Feedback';
    if ($feedbackEmail !== '') {
        $sent = send_email($feedbackEmail, $feedbackName, 'Tsjilp feedback', $textBody, $htmlBody);
    } else {
        $sent = false;
        error_log('[feedback] FEEDBACK_TO_EMAIL not configured — feedback dropped');
    }

    json_response(['ok' => $sent]);
}

if ($action === 'resolve_member_token') {
    $token = trim((string) ($_GET['member_token'] ?? $input['member_token'] ?? $_SESSION['guest_member_token'] ?? ''));
    $result = resolve_member_token_response($token);
    if (! empty($result['ok'])) {
        json_response($result);
    }

    json_response($result, 404);
}

if ($action === 'prepare_uid_invite') {
    require_login();
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? $_GET['chat_id'] ?? ''));
    $uid = trim((string) ($input['uid'] ?? $_GET['uid'] ?? $input['member_id'] ?? $input['participant_id'] ?? ''));

    if ($chatId === '' || $uid === '') {
        json_response([
                'ok' => false,
                'error' => 'Missing chat_id or uid'
        ], 400);
    }

    $profile = load_current_user_profile();
    if (is_invited_member_profile($profile)) {
        json_response([
                'ok' => false,
                'error' => 'Guests cannot add members.'
        ], 403);
    }

    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        json_response([
                'ok' => false,
                'error' => 'Chat not found'
        ], 404);
    }

    $currentUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) get_current_user_id());
    $result = ensure_chat_participant_for_uid($chatId, $uid, true, $currentUserId);
    if (empty($result['ok'])) {
        json_response($result, 400);
    }

    $contactId = trim((string) ($result['contact_id'] ?? ''));
    if ($contactId === '' || empty($result['is_in_chat'])) {
        json_response([
                'ok' => false,
                'error' => 'Participant not found in chat.'
        ], 404);
    }

        $invitePath = '/?chat=' . rawurlencode($chatId) . '&uid=' . rawurlencode($contactId);
        $baseUrl = rtrim((string) ($GLOBALS['app_base_url'] ?? ''), '/');
    json_response([
            'ok' => true,
            'chat_id' => $chatId,
            'uid' => $contactId,
            'contact_id' => $contactId,
            'invite_path' => $invitePath,
            'invite_link' => ($baseUrl !== '' ? $baseUrl : '/') . $invitePath,
            'chat' => $result['chat'] ?? []
    ]);
}

if ($action === 'accept_invite') {
    require_login();
    $inviteId = sanitize_invite_id((string) ($input['invite_id'] ?? $_SESSION['pending_invite_id'] ?? ''));
    if ($inviteId === '') {
        json_response([
                'error' => 'Missing invite_id'
        ], 400);
    }
    $result = accept_invite_for_current_user($inviteId);
    if (! empty($result['ok'])) {
        json_response($result);
    }
    json_response($result, ! empty($result['needs_verification']) ? 409 : 400);
}

if ($action === 'consume_pending_invites') {
    require_login();
    $accepted = consume_pending_invites_for_current_user();
    json_response([
            'ok' => true,
            'accepted' => $accepted,
            'pending_invite_id' => $_SESSION['pending_invite_id'] ?? null
    ]);
}

if ($action === 'get_public_chat_participant_names') {
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    if ($chatId === '') {
        json_response([
                'ok' => false,
                'error' => 'Missing chat_id'
        ], 400);
    }

    $owner = find_chat_owner_for_chat_id($chatId);
    $ownerUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($owner['owner_user_id'] ?? ''));
    if ($ownerUserId === '') {
        json_response([
                'ok' => false,
                'error' => 'Chat not found'
        ], 404);
    }

    $chat = load_chat_for_owner($ownerUserId, $chatId);
    if (!is_array($chat)) {
        json_response([
                'ok' => false,
                'error' => 'Chat not found'
        ], 404);
    }

    json_response([
            'ok' => true,
            'chat_id' => $chatId,
            'names' => get_chat_participant_display_names($chat, $ownerUserId)
    ]);
}

if ($action === 'get_invite') {
    $inviteId = sanitize_invite_id((string) ($_GET['invite_id'] ?? $input['invite_id'] ?? $_SESSION['pending_invite_id'] ?? ''));
    if ($inviteId === '') {
        json_response([
                'error' => 'Missing invite_id'
        ], 400);
    }
    $invite = load_invite_record($inviteId);
    if (! $invite) {
        json_response([
                'error' => 'Invite not found'
        ], 404);
    }
    json_response([
            'ok' => true,
            'invite' => [
                    'invite_id' => $invite['invite_id'],
                    'chat_id' => $invite['chat_id'],
                'invite_kind' => (string) ($invite['invite_kind'] ?? 'account'),
                'status' => (string) ($invite['status'] ?? 'pending'),
                    'name' => $invite['name'] ?? '',
                    'email' => $invite['email'] ?? '',
                'owner_name' => $invite['owner_name'] ?? '',
                'used_at' => $invite['used_at'] ?? '',
                'used_guest_token' => $invite['used_guest_token'] ?? ''
            ]
    ]);
}

if ($action === 'get_chat_memory') {
    require_login();
    
    //     if (!is_debug_mode()) {
    //         json_response([
    //             'error' => 'Debug mode is disabled'
    //         ], 403);
    //     }
    
    $chatId = sanitize_chat_id((string) ($_GET['chat_id'] ?? $input['chat_id'] ?? ''));
    if ($chatId === '') {
        json_response([
                'error' => 'Missing chat_id'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (!$ref) {
        maybe_chat_not_found('Chat not found');
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    $ownerUserId = (string) $ref['owner_user_id'];
    $memory = load_chat_memory_for_owner($ownerUserId, $chatId);
    $context = build_assistant_context($chat, $memory);
    $contextMessages = context_to_prompt_messages($context);
    
    json_response([
            'ok' => true,
            'chat_id' => $chatId,
            'turn_summary_upto' => $memory['turn_summary_upto'] ?? 0,
            'summary_blocks' => $memory['summary_blocks'] ?? [],
            'stable_memory' => $memory['stable_memory'] ?? [],
            'context_messages' => $contextMessages
    ]);
}

if ($action === 'get_chat_open_issues') {
    require_login();
    
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? ''));
    $offset = max(0, (int) ($input['offset'] ?? 0));
    $limit  = max(1, min(20, (int) ($input['limit'] ?? 5)));
    
    if ($chatId === '') {
        json_response(['items' => [], 'has_more' => false, 'total' => 0, 'count' => 0]);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        json_response(['items' => [], 'has_more' => false, 'total' => 0, 'count' => 0]);
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    if (! $chat) {
        json_response(['items' => [], 'has_more' => false, 'total' => 0, 'count' => 0]);
    }
    
    $messages = $chat['messages'] ?? [];
    $messageMap = [];
    
    foreach ($messages as $msg) {
        $id = trim((string) ($msg['id'] ?? ''));
        if ($id !== '') {
            $messageMap[$id] = $msg;
        }
    }
    
    $ownerUserId = (string) $ref['owner_user_id'];
    $chatOpenIssues = load_chat_open_issues_for_owner($ownerUserId, $chatId);
    $items = [];
    
    if (is_array($chatOpenIssues)) {
        foreach ($chatOpenIssues as $messageId => $entry) {
            $msg = $messageMap[$messageId] ?? null;
            if (!is_array($msg)) {
                continue;
            }
            
            $items[] = [
                    'text' => trim((string) ($entry['text'] ?? $msg['content'] ?? $msg['text'] ?? '')),
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'type' => normalize_open_issue_type($entry['type'] ?? 1),
                    'name' => trim((string) ($msg['name'] ?? '')),
                    'time' => trim((string) ($entry['time'] ?? $msg['time'] ?? ''))
            ];
        }
    }
    
    usort($items, function ($a, $b) {
        return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
    });
        
        $total = count($items);
        $slice = array_slice($items, $offset, $limit);
        
        json_response([
                'items' => array_values($slice),
                'has_more' => ($offset + $limit) < $total,
                'next_offset' => $offset + $limit,
                'total' => $total,
                'count' => $total
        ]);
}

if ($action === 'get_all_open_issues') {
    require_login();
    
    $limitPerChat = max(1, min(5, (int) ($input['limit_per_chat'] ?? 2)));
    $maxChats     = max(1, min(50, (int) ($input['max_chats'] ?? 20)));
    
    $visibleChats = get_visible_chats_for_current_user();
    $openIssuesByChat = [];
    
    $result = [];
    
    foreach ($visibleChats as $chat) {
        
        if (count($result) >= $maxChats) {
            break;
        }
        
        $chatId = sanitize_chat_id((string) ($chat['id'] ?? ''));
        if (!$chatId) continue;
        
        $ownerUserId = (string) ($chat['is_owner'] ? get_current_user_id() : ($chat['member_of'] ?? ''));
        if ($ownerUserId === '') {
            $ref = resolve_chat_reference_for_current_user($chatId);
            if (! $ref) continue;
            $ownerUserId = (string) $ref['owner_user_id'];
        }

        if (!isset($openIssuesByChat[$chatId])) {
            $openIssuesByChat[$chatId] = load_chat_open_issues_for_owner($ownerUserId, $chatId);
        }

        $chatOpenIssues = $openIssuesByChat[$chatId] ?? [];
        if (!$chatOpenIssues) continue;
        
        $ref = resolve_chat_reference_for_current_user($chatId);
        if (! $ref) continue;
        
        $chatData = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
        if (! $chatData) continue;
        
        $messages = $chatData['messages'] ?? [];
        $messageMap = [];
        
        foreach ($messages as $msg) {
            $id = trim((string) ($msg['id'] ?? ''));
            if ($id !== '') {
                $messageMap[$id] = $msg;
            }
        }
        
        $items = [];
        
        foreach ($chatOpenIssues as $messageId => $entry) {
            
            $msg = $messageMap[$messageId] ?? null;
            if (!$msg) continue;
            
            $items[] = [
                    'text' => trim((string) ($entry['text'] ?? $msg['content'] ?? '')),
                    'message_id' => $messageId,
                    'type' => normalize_open_issue_type($entry['type'] ?? 1),
                    'name' => trim((string) ($msg['name'] ?? '')),
                    'time' => trim((string) ($entry['time'] ?? $msg['time'] ?? ''))
            ];
            
            if (count($items) >= $limitPerChat) {
                break;
            }
        }
        
        if ($items) {
            $result[] = [
                    'chat_id' => $chatId,
                    'title'   => $chat['title'] ?? 'Chat',
                    'items'   => $items
            ];
        }
    }
    
    json_response([
            'chats' => $result
    ]);
}

if ($action === 'toggle_open_issue') {
    require_login();
    
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? ''));
    $messageId = trim((string) ($input['message_id'] ?? ''));
    $openIssue = !empty($input['open_issue']);
    $openIssueType = normalize_open_issue_type($input['open_issue_type'] ?? 1);
    
    if ($chatId === '' || $messageId === '') {
        json_response([
                'error' => 'Missing chat_id or message_id'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        maybe_chat_not_found('Chat not found');
    }
    
    $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
    if (! $chat) {
        maybe_chat_not_found('Chat not found');
    }
    
    $messages = $chat['messages'] ?? [];
    $targetMessage = null;
    
    foreach ($messages as $msg) {
        if (trim((string) ($msg['id'] ?? '')) === $messageId) {
            $targetMessage = $msg;
            break;
        }
    }
    
    if (!is_array($targetMessage)) {
        json_response([
                'error' => 'Message not found'
        ], 404);
    }
    
    $time = now_str();
    $ownerUserId = (string) $ref['owner_user_id'];
    $chatOpenIssues = load_chat_open_issues_for_owner($ownerUserId, $chatId);
    
    if ($openIssue) {
        $chatOpenIssues[$messageId] = [
                'type' => $openIssueType,
                'text' => build_open_issue_text($chat, $targetMessage),
                'time' => $time
        ];
    } else {
        unset($chatOpenIssues[$messageId]);
    }

    save_chat_open_issues_for_owner($ownerUserId, $chatId, $chatOpenIssues);
    
    $entry = is_array($chatOpenIssues[$messageId] ?? null) ? $chatOpenIssues[$messageId] : null;
    $items = [];
    
    if (is_array($chatOpenIssues)) {
        foreach ($chatOpenIssues as $mid => $issueEntry) {
            $items[] = [
                    'text' => trim((string) ($issueEntry['text'] ?? '')),
                    'chat_id' => $chatId,
                    'message_id' => $mid,
                    'type' => normalize_open_issue_type($issueEntry['type'] ?? 1)
            ];
        }
    }
    
    json_response([
            'ok' => true,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'open_issue' => $entry !== null,
            'open_issue_text' => trim((string) ($entry['text'] ?? '')),
            'open_issue_type' => (int) ($entry['type'] ?? 0),
            'count' => count($items),
            'total' => count($items),
            'items' => array_values(array_slice($items, 0, 5))
    ]);
}

if ($action === 'resolve_open_issue') {
    require_login();
    
    $chatId = sanitize_chat_id((string) ($input['chat_id'] ?? ''));
    $messageId = trim((string) ($input['message_id'] ?? ''));
    
    if ($chatId === '' || $messageId === '') {
        json_response([
                'error' => 'Missing chat_id or message_id'
        ], 400);
    }
    
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        maybe_chat_not_found('Chat not found');
    }
    
    $ownerUserId = (string) $ref['owner_user_id'];
    $chatOpenIssues = load_chat_open_issues_for_owner($ownerUserId, $chatId);
    unset($chatOpenIssues[$messageId]);
    save_chat_open_issues_for_owner($ownerUserId, $chatId, $chatOpenIssues);
    
    json_response([
            'ok' => true,
            'chat_id' => $chatId,
            'message_id' => $messageId
    ]);
}

$messages = $input['messages'] ?? [];
$requestedMode = $input['mode'] ?? null;
$requestedAction = $input['action'] ?? 'chat';
$incomingIntent = trim((string) ($input['incoming_intent'] ?? ''));
$incomingIsLatest = (string) ($input['incoming_is_latest'] ?? '') === '1';
$incomingMessageTime = trim((string) ($input['incoming_message_time'] ?? ''));
$toolAction = trim((string) ($input['tool_action'] ?? ''));
$translateLanguage = trim((string) ($input['translate_language'] ?? ''));
$rewriteTone = trim((string) ($input['rewrite_tone'] ?? ''));
$freetextScope = trim((string) ($input['freetext_scope'] ?? ''));
$currentMessageText = trim((string) ($input['current_message_text'] ?? ''));

$chatId = sanitize_chat_id((string) ($input['chat_id'] ?? ''));

$isAssistRequest = ($requestedAction === 'assist');
$isSuggestRequest = ($requestedAction === 'suggest');
$isIncomingAssistRequest = ($requestedAction === 'incoming_assist');
$isIncomingAnalyzeRequest = ($requestedAction === 'incoming_analyze');
$isCatchupRequest = ($requestedAction === 'catchup');
$isChatRequest = ($requestedAction === 'chat');
$isPolishRequest = ($requestedAction === 'polish');
$isFreetextRequest = ($requestedAction === 'freetext');

$selectedIntent = trim((string) ($input['selected_intent'] ?? ''));

if (! in_array($incomingIntent, [
        'reply',
        'translate',
        'explain'
], true)) {
    $incomingIntent = '';
}

if (! is_array($messages)) {
    $messages = [];
}

$allowGuestAssist = ($requestedAction === 'assist_guest');

if (! $allowGuestAssist) {
    require_login();
}

if ($allowGuestAssist) {
    if (getGuestCreditsLeft() <= 0) {
        assist_json_response('Guest trial finished', 'error', 'guest_trial_exhausted', [
            'error' => 'Guest trial limit reached.',
        ], 403);
    }
    setGuestCreditsLeft(getGuestCreditsLeft() - 1);
}

// ── Rate limiting for AI endpoints ───────────────────────────────────────────
$aiActions = ['assist', 'assist_guest', 'freetext', 'polish', 'suggest',
              'incoming_assist', 'incoming_analyze', 'catchup', 'chat'];

if (in_array($requestedAction, $aiActions, true)) {
    // Bucket key: logged-in user id, or session id for guests
    $rl_bucket = $allowGuestAssist
        ? 'g_' . session_id()
        : 'u_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SESSION['user_id'] ?? session_id()));

    $rl_limit   = $allowGuestAssist ? 5  : 60;   // requests per window
    $rl_window  = $allowGuestAssist ? 60 : 60;    // seconds

    $rl_key     = sys_get_temp_dir() . '/rl_' . md5($rl_bucket) . '.json';
    $rl_now     = time();
    $rl_state   = [];
    if (file_exists($rl_key)) {
        $rl_state = json_decode(file_get_contents($rl_key), true) ?: [];
    }
    $rl_state = array_filter((array) $rl_state, fn($t) => ($rl_now - $t) < $rl_window);
    if (count($rl_state) >= $rl_limit) {
        json_response(['error' => 'Too many requests', 'retry_after' => $rl_window], 429);
    }
    $rl_state[] = $rl_now;
    file_put_contents($rl_key, json_encode(array_values($rl_state)), LOCK_EX);
    unset($rl_bucket, $rl_limit, $rl_window, $rl_key, $rl_now, $rl_state);
}

// Limit prompt / message payload length (prevent token-stuffing)
if (in_array($requestedAction, $aiActions, true)) {
    $rl_rawlen = mb_strlen(json_encode($messages) . json_encode($input), 'UTF-8');
    if ($rl_rawlen > 40000) {
        json_response(['error' => 'Request too large'], 413);
    }
    unset($rl_rawlen);
}
// ─────────────────────────────────────────────────────────────────────────────

if (! $allowGuestAssist && in_array($requestedAction, ['send_message', 'chat'], true) && $chatId === '') {
    json_response([
            'error' => 'Missing chat_id'
    ], 400);
}

if ($allowGuestAssist) {
    $chat = [
            'id' => '',
            'title' => 'Guest trial',
            'participants' => [],
            'messages' => []
    ];
    $chatId = '';
    $ownerUserId = '';
} elseif ($chatId === '') {
    $chat = create_new_chat();
    $chatId = $chat['id'];
    $ownerUserId = (string) get_current_user_id();
} else {
    $ref = resolve_chat_reference_for_current_user($chatId);
    if (! $ref) {
        maybe_chat_not_found('Chat not found', [
                'chat_id' => $chatId
        ]);
    } else {
        $chat = load_chat_for_owner((string) $ref['owner_user_id'], $chatId);
        if (! is_array($chat)) {
            maybe_chat_not_found('Chat not found', [
                    'chat_id' => $chatId
            ]);
        }
        $ownerUserId = (string) $ref['owner_user_id'];
        $_SESSION['current_chat_id'] = $chatId;
    }
}

if (! $allowGuestAssist && in_array($requestedAction, ['send_message', 'chat'], true)) {
    enforce_chat_send_access_or_fail($chat, (string) $ownerUserId, (string) get_current_user_id());
}

// Block sending to a chat where every non-sender participant is hidden
if ($requestedAction === 'send_message' && $isMultiChat) {
    $senderId = (string) get_current_user_id();
    $allHidden = true;
    foreach (($chat['participants'] ?? []) as $participant) {
        $pContactId = trim((string)($participant['contact_id'] ?? ''));
        $pContact = $pContactId !== '' ? resolve_contact_by_id_for_owner($ownerUserId, $pContactId) : [];
        $pUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($pContact['user_id'] ?? ''));
        if ($pUserId === '' || $pUserId === $senderId) continue;
        $pProfile = load_user_profile_by_id($pUserId);
        if (!is_user_hidden($pProfile)) {
            $allHidden = false;
            break;
        }
    }
    if ($allHidden) {
        json_response([
            'ok' => false,
            'error' => 'This user is not available.'
        ], 403);
    }
}

$isMultiChat = isset($chat['participants']) && count($chat['participants']) > 1;

$lastUserMessage = '';

if ($requestedAction === 'send_message') {
    for ($i = count($messages) - 1; $i >= 0; $i --) {
        if (($messages[$i]['role'] ?? '') === 'user') {
            $lastUserMessage = trim((string) ($messages[$i]['content'] ?? ''));
            break;
        }
    }

    if ($lastUserMessage === '') {
        json_response([
                'error' => 'Missing message content'
        ], 400);
    }

    $rawMeta = $input['meta'] ?? [];
    $messageMeta = is_array($rawMeta) ? $rawMeta : [];

    $labels = $messageMeta['labels'] ?? [];
    if (! is_array($labels)) {
        $labels = [];
    }

    $labels = array_values(array_filter(array_map(function ($label) {
        if (! is_array($label)) {
            return null;
        }

        $type = trim((string) ($label['type'] ?? ''));
        $text = trim((string) ($label['text'] ?? ''));

        if ($type === '' || $text === '') {
            return null;
        }

        return [
                'type' => $type,
                'text' => $text
        ];
    }, $labels)));

    $newChatMessage = [
            'id' => bin2hex(random_bytes(6)),
            'role' => $isMultiChat ? 'other' : 'user',
            'user_id' => (string) get_current_user_id(),
            'name' => (string) ($_SESSION['user_name'] ?? 'User'),
            'content' => $lastUserMessage,
            'time' => now_str(),
            'recipient_label' => trim((string) ($input['recipient_label'] ?? '')),
            'reply_to' => trim((string) ($input['reply_to'] ?? '')),
            'meta' => [
                    'labels' => $labels
            ]
    ];

    $chat['messages'][] = $newChatMessage;

    if (($chat['title'] ?? 'New chat') === 'New chat') {
        $chat['title'] = make_chat_title_from_text($lastUserMessage);
    }

    append_message_to_chat_search_index($ownerUserId, $chat, $newChatMessage);
    save_chat_for_owner($chat, $ownerUserId);

    if ($isMultiChat) {
        increment_unread_for_other_participants($chat, (string) get_current_user_id());
    }

    maybe_summarize_chat($chat, $ownerUserId);

    json_response([
            'ok' => true,
            'chat_id' => $chat['id'],
            'title' => $chat['title'],
            'message_id' => $newChatMessage['id'],
            'multi' => $isMultiChat,
            'message_count' => count($chat['messages'])
    ]);
}

if ($allowGuestAssist) {
    $mode = 'guest';
} elseif ($isIncomingAnalyzeRequest) {
    $mode = 'incoming_analyze';
} elseif ($isIncomingAssistRequest) {
    $mode = 'incoming_assist';
} elseif ($isCatchupRequest) {
    $mode = 'catchup';
} elseif ($isAssistRequest) {
    $mode = 'assistant';
} elseif ($isSuggestRequest && $toolAction === 'translate') {
    $mode = 'translate';
} elseif ($isSuggestRequest && $toolAction === 'rewrite') {
    $mode = 'rewrite';
} elseif ($isSuggestRequest) {
    $mode = 'suggest';
} elseif ($isPolishRequest) {
    $mode = 'polish';
} elseif ($isFreetextRequest) {
    $mode = 'freetext';
} else {
    $mode = $requestedMode ?: (count($messages) === 1 ? 'first_message' : 'assistant');
}

// Guest short-term session memory
if ($requestedAction === 'assist_guest') {
    if (! isset($_SESSION['guest_assist_history']) || ! is_array($_SESSION['guest_assist_history'])) {
        $_SESSION['guest_assist_history'] = [];
    }
    
    // append current user message
    $lastUser = '';
    for ($i = count($messages) - 1; $i >= 0; $i --) {
        if (($messages[$i]['role'] ?? '') === 'user') {
            $lastUser = trim((string) $messages[$i]['content']);
            break;
        }
    }
    
    if ($lastUser !== '') {
        $_SESSION['guest_assist_history'][] = [
                'role' => 'user',
                'content' => $lastUser
        ];
    }
    
    // keep last 6
    $_SESSION['guest_assist_history'] = array_slice($_SESSION['guest_assist_history'], - 6);
    
    // use session history as conversation
    $messages = $_SESSION['guest_assist_history'];
}

if ($isIncomingAssistRequest && $incomingIntent !== '') {
    $intentInstruction = '';
    
    if ($incomingIntent === 'reply') {
        if ($selectedIntent !== '') {
            $intentInstruction = 'Incoming assist intent: reply with selected intent "' . $selectedIntent . '". Generate a single natural reply that expresses exactly this intent. Return reply_type "draft".';
        } elseif ($incomingIsLatest) {
            $intentInstruction = 'Incoming assist intent: reply. This is the latest incoming message. Return JSON according to the REPLY DECISION RULE.';
        } else {
            $intentInstruction = 'Incoming assist intent: reply. This is NOT the latest incoming message. Return JSON according to the REPLY DECISION RULE. If a draft or option refers to the old timing, make that clear naturally without mentioning the app.';
        }
    } elseif ($incomingIntent === 'translate') {
        $intentInstruction = 'Incoming assist intent: translate. Translate the incoming message only. Do not explain it.';
    } elseif ($incomingIntent === 'explain') {
        $intentInstruction = 'Incoming assist intent: explain. Briefly explain what the incoming message likely means or implies. Do not draft a reply unless absolutely necessary.';
    }
    
    if ($incomingMessageTime !== '' && $incomingIntent === 'reply' && ! $incomingIsLatest) {
        $intentInstruction .= ' Original message time: ' . $incomingMessageTime . '.';
    }
    
    if ($intentInstruction !== '') {
        array_unshift($messages, [
                'role' => 'user',
                'content' => $intentInstruction
        ]);
    }
}

$chatMemory = [];
$contextMessages = [];

if (! $allowGuestAssist && ! $isFreetextRequest && ! $isPolishRequest && ! empty($chat['id']) && ! empty($ownerUserId)) {
    $chatMemory = load_chat_memory_for_owner($ownerUserId, $chat['id']);
    $context = build_assistant_context($chat, $chatMemory);
    $contextMessages = context_to_prompt_messages($context);
}

$sanitizedMessages = sanitize_openai_input_messages($messages);

$sanitizedContextMessages = sanitize_openai_input_messages($contextMessages);

$promptUserProfile = $allowGuestAssist ? [
        'user' => []
] : load_current_user_profile();

$promptContext = build_conversation_prompt_context($chat, $sanitizedMessages, $promptUserProfile, $mode, $input);

$systemPrompt = build_prompt($sanitizedMessages, $mode, $promptContext, $incomingIntent ?? '');

$GLOBALS['system_prompt'] = $systemPrompt;
$GLOBALS['prompt_context'] = $promptContext;
$GLOBALS['sanitized_messages'] = $sanitizedMessages;
$GLOBALS['sanitized_context_messages'] = $sanitizedContextMessages;

$payload = [
        'model' => 'gpt-5-nano',
        'input' => array_merge([
                [
                        'role' => 'system',
                        'content' => $systemPrompt
                ]
        ], $sanitizedContextMessages, $sanitizedMessages),
        'max_output_tokens' => 800,
        'reasoning' => [
                'effort' => 'minimal'
        ],
        'text' => [
                'format' => [
                        'type' => 'text'
                ]
        ]
];

$GLOBALS['payload'] = $payload;

$assistantApiKey = get_server_assistant_api_key();

if ($assistantApiKey === '' && $requestedAction === 'assist_guest') {
    $assistantApiKey = get_server_assistant_api_key();
}

// ── Private beta: logged-in users without their own API key ──────────────────
$betaUserId        = '';
$usingBetaKey      = false;
$betaUserProfile   = [];

if ($assistantApiKey === '' && !$allowGuestAssist) {
    $betaUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SESSION['user_id'] ?? ''));
    if ($betaUserId !== '') {
        $betaUserProfile = load_user_profile_by_id($betaUserId);
        $betaUsage       = get_beta_usage($betaUserProfile);
        $betaCheck       = check_beta_limits($betaUsage);

        // Estimate input tokens: last user message chars / 4
        $estimatedInputTokens = 0;
        foreach (array_reverse($sanitizedMessages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $estimatedInputTokens = (int)ceil(mb_strlen((string)($m['content'] ?? '')) / 4);
                break;
            }
        }

        if (!$betaCheck['allowed']) {
            $betaErrorMsg = $betaCheck['reason'] === 'daily_limit'
                ? 'Daily beta limit reached. Try again tomorrow or add your own API key.'
                : 'Beta access ended. Add your own API key to continue using the assistant.';

            if ($isChatRequest) {
                persist_latest_user_message_for_chat($chat, $ownerUserId, $isMultiChat, $messages, $input);
                json_response([
                    'ok' => true,
                    'reply' => '',
                    'mode' => $mode,
                    'chat_id' => $chat['id'],
                    'title' => $chat['title'],
                    'multi' => $isMultiChat,
                    'message_count' => count($chat['messages']),
                    'assistant_unavailable' => true,
                    'beta_limit' => $betaCheck['reason'],
                    'beta_message' => $betaErrorMsg,
                ]);
            }
            assist_json_response($betaErrorMsg, 'error', 'beta_limit_' . $betaCheck['reason'], [
                'beta_limit' => $betaCheck['reason'],
                'beta_message' => $betaErrorMsg,
            ], 403);
        }

        if ($estimatedInputTokens > BETA_MAX_INPUT_TOKENS) {
            $tooLongMsg = 'Your message is too long for beta access (max ~1,000 tokens). Please shorten it or add your own API key.';
            if ($isChatRequest) {
                persist_latest_user_message_for_chat($chat, $ownerUserId, $isMultiChat, $messages, $input);
                json_response([
                    'ok' => true,
                    'reply' => '',
                    'mode' => $mode,
                    'chat_id' => $chat['id'],
                    'title' => $chat['title'],
                    'multi' => $isMultiChat,
                    'message_count' => count($chat['messages']),
                    'assistant_unavailable' => true,
                    'beta_limit' => 'input_too_long',
                    'beta_message' => $tooLongMsg,
                ]);
            }
            assist_json_response($tooLongMsg, 'error', 'beta_limit_input_too_long', [
                'beta_limit' => 'input_too_long',
            ], 403);
        }

        // Ensure beta_started_at is recorded on first use
        if ($betaUsage['beta_started_at'] === '') {
            $betaUserProfile['user']['beta_started_at'] = date('Y-m-d H:i:s');
            $betaUserProfile['user']['total_tokens_used'] = 0;
            $betaUserProfile['user']['daily_tokens_used'] = 0;
            $betaUserProfile['user']['daily_reset_date']  = date('Y-m-d');
            save_user_by_email(
                normalize_email((string)($betaUserProfile['user']['email'] ?? '')),
                $betaUserProfile
            );
        }

        $assistantApiKey = $guest_api_key ?? '';
        $usingBetaKey    = true;
    }
}
// ─────────────────────────────────────────────────────────────────────────────

if ($assistantApiKey === '') {
    if ($isChatRequest) {
        persist_latest_user_message_for_chat($chat, $ownerUserId, $isMultiChat, $messages, $input);
        json_response([
                'ok' => true,
                'reply' => '',
                'mode' => $mode,
                'chat_id' => $chat['id'],
                'title' => $chat['title'],
                'multi' => $isMultiChat,
                'message_count' => count($chat['messages']),
                'assistant_unavailable' => true
        ]);
    }

    assist_json_response('Assist unavailable', 'error', 'assist_unavailable', [
            'error' => 'Missing API key',
            'details' => 'No assistant API key found in session.'
    ], 400);
}

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $assistantApiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);

if ($response === false) {
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($isChatRequest) {
        persist_latest_user_message_for_chat($chat, $ownerUserId, $isMultiChat, $messages, $input);
        json_response([
                'ok' => true,
                'reply' => '',
                'mode' => $mode,
                'chat_id' => $chat['id'],
                'title' => $chat['title'],
                'multi' => $isMultiChat,
                'message_count' => count($chat['messages']),
                'assistant_unavailable' => true,
                'assistant_error' => 'curl_error'
        ]);
    }

    assist_json_response('Assist unavailable', 'error', 'assist_unavailable', [
            'error' => 'Curl error',
            'details' => $curlError
    ], 500);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$GLOBALS['data'] = $data;

if ($httpCode < 200 || $httpCode >= 300) {
    $apiMessage = $data['error']['message'] ?? 'Message unclear';
    $apiType = $data['error']['type'] ?? '';
    
    if ($httpCode === 401) {
        if ($isChatRequest) {
            persist_latest_user_message_for_chat($chat, $ownerUserId, $isMultiChat, $messages, $input);
            json_response([
                    'ok' => true,
                    'reply' => '',
                    'mode' => $mode,
                    'chat_id' => $chat['id'],
                    'title' => $chat['title'],
                    'multi' => $isMultiChat,
                    'message_count' => count($chat['messages']),
                    'assistant_unavailable' => true,
                    'assistant_error' => 'invalid_api_key'
            ]);
        }

        assist_json_response('Assist unavailable', 'error', 'assist_unavailable', [
                'error' => 'Invalid API key',
                'status' => $httpCode,
                'details' => $apiMessage,
                'type' => $apiType
        ], 401);
    }

    if ($isChatRequest) {
        persist_latest_user_message_for_chat($chat, $ownerUserId, $isMultiChat, $messages, $input);
        json_response([
                'ok' => true,
                'reply' => '',
                'mode' => $mode,
                'chat_id' => $chat['id'],
                'title' => $chat['title'],
                'multi' => $isMultiChat,
                'message_count' => count($chat['messages']),
                'assistant_unavailable' => true,
                'assistant_error' => 'http_error'
        ]);
    }
    
    assist_json_response('Message unclear', 'passive', 'message_unclear', [
            'error' => 'Message unclear',
            'status' => $httpCode,
            'details' => $apiMessage,
            'type' => $apiType,
            'raw' => $data ?: $response
    ], 500);
}

if (! is_array($data)) {
    if ($isChatRequest) {
        persist_latest_user_message_for_chat($chat, $ownerUserId, $isMultiChat, $messages, $input);
        json_response([
                'ok' => true,
                'reply' => '',
                'mode' => $mode,
                'chat_id' => $chat['id'],
                'title' => $chat['title'],
                'multi' => $isMultiChat,
                'message_count' => count($chat['messages']),
                'assistant_unavailable' => true,
                'assistant_error' => 'invalid_response'
        ]);
    }

    assist_json_response('Assist unavailable', 'error', 'assist_unavailable', [
            'error' => 'Assist unavailable',
            'raw' => $response
    ], 500);
}

// ── Beta: record token usage after a successful API call ─────────────────────
if ($usingBetaKey && $betaUserId !== '' && is_array($data)) {
    $betaTokensUsed = (int)($data['usage']['input_tokens'] ?? 0)
                    + (int)($data['usage']['output_tokens'] ?? 0);
    if ($betaTokensUsed <= 0) {
        // Fallback estimate from output text length
        $betaTokensUsed = (int)ceil(mb_strlen(extract_output_text($data)) / 4) + 100;
    }
    record_beta_token_usage($betaUserId, $betaTokensUsed);
}
// ─────────────────────────────────────────────────────────────────────────────

$normalized = normalize_assist_payload($data ?? [], 'draft');

$messageMap = [
        'message_unclear' => 'Message unclear.',
        'no_edits_suggested' => 'No edits suggested.',
        'assist_unavailable' => 'Assistant unavailable.',
        'reply_failed' => 'Reply could not be generated.',
        'api_key_missing' => 'API key missing.',
        'no_response' => 'No response generated.'
];

if ($isIncomingAssistRequest || $isIncomingAnalyzeRequest) {
    $structured = extract_structured_output($data, 'draft');

    $replyType = $structured['reply_type'] ?? 'passive';
    $messageKey = $structured['message_key'] ?? '';
    $reply = trim((string) ($structured['content'] ?? ''));
    $options = array_values(array_filter((array) ($structured['options'] ?? [])));

    if ($reply === '' && $replyType !== 'options' && isset($messageMap[$messageKey])) {
        $reply = $messageMap[$messageKey];
    }

    json_response([
            'ok' => true,
            'reply_type' => $replyType,
            'reply' => $reply,
            'options' => $options,
            'message_key' => $messageKey
    ]);
}

if ($isCatchupRequest) {
    json_response([
            'ok' => true,
            'reply_type' => 'catchup',
            'reply' => $normalized['content'] ?? '',
            'items' => array_values($normalized['items'] ?? [])
    ]);
}

if ($isChatRequest) {
    $output = extract_output_text($data);
    
    if ($output === '') {
    persist_latest_user_message_for_chat($chat, $ownerUserId, $isMultiChat, $messages, $input);
    json_response([
        'ok' => true,
        'reply' => '',
        'mode' => $mode,
        'chat_id' => $chat['id'],
        'title' => $chat['title'],
        'multi' => $isMultiChat,
        'message_count' => count($chat['messages']),
        'assistant_unavailable' => true,
        'assistant_error' => 'empty_output'
    ]);

        assist_json_response('No edits suggested', 'passive', 'no_edits_suggested', [
                'error' => 'No edits suggested',
                'raw' => $data
        ], 500);
    }
} else {
    $structured = extract_structured_output($data, 'draft');
    $output = (string) ($structured['content'] ?? '');
    $outputReplyType = (string) ($structured['reply_type'] ?? 'draft');
    $outputMessageKey = (string) ($structured['message_key'] ?? '');
}

// store guest assistant reply in session
if ($requestedAction === 'assist_guest' && $output !== '') {
    if (! isset($_SESSION['guest_assist_history']) || ! is_array($_SESSION['guest_assist_history'])) {
        $_SESSION['guest_assist_history'] = [];
    }
    
    $_SESSION['guest_assist_history'][] = [
            'role' => 'assistant',
            'content' => $output
    ];
    
    $_SESSION['guest_assist_history'] = array_slice($_SESSION['guest_assist_history'], - 6);
}

$lastUserMessage = '';
for ($i = count($messages) - 1; $i >= 0; $i --) {
    if (($messages[$i]['role'] ?? '') === 'user') {
        $lastUserMessage = trim((string) ($messages[$i]['content'] ?? ''));
        break;
    }
}

if ($isChatRequest) {
    $rawMeta = $input['meta'] ?? [];
    $messageMeta = is_array($rawMeta) ? $rawMeta : [];
    
    $labels = $messageMeta['labels'] ?? [];
    if (! is_array($labels)) {
        $labels = [];
    }
    
    $labels = array_values(array_filter(array_map(function ($label) {
        if (! is_array($label)) {
            return null;
        }
        
        $type = trim((string) ($label['type'] ?? ''));
        $text = trim((string) ($label['text'] ?? ''));
        
        if ($type === '' || $text === '') {
            return null;
        }
        
        return [
                'type' => $type,
                'text' => $text
        ];
    }, $labels)));
        
        $newChatMessage = [
                'id' => bin2hex(random_bytes(6)),
                'role' => $isMultiChat ? 'other' : 'user',
                'user_id' => (string) get_current_user_id(),
                'name' => (string) ($_SESSION['user_name'] ?? 'User'),
                'content' => $lastUserMessage,
                'time' => now_str(),
                'recipient_label' => trim((string) ($input['recipient_label'] ?? '')),
                'reply_to' => trim((string) ($input['reply_to'] ?? '')),
                'meta' => [
                        'labels' => $labels
                ]
        ];
        
        $chat['messages'][] = $newChatMessage;
        
        if (($chat['title'] ?? 'New chat') === 'New chat' && $lastUserMessage !== '') {
            $chat['title'] = make_chat_title_from_text($lastUserMessage);
        }
        
        append_message_to_chat_search_index($ownerUserId, $chat, $newChatMessage);
        
        save_chat_for_owner($chat, $ownerUserId);
        
        if ($isMultiChat) {
            increment_unread_for_other_participants($chat, (string) get_current_user_id());
        }
        
        maybe_summarize_chat($chat, $ownerUserId);
        
        json_response([
            'ok' => true,
                'reply' => $output,
                'mode' => $mode,
                'chat_id' => $chat['id'],
            'title' => $chat['title'],
            'multi' => $isMultiChat,
            'message_count' => count($chat['messages'])
        ]);
}

assist_json_response($output, $outputReplyType, $outputMessageKey, [
        'action' => $requestedAction
]);
