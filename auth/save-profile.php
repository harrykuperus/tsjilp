<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/contacts-common.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];

$firstName = trim((string)($data['first_name'] ?? ''));
$lastName = trim((string)($data['last_name'] ?? ''));
$avatar = trim((string)($data['avatar'] ?? ''));
$avatarEnabled = !array_key_exists('avatar_enabled', $data) || !empty($data['avatar_enabled']);
$visibilityRaw = trim((string)($data['visibility'] ?? ''));
$visibility = in_array($visibilityRaw, ['visible', 'hidden'], true) ? $visibilityRaw : null;
const AVATAR_MAX_BYTES = 1048576; // 1 MB

$currentPassword = (string)($data['current_password'] ?? '');
$newPassword = (string)($data['new_password'] ?? '');
$newPassword2 = (string)($data['new_password2'] ?? '');

if ($firstName === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing name']);
    exit;
}

if ($avatar !== '') {
    $isImageAvatar = preg_match('/^data:image\/(?:png|jpe?g|webp|gif);base64,[A-Za-z0-9+\/=\r\n]+$/i', $avatar) === 1;

    if ($isImageAvatar) {
        $parts = explode(',', $avatar, 2);
        $base64 = isset($parts[1]) ? preg_replace('/\s+/', '', (string)$parts[1]) : '';
        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            echo json_encode(['ok' => false, 'error' => 'Invalid avatar image data']);
            exit;
        }

        if (strlen($decoded) > AVATAR_MAX_BYTES) {
            echo json_encode(['ok' => false, 'error' => 'Avatar image is too large']);
            exit;
        }
    } elseif (mb_strlen($avatar) > 12) {
        // Keep avatar text limited to emoji or short initials.
        $avatar = '';
    }
}

$email = normalize_email($_SESSION['user_email']);
$user = load_user_by_email($email);

if (!$user || empty($user['user'])) {
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);
if ($fullName === '') {
    $fullName = $firstName;
}

$previousName = trim((string)($user['user']['name'] ?? ''));
$previousAvatar = trim((string)($user['user']['avatar'] ?? ''));
$profileVersion = (int)($user['user']['profile_version'] ?? 0);
if ($profileVersion < 1) {
    $profileVersion = 1;
}

if ($previousName !== $fullName || $previousAvatar !== $avatar) {
    $profileVersion += 1;
}

$user['user']['name'] = $fullName;
$user['user']['lastname'] = $lastName;
$user['user']['avatar'] = $avatar;
$user['user']['avatar_enabled'] = $avatarEnabled;
$user['user']['initials'] = make_initials($fullName);
$user['user']['profile_version'] = $profileVersion;

if ($visibility !== null) {
    $user['user']['visibility'] = $visibility;
}

$hash = (string)($user['user']['password_hash'] ?? '');
$passwordChanged = false;
$isGuestMember = ((string)($user['user']['member_kind'] ?? '') === 'invited_member');

if ($newPassword !== '' || $newPassword2 !== '') {
    if ($isGuestMember) {
        echo json_encode(['ok' => false, 'error' => 'Sign up to set a password']);
        exit;
    }

    if ($newPassword === '' || $newPassword2 === '') {
        echo json_encode(['ok' => false, 'error' => 'Please fill in both new password fields']);
        exit;
    }

    if ($newPassword !== $newPassword2) {
        echo json_encode(['ok' => false, 'error' => 'Passwords do not match']);
        exit;
    }

    if (mb_strlen($newPassword) < 6) {
        echo json_encode(['ok' => false, 'error' => 'Password must be at least 6 characters']);
        exit;
    }

    if ($hash !== '') {
        if ($currentPassword === '') {
            echo json_encode(['ok' => false, 'error' => 'Please enter your current password']);
            exit;
        }

        if (!password_verify($currentPassword, $hash)) {
            echo json_encode(['ok' => false, 'error' => 'Incorrect current password']);
            exit;
        }
    }

    $user['user']['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $passwordChanged = true;
}

$saved = save_user_by_email($email, $user);
if (!$saved) {
    echo json_encode(['ok' => false, 'error' => 'Could not save profile']);
    exit;
}

// Keep the owner's contact avatar in sync with profile avatar.
try {
    $ownerUserId = (string) $_SESSION['user_id'];
    $contactsPayload = load_user_contacts_by_id($ownerUserId);

    $candidate = [
        'user_id' => $ownerUserId,
        'email' => $email,
        'display_name' => $fullName,
    ];
    $existingContact = find_matching_contact_record($contactsPayload, $candidate);
    if (!is_array($existingContact)) {
        $existingContact = [];
    }

    $contactInput = [
        'id' => (string)($existingContact['id'] ?? ''),
        'user_id' => $ownerUserId,
        'email' => $email,
        'display_name' => $fullName,
        'name' => $fullName,
        'initials' => (string)($user['user']['initials'] ?? ''),
        'avatar' => $avatar,
        'relation' => $existingContact['relation'] ?? 3,
        'tone' => $existingContact['tone'] ?? 3,
        'status' => $existingContact['status'] ?? 'active',
        'topics' => $existingContact['topics'] ?? [],
        'notes' => (string)($existingContact['notes'] ?? ''),
        'invite_chat_id' => (string)($existingContact['invite_chat_id'] ?? ''),
        'guest_token' => (string)($existingContact['guest_token'] ?? ''),
        'preferred_language' => (string)($existingContact['preferred_language'] ?? ''),
        'last_seen_at' => (string)($existingContact['last_seen_at'] ?? ''),
        'created_at' => (string)($existingContact['created_at'] ?? ''),
        'profile_version' => $profileVersion,
    ];

    $updatedContact = contacts_hydrate_record($contactInput, $existingContact);
    $contactsPayload['contacts'][$updatedContact['id']] = $updatedContact;
    save_user_contacts_by_id($ownerUserId, $contactsPayload);
} catch (Throwable $e) {
    error_log('save-profile contact avatar sync failed: ' . $e->getMessage());
}

$_SESSION['user_name'] = $fullName;
$_SESSION['user_avatar'] = $avatar;
$_SESSION['user_avatar_enabled'] = $avatarEnabled;

echo json_encode([
    'ok' => true,
    'name' => $fullName,
    'initials' => $user['user']['initials'],
    'avatar' => $avatar,
    'avatar_enabled' => $avatarEnabled,
    'profile_version' => $profileVersion,
    'lastname' => $lastName,
    'password_changed' => $passwordChanged,
    'visibility' => $user['user']['visibility'] ?? 'visible'
]);