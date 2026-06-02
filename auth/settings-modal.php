<?php
require_once __DIR__ . '/common.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    exit();
}

$email = normalize_email($_SESSION['user_email']);
$user = load_user_by_email($email);

$userEmail = $user['user']['email'] ?? $email;
$assistantEnabled = !empty($user['user']['assistant_enabled']);
$assistantProvider = $user['user']['assistant_provider'] ?? 'ChatGPT';
$allowedProviders = ['ChatGPT'];
if (!in_array($assistantProvider, $allowedProviders, true)) {
    $assistantProvider = 'ChatGPT';
}

$assistantProviderKeys = [];
if (isset($user['user']['assistant_api_keys']) && is_array($user['user']['assistant_api_keys'])) {
    foreach ($user['user']['assistant_api_keys'] as $provider => $key) {
        $providerName = trim((string)$provider);
        if (!in_array($providerName, $allowedProviders, true)) {
            continue;
        }
        $assistantProviderKeys[$providerName] = decrypt_secret_value(trim((string)$key));
    }
}

if ($assistantProviderKeys === []) {
    $legacyKey = decrypt_secret_value(trim((string)($user['user']['assistant_api_key'] ?? '')));
    if ($legacyKey !== '') {
        $assistantProviderKeys[$assistantProvider] = $legacyKey;
    }
}

$assistantApiKey = trim((string)($assistantProviderKeys[$assistantProvider] ?? ''));
$assistantHasKey = $assistantApiKey !== '';

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

$assistantKeyMasked = $assistantHasKey ? mask_api_key($assistantApiKey) : '';

$providerApiLinks = [
    'ChatGPT' => 'https://platform.openai.com/api-keys',
    'Gemini' => 'https://aistudio.google.com/app/apikey',
    'Claude' => 'https://console.anthropic.com/settings/keys'
];

$assistantProviderLink = $providerApiLinks[$assistantProvider] ?? $providerApiLinks['ChatGPT'];

$communicationProfile = $user['user']['communication_profile']['preset'] ?? 'neutral_practical';
$communicationCustomPrompt = $user['user']['communication_profile']['custom_prompt'] ?? '';

$languages = [
        'en' => 'English',
        'nl' => 'Dutch',
        'it' => 'Italian',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'pt' => 'Portuguese',
        'zh' => 'Chinese'
];

$reading_language = $user['user']['language'] ?? 'en';
$writing_languages = $user['user']['quick_languages'] ?? ['en', 'de', 'es'];

$embed = !empty($_GET['embed']);

// ── Private beta data ────────────────────────────────────────────────────────
$betaUsage    = get_beta_usage($user);
$betaDailyUsed   = $betaUsage['daily_tokens_used'];
$betaTotalUsed   = $betaUsage['total_tokens_used'];
$betaDaysUsed    = $betaUsage['days_used'];
$betaDailyLeft   = max(0, BETA_DAILY_TOKEN_LIMIT  - $betaDailyUsed);
$betaTotalLeft   = max(0, BETA_TOTAL_TOKEN_LIMIT  - $betaTotalUsed);
$betaDaysLeft    = max(0, BETA_ACCESS_DAYS - $betaDaysUsed);
$betaStarted     = $betaUsage['beta_started_at'] !== '';
$betaLimitCheck  = check_beta_limits($betaUsage);
$betaActive      = $betaLimitCheck['allowed'];
$betaLimitReason = $betaLimitCheck['reason'];
// ─────────────────────────────────────────────────────────────────────────────

