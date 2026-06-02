<?php
require_once __DIR__ . '/common.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    exit();
}

$email = normalize_email($_SESSION['user_email']);
$user = load_user_by_email($email);
$userData = $user['user'] ?? [];
$isGuestMember = ((string)($userData['member_kind'] ?? '') === 'invited_member');

$fullName = trim((string)($userData['name'] ?? ($_SESSION['user_name'] ?? '')));
$storedLastname = trim((string)($userData['lastname'] ?? ''));

$firstName = $fullName;
$lastName = $storedLastname;

if ($fullName !== '' && $storedLastname !== '' && substr($fullName, -strlen($storedLastname)) === $storedLastname) {
    $firstName = trim(substr($fullName, 0, max(0, strlen($fullName) - strlen($storedLastname))));
}

if ($fullName !== '' && $storedLastname === '') {
    $parts = preg_split('/\s+/', $fullName) ?: [];
    if (count($parts) > 1) {
        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);
    }
}

$userEmail = trim((string)($userData['email'] ?? $email));
$hasPassword = !$isGuestMember && !empty($userData['password_hash']);
$avatar = trim((string)($userData['avatar'] ?? ''));
$avatarIsImage = preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+\/=\r\n]+$/', $avatar) === 1;
$initials = trim((string)($userData['initials'] ?? make_initials($fullName)));
$avatarEnabled = !array_key_exists('avatar_enabled', $userData) || !empty($userData['avatar_enabled']);
$avatarDisplay = $avatar !== '' ? $avatar : '';
$currentAvatarText = (!$avatarIsImage && $avatarDisplay !== '')
    ? $avatarDisplay
    : ($initials !== '' ? $initials : 'U');
$ssoLink = trim((string)($userData['sso_link'] ?? ''));
$visibility = trim((string)($userData['visibility'] ?? 'visible'));
if (!in_array($visibility, ['visible', 'hidden'], true)) {
    $visibility = 'visible';
}

$embed = !empty($_GET['embed']);
$avatarOptions = ['😀', '🙂', '😎', '🤖', '🦊', '🐼', '🐱', '🐶', '🦉'];
?>
<div id="profileMeta"
     data-has-password="<?= $hasPassword ? '1' : '0' ?>"
    data-avatar="<?= htmlspecialchars($avatarDisplay, ENT_QUOTES, 'UTF-8') ?>"
    data-initials="<?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>"
    data-name="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
    data-avatar-enabled="<?= $avatarEnabled ? '1' : '0' ?>"
    data-visibility="<?= htmlspecialchars($visibility, ENT_QUOTES, 'UTF-8') ?>"
></div>
<?php if ($embed): ?>
<div id="profileRoot" class="settings-embed-root">
<?php else: ?>
<div id="profileModal" class="settings-modal hidden">
    <div id="profileRoot" class="settings-modal-card">
<?php endif; ?>
        <form id="profileForm" autocomplete="on" onsubmit="event.preventDefault(); saveProfile({ silent: false });">
            <?php if (!$embed): ?>
                <button class="settings-modal-close" type="button" onclick="closeProfileModal()">×</button>
            <?php endif; ?>

            <div class="settings-section profile-avatar-section">
            <div class="profile-avatar-current-wrap">
                <button
                    type="button"
                    id="profileAvatarUploadTrigger"
                    class="profile-avatar-current-btn"
                    aria-label="Upload avatar image"
                    title="Upload avatar image"
                >
                    <span id="profileAvatarCurrent" class="profile-avatar-current<?= $avatarIsImage ? ' has-image' : '' ?>">
                        <span id="profileAvatarCurrentEmoji" class="profile-avatar-current-emoji<?= $avatarIsImage ? ' hidden' : '' ?>"><?= htmlspecialchars($currentAvatarText, ENT_QUOTES, 'UTF-8') ?></span>
                        <img
                            id="profileAvatarCurrentImage"
                            class="profile-avatar-current-image<?= $avatarIsImage ? '' : ' hidden' ?>"
                            src="<?= $avatarIsImage ? htmlspecialchars($avatarDisplay, ENT_QUOTES, 'UTF-8') : '' ?>"
                            alt="Selected avatar"
                        >
                    </span>
                    <span class="profile-avatar-upload-badge" aria-hidden="true">⇪</span>
                </button>
            </div>
            <div id="profileAvatarInlineError" class="profile-avatar-inline-error hidden"></div>

            <div class="profile-avatar-picker" id="profileAvatarPicker">
                <button
                    type="button"
                    class="profile-avatar-option profile-avatar-option-none<?= (!$avatarIsImage && $avatarDisplay === '') ? ' active' : '' ?>"
                    data-avatar=""
                    aria-label="Use initials avatar"
                    title="No emoji"
                ><span class="profile-avatar-none-icon" aria-hidden="true"></span></button>
                <?php foreach ($avatarOptions as $option): ?>
                    <button
                        type="button"
                        class="profile-avatar-option<?= (!$avatarIsImage && $avatarDisplay !== '' && $avatarDisplay === $option) ? ' active' : '' ?>"
                        data-avatar="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="Choose avatar <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"
                    ><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></button>
                <?php endforeach; ?>
            </div>

            <input
                class="profile-avatar-upload-input"
                id="profileAvatarUpload"
                type="file"
                name="avatar_upload"
                accept="image/png,image/jpeg,image/webp,image/gif"
            >
            </div>

            <input type="hidden" id="profileAvatar" name="avatar" value="<?= htmlspecialchars($avatarDisplay, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" id="profileAvatarEnabled" name="avatar_enabled" value="<?= $avatarEnabled ? '1' : '0' ?>">

            <?php if (!$isGuestMember): ?>
            <div class="settings-section">
                <input class="settings-input" id="profileUsername" type="email" name="username" placeholder="Username" value="<?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" readonly>
            </div>
            <?php endif; ?>

            <?php if ($ssoLink !== ''): ?>
            <div class="settings-section">
                <input class="settings-input" type="text" value="<?= htmlspecialchars($ssoLink, ENT_QUOTES, 'UTF-8') ?>" readonly onclick="this.select();">
            </div>
            <?php endif; ?>

            <div class="settings-section<?= $isGuestMember ? '' : ' profile-name-row' ?>">
                <input class="settings-input" id="profileFirstName" type="text" name="first_name" placeholder="<?= $isGuestMember ? 'Display name' : 'Name' ?>" value="<?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (!$isGuestMember): ?>
                <input class="settings-input" id="profileLastName" type="text" name="last_name" placeholder="Lastname" value="<?= htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
            </div>

            <?php if (!$isGuestMember): ?>
            <div class="settings-section">
                <?php if ($hasPassword): ?>
                    <input class="settings-input" id="profileCurrentPassword" name="current_password" type="password" placeholder="Current password" autocomplete="current-password">
                <?php endif; ?>
                <input class="settings-input" id="profileNewPassword" name="new_password" type="password" placeholder="<?= $hasPassword ? 'New password' : 'Choose a password' ?>" autocomplete="new-password">
                <input class="settings-input" id="profileNewPassword2" name="new_password2" type="password" placeholder="Repeat new password" autocomplete="new-password">
            </div>
            <?php endif; ?>

            <?php if (!$isGuestMember): ?>
            <div class="settings-section profile-visibility-section">
                <label class="profile-visibility-row">
                    <div class="profile-visibility-text">
                        <span class="profile-visibility-label">Show profile in Tsjilp</span>
                        <span class="profile-visibility-desc">When off, you won&rsquo;t appear in search or contacts and cannot receive new messages.</span>
                    </div>
                    <label class="settings-toggle profile-visibility-toggle">
                        <input type="checkbox" id="profileVisibilityToggle" name="visibility" value="visible" <?= $visibility === 'visible' ? 'checked' : '' ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </label>
            </div>
            <?php endif; ?>

            <div id="profileNotice" class="settings-notice"></div>
        </form>
<?php if ($embed): ?>
</div>
<?php else: ?>
    </div>
</div>
<?php endif; ?>