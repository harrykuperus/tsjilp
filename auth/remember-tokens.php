<?php
/**
 * remember-tokens.php
 * Persistent login (remember-me) token management.
 *
 * Included from:
 *   - auth/common.php (restores session on every request if no active session)
 *   - auth/logout.php (revokes token on logout)
 *
 * Each login entry point (password-login, google-callback, apple-callback,
 * email-verify-signup) calls issue_remember_token() after setting $_SESSION.
 *
 * Tokens are stored hashed in data/remember_tokens.json.
 * The raw token is only ever in the cookie — never on disk.
 */

declare(strict_types=1);

define('REMEMBER_COOKIE', 'tsjilp_remember');
define('REMEMBER_DAYS',   90);

// --------------------------------------------------------------------------
// Internal helpers
// --------------------------------------------------------------------------

function _remember_tokens_file(): string
{
    return __DIR__ . '/../data/remember_tokens.json';
}

function _remember_load(): array
{
    $file = _remember_tokens_file();
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function _remember_save(array $tokens): void
{
    $file = _remember_tokens_file();
    $dir  = dirname($file);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log('[remember] Could not create data directory');
        return;
    }

    // Atomic write via temp file + rename
    $tmp = $file . '.tmp.' . getmypid();
    $ok  = file_put_contents(
        $tmp,
        json_encode(array_values($tokens), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    if ($ok === false) {
        error_log('[remember] Could not write tokens file');
        @unlink($tmp);
        return;
    }

    rename($tmp, $file);
}

function _remember_set_cookie(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// --------------------------------------------------------------------------
// Public API
// --------------------------------------------------------------------------

/**
 * Issue a new remember-me token for $userId and set the cookie.
 * Call this immediately after a successful login.
 */
function issue_remember_token(string $userId): void
{
    if ($userId === '') {
        return;
    }

    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $now       = time();
    $expires   = $now + (REMEMBER_DAYS * 86400);

    $tokens = _remember_load();

    // Prune expired / revoked tokens opportunistically (no extra I/O cost)
    $tokens = array_values(array_filter($tokens, static function (array $t) use ($now): bool {
        return empty($t['revoked'])
            && !empty($t['expires_at'])
            && strtotime((string) $t['expires_at']) > $now;
    }));

    $tokens[] = [
        'token_hash' => $tokenHash,
        'user_id'    => $userId,
        'created_at' => date('Y-m-d H:i:s', $now),
        'expires_at' => date('Y-m-d H:i:s', $expires),
        'revoked'    => false,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ];

    _remember_save($tokens);

    _remember_set_cookie($rawToken, $expires);
}

/**
 * If no active PHP session but a valid remember cookie exists,
 * restore $_SESSION['user_id'] and rotate the token.
 * Call this once per request after session_start().
 */
function restore_session_from_remember_cookie(): void
{
    $rawToken = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if ($rawToken === '') {
        return;
    }

    $inputHash = hash('sha256', $rawToken);
    $now       = time();

    $tokens   = _remember_load();
    $found    = null;
    $foundIdx = null;

    foreach ($tokens as $i => $t) {
        if (
            !empty($t['token_hash'])
            && hash_equals((string) $t['token_hash'], $inputHash)
            && empty($t['revoked'])
            && !empty($t['expires_at'])
            && strtotime((string) $t['expires_at']) > $now
        ) {
            $found    = $t;
            $foundIdx = $i;
            break;
        }
    }

    if ($found === null) {
        // Cookie is unknown or expired — delete it to stop future lookups
        _remember_set_cookie('', time() - 86400);
        return;
    }

    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($found['user_id'] ?? ''));
    if ($userId === '') {
        return;
    }

    // Restore the session
    $_SESSION['user_id'] = $userId;

    // Rotate: remove the old token record, issue a fresh one
    unset($tokens[$foundIdx]);
    _remember_save(array_values($tokens));

    issue_remember_token($userId);
}

/**
 * Revoke the remember cookie for the current browser and delete it.
 * Call this on logout before session_destroy().
 */
function revoke_remember_cookie(): void
{
    $rawToken = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');

    if ($rawToken !== '') {
        $inputHash = hash('sha256', $rawToken);
        $tokens    = _remember_load();

        // Remove the matching token record
        $tokens = array_values(array_filter($tokens, static function (array $t) use ($inputHash): bool {
            return !isset($t['token_hash']) || !hash_equals((string) $t['token_hash'], $inputHash);
        }));

        _remember_save($tokens);
    }

    // Expire the cookie in the browser
    _remember_set_cookie('', time() - 86400);
}
