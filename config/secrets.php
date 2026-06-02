<?php
/**
 * secrets.php — server-side only secret loader.
 * NEVER include this file in frontend output or echo its values.
 * NEVER place a copy of this file under public web root.
 *
 * All secrets are read from environment variables set in Docker / server config.
 * Required env vars:
 *   OPENAI_API_KEY       — guest/beta OpenAI key
 *   SMTP_PASSWORD        — SMTP server password
 *   APP_SECRET           — general app HMAC secret (future use)
 *   WEBHOOK_SECRET       — webhook signature secret (future use)
 */

/**
 * Return a secret by environment-variable name.
 * Logs a server-side warning (never to the browser) if the key is absent.
 */
function secret(string $name): string {
    static $env = null;
    if ($env === null) {
        $env = [];
        $envPath = getenv('ENV_FILE') ?: __DIR__ . '/../.env';
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strncmp($line, '#', 1) === 0) {
                    continue;
                }
                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                $env[trim($key)] = trim($value);
            }
        }
    }
    $value = (string) ($env[$name] ?? '');
    if ($value === '') {
        error_log('[secrets] Missing secret: ' . $name);
    }
    return $value;
}

/**
 * Scrub all known secret values from an arbitrary data structure before it
 * is ever serialised to JSON (used for debug output).
 */
function redact_secrets(array $data): array {
    $secrets = array_filter([
        secret('OPENAI_API_KEY'),
        secret('GEMINI_API_KEY'),
        secret('ANTHROPIC_API_KEY'),
        secret('SMTP_PASSWORD'),
        secret('APP_SECRET'),
        secret('WEBHOOK_SECRET'),
    ]);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    foreach ($secrets as $s) {
        if (is_string($s) && $s !== '') {
            $json = str_replace($s, '[REDACTED]', $json);
        }
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : $data;
}
