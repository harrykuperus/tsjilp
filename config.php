<?php

require_once __DIR__ . '/auth/common.php';
require_once __DIR__ . '/config/secrets.php';

$guest_api_key = secret('OPENAI_API_KEY');

define('GUEST_TRIAL_LIMIT', 10);

function getGuestCreditsLeft(): int {
    $dir = __DIR__ . '/data/guest_trials';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ip = preg_replace('/[^a-zA-Z0-9\.\:\-_]/', '_', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file = $dir . '/' . $ip . '.txt';
    if (!file_exists($file) || filemtime($file) < time() - 86400) file_put_contents($file, (string)GUEST_TRIAL_LIMIT, LOCK_EX);
    return max(0, (int)trim((string)file_get_contents($file)));
}

function setGuestCreditsLeft(int $left): void {
    $dir = __DIR__ . '/data/guest_trials';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ip = preg_replace('/[^a-zA-Z0-9\.\:\-_]/', '_', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    file_put_contents($dir . '/' . $ip . '.txt', (string)max(0, $left), LOCK_EX);
}

function merge_config($base, $override) {
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = merge_config($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

function load_app_config() {
    $system = load_json(__DIR__ . '/config/system.json');
    $ui = load_json(__DIR__ . '/config/ui-config.json');
    
    $user = load_json(__DIR__ . '/config/demo_user.json');
    
    if (!empty($_SESSION['user_email'])) {
        $realUser = load_user_by_email($_SESSION['user_email']);
        if (!empty($realUser['user'])) {
            $user = $realUser;

            $allowedProviders = ['ChatGPT', 'Gemini', 'Claude'];
            $provider = trim((string)($user['user']['assistant_provider'] ?? 'ChatGPT'));
            if (!in_array($provider, $allowedProviders, true)) {
                $provider = 'ChatGPT';
            }

            $providerKeys = [];
            if (isset($user['user']['assistant_api_keys']) && is_array($user['user']['assistant_api_keys'])) {
                foreach ($user['user']['assistant_api_keys'] as $p => $k) {
                    $p = trim((string)$p);
                    if (!in_array($p, $allowedProviders, true)) {
                        continue;
                    }
                    $providerKeys[$p] = decrypt_secret_value(trim((string)$k));
                }
            }

            if ($providerKeys === []) {
                $legacyKey = decrypt_secret_value(trim((string)($user['user']['assistant_api_key'] ?? '')));
                if ($legacyKey !== '') {
                    $providerKeys[$provider] = $legacyKey;
                }
            }

            $activeKey = trim((string)($providerKeys[$provider] ?? ''));
            $user['user']['assistant_provider'] = $provider;
            $user['user']['assistant_api_keys'] = $providerKeys;
            $user['user']['assistant_api_key'] = $activeKey;
            
            $_SESSION['assistant_enabled'] = !empty($user['user']['assistant_enabled']);
            $_SESSION['assistant_provider'] = $provider;
            $_SESSION['assistant_api_key'] = $activeKey;
            $_SESSION['user_name'] = $user['user']['name'] ?? ($_SESSION['user_name'] ?? '');
        }
    }
    
    if (!empty($_SESSION['assistant_provider'])) {
        $user['user']['assistant_provider'] = $_SESSION['assistant_provider'];
    }
    
    if (array_key_exists('assistant_api_key', $_SESSION)) {
        $_SESSION['assistant_api_key'] = decrypt_secret_value(trim((string)($_SESSION['assistant_api_key'] ?? '')));
        $user['user']['assistant_api_key'] = $_SESSION['assistant_api_key'];
        if (!empty($user['user']['assistant_provider'])) {
            if (!isset($user['user']['assistant_api_keys']) || !is_array($user['user']['assistant_api_keys'])) {
                $user['user']['assistant_api_keys'] = [];
            }
            $user['user']['assistant_api_keys'][$user['user']['assistant_provider']] = $_SESSION['assistant_api_key'];
        }
    }
    
    if (array_key_exists('assistant_enabled', $_SESSION)) {
        $user['user']['assistant_enabled'] = !empty($_SESSION['assistant_enabled']);
    }
    
    if (isset($user['user']['ui_preferences'])) {
        $ui['ui'] = merge_config($ui['ui'] ?? [], $user['user']['ui_preferences']);
    }
    
    return [
            'system' => $system['system'] ?? [],
            'user' => $user['user'] ?? [],
            'ui' => $ui['ui'] ?? []
    ];
}