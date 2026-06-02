<?php
require_once __DIR__ . '/common.php';

function contacts_normalize_list($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/\s*,\s*/', trim((string)$value)) ?: [];
    }

    $items = array_values(array_unique(array_filter(array_map(
        static fn($item) => trim((string)$item),
        $items
    ))));

    return $items;
}

function contacts_empty_payload(): array
{
    return ['contacts' => []];
}

function contacts_resolve_owner_context(): array
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
        throw new RuntimeException('Not logged in');
    }

    $email = normalize_email($_SESSION['user_email']);
    $user = load_user_by_email($email);

    if (!$user || empty($user['user'])) {
        throw new RuntimeException('User not found');
    }

    $userFile = '';
    foreach (['file', 'path', 'user_file', '_file'] as $key) {
        if (!empty($user[$key]) && is_string($user[$key])) {
            $userFile = $user[$key];
            break;
        }
    }

    if ($userFile === '' && !empty($user['user']['_file']) && is_string($user['user']['_file'])) {
        $userFile = $user['user']['_file'];
    }

    if ($userFile === '' && get_users_dir()) {
        $userFile = rtrim((string) get_users_dir(), '/\\') . '/' . $email . '/profile.json';
    }

    if ($userFile === '' && get_users_dir()) {
        $userFile = rtrim((string) get_users_dir(), '/\\') . '/' . $email . '/user.json';
    }

    if ($userFile === '') {
        throw new RuntimeException('Could not resolve user path');
    }

    $userDir = dirname($userFile);
    if (!is_dir($userDir) && !@mkdir($userDir, 0775, true) && !is_dir($userDir)) {
        throw new RuntimeException('Could not create user directory');
    }

    return [
        'email' => $email,
        'user' => $user,
        'user_dir' => $userDir,
        'contacts_file' => $userDir . '/contacts.json'
    ];
}

function contacts_load_file(string $contactsFile): array
{
    if (!is_file($contactsFile)) {
        return contacts_empty_payload();
    }

    $raw = file_get_contents($contactsFile);
    $data = json_decode((string)$raw, true);

    if (!is_array($data)) {
        return contacts_empty_payload();
    }

    if (!isset($data['contacts']) || !is_array($data['contacts'])) {
        $data['contacts'] = [];
    }

    if (function_exists('normalize_contacts_payload')) {
        $changed = false;
        $data = normalize_contacts_payload($data, $changed);

        foreach ($data['contacts'] as $id => $record) {
            if (!is_array($record)) {
                continue;
            }

            $normalizedRecord = contacts_normalize_record_schema($record);
            if ($normalizedRecord !== $record) {
                $data['contacts'][$id] = $normalizedRecord;
                $changed = true;
            }
        }

        if ($changed) {
            contacts_save_file($contactsFile, $data);
        }
    }

    return $data;
}

function contacts_save_file(string $contactsFile, array $payload): void
{
    if (function_exists('normalize_contacts_payload')) {
        $changed = false;
        $payload = normalize_contacts_payload($payload, $changed);
    } elseif (!isset($payload['contacts']) || !is_array($payload['contacts'])) {
        $payload['contacts'] = [];
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Could not encode contacts');
    }

    if (file_put_contents($contactsFile, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Could not save contacts');
    }
}

function contacts_generate_id(): string
{
    return 'c_' . bin2hex(random_bytes(6));
}

function contacts_generate_guest_token(): string
{
    return 'gt_' . bin2hex(random_bytes(16));
}

function contacts_normalize_scale_value($value, int $fallback = 3): int
{
    $n = (int) trim((string) $value);
    if ($n < 1 || $n > 5) {
        return $fallback;
    }
    return $n;
}

function contacts_normalize_status_value($value, string $fallback = 'active'): string
{
    $raw = mb_strtolower(trim((string) $value));
    if ($raw === 'blocked') {
        return 'blocked';
    }
    if ($raw === 'active') {
        return 'active';
    }
    return $fallback === 'blocked' ? 'blocked' : 'active';
}

function contacts_normalize_profile_version($value, int $fallback = 0): int
{
    $version = (int) $value;
    if ($version < 0) {
        return $fallback;
    }
    return $version;
}

function contacts_normalize_record_schema(array $record): array
{
    $relationSource = $record['relation'] ?? $record['distance'] ?? 3;
    $toneSource = $record['tone'] ?? 3;
    if (is_array($toneSource)) {
        $toneSource = $toneSource[0] ?? 3;
    }

    $statusSource = $record['status'] ?? 'active';
    if (!isset($record['status']) && array_key_exists('blocked', $record)) {
        $statusSource = !empty($record['blocked']) ? 'blocked' : 'active';
    }

    $record['relation'] = contacts_normalize_scale_value($relationSource, 3);
    $record['tone'] = contacts_normalize_scale_value($toneSource, 3);
    $record['status'] = contacts_normalize_status_value($statusSource, 'active');
    $profileVersionSource = $record['profile_version'] ?? 0;
    $record['profile_version'] = contacts_normalize_profile_version($profileVersionSource, 0);

    return $record;
}

function find_matching_contact_record(array $payload, array $candidate): array
{
    $contacts = $payload['contacts'] ?? [];
    if (!is_array($contacts) || $contacts === []) {
        return [];
    }

    $candidateId = trim((string) ($candidate['id'] ?? ''));
    $candidateEmail = normalize_email(trim((string) ($candidate['email'] ?? '')));
    $candidateUserId = trim((string) ($candidate['user_id'] ?? ''));
    $candidateInviteChatId = trim((string) ($candidate['invite_chat_id'] ?? ''));
    $candidateGuestToken = trim((string) ($candidate['guest_token'] ?? ''));
    $candidateDisplayName = mb_strtolower(trim((string) ($candidate['display_name'] ?? $candidate['name'] ?? '')));

    $bestMatch = [];

    foreach ($contacts as $id => $record) {
        if (!is_array($record)) {
            continue;
        }

        $recordId = trim((string) ($record['id'] ?? $id));
        $recordEmail = normalize_email(trim((string) ($record['email'] ?? '')));
        $recordUserId = trim((string) ($record['user_id'] ?? ''));
        $recordInviteChatId = trim((string) ($record['invite_chat_id'] ?? ''));
        $recordGuestToken = trim((string) ($record['guest_token'] ?? ''));
        $recordDisplayName = mb_strtolower(trim((string) ($record['display_name'] ?? $record['name'] ?? '')));

        if ($candidateId !== '' && $recordId === $candidateId) {
            return $record;
        }

        if ($candidateGuestToken !== '' && $recordGuestToken !== '' && hash_equals($recordGuestToken, $candidateGuestToken)) {
            return $record;
        }

        if ($candidateEmail !== '' && $recordEmail !== '' && $recordEmail === $candidateEmail) {
            return $record;
        }

        if ($candidateUserId !== '' && $recordUserId !== '' && $recordUserId === $candidateUserId) {
            return $record;
        }

        if ($candidateInviteChatId !== '' && $recordInviteChatId !== '' && $recordInviteChatId === $candidateInviteChatId) {
            return $record;
        }

        if ($candidateDisplayName !== '' && $recordDisplayName !== '' && $recordDisplayName === $candidateDisplayName) {
            $bestMatch = $record;
        }
    }

    return $bestMatch;
}

function contacts_hydrate_record(array $input, array $existing = []): array
{
    $now = date('Y-m-d H:i:s');

    $relationSource = $input['relation'] ?? $input['distance'] ?? $existing['relation'] ?? $existing['distance'] ?? 3;
    $toneSource = $input['tone'] ?? $existing['tone'] ?? 3;
    if (is_array($toneSource)) {
        $toneSource = $toneSource[0] ?? 3;
    }

    $statusSource = $input['status'] ?? $existing['status'] ?? 'active';
    if (!array_key_exists('status', $input)) {
        if (array_key_exists('blocked', $input)) {
            $statusSource = !empty($input['blocked']) ? 'blocked' : 'active';
        } elseif (array_key_exists('blocked', $existing)) {
            $statusSource = !empty($existing['blocked']) ? 'blocked' : (string) $statusSource;
        }
    }

    $displayName = trim((string)($input['display_name'] ?? $existing['display_name'] ?? $existing['name'] ?? ''));
    $email = trim((string)($input['email'] ?? $existing['email'] ?? ''));
    $resolvedId = trim((string)($existing['id'] ?? $input['id'] ?? ''));
    if ($resolvedId === '') {
        $resolvedId = contacts_generate_id();
    }

    $record = [
        'id' => $resolvedId,
        'display_name' => $displayName,
        'name' => trim((string)($input['name'] ?? $existing['name'] ?? $displayName)),
        'initials' => trim((string)($input['initials'] ?? $existing['initials'] ?? make_initials($displayName !== '' ? $displayName : $email))),
        'email' => $email,
        'user_id' => trim((string)($input['user_id'] ?? $existing['user_id'] ?? '')),
        'verified' => !empty($input['verified']) || !empty($existing['verified']),
        'preferred_language' => trim((string)($input['preferred_language'] ?? $existing['preferred_language'] ?? '')),
        'avatar' => trim((string)($input['avatar'] ?? $existing['avatar'] ?? '')),
        'invite_chat_id' => trim((string)($input['invite_chat_id'] ?? $existing['invite_chat_id'] ?? '')),
        'guest_token' => trim((string)($existing['guest_token'] ?? $input['guest_token'] ?? '')),
        'relation' => contacts_normalize_scale_value($relationSource, 3),
        'tone' => contacts_normalize_scale_value($toneSource, 3),
        'topics' => contacts_normalize_list($input['topics'] ?? $existing['topics'] ?? []),
        'notes' => trim((string)($input['notes'] ?? $existing['notes'] ?? '')),
        'last_seen_at' => trim((string)($input['last_seen_at'] ?? $existing['last_seen_at'] ?? '')),
        'created_at' => (string)($existing['created_at'] ?? $input['created_at'] ?? $now),
        'updated_at' => $now,
        'status' => contacts_normalize_status_value($statusSource, 'active'),
        'profile_version' => contacts_normalize_profile_version($input['profile_version'] ?? $existing['profile_version'] ?? 0, 0),
    ];

    if ($record['guest_token'] === '' && $record['invite_chat_id'] !== '') {
        $record['guest_token'] = contacts_generate_guest_token();
    }

    return $record;
}

function contacts_records_for_response(array $payload): array
{
    $contacts = [];

    foreach ($payload['contacts'] as $id => $record) {
        if (!is_array($record)) {
            continue;
        }
        if (empty($record['id'])) {
            $record['id'] = (string)$id;
        }
        if (empty($record['id'])) {
            $record['id'] = contacts_generate_id();
        }
        $contacts[] = $record;
    }

    usort($contacts, static function (array $a, array $b): int {
        $aTime = (string)($a['updated_at'] ?? $a['created_at'] ?? '');
        $bTime = (string)($b['updated_at'] ?? $b['created_at'] ?? '');
        return strcmp($bTime, $aTime);
    });

    return $contacts;
}