$communicationProfiles = [
        'corporate_friendly' => [
                'name' => 'Corporate & friendly',
                'desc' => 'Professional, clear, approachable'
        ],
        'corporate_direct' => [
                'name' => 'Corporate & direct',
                'desc' => 'Structured, efficient, businesslike'
        ],
        'polite_thoughtful' => [
                'name' => 'Polite & thoughtful',
                'desc' => 'Warm, careful, respectful'
        ],
        'neutral_practical' => [
                'name' => 'Neutral & practical',
                'desc' => 'Simple, natural, no extra tone'
        ],
        'casual_friendly' => [
                'name' => 'Casual & friendly',
                'desc' => 'Relaxed, open, natural'
        ],
        'casual_direct' => [
                'name' => 'Casual & direct',
                'desc' => 'Informal, short, to the point'
        ],
        'playful_light' => [
                'name' => 'Playful & light',
                'desc' => 'Friendly with a touch of humor'
        ],
        'bold_confident' => [
                'name' => 'Bold & confident',
                'desc' => 'Assertive, strong, decisive'
        ]
];
?>
<div id="settingsMeta" data-assistant-enabled="<?= $assistantEnabled ? '1' : '0' ?>" data-assistant-provider="<?= htmlspecialchars($assistantProvider, ENT_QUOTES, 'UTF-8') ?>"
    data-assistant-has-key="<?= $assistantHasKey ? '1' : '0' ?>" data-assistant-key-masked="<?= htmlspecialchars($assistantKeyMasked, ENT_QUOTES, 'UTF-8') ?>"
    data-beta-active="<?= (!$assistantHasKey && $betaActive) ? '1' : '0' ?>"></div>
<?php if ($embed): ?>
<div id="settingsRoot" class="settings-embed-root">
<?php else: ?>
<div id="settingsModal" class="settings-modal hidden">
    <div id="settingsRoot" class="settings-modal-card">
<?php endif; ?>
        <form id="settingsForm" autocomplete="on" onsubmit="event.preventDefault(); saveSettings();">
            <?php if (!$embed): ?>
                <button class="settings-modal-close" type="button" onclick="closeSettingsModal()">×</button>
            <?php endif; ?>
            <div class="settings-tabs">
                <button type="button" class="settings-tab active" data-tab="account" onclick="switchSettingsTab('account', this)">Account</button>
                <button type="button" class="settings-tab" data-tab="communication" onclick="switchSettingsTab('communication', this)">Writing</button>
            </div>
            <div class="settings-modal-sub hidden"></div>
            <div class="settings-tab-panel active" data-tab="account">
                <div class="settings-section">
                    <div class="settings-assistance-card">
                        <label class="settings-toggle"> <input type="checkbox" id="settingsAssistantEnabled" <?= $assistantEnabled ? 'checked' : '' ?>> <span class="settings-toggle-slider"></span>
                        </label>
                        <div class="settings-assistance-left">
                            <div class="settings-assistance-text">
                                <div class="settings-assistance-title">Assisted communication</div>
                                <div class="settings-assistance-sub" style="margin:0;">Suggest, explain, and remember for better conversations</div>
                                <div class="settings-assistance-sub" style="margin:0;" id="settingsAssistantProviderLabel">Model <?= htmlspecialchars($assistantProvider, ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="settings-section">
                    <div id="assistantSettingsBlock">
                        <div class="settings-provider-key-row">
                            <div class="settings-provider-group" role="radiogroup" aria-label="Assistant provider">
                                <label class="settings-provider-option">
                                    <input type="radio" name="settings_assistant_provider" value="ChatGPT" <?= $assistantProvider === 'ChatGPT' ? 'checked' : '' ?>>
                                    <span>ChatGPT</span>
                                </label>
                                <label class="settings-provider-option" style="opacity: 0.5; pointer-events: none;">
                                    <input type="radio" name="settings_assistant_provider" value="Gemini" disabled>
                                    <span>Gemini</span>
                                </label>
                                <label class="settings-provider-option" style="opacity: 0.5; pointer-events: none;">
                                    <input type="radio" name="settings_assistant_provider" value="Claude" disabled>
                                    <span>Claude</span>
                                </label>
                            </div>
                            <input type="password" id="settingsAssistantApiKey" name="assistant_api_key" class="settings-input settings-provider-key-input" placeholder="API key"
                                value="<?= $assistantHasKey ? htmlspecialchars($assistantKeyMasked, ENT_QUOTES, 'UTF-8') : '' ?>"
                                data-api-key-mask="<?= htmlspecialchars($assistantKeyMasked, ENT_QUOTES, 'UTF-8') ?>"
                                autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
                        </div>
                        <div class="settings-api-note" id="settingsApiNote">
                            Find your API key <a href="<?= htmlspecialchars($assistantProviderLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">here</a>
                        </div>
                    </div>
                    <i class="settings-assistance-sub" style="display: flex;justify-content: center;">(Gemini and Claude will be available soon)</i>
                </div>

                <?php if (!$assistantHasKey): ?>
                <div class="settings-section settings-beta-section">
                    <div class="settings-beta-title">Token Usage</div>
                    <div class="settings-beta-desc">You have 10 days to add your own API key. Till then usage is limited</div>

                    <?php if ($betaStarted && !$betaActive): ?>
                        <div class="settings-beta-notice settings-beta-notice--expired">
                            <?php if ($betaLimitReason === 'daily_limit'): ?>
                                Daily limit reached. Try again tomorrow or add your own API key.
                            <?php else: ?>
                                Beta access ended. Add your own API key to continue using the assistant.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($betaStarted): ?>
                    <div class="settings-beta-stats">
                        <div class="settings-beta-stat">
                            <span class="settings-beta-stat-label">Today</span>
                            <span class="settings-beta-stat-value"><?= number_format($betaDailyUsed) ?> used &middot; <?= number_format($betaDailyLeft) ?> left</span>
                            <div class="settings-beta-bar">
                                <div class="settings-beta-bar-fill" style="width:<?= min(100, round($betaDailyUsed / BETA_DAILY_TOKEN_LIMIT * 100)) ?>%"></div>
                            </div>
                        </div>
                        <div class="settings-beta-stat">
                            <span class="settings-beta-stat-label">Total</span>
                            <span class="settings-beta-stat-value"><?= number_format($betaTotalUsed) ?> used &middot; <?= number_format($betaTotalLeft) ?> left</span>
                            <div class="settings-beta-bar">
                                <div class="settings-beta-bar-fill" style="width:<?= min(100, round($betaTotalUsed / BETA_TOTAL_TOKEN_LIMIT * 100)) ?>%"></div>
                            </div>
                        </div>
                        <div class="settings-beta-stat">
                            <span class="settings-beta-stat-label">Days remaining</span>
                            <span class="settings-beta-stat-value"><?= $betaDaysLeft ?> of <?= BETA_ACCESS_DAYS ?></span>
                            <div class="settings-beta-bar">
                                <div class="settings-beta-bar-fill" style="width:<?= min(100, round($betaDaysUsed / BETA_ACCESS_DAYS * 100)) ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
            <div class="settings-tab-panel" data-tab="communication">
                <div class="settings-language-row">

                    <div class="settings-language-col">
                        <span class="settings-language-label">Default language</span>
                        <select class="settings-input settings-select" id="settingsLanguage" name="language" data-selected="<?= htmlspecialchars($reading_language, ENT_QUOTES, 'UTF-8') ?>">
                        </select>
                    </div>

                    <div class="settings-language-col">
                        <span class="settings-language-label">Known languages</span>
                        <div class="settings-multi" id="settingsQuickMulti">
                            <div class="settings-multi-selected" id="settingsQuickSelected" onclick="quickLang('open')">
                                <span class="settings-multi-placeholder">Select languages…</span>
                            </div>

                            <div class="settings-multi-dropdown" id="settingsQuickDropdown"></div>
                        </div>

                        <input type="hidden" id="settingsQuickLanguagesInput" name="quick_languages" value='<?= htmlspecialchars(json_encode(array_values($writing_languages)), ENT_QUOTES, 'UTF-8') ?>'>
                    </div>

                </div>
                <div class="settings-assistance-sub">Writing personality</div>
                <div class="settings-section">
                    <div class="settings-personality-grid">
                    <?php foreach ($communicationProfiles as $key => $profile): ?>
                        <label class="settings-personality-option"> <input type="radio" name="communication_profile" value="<?= htmlspecialchars($key) ?>"
                            <?= $communicationProfile === $key ? 'checked' : '' ?>> <span class="settings-personality-copy"> <span class="settings-personality-name">
                                    <?= htmlspecialchars($profile['name']) ?>
                                </span> <span class="settings-personality-desc">
                                    <?= htmlspecialchars($profile['desc']) ?>
                                </span>
                        </span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>

                <div class="settings-section settings-custom-personality">
                    <label class="settings-subtle">Custom personality (optional)</label>
                    <textarea class="settings-input" name="communication_custom_prompt" rows="4" placeholder="Keep it short: natural and warm - cold and distant" style="border-radius: 14px;"><?= htmlspecialchars($communicationCustomPrompt, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
            <div id="settingsNotice" class="settings-notice"></div>
        </form>
        <div class="settings-cookie-footer">
            <button type="button" class="settings-cookie-link" onclick="localStorage.removeItem('cookie_consent'); if(typeof initCookieConsent==='function') initCookieConsent();">Cookie settings</button>
        </div>
<?php if ($embed): ?>
</div>
<?php else: ?>
    </div>
</div>
<?php endif; ?>
<script>
(function () {
    const providerRadios = document.querySelectorAll('input[name="settings_assistant_provider"]');
    const enableCheckbox = document.getElementById('settingsAssistantEnabled');
    const block = document.getElementById('assistantSettingsBlock');
    const form = document.getElementById('settingsForm');
    const keyInput = document.getElementById('settingsAssistantApiKey');
    let providerKeys = {};

    try {
        providerKeys = JSON.parse(form?.dataset?.providerKeys || '{}') || {};
    } catch (e) {
        providerKeys = {};
    }

    function getSelectedProvider() {
        const checked = document.querySelector('input[name="settings_assistant_provider"]:checked');
        return String(checked?.value || 'ChatGPT');
    }

    function persistProviderKeys() {
        if (!form) return;
        form.dataset.providerKeys = JSON.stringify(providerKeys);
    }

    function updateProviderUI() {
        const note = document.querySelector('#settingsApiNote a');
        if (!note) return;

        const provider = getSelectedProvider();
        const links = {
            ChatGPT: 'https://platform.openai.com/api-keys',
            Gemini: 'https://aistudio.google.com/app/apikey',
            Claude: 'https://console.anthropic.com/settings/keys'
        };

        note.href = links[provider] || links.ChatGPT;
        note.textContent = 'here';
    }

    function syncKeyFieldFromProvider() {
        if (!keyInput) return;
        const provider = getSelectedProvider();
        keyInput.value = String(providerKeys[provider] || '');
    }

    function updateAssistVisibility() {
        if (!enableCheckbox || !block) return;
        block.classList.toggle('assistance-disabled', !enableCheckbox.checked);
    }

    let currentProvider = getSelectedProvider();

    if (keyInput && !providerKeys[currentProvider] && keyInput.value.trim() !== '') {
        providerKeys[currentProvider] = keyInput.value;
        persistProviderKeys();
    }

    providerRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (keyInput) {
                providerKeys[currentProvider] = keyInput.value;
            }
            currentProvider = getSelectedProvider();
            persistProviderKeys();
            updateProviderUI();
            syncKeyFieldFromProvider();
        });
    });

    if (keyInput) {
        keyInput.addEventListener('input', () => {
            providerKeys[getSelectedProvider()] = keyInput.value;
            persistProviderKeys();
        });
    }

    if (providerRadios.length) {
        updateProviderUI();
        syncKeyFieldFromProvider();
    }

    if (enableCheckbox) {
        enableCheckbox.addEventListener('change', updateAssistVisibility);
        updateAssistVisibility();
    }

    const toggle = document.getElementById('settingsAssistantEnabled');
    const card = document.querySelector('.settings-assistance-card');

    if (toggle && card) {
        toggle.addEventListener('change', () => {
            card.classList.toggle('enabled', toggle.checked);
        });

        card.classList.toggle('enabled', toggle.checked);
    }
})();
</script>