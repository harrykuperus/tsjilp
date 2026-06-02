<?php
require_once 'config.php';
$appConfig = load_app_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
<title>Tsjilp | Assisted communication</title>
<meta name="description" content="Better conversations, not more messages.">
<link rel="icon" href="/eidolon/assets/images/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/eidolon/assets/images/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/eidolon/assets/images/favicon-16.png">
<link rel="apple-touch-icon" href="/eidolon/assets/images/favicon-180.png">
<meta name="theme-color" content="#0f2a44">
<meta name="application-name" content="Tsjilp">
<meta name="apple-mobile-web-app-title" content="Tsjilp">
<meta property="og:title" content="Tsjilp — Assisted communication">
<meta property="og:description" content="Better conversations, not more messages.">
<meta property="og:type" content="website">
<meta property="og:url" content="https://www.tsjilp.me/">
<meta property="og:image" content="https://www.tsjilp.me/assets/images/tsjilp-logo.png">
<link rel="stylesheet" type="text/css" href="assets/style.css?<?= rand(100, 999) ?>">
<style>
body {
	opacity: 0;
	transition: opacity .15s ease;
}

body.loaded {
	opacity: 1;
}

.bubble {
	position: relative;
	padding: 0;
	border-radius: 16px;
	display: flex;
	flex-direction: column;
	align-items: stretch;
	gap: 0;
	font-size: 14px;
	line-height: 1.55;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
	max-width: 420px;
}

.bubble-text {
	position: relative;
	padding: 10px 12px 6px 10px;
	word-break: break-word;
	width: 99%;
}

.bubble-text label {
	margin-right: 8px;
	font-size: 12px;
	font-weight: 600;
	vertical-align: baseline;
}

.bubble-footer {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 6px;
	padding: 0 8px 6px 8px;
	min-height: 20px;
}

.bubble-time {
	font-size: 11px;
	color: #9ca3af;
	line-height: 1;
}

.message-menu-btn {
	position: absolute;
	top: 9px;
	right: 10px;
	width: 18px;
	height: 18px;
	border: 0;
	background: transparent;
	padding: 0;
	cursor: pointer;
	opacity: .55;
	color: #9ca3af;
	font-size: 16px;
	line-height: 1;
}

.message-menu-btn::before {
	content: "»";
}

.message-menu-btn:hover, .message-menu-btn.open {
	opacity: .95;
}

.temp-flow-inline-draft {
	background: transparent;
	border: 0;
	box-shadow: none;
	padding: 0;
	max-width: 360px;
}

.inline-reply-composer {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	background: transparent;
	border: 0;
	padding: 0;
	box-shadow: none;
}

.inline-reply-actions {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-top: 3px;
	padding-left: 2px;
}

.inline-reply-editor {
	flex: 1 1 auto;
	min-height: 22px;
	outline: none;
	white-space: pre-wrap;
	word-break: break-word;
	font-size: 14px;
	line-height: 1.5;
	color: #1f2937;
	padding-bottom: 1px;
	border-bottom: 1px solid #e5e7eb;
}

.inline-reply-editor:focus {
	border-bottom-color: #cfd6df;
}

.inline-reply-send {
	flex: 0 0 auto;
	min-width: 32px;
	height: 22px;
	border: 0;
	border-radius: 999px;
	background: #e7f7e1;
	color: #6b7280;
	cursor: pointer;
	font-size: 11px;
	line-height: 1;
	padding: 0 9px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, .08);
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.inline-reply-send:hover {
	color: #111827;
	background: #ddf3d6;
}

.inline-reply-icon, .reply-countdown-cancel {
	width: 18px;
	height: 18px;
	border: 0;
	background: transparent;
	color: #7b8494;
	cursor: pointer;
	font-size: 13px;
	line-height: 1;
	padding: 0;
	box-shadow: none;
	margin-left: 0;
}

.inline-reply-icon:hover, .reply-countdown-cancel:hover {
	color: #111827;
}

.reply-countdown {
	font-size: 13px;
	color: #6b7280;
	display: flex;
	align-items: center;
	gap: 8px;
}

.input-focus {
	display: inline-block;
	width: 1px;
	height: 1.2em;
	background: #6b7280;
	margin-left: 2px;
	vertical-align: text-bottom;
	animation: caretBlink 1s steps(1) infinite;
}
.composer-languages {
display:flex;
gap:6px;
align-items:center;
margin-left:6px;
}

.composer-lang {
font-size:12px;
padding:3px 8px;
border:1px solid #e5e7eb;
border-radius:6px;
background:#fff;
cursor:pointer;
color:#374151;
}
.composer-lang.active {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #111827;
    font-weight: 600;
}
.composer-lang:hover {
background:#f9fafb;
}
@keyframes caretBlink { 
50% {opacity: 0;}
}
</style>
<script>
window.APP_CONFIG = <?php echo json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const config = window.APP_CONFIG;

let chatHistory = [];
let currentChatId = null;
let currentChatMeta = null;
let composerRecipient = {type: 'all',recipients: ['all'],label: 'All'};
let temporaryChatUi = {};
let lastMessageCount = 0;
let lastRenderedDay = '';
let chatSearchQuery = '';
let composerReplyLoading = false;
let autoDraftHandledByChat = {};

window.currentUserId = "<?= $_SESSION['user_id'] ?? '' ?>";
let currentSuggestionText = '';
let introStopped = false;
let chatListCache = [];
let archivedChatListCache = [];
const isLoggedIn = <?= empty($_SESSION['user_id']) ? 'false' : 'true' ?>;
const GUEST_TRIAL_COOKIE = 'tsjilp_guest_trial';
const GUEST_TRIAL_LIMIT = 10;

let userAssistantSettings = {
    enabled: false,
    provider: 'openai',
    hasKey: false,
    keyMasked: ''
};

let composerAssistantSettings = {
    enabled: true,
    mode: 'adaptive',
    draftReplies: true,
    checkBeforeSend: true,
    toneSuggestions: false,
    translate: false,
    variations: false
};

let composerSuggestionState = {
    original: '',
    suggestion: '',
    loading: false
};

let nextOutgoingAssistLabel = '';
let activeMessageMenu = null;
let activeMessageMenuBtn = null;
let editingMessageId = null;
let editingMessageWrap = null;
let replyCountdownTimers = {};
const REPLY_COUNTDOWN_SECONDS = 8;
    
const pendingInviteId = new URLSearchParams(window.location.search).get('invite') || '';

const originalFetch = window.fetch.bind(window);
window.fetch = async (...args) => {
    const res = await originalFetch(...args);

    if (res && res.status === 401) {
        let shouldOpenLogin = false;

        try {
            const clone = res.clone();
            const data = await clone.json();

            if (data?.error === 'Not logged in') {
                shouldOpenLogin = true;
            }
        } catch (e) {
            // ignore non-json 401 responses
        }

        if (shouldOpenLogin) {
            setTimeout(() => {
                if (typeof openAuthModal === 'function') openAuthModal('login');
            }, 0);
        }
    }

    return res;
};

const assistantSteps = {
        incoming_assist: { enabled: true, status: 'placeholder' },
        outgoing_check: { enabled: true, status: 'placeholder' },
        catchup: { enabled: true, status: 'placeholder' },
        private_lane: { enabled: true, status: 'placeholder' }
    };

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()\[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

function setCookie(name, value, days = 30) {
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; SameSite=Lax`;
}

function getGuestCreditsLeft() {
    if (isLoggedIn) return GUEST_TRIAL_LIMIT;
    const raw = parseInt(getCookie(GUEST_TRIAL_COOKIE) || GUEST_TRIAL_LIMIT, 10);
    return Number.isFinite(raw) ? Math.max(0, Math.min(GUEST_TRIAL_LIMIT, raw)) : GUEST_TRIAL_LIMIT;
}

function setGuestCreditsLeft(value) {
    setCookie(GUEST_TRIAL_COOKIE, Math.max(0, value));
}

function consumeGuestCredit() {
    const left = getGuestCreditsLeft();
    const next = Math.max(0, left - 1);
    setGuestCreditsLeft(next);
    return next;
}

function canUseGuestTrial() {
    return !isLoggedIn && getGuestCreditsLeft() > 0;
}

async function handleGuestTrialSend(text) {
    const input = document.getElementById('input');
    const actionBtn = document.getElementById('actionBtn');
    const composerWrap = document.getElementById('composerWrap');

    composerWrap?.classList.add('active');
    document.getElementById('chatIntro')?.remove();
    
    const state = getTempUiState('__guest__');
    state.items.push({
        kind: 'assistant_user',
        label: 'guest',
        content: text
    });
    renderTemporaryFlow();
    resetTextarea(input);
    renderThinking();
    if (actionBtn) actionBtn.disabled = true;

    const prompt = [
        'You are the Tsjilp assistant in guest trial mode.',
        'Answer briefly, clearly, and helpfully.',
        'Show the value of assisted communication.',
        'Do not mention API keys or settings unless the user asks.',
        '',
        text
    ].join('\n');

    try {
        const data = await sendToAPI([{ role: 'user', content: prompt }], 'assist_guest');
        const answer = data?.reply || data?.text || data?.message || data?.error || 'I could not help with that right now.';

        const guestState = getTempUiState('__guest__');
        guestState.items = guestState.items.filter(item => item.kind !== 'thinking');
        guestState.items.push({
            kind: 'assistant_reply',
            content: answer,
            draft: answer
        });
        consumeGuestCredit();
        updateGuestTrialUi();
    } catch (err) {
        const guestState = getTempUiState('__guest__');
        guestState.items = guestState.items.filter(item => item.kind !== 'thinking');
        guestState.items.push({
            kind: 'assistant_reply',
            content: 'I could not help with that right now.'
        });
    } finally {
        if (actionBtn) actionBtn.disabled = false;
        renderTemporaryFlow();
        focusInput();
    }
}

function updateGuestTrialUi() {
    const assistantStatus = document.getElementById('assistantStatus');
    if (!assistantStatus || isLoggedIn) return;
    const left = getGuestCreditsLeft();
    assistantStatus.textContent = left > 0
        ? `Try Tsjilp assistant free · ${left} ${left === 1 ? 'message' : 'messages'} left`
        : 'Free trial finished · Sign up to continue';
}

function scrollChatToBottom(target = false) {
    const chat = document.getElementById('chatScroll');
    if (!chat) return;

    if (typeof target === 'string' && target.trim()) {
        const targetWrap = chat.querySelector(`.message-wrap[data-message-id="${CSS.escape(target.trim())}"]`);
        const targetRow = targetWrap?.closest('.message-row') || targetWrap?.closest('.assistant-info-row');

        if (targetRow) {
            const chatRect = chat.getBoundingClientRect();
            const rowRect = targetRow.getBoundingClientRect();

            const start = chat.scrollTop;
            const end = start + (rowRect.top - chatRect.top) - 24;
            const distance = end - start;
            const duration = Math.min(600, Math.max(200, Math.abs(distance) / 2));

            let startTime = null;

            function animateScroll(timestamp) {
                if (!startTime) startTime = timestamp;
                const progress = timestamp - startTime;
                const percent = Math.min(progress / duration, 1);

                const ease = percent < 0.5
                    ? 2 * percent * percent
                    : 1 - Math.pow(-2 * percent + 2, 2) / 2;

                chat.scrollTop = start + distance * ease;

                if (percent < 1) {
                    requestAnimationFrame(animateScroll);
                }
            }

            requestAnimationFrame(animateScroll);
            return;
        }
    }

    const start = chat.scrollTop;
    const end = chat.scrollHeight;
    const distance = end - start;
    const duration = Math.min(600, Math.max(200, Math.abs(distance) / 2));

    let startTime = null;

    function animateScroll(timestamp) {
        if (!startTime) startTime = timestamp;
        const progress = timestamp - startTime;
        const percent = Math.min(progress / duration, 1);

        const ease = percent < 0.5
            ? 2 * percent * percent
            : 1 - Math.pow(-2 * percent + 2, 2) / 2;

        chat.scrollTop = start + distance * ease;

        if (percent < 1) {
            requestAnimationFrame(animateScroll);
        }
    }

    requestAnimationFrame(animateScroll);
}

function focusInput() {
//     const input = document.getElementById('input');
//     if (input) input.focus();
}

window.addEventListener('load', async () => {
    
    const sidebar = document.getElementById('sidebar');
    const btnInside = document.getElementById('leftmenu');
    const btnFloating = document.getElementById('leftmenuFloating');

    const chatScroll = document.getElementById('chatScroll');
    const scrollBtn = document.getElementById('scrollToBottom');
    
    chatScroll?.addEventListener('scroll', () => {
        const isNearBottom = chatScroll.scrollHeight - chatScroll.scrollTop - chatScroll.clientHeight < 120;
        scrollBtn.style.display = isNearBottom ? 'none' : 'block';
    });

    function toggleSidebar() {
        sidebar.classList.toggle('hidden');
    }

    if (btnInside) btnInside.onclick = toggleSidebar;
    if (btnFloating) btnFloating.onclick = toggleSidebar;

    if (window.innerWidth < 768) {
        sidebar.classList.add('hidden');
    }
    
    const input = document.getElementById('input');
    
    input.addEventListener('input', function () {
        autoGrowTextarea.call(input);
        cancelAllReplyCountdowns();
    });
    
    input.addEventListener('keydown', async function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            await handleSend();
        }
    });

    autoGrowTextarea.call(input);

    if (isLoggedIn) {
        await loadAssistantSettings();
        await bootChats();
    }

    await handlePendingInviteOnLoad();

    if (isLoggedIn) {
        setInterval(pollMessages, 15000);
    }

    const assistantToggleBtn = document.getElementById('assistantToggleBtn');
    const assistantMenu = document.getElementById('assistantMenu');
    const assistantStatus = document.getElementById('assistantStatus');
    const actionBtn = document.getElementById('actionBtn');

    const assistEnabled = document.getElementById('assistEnabled');
    const optDraftReplies = document.getElementById('optDraftReplies');
    const optCheckBeforeSend = document.getElementById('optCheckBeforeSend');
    const optToneSuggestions = document.getElementById('optToneSuggestions');
    const optTranslate = document.getElementById('optTranslate');
    const optVariations = document.getElementById('optVariations');
    
    const ASSISTANT_SETTINGS_KEY = 'tsjilp_assistant_settings';

    let assistantSettings = { ...composerAssistantSettings };
    loadComposerAssistantSettings();
    
    function loadComposerAssistantSettings() {
        try {
            const saved = JSON.parse(localStorage.getItem(ASSISTANT_SETTINGS_KEY) || '{}');
            assistantSettings = { ...assistantSettings, ...saved };
        } catch (e) {}

        if (!userAssistantSettings?.hasKey) {
            assistantSettings.enabled = false;
        }

        composerAssistantSettings = { ...assistantSettings };
        syncAssistantForm();
        updateAssistantStatus();
    }

    function saveComposerAssistantSettings() {
        assistantSettings.enabled = userAssistantSettings?.hasKey ? !!assistEnabled.checked : false;
        assistantSettings.mode = document.querySelector('input[name="assistMode"]:checked')?.value || 'adaptive';
        assistantSettings.draftReplies = !!optDraftReplies.checked;
        assistantSettings.checkBeforeSend = !!optCheckBeforeSend.checked;
        assistantSettings.toneSuggestions = !!optToneSuggestions.checked;
        assistantSettings.translate = !!optTranslate.checked;
        assistantSettings.variations = !!optVariations.checked;

        composerAssistantSettings = { ...assistantSettings };
        localStorage.setItem(ASSISTANT_SETTINGS_KEY, JSON.stringify(assistantSettings));
        updateAssistantStatus();
        renderRecipientPills();
    }

    function syncAssistantForm() {
        assistEnabled.checked = !!assistantSettings.enabled;
        optDraftReplies.checked = !!assistantSettings.draftReplies;
        optCheckBeforeSend.checked = !!assistantSettings.checkBeforeSend;
        optToneSuggestions.checked = !!assistantSettings.toneSuggestions;
        optTranslate.checked = !!assistantSettings.translate;
        optVariations.checked = !!assistantSettings.variations;

        const modeRadio = document.querySelector(`input[name="assistMode"][value="${assistantSettings.mode}"]`);
        if (modeRadio) modeRadio.checked = true;
    }

    function updateAssistantStatus() {
        if (!assistantStatus) return;

        const dot = assistantSettings.enabled ? '<span class="assistant-dot on"></span>' : '<span class="assistant-dot off"></span>';

        if (!userAssistantSettings?.hasKey) {
            assistantStatus.innerHTML = `<span class="assistant-dot off"></span>Assistant off · API key missing`;
            return;
        }
        
        if (!assistantSettings.enabled) {
            assistantStatus.innerHTML = `${dot}Assistant off`;
            return;
        }

        const modeLabelMap = {
            adaptive: 'Adaptive',
            always: 'Always assist',
            manual: 'Manual only'
        };

        const activeOptions = [];
        if (assistantSettings.checkBeforeSend) activeOptions.push('Check before sending');
        if (assistantSettings.draftReplies) activeOptions.push('Draft replies');
        if (assistantSettings.translate) activeOptions.push('Translate');
        if (assistantSettings.toneSuggestions) activeOptions.push('Tone suggestions');
        if (assistantSettings.variations) activeOptions.push('Variations');

        const summary = activeOptions.length ? activeOptions.join(' · ') : 'No extra options';

        assistantStatus.innerHTML =
            `${dot}Assistant on · ${modeLabelMap[assistantSettings.mode]} · ${summary}`;
    }

    function toggleAssistantMenu(force) {
        if (!assistantMenu) return;
        const open = typeof force === 'boolean' ? force : assistantMenu.classList.contains('hidden');
        assistantMenu.classList.toggle('hidden', !open);
    }

    function autosizeTextarea(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 180) + 'px';
    }

    function assistantPrefillReply(text) {
        if (!input) return;
        input.value = text || '';
        autosizeTextarea(input);
        focusInput();
    }

    function checkMessageBeforeSend(message) {
        if (!assistantSettings.enabled) return { ok: true, message };
        if (!assistantSettings.checkBeforeSend) return { ok: true, message };
        if (assistantSettings.mode === 'manual') return { ok: true, message };

        let checked = message.trim();

        checked = checked.replace(/\s{2,}/g, ' ');
        checked = checked.replace(/\s+([,.!?;:])/g, '$1');

        if (checked && !/[.!?]$/.test(checked)) {
            checked += '.';
        }

        return { ok: true, message: checked };
    }

    const sidebarSearch = document.querySelector('.sidebar-search');
    if (sidebarSearch) {
        sidebarSearch.addEventListener('input', function () {
            chatSearchQuery = this.value || '';
            applyChatSearch();
        });
    }
    
    assistantToggleBtn?.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleAssistantMenu();
    });

    document.addEventListener('click', function (e) {
        if (!assistantMenu || !assistantToggleBtn) return;
        if (assistantMenu.classList.contains('hidden')) return;
        if (assistantMenu.contains(e.target) || assistantToggleBtn.contains(e.target)) return;
        toggleAssistantMenu(false);
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.message-menu-btn') || e.target.closest('.message-menu')) return;
        closeAllMessageMenus();
    });

    assistantMenu?.addEventListener('change', function () {
        saveComposerAssistantSettings();
        refreshAssistantUi();
        renderRecipientPills();
    });

    loadComposerAssistantSettings();
    refreshAssistantUi();
    renderRecipientPills();
    renderComposerLanguages();

    input?.addEventListener('input', function () {
        autosizeTextarea(input);
        cancelAllReplyCountdowns();
    });

    actionBtn?.addEventListener('click', async function () {
        await handleSend();
    });

    document.getElementById('recipientPills')?.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-recipient-type]');
        if (!btn) return;
        setComposerRecipient(
            btn.getAttribute('data-recipient-type') || 'all',
            btn.getAttribute('data-recipient-value') || 'all',
            btn.getAttribute('data-recipient-label') || 'All'
        );
    });
    
    autosizeTextarea(input);
    
    document.body.classList.add('loaded');
    
});

function getTempUiState(chatId = currentChatId || '__new__') {
    if (!temporaryChatUi[chatId]) temporaryChatUi[chatId] = { items: [] };
    return temporaryChatUi[chatId];
}

function clearTempUiForChat(chatId = currentChatId || '__new__') {
    temporaryChatUi[chatId] = { items: [] };
}

function setComposerRecipient(type, value = 'all', label = 'All') {
    composerRecipient = { type, recipients: [value], label };
    renderRecipientPills();
    updateGuestTrialUi();
}

function getRecipientOptions() {
    const options = [
        { type: 'all', value: 'all', label: 'All' }
    ];

    const participants = Array.isArray(currentChatMeta?.participants)
        ? currentChatMeta.participants
        : [];

    const seen = new Set();

    participants.forEach(p => {
        const id = String(p.user_id || p.email || p.name || '').trim();
        if (!id || id === String(window.currentUserId || '')) return;

        const first = String(p.name || p.email || 'User').split(' ')[0];
        if (!first || seen.has(first.toLowerCase())) return;

        seen.add(first.toLowerCase());

        options.push({
            type: 'participant',
            value: id,
            label: first
        });
    });

    const assistantVisible =
        !!composerAssistantSettings?.enabled &&
        !!userAssistantSettings?.enabled;

    if (assistantVisible) {
      options.push({
          type: 'assistant',
          value: 'assistant',
          label: userAssistantSettings?.hasKey ? 'Assistant' : 'Assistant (no key)'
      });
    }

    if (!assistantVisible && composerRecipient?.type === 'assistant') {
        composerRecipient = {
            type: 'all',
            recipients: ['all'],
            label: 'All'
        };
    }

    return options;
}

function renderRecipientPills() {
    const wrap = document.getElementById('recipientPills');
    if (!wrap) return;

    const options = getRecipientOptions();

    wrap.innerHTML = options.map(opt => {
        const active =
            composerRecipient.type === opt.type &&
            composerRecipient.recipients?.[0] === opt.value;

        return `
            <button
                type="button"
                class="recipient-pill ${active ? 'active' : ''}"
                data-type="${escapeHtml(opt.type)}"
                data-recipient-type="${escapeHtml(opt.type)}"
                data-recipient-value="${escapeHtml(opt.value)}"
                data-recipient-label="${escapeHtml(opt.label)}"
            >
                ${escapeHtml(opt.label)}
            </button>
        `;
    }).join('');
}

function renderTemporaryFlow() {
    const chat = document.getElementById('chat');
    if (!chat) return;

    chat.querySelectorAll('.temp-flow-row').forEach(el => el.remove());

    const items = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__')).items || [];

    items.forEach((item, idx) => {
        const row = document.createElement('div');

        const isAssistantReply =
            item.kind === 'assistant_reply' ||
            item.kind === 'live_helper' ||
            item.kind === 'thinking' ||
            item.kind === 'reply_countdown';

        const isUserSide = !isAssistantReply;

        const targetMessageId = item.targetMessageId || '';
        const targetWrap = targetMessageId
            ? chat.querySelector(`.message-wrap[data-message-id="${CSS.escape(targetMessageId)}"]`)
            : null;

        const targetIsIncoming = !!targetWrap?.classList.contains('incoming');
        const sideClass = targetIsIncoming ? 'left' : 'right';
        const isInlineAssist = !!targetMessageId;
        const assistantLabel = isInlineAssist ? 'Suggested reply' : 'Assistant';
        const normalizedContent = String(item.content || '').trim().toLowerCase();
        const isPassiveAssistantState = ['no edits suggested','message unclear','Assist unavailable'].includes(normalizedContent);
        const showDraftActions = item.kind === 'assistant_reply' && item.draft && isInlineAssist && !isPassiveAssistantState; 
        row.className = 'temp-flow-row ' + sideClass + ' ' + (isUserSide ? 'temp-flow-user-row' : 'temp-flow-assistant-row');

        row.dataset.tempIdx = String(idx);
        row.dataset.targetMessageId = targetMessageId;

        if (item.kind === 'assistant_user' && item.label !== 'guest' && composerRecipient?.type !== 'assistant') {
            return;
        }

        const bubble = document.createElement('div');

        if (item.kind === 'thinking') {
            bubble.className = 'temp-flow-item temp-flow-thinking';
            bubble.innerHTML = `
                <span class="thinking-dot"></span>
                <span class="thinking-dot"></span>
                <span class="thinking-dot"></span>
            `;
        } else if (item.kind === 'live_helper') {
            bubble.className = 'temp-flow-item temp-flow-assistant live-helper';
            bubble.textContent = ((item.meta || '') + ((item.content) ? '\n\n' + item.content : '')).trim();
        } else if (item.kind === 'reply_countdown') {
            bubble.className = 'temp-flow-item temp-flow-assistant temp-flow-reply-countdown';
            bubble.innerHTML = `
                <div class="reply-countdown-row">
                    <span class="reply-countdown-text">Reply in ${Number(item.seconds || 0)}</span>
                    <button
                        type="button"
                        class="reply-countdown-cancel"
                        data-target-message-id="${escapeHtml(item.targetMessageId || '')}"
                    >✕</button>
                </div>
            `;
        } else {
            bubble.className =
                'temp-flow-item ' +
                (isAssistantReply ? 'temp-flow-assistant' : 'temp-flow-user');
        
            if (item.kind === 'assistant_reply') {
                if (targetMessageId) {
                    bubble.classList.add('temp-flow-inline-draft');
                    bubble.innerHTML = `
                        <div class="inline-reply-composer" data-target-message-id="${escapeHtml(targetMessageId)}">
                            <div
                                class="inline-reply-editor"
                                contenteditable="true"
                                spellcheck="true"
                                data-target-message-id="${escapeHtml(targetMessageId)}"
                                onfocus="this.textContent = this.innerText"
                            >${escapeHtml(item.content || '')}</div>
                        </div>
            
                        <div class="inline-reply-actions">
                            <button
                                class="inline-reply-icon"
                                data-action="rewrite-inline-reply"
                                data-target-message-id="${escapeHtml(targetMessageId)}"
                                title="Rewrite"
                                aria-label="Rewrite"
                            >↻</button>
            
                            <button
                                class="inline-reply-icon"
                                data-action="dismiss-inline-reply"
                                data-target-message-id="${escapeHtml(targetMessageId)}"
                                title="Dismiss"
                                aria-label="Dismiss"
                            >✕</button>
                            <button
                                class="inline-reply-send"
                                data-action="send-inline-reply"
                                data-target-message-id="${escapeHtml(targetMessageId)}"
                                title="Send"
                                aria-label="Send"
                            >➤</button>
                        </div>
                    `;
                } else {
                    bubble.className = 'temp-flow-item temp-flow-assistant';
                    bubble.textContent = item.content || '';
                }
            } else {
                bubble.textContent = item.content || '';
            }
        }

        row.appendChild(bubble);

        const inlineEditor = bubble.querySelector('.inline-reply-editor');
        if (inlineEditor) {
            const caret = document.createElement('span');
            caret.className = 'input-focus';
            inlineEditor.appendChild(caret);
        }
        
        const cancelBtn = bubble.querySelector('.reply-countdown-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                cancelReplyCountdown(this.dataset.targetMessageId || '');
            });
        }
        
        const sendBtn = bubble.querySelector('[data-action="send-inline-reply"]');
        if (sendBtn) {
            sendBtn.addEventListener('click', function () {
                sendInlineReply(this.dataset.targetMessageId || '');
            });
        }
        
        const dismissBtn = bubble.querySelector('[data-action="dismiss-inline-reply"]');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                removeInlineReplyDraft(this.dataset.targetMessageId || '');
            });
        }

        const rewriteBtn = bubble.querySelector('[data-action="rewrite-inline-reply"]');
        if (rewriteBtn) {
            rewriteBtn.addEventListener('click', async function () {
                const targetMessageId = this.dataset.targetMessageId || '';
                if (!targetMessageId) return;
        
                const targetWrap = document.querySelector(
                    `.message-wrap[data-message-id="${CSS.escape(String(targetMessageId))}"]`
                );
                if (!targetWrap) return;
        
                const editor = bubble.querySelector('.inline-reply-editor');
                if (!editor) return;
        
                const sourceText = String(editor.innerText || '').trim();
                if (!sourceText) return;
        
                await runMenuAssist(targetWrap, 'rewrite', sourceText);
            });
        }

        if (item.kind === 'live_helper' && item.actions === 'suggestion') {
            const actions = document.createElement('div');
            actions.className = 'temp-flow-actions';
            actions.innerHTML = `
                <button type="button" class="temp-flow-action primary" onclick="useComposerSuggestionAndSend()">Use & send</button>
                <button type="button" class="temp-flow-action" onclick="editComposerSuggestion()">Edit</button>
                <button type="button" class="temp-flow-action" onclick="sendComposerOriginal()">Send original</button>
                <button type="button" class="temp-flow-action" onclick="hideComposerSuggestion()">Clear</button>
            `;
            row.appendChild(actions);
        }

        if (targetWrap) {
            const anchorRow = targetWrap.closest('.message-row') || targetWrap.closest('.assistant-info-row');
            let insertAfter = anchorRow;

            while (
                insertAfter &&
                insertAfter.nextElementSibling &&
                insertAfter.nextElementSibling.classList.contains('temp-flow-row') &&
                insertAfter.nextElementSibling.dataset.targetMessageId === targetMessageId
            ) {
                insertAfter = insertAfter.nextElementSibling;
            }

            if (insertAfter && insertAfter.parentNode) {
                insertAfter.parentNode.insertBefore(row, insertAfter.nextElementSibling);
            } else {
                chat.appendChild(row);
            }
        } else {
            chat.appendChild(row);
        }
    });

}

function clearAssistantFlow(idx = null) {
    const state = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__'));
    if (idx === null) state.items = [];
    else state.items = state.items.filter((_, i) => i !== idx && i !== idx-1);
    renderTemporaryFlow();
}

function startReplyCountdown(targetMessageId, sourceText) {
    if (!targetMessageId || !sourceText) return;

    cancelReplyCountdown(targetMessageId);

    const state = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__'));

    state.items = state.items.filter(item =>
        !(item.targetMessageId === targetMessageId &&
          (item.kind === 'thinking' || item.kind === 'reply_countdown' || item.kind === 'assistant_reply'))
    );

    state.items.push({
        kind: 'reply_countdown',
        targetMessageId,
        sourceText,
        seconds: REPLY_COUNTDOWN_SECONDS
    });

    renderTemporaryFlow();
    scrollChatToBottom(targetMessageId || true);

    replyCountdownTimers[targetMessageId] = window.setInterval(async () => {
        const currentState = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__'));
        const countdownItem = currentState.items.find(item =>
            item.kind === 'reply_countdown' && item.targetMessageId === targetMessageId
        );

        if (!countdownItem) {
            cancelReplyCountdown(targetMessageId);
            return;
        }

        countdownItem.seconds = Math.max(0, Number(countdownItem.seconds || 0) - 1);

        if (countdownItem.seconds <= 0) {
            const latestSourceText = String(countdownItem.sourceText || sourceText || '');
            cancelReplyCountdown(targetMessageId);
            await runIncomingAssistPlaceholder(targetMessageId, latestSourceText, 'reply');
            return;
        }

        renderTemporaryFlow();
        scrollChatToBottom(targetMessageId || true);
        
    }, 1000);
}

function cancelReplyCountdown(targetMessageId) {
    if (!targetMessageId) return;

    if (replyCountdownTimers[targetMessageId]) {
        clearInterval(replyCountdownTimers[targetMessageId]);
        delete replyCountdownTimers[targetMessageId];
    }

    const state = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__'));
    state.items = state.items.filter(item =>
        !(item.kind === 'reply_countdown' && item.targetMessageId === targetMessageId)
    );

    renderTemporaryFlow();
}

function cancelAllReplyCountdowns() {
    Object.keys(replyCountdownTimers).forEach(targetMessageId => {
        cancelReplyCountdown(targetMessageId);
    });
}

function upsertInlineReplyDraft(targetMessageId, draftText, labelText = 'assisted reply') {
    if (!targetMessageId || !draftText) return;

    const state = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__'));

    state.items = state.items.filter(item =>
        !(item.targetMessageId === targetMessageId &&
          (item.kind === 'thinking' || item.kind === 'reply_countdown' || item.kind === 'assistant_reply'))
    );

    state.items.push({
        kind: 'assistant_reply',
        targetMessageId,
        content: draftText,
        draft: draftText,
        replyLabel: labelText
    });

    renderTemporaryFlow();
    // scrollChatToBottom(targetMessageId || true);
}

function removeInlineReplyDraft(targetMessageId) {
    if (!targetMessageId) return;

    const state = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__'));
    state.items = state.items.filter(item =>
        !(item.targetMessageId === targetMessageId &&
          (item.kind === 'assistant_reply' || item.kind === 'reply_countdown'))
    );

    renderTemporaryFlow();
}

async function sendInlineReply(targetMessageId) {
    if (!targetMessageId) return;

    const editor = document.querySelector(`.inline-reply-editor[data-target-message-id="${CSS.escape(String(targetMessageId))}"]`);
    if (!editor) return;

    const text = String(editor.innerText || '').trim();
    if (!text) return;

    const recipientLabel = '';
    const messageMeta = {
        labels: [
            {
                type: 'assisted_reply',
                text: 'assisted reply'
            }
        ]
    };

    const userMsg = {
        id: '',
        role: 'user',
        content: text,
        text: text,
        reply_to: targetMessageId || null,
        recipient_label: recipientLabel
    };

    chatHistory.push(userMsg);
    renderMessage(userMsg, {
        historyIndex: chatHistory.length - 1,
        insertAfterMessageId: targetMessageId
    });
    // scrollChatToBottom(targetMessageId || true);

    removeInlineReplyDraft(targetMessageId);

    const participants = Array.isArray(currentChatMeta?.participants) ? currentChatMeta.participants : [];
    const isAloneChat = participants.length <= 1;

    if (isAloneChat) {
        await refreshChatList(currentChatId);
        return;
    }

    const data = await sendToAPI(chatHistory, 'chat', {
        meta: messageMeta,
        recipient_label: recipientLabel,
        reply_to: targetMessageId || ''
    });

    if (data.multi) {
        lastMessageCount = data.message_count || lastMessageCount;
        await refreshChatList(currentChatId);
        return;
    }

    if (!canUseAssistantFeatures()) {
        return;
    }

    renderThinking();

    const assistantMsg = {
        role: 'assistant',
        content: data?.reply || data?.text || data?.error || data?.message || 'Error'
    };
    chatHistory.push(assistantMsg);

    const state = getTempUiState();
    state.items = state.items.filter(item => item.kind !== 'thinking');
    renderTemporaryFlow();

    renderMessage(assistantMsg, { historyIndex: chatHistory.length - 1 });

    if (data.chat_id) currentChatId = data.chat_id;
    updateHeaderForChat(data);
    await refreshChatList(currentChatId);
}

function shouldAutoDraftIncomingReplies() {
    const settings = composerAssistantSettings || {};
    if (!isLoggedIn) return false;
    if (!currentChatId) return false;
    if (!settings.enabled) return false;
    if (!settings.draftReplies) return false;
    if (settings.mode === 'manual') return false;
    if (!userAssistantSettings.enabled || !userAssistantSettings.hasKey) return false;
    return true;
}

function canUseAssistantFeatures() {
    return !!(
        isLoggedIn &&
        userAssistantSettings?.enabled &&
        userAssistantSettings?.hasKey
    );
}

function getSelectedAssistantProviderLabel() {
    if (userAssistantSettings.provider === 'gemini') return 'Gemini';
    return 'ChatGPT';
}

function getAssistantStatusText() {
    if (!isLoggedIn) {
        const left = getGuestCreditsLeft();
        return left > 0
            ? `Try Tsjilp assistant free · ${left} ${left === 1 ? 'message' : 'messages'} left`
            : 'Free trial finished · Sign up to continue';
    }
    if (!userAssistantSettings.enabled) return 'Normal chat only';
    if (!userAssistantSettings.hasKey) return 'Add API key';
    return getSelectedAssistantProviderLabel() + ' connected';
}

function refreshAssistantUi() {
    const statusEls = document.querySelectorAll('[data-assistant-status]');
    statusEls.forEach(el => {
        el.textContent = getAssistantStatusText();
        el.classList.toggle(
            'assistant-status-live',
            isLoggedIn && userAssistantSettings.enabled && userAssistantSettings.hasKey
        );
    });

    // only update visible labels, not metadata nodes
    const providerEls = document.querySelectorAll('[data-assistant-provider-label]');
    providerEls.forEach(el => {
        el.textContent = getSelectedAssistantProviderLabel();
    });

    renderRecipientPills();
}

async function loadAssistantSettings() {
    try {
        await openSettingsModal(true);
    } catch (err) {
        console.error('Could not preload assistant settings', err);
    }
}

function toggleInfoTip(id) {
    const tip = document.getElementById(id);
    if (!tip) return;
    tip.classList.toggle('hidden');
}

function updateHeaderForChat(chat) {
    const titleEl = document.getElementById('headerTitle');
    const subEl = document.getElementById('headerSubline');
    const avatarsEl = document.getElementById('chatHeaderAvatars');
    if (!titleEl || !subEl || !avatarsEl) return;

    const participants = Array.isArray(chat?.participants) ? chat.participants.slice() : [];
    const orderedParticipants = participants.sort((a, b) => {
        const ar = String(a?.role || '') === 'owner' ? 0 : 1;
        const br = String(b?.role || '') === 'owner' ? 0 : 1;
        return ar - br;
    });

    const chatTitle = String(chat?.title || 'New chat');
    const names = orderedParticipants
        .map(p => String(p?.name || '').split(' ')[0])
        .filter(Boolean)
        .join(', ');

    titleEl.textContent = chatTitle;
    subEl.textContent = names;
    avatarsEl.innerHTML = renderHeaderAvatars(chat);
}

function isOwnerChat(chat = null) {
    if (!chat) return false;

    if (typeof chat.is_owner !== 'undefined') {
        return !!chat.is_owner;
    }

    const me = String(window.currentUserId || '');
    const participants = Array.isArray(chat.participants) ? chat.participants : [];
    return participants.some(p =>
        String(p.user_id || '') === me && String(p.role || '') === 'owner'
    );
}

function updateInviteButtonsVisibility(chat = null) {
    const topBtn = document.getElementById('chatHeaderInviteTopBtn');
    const menuBtn = document.getElementById('chatHeaderInviteMenuBtn');

    const visible = isOwnerChat(chat);

    if (topBtn) {
        topBtn.classList.toggle('hidden', !visible);
    }

    if (menuBtn) {
        menuBtn.classList.toggle('hidden', !visible);
    }
}

// -------------------------
// CHAT BOOT / SIDEBAR
// -------------------------
async function bootChats() {
    const data = await fetchChatList();

    chatListCache = data.chats || [];
    archivedChatListCache = data.archived_chats || [];
    renderChatList(chatListCache, data.current_chat_id, archivedChatListCache);

    bindChatListEvents();

    if (data.current_chat_id) {
        await loadChat(data.current_chat_id);
        return;
    }

    if (chatListCache.length) {
        await loadChat(chatListCache[0].id);
        return;
    }

    if (archivedChatListCache.length) {
        currentChatId = null;
        currentChatMeta = null;
        chatHistory = [];
        clearConversationUI();
        updateHeaderForChat({ title: 'New chat', participants: [] });
        updateInviteButtonsVisibility(null);
        renderRecipientPills();
        return;
    }

    await createNewChat();
}

async function fetchChatList() {
    const res = await fetch('api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=list_chats' : '&action=list_chats' ?>', {
        credentials: 'same-origin'
    });
    return await res.json();
}

async function refreshChatList(preferredChatId = null) {
    const data = await fetchChatList();
    if (!data?.ok) return;

    chatListCache = data.chats || [];
    archivedChatListCache = data.archived_chats || [];
    currentChatId = preferredChatId || data.current_chat_id || currentChatId;

    applyChatSearch();
}

function getOwnerInitials(name = '') {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'U';
    return parts.slice(0, 2).map(part => part[0] || '').join('').toUpperCase();
}

function getChatListAvatarStyle(seed = '') {
    return `background:${escapeHtml(getAvatarColor(seed))};color:${escapeHtml(getNameColor(seed))};`;
}

function getChatListParticipants(chat) {
    const names = Array.isArray(chat?.participant_names) ? chat.participant_names : [];
    const unique = [];
    const seen = new Set();

    names.forEach(name => {
        const cleaned = String(name || '').trim();
        if (!cleaned) return;
        const key = cleaned.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        unique.push(cleaned);
    });

    return unique;
}

function normalizeChatSearchValue(value = '') {
    return String(value || '').trim().toLowerCase();
}

function chatMatchesSearch(chat, query) {
    const q = normalizeChatSearchValue(query);
    if (q.length < 2) return true;

    const haystack = [
        String(chat?.title || ''),
        String(chat?.owner_name || ''),
        ...(Array.isArray(chat?.participant_names) ? chat.participant_names : [])
    ]
        .join(' ')
        .toLowerCase();

    return haystack.includes(q);
}

function applyChatSearch() {
    const visibleChats = (chatListCache || []).filter(chat => chatMatchesSearch(chat, chatSearchQuery));
    const visibleArchivedChats = (archivedChatListCache || []).filter(chat => chatMatchesSearch(chat, chatSearchQuery));

    renderChatList(visibleChats, currentChatId, visibleArchivedChats);
}

function renderChatListAvatars(chat) {
    const names = getChatListParticipants(chat).slice(0, 3);
    if (!names.length) {
        const ownerSeed = String(chat.owner_name || (chat.is_owner ? 'You' : 'Shared chat'));
        return `<span class="chat-history-avatars"><span class="chat-history-avatar" style="${getChatListAvatarStyle(ownerSeed)}">${escapeHtml(getOwnerInitials(ownerSeed))}</span></span>`;
    }

    return `<span class="chat-history-avatars">${names.map(name => `<span class="chat-history-avatar" style="${getChatListAvatarStyle(name)}">${escapeHtml(getOwnerInitials(name))}</span>`).join('')}</span>`;
}


function renderHeaderAvatars(chat) {
    const participants = Array.isArray(chat?.participants) ? chat.participants.slice() : [];
    const ordered = participants.sort((a, b) => {
        const ar = String(a?.role || '') === 'owner' ? 0 : 1;
        const br = String(b?.role || '') === 'owner' ? 0 : 1;
        return ar - br;
    }).map(p => String(p?.name || '').trim()).filter(Boolean).slice(0, 3);

    if (!ordered.length) {
        const ownerSeed = String(chat?.owner_name || 'Tsjilp.me');
        return `<span class="chat-header-avatar" style="${getChatListAvatarStyle(ownerSeed)}">${escapeHtml(getOwnerInitials(ownerSeed))}</span>`;
    }

    return ordered.map(name => `<span class="chat-header-avatar" style="${getChatListAvatarStyle(name)}">${escapeHtml(getOwnerInitials(name))}</span>`).join('');
}

function formatUnreadBadge(count = 0) {
    const unread = Math.max(0, Number(count || 0));
    if (!unread) return '';
    return `<span class="chat-history-badge">${unread > 99 ? '99+' : unread}</span>`;
}

function formatChatListTime(value = '') {
    if (!value) return '';

    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';

    const now = new Date();
    const sameDay =
        d.getFullYear() === now.getFullYear() &&
        d.getMonth() === now.getMonth() &&
        d.getDate() === now.getDate();

    if (sameDay) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    return d.toLocaleDateString([], { day: '2-digit', month: '2-digit' });
}

async function renameChat(chatId) {
    const chat = (chatListCache || []).find(item => String(item.id || '') === String(chatId));
    const currentTitle = String(chat?.title || 'New chat');
    const nextTitle = window.prompt('Rename chat', currentTitle);

    if (nextTitle === null) return;

    const cleanedTitle = String(nextTitle || '').trim();
    if (!cleanedTitle) return;

    const res = await fetch('api.php<?= $nocache ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'rename_chat',
            chat_id: chatId,
            title: cleanedTitle
        })
    });

    const data = await res.json();
    if (!data?.ok) {
        alert(data?.error || 'Could not rename chat');
        return;
    }

    chatListCache = (chatListCache || []).map(item =>
        String(item.id || '') === String(chatId)
            ? { ...item, title: data.title || cleanedTitle, updated_at: data.updated_at || item.updated_at }
            : item
    );
    archivedChatListCache = (archivedChatListCache || []).map(item =>
        String(item.id || '') === String(chatId)
            ? { ...item, title: data.title || cleanedTitle, updated_at: data.updated_at || item.updated_at }
            : item
    );

    if (currentChatMeta && String(currentChatMeta.id || '') === String(chatId)) {
        currentChatMeta.title = data.title || cleanedTitle;
    }

    renderChatList(chatListCache, currentChatId, archivedChatListCache);

    if (currentChatId === chatId) {
        await loadChat(chatId);
    } else {
        await refreshChatList(chatId);
    }
}

async function restoreChat(chatId) {
    const res = await fetch('api.php<?= $nocache ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'restore_chat', chat_id: chatId })
    });

    const data = await res.json();
    if (!data?.ok) {
        alert(data?.error || 'Could not restore chat');
        return;
    }

    await refreshChatList(data.current_chat_id || chatId);
    await loadChat(data.current_chat_id || chatId);
}

function renderChatSectionItems(chats, activeChatId = null, isArchived = false) {
    return chats.map(chat => {
        const isActive = chat.id === activeChatId ? 'active' : '';
        const chatId = String(chat.id || '');
        const ownerSeed = String(chat.owner_name || (chat.is_owner ? 'You' : 'Shared chat'));
        const title = escapeHtml(chat.title || 'New chat');
        const ownerName = escapeHtml(ownerSeed);
        const avatarsHtml = renderChatListAvatars(chat);
        const updatedAt = escapeHtml(formatChatListTime(chat.updated_at || ''));
        const unreadHtml = formatUnreadBadge(chat.unread_count || 0);
        const inviteButton = (!isArchived && chat.is_owner)
            ? `<button type="button" onclick="openInviteModal('${escapeHtml(chatId)}')">Invite</button>`
            : '';
        const metaLine = chat.is_owner ? 'Your chat' : ownerName;
        let primaryAction = '';

        if (isArchived) {
            primaryAction = `
                <button type="button" data-action="restore-chat" data-chat-id="${escapeHtml(chatId)}">Restore</button>
                <button type="button" data-action="delete-chat-permanently" data-chat-id="${escapeHtml(chatId)}" class="danger">Delete permanently</button>
            `;
        } else if (chat.is_owner) {
            primaryAction = `<button type="button" data-action="archive-chat" data-chat-id="${escapeHtml(chatId)}">Archive</button>`;
        } else {
            primaryAction = `
                <button type="button" data-action="archive-chat" data-chat-id="${escapeHtml(chatId)}">Archive</button>
                <button type="button" data-action="leave-chat" data-chat-id="${escapeHtml(chatId)}">Leave chat</button>
            `;
        }

        return `
            <div class="chat-history-item ${isActive}" data-chat-id="${escapeHtml(chatId)}" data-chat-state="${isArchived ? 'archived' : 'active'}">
                <button class="chat-history-main" type="button" data-action="open-chat" data-chat-id="${escapeHtml(chatId)}">
                    ${avatarsHtml}
                    <span class="chat-history-copy">
                        <span class="chat-history-title">${title}</span>
                        <span class="chat-history-sub">${metaLine}</span>
                    </span>
                    <span class="chat-history-meta">
                        <span class="chat-history-time">${updatedAt}</span>
                        ${isArchived ? '' : unreadHtml}
                    </span>
                </button>

                <div class="chat-history-actions">
                    <button
                        class="chat-history-menu-btn"
                        type="button"
                        aria-label="Chat options"
                        data-action="toggle-chat-menu"
                        data-chat-id="${escapeHtml(chatId)}">⋯</button>

                    <div class="chat-history-menu hidden" data-menu-for="${escapeHtml(chatId)}">
                        ${primaryAction}
                        ${inviteButton}
                        ${(!isArchived && chat.is_owner) ? `<button type="button" data-action="rename-chat" data-chat-id="${escapeHtml(chatId)}">Rename</button>`: ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderChatList(chats, activeChatId = null, archivedChats = []) {
    const list = document.getElementById('chatList');
    if (!list) return;

    const activeItems = Array.isArray(chats) ? chats : [];
    const archivedItems = Array.isArray(archivedChats) ? archivedChats : [];
    const hasSearch = normalizeChatSearchValue(chatSearchQuery).length >= 2;

    let html = '';

    if (activeItems.length) {
        html += renderChatSectionItems(activeItems, activeChatId, false);
    } else if (!archivedItems.length) {
        html += `<div class="conversation-empty">${hasSearch ? 'No matching chats' : 'No chats yet'}</div>`;
    }

    if (archivedItems.length) {
        html += `
            <div class="sidebar-label archived-label">Archived</div>
            ${renderChatSectionItems(archivedItems, activeChatId, true)}
        `;
    }

    list.innerHTML = html;
}

function closeAllChatMenus() {
    document.querySelectorAll('.chat-history-menu').forEach(menu => {
        menu.classList.add('hidden');
    });
}

function toggleChatMenu(chatId) {
    const menu = document.querySelector(`.chat-history-menu[data-menu-for="${CSS.escape(String(chatId))}"]`);
    if (!menu) return;

    const shouldOpen = menu.classList.contains('hidden');
    closeAllChatMenus();

    if (shouldOpen) {
        menu.classList.remove('hidden');
    }
}

let chatListEventsBound = false;

function bindChatListEvents() {
    if (chatListEventsBound) return;
    chatListEventsBound = true;

    const list = document.getElementById('chatList');
    if (!list) return;

    list.addEventListener('click', async (e) => {
        const menuBtn = e.target.closest('[data-action="toggle-chat-menu"]');
        if (menuBtn) {
            e.stopPropagation();
            toggleChatMenu(menuBtn.dataset.chatId);
            return;
        }

        const archiveBtn = e.target.closest('[data-action="archive-chat"]');
        if (archiveBtn) {
            e.stopPropagation();
            closeAllChatMenus();
            await archiveChat(archiveBtn.dataset.chatId);
            return;
        }

        const leaveBtn = e.target.closest('[data-action="leave-chat"]');
        if (leaveBtn) {
            e.stopPropagation();
            closeAllChatMenus();
            await leaveChat(leaveBtn.dataset.chatId);
            return;
        }

        const renameBtn = e.target.closest('[data-action="rename-chat"]');
        if (renameBtn) {
            e.stopPropagation();
            closeAllChatMenus();
            await renameChat(renameBtn.dataset.chatId);
            return;
        }

        const restoreBtn = e.target.closest('[data-action="restore-chat"]');
        if (restoreBtn) {
            e.stopPropagation();
            closeAllChatMenus();
            await restoreChat(restoreBtn.dataset.chatId);
            return;
        }

        const openBtn = e.target.closest('[data-action="open-chat"]');
        if (openBtn) {
            closeAllChatMenus();
            await loadChat(openBtn.dataset.chatId);

            if (window.innerWidth < 768) {
                document.getElementById('sidebar')?.classList.add('hidden');
            }

            return;
        }

        const deletePermanentBtn = e.target.closest('[data-action="delete-chat-permanently"]');
        if (deletePermanentBtn) {
            e.stopPropagation();
            closeAllChatMenus();
            await deleteChatPermanently(deletePermanentBtn.dataset.chatId);
            return;
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.chat-history-actions')) {
            closeAllChatMenus();
        }
    });
}

async function createNewChat() {
    const res = await fetch('api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=create_chat' : '&action=create_chat' ?>', {
        method: 'POST',
        credentials: 'same-origin'
    });

    if (res.status === 401) {
        openAuthModal('login');
        return;
    }
    
    const data = await res.json();
    if (!data?.ok) return;

    currentChatId = data.chat_id;
    currentChatMeta = data.chat || null;
    updateInviteButtonsVisibility(data.chat || null);
    
    chatHistory = [];
    renderRecipientPills();

    clearConversationUI();
    updateHeaderForChat(data.chat || { title: 'New chat', participants: [] });
    renderAssistantMessage("<b>Hmm… looks like you're alone here.</b><br>Click Invite at the top to start a conversation.", { trustedHtml: true });
    
    chatListCache = [{
        id: data.chat_id,
        title: data.chat?.title || 'New chat',
        updated_at: data.chat?.updated_at || ''
    }, ...chatListCache.filter(chat => chat.id !== data.chat_id)];

    renderChatList(chatListCache, data.chat_id, archivedChatListCache);
    await refreshChatList(data.chat_id);
    focusInput();
}

async function loadChat(chatId) {
    const res = await fetch(`api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=load_chat' : '&action=load_chat' ?>&chat_id=${encodeURIComponent(chatId)}`, {
        credentials: 'same-origin'
    });

    const data = await res.json();
    if (!data?.ok || !data.chat) return;

    currentChatId = data.chat.id;
    currentChatMeta = data.chat;
    resetAutoDraftHandled(data.chat.id);
    
    updateInviteButtonsVisibility(data.chat);
    
    chatHistory = Array.isArray(data.chat.messages)
        ? data.chat.messages.filter(msg => msg.role === 'user' || msg.role === 'assistant').map(msg => ({
            role: msg.role,
            content: msg.content ?? msg.text ?? ''
        }))
        : [];

    renderChatFromHistory(data.chat);
    renderChatContext(data.chat);
    renderHeaderParticipants(data.chat);
    renderSidebarChatDetails(data.chat);
    lastMessageCount = (data.chat.messages || []).length;
    
    const existingIncomingIds = (Array.isArray(data.chat.messages) ? data.chat.messages : [])
    .filter(msg =>
        msg?.role === 'other' &&
        String(msg?.user_id || '') !== String(window.currentUserId || '') &&
        String(msg?.id || '') !== ''
    )
    .map(msg => String(msg.id || ''));

    const autoDraftState = getAutoDraftState(data.chat.id);
    autoDraftState.handledMessageIds = existingIncomingIds.slice();

    await refreshChatList(currentChatId);
    scrollChatToBottom(currentChatId || true);
    focusInput();
}

function renderChatFromHistory(chat) {
    clearConversationUI();
    lastRenderedDay = '';
    updateHeaderForChat(chat || 'New chat');

    chat.messages.forEach((message, index) => renderStoredMessage(message, index));
    renderTemporaryFlow();
    renderRecipientPills();
    
}

function renderStoredMessage(message, index = null) {

    const meta = {
        storedIndex: index,
        messageId: message.id || '',
        time: message?.time
    };

    renderDay(message?.time || message?.created_at || message?.sent_at || '');
    
    if (message.role === 'assistant') {
        return renderAssistantMessage(message.content ?? message.text ?? '', meta);
    }

    if (message.role === 'other') {
        if ((message.user_id || '') === window.currentUserId) {
            return renderMessage({
                role: 'user',
                content: message.content ?? message.text ?? '',
                meta: message.meta || {},
                recipient_label: message.recipient_label || '',
                reply_to: message.reply_to || ''
            }, {
                ...meta,
                insertAfterMessageId: message.reply_to || ''
            });
        }

        return renderIncomingMessage(
            message.name || 'Other',
            message.content ?? message.text ?? '',
            {
                ...meta,
                meta: message.meta || {},
                recipient_label: message.recipient_label || ''
            }
        );
    }

    if (message.role === 'user') {
        return renderMessage({
            role: 'user',
            content: message.content ?? message.text ?? '',
            meta: message.meta || {},
            recipient_label: message.recipient_label || '',
            reply_to: message.reply_to || ''
        }, {
            ...meta,
            insertAfterMessageId: message.reply_to || ''
        });
    }

    if (message.role === 'sticky') return renderSticky(message.content ?? '');
    if (message.role === 'timeline') return addTimelineItem(message.title || 'Timeline', message.content ?? '');
    if (message.role === 'catchup') return showCatchup(Array.isArray(message.items) ? message.items : []);
    if (message.role === 'join_note') return showJoinNote(message.content ?? '');
    if (message.role === 'assist') return showAssist(message.line1 || '', message.line2 || '', message.draft || '');
}

async function archiveChat(chatId) {
    const res = await fetch('api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=archive_chat' : '&action=archive_chat' ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ chat_id: chatId })
    });

    const data = await res.json();
    if (!data?.ok) {
        alert(data?.error || 'Could not archive chat');
        return;
    }

    await refreshChatList(data.current_chat_id || null);

    if (data.current_chat_id) {
        await loadChat(data.current_chat_id);
        return;
    }

    currentChatId = null;
    currentChatMeta = null;
    chatHistory = [];
    clearConversationUI();
    updateHeaderForChat({ title: 'New chat', participants: [] });
    updateInviteButtonsVisibility(null);
    renderRecipientPills();
}

async function deleteChatPermanently(chatId) {
    if (!confirm('Delete this chat permanently? This cannot be undone.')) {
        return;
    }

    const res = await fetch('api.php<?= $nocache ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete_chat_permanently',
            chat_id: chatId
        })
    });

    const data = await res.json();
    if (!data?.ok) {
        alert(data?.error || 'Could not delete chat');
        return;
    }

    await refreshChatList(data.current_chat_id || null);
}

async function leaveChat(chatId) {
    const ok = window.confirm('Leave this chat?\n\nYou will no longer have access to it.\nTo join again, you must be invited again.');
    if (!ok) return;

    const res = await fetch('api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=leave_chat' : '&action=leave_chat' ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ chat_id: chatId })
    });

    const data = await res.json();
    if (!data?.ok) {
        alert(data?.error || 'Could not leave chat');
        return;
    }

    await refreshChatList(data.current_chat_id || null);

    if (data.current_chat_id) {
        await loadChat(data.current_chat_id);
        return;
    }

    currentChatId = null;
    currentChatMeta = null;
    chatHistory = [];
    clearConversationUI();
    updateHeaderForChat({ title: 'New chat', participants: [] });
    updateInviteButtonsVisibility(null);
    renderRecipientPills();
}

// -------------------------
// CLEAR DYNAMIC UI
// -------------------------
function clearConversationUI() {
    document.getElementById('chat').innerHTML = '';
    document.getElementById('timelineItems').innerHTML = '';
    document.getElementById('catchupList').innerHTML = '';
    document.getElementById('joinNote').textContent = '';
    currentSuggestionText = '';

    document.getElementById('timelineStrip').classList.add('hidden');
    document.getElementById('joinNote').classList.add('hidden');
    document.getElementById('catchupCard').classList.add('hidden');
}

function getComposerAssistantName() {
    return config?.assistants?.[config?.user?.assistant]?.name || 'Assistant';
}

function setLiveHelper(meta = 'Ready to check your message before sending', suggestion = '', actions = 'none', original = '') {
    liveBoxState = { status: actions, original, suggestion, meta, actions };
    const state = getTempUiState();
    state.items = state.items.filter(item => item.kind !== 'live_helper');
    state.items.push({ kind: 'live_helper', content: suggestion, meta, actions, original });
    renderTemporaryFlow();
}

function showComposerSuggestion(original, suggestion, metaText = 'Suggestion ready') {
    composerSuggestionState.original = original || '';
    composerSuggestionState.suggestion = suggestion || '';
    composerSuggestionState.loading = false;
    setLiveHelper(metaText, suggestion || '', metaText === 'Suggestion ready' ? 'suggestion' : 'none', original || '');
}

function showComposerSuggestionLoading() {
    composerSuggestionState.loading = true;
    setLiveHelper('Checking message', '...', 'none', composerSuggestionState.original || '');
}

function hideComposerSuggestion() {
    composerSuggestionState.original = '';
    composerSuggestionState.suggestion = '';
    composerSuggestionState.loading = false;
    setLiveHelper('Ready to check your message before sending', '', 'none', '');
}

async function useComposerSuggestionAndSend() {
    if (!composerSuggestionState.suggestion) return;
    const input = document.getElementById('input');
    if (!input) return;
    input.value = composerSuggestionState.suggestion;
    autoGrowTextarea.call(input);
    nextOutgoingAssistLabel = 'assisted reply';
    hideComposerSuggestion();
    await handleSend();
}

function editComposerSuggestion() {
    if (!composerSuggestionState.suggestion) return;
    const input = document.getElementById('input');
    if (!input) return;
    input.value = composerSuggestionState.suggestion;
    autoGrowTextarea.call(input);
    focusInput();
}

async function sendComposerOriginal() {
    if (!composerSuggestionState.original) return;
    const input = document.getElementById('input');
    if (!input) return;
    input.value = composerSuggestionState.original;
    autoGrowTextarea.call(input);
    nextOutgoingAssistLabel = '';
    hideComposerSuggestion();
    await handleSend();
}

async function requestComposerSuggestion(message) {
    const prompt = [
        'Improve this outgoing message.',
        'Keep the same meaning.',
        'Keep it short, natural, and ready to send.',
        'Preserve the language of the original message.',
        'Return only the rewritten message and nothing else.',
        '',
        message
    ].join('\n');

    const data = await sendToAPI([
        { role: 'user', content: prompt }
    ], 'suggest');

    if (typeof data === 'string') return data.trim();
    if (data?.reply) return String(data.reply).trim();
    if (data?.text) return String(data.text).trim();
    return '';
}
function shouldCheckBeforeSend() {
    const settings = composerAssistantSettings || {};
    if (!settings.enabled) return false;
    if (!settings.checkBeforeSend) return false;
    if (settings.mode === 'manual') return false;
    if (!userAssistantSettings.enabled || !userAssistantSettings.hasKey) return false;
    return true;
}

async function handleComposerSend() {
    const input = document.getElementById('input');
    const actionBtn = document.getElementById('actionBtn');
    if (!input) return;

    const rawMessage = (input.value || '').trim();
    if (!rawMessage) return;

    if (!shouldCheckBeforeSend()) {
        nextOutgoingAssistLabel = '';
        showComposerSuggestion(rawMessage, rawMessage, 'No assistance used · Message sent');
        await handleSend();
        return;
    }

    if (composerSuggestionState.loading) return;

    if (actionBtn) actionBtn.disabled = true;

    try {
        const result = await runOutgoingCheckPlaceholder(rawMessage);
        const cleanedSuggestion = String(result?.reply || '').trim();

        if (!cleanedSuggestion) {
            nextOutgoingAssistLabel = '';
            showComposerSuggestion(rawMessage, rawMessage, 'No assistance used · Message sent');
            await handleSend();
            return;
        }

        if (result.reply_type === 'passive') {
            nextOutgoingAssistLabel = 'assisted reply';
            showComposerSuggestion(rawMessage, rawMessage, 'No changes needed · Message sent');
            await handleSend();
            return;
        }

        showComposerSuggestion(rawMessage, cleanedSuggestion, 'Suggestion ready');
    } catch (err) {
        console.error('Could not generate composer suggestion', err);
        nextOutgoingAssistLabel = '';
        showComposerSuggestion(rawMessage, rawMessage, 'No assistance used · Message sent');
        await handleSend();
    } finally {
        composerSuggestionState.loading = false;
        if (actionBtn) actionBtn.disabled = false;
    }
}

// -------------------------
// SEND FLOW
// -------------------------
async function handleSend() {
    const input = document.getElementById('input');
    const text = input.value.trim();

    if (composerReplyLoading) return;
    
    if (!text) return;
    
    cancelAllReplyCountdowns();

    if (!isLoggedIn) {
        if (!canUseGuestTrial()) {
        setTimeout(() => {
            openAuthModal('signup', 'trial_exhausted');
            }, 400);
            return;
        }

        await handleGuestTrialSend(text);
        return;
    }
    
    composerWrap?.classList.add('active');
    document.querySelector('.chat-header-right')?.classList.remove('hidden');
    
    if (!currentChatId) {
        await createNewChat();
    }

    if (editingMessageId) {
        try {
            await fetch('api.php<?= $nocache ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'edit_message',
                    chat_id: currentChatId,
                    message_id: editingMessageId,
                    content: text
                })
            });

            updateMessageInLocalState(editingMessageId, text);

            if (editingMessageWrap) {
                updateMessageTextInWrap(editingMessageWrap, text);
            }

            editingMessageId = null;
            editingMessageWrap = null;
            document.getElementById('composerWrap')?.classList.remove('is-editing');

            resetTextarea(input);
            return;
        } catch (err) {
            console.error('Could not save edited message', err);
            return;
        }
    }
    
    // ROUTE TO ASSISTANT
    if (composerRecipient?.type === 'assistant') {
        if (!canUseAssistantFeatures()) {
            renderSticky('Add your API key in Settings to chat privately with your assistant.');
            return;
        }
    
        resetTextarea(input);
        await runPrivateAssistantLanePlaceholder(text);
        focusInput();
        return;
    }

    // NORMAL CHAT SEND
    const currentChat = chatListCache.find(chat => chat.id === currentChatId) || null;
    const isMulti = !!(currentChat && currentChat.is_owner === false || currentChat?.member_of);

    resetTextarea(input);
    hideAssist();

    const recipientLabel = composerRecipient?.type === 'participant'
        ? ('to ' + (composerRecipient.label || 'participant'))
        : '';

    const messageMeta = nextOutgoingAssistLabel
    ? {
        labels: [
            {
                type: 'assisted_reply',
                text: nextOutgoingAssistLabel
            }
        ]
    }
    : {};

    const userMsg = {
        id: '',
        role: 'user',
        content: text,
        text: text,
        recipient_label: recipientLabel
    };
    
    chatHistory.push(userMsg);
    renderMessage(userMsg, { historyIndex: chatHistory.length - 1 });
    scrollChatToBottom(currentChatId || true);
    
    nextOutgoingAssistLabel = '';

    const participants = Array.isArray(currentChatMeta?.participants) ? currentChatMeta.participants : [];
    const isAloneChat = participants.length <= 1;

    if (isAloneChat) {
        await refreshChatList(currentChatId);
        return;
    }
    
    const data = await sendToAPI(chatHistory, 'chat', {
        meta: messageMeta,
        recipient_label: recipientLabel
    });

    if (data.multi) {
        lastMessageCount = data.message_count || lastMessageCount;
        await refreshChatList(currentChatId);
        return;
    }

    if (!canUseAssistantFeatures()) {
        renderSticky('Normal chat works without AI. Open Settings to add your API key when you want summaries, suggestions, and replies.');
        return;
    }

    renderThinking();

    const assistantMsg = {
        role: 'assistant',
        content: data?.reply || data?.text || data?.error || data?.message || 'Error'
    };
    chatHistory.push(assistantMsg);

    const state = getTempUiState();
    state.items = state.items.filter(item => item.kind !== 'thinking');
    renderTemporaryFlow();
    
    renderMessage(assistantMsg, { historyIndex: chatHistory.length - 1 });

    if (data.chat_id) currentChatId = data.chat_id;
    updateHeaderForChat(data);
    await refreshChatList(currentChatId);
}

function autoGrowTextarea() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 220) + 'px';
}

function resetTextarea(input) {
    input.value = '';
    input.style.height = 'auto';
}

// -------------------------
// API
// -------------------------
async function sendToAPI(messages, action = 'chat', extra = {}) {
    const payload = {
        messages,
        action,
        chat_id: currentChatId,
        ...extra
    };

    const res = await fetch('api.php<?= $nocache ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    return await res.json();
}

// -------------------------
// RENDER HELPERS
// -------------------------
function closeAllMessageMenus() {
    const menu = document.getElementById('globalMessageMenu');
    if (menu) menu.classList.add('hidden');
    document.querySelectorAll('.message-menu-btn.open').forEach(btn => btn.classList.remove('open'));
    activeMessageMenu = null;
    activeMessageMenuBtn = null;
}

function getOrCreateGlobalMessageMenu() {
    let menu = document.getElementById('globalMessageMenu');
    if (menu) return menu;

    menu = document.createElement('div');
    menu.id = 'globalMessageMenu';
    menu.className = 'message-menu hidden';
    menu.innerHTML = `
        <button type="button" data-menu-action="reply"><span class="menu-icon">↩</span> Reply</button>
        <button type="button" data-menu-action="explain"><span class="menu-icon">✨</span> Explain</button>
        <button type="button" data-menu-action="translate"><span class="menu-icon">🌐</span> Translate</button>
        <button type="button" data-menu-action="rewrite"><span class="menu-icon">✦</span> Rewrite</button>
        <button type="button" data-menu-action="edit"><span class="menu-icon">✎</span> Edit</button>
        <button type="button" data-menu-action="delete"><span class="menu-icon">🗑</span> Delete</button>
    `;

    menu.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-menu-action]');
        if (!btn || !activeMessageMenu) return;

        e.stopPropagation();
        const action = btn.getAttribute('data-menu-action');
        const target = activeMessageMenu;

        if (action === 'reply') runMenuAssist(target, 'reply');
        if (action === 'explain') runMenuAssist(target, 'explain');
        if (action === 'translate') runMenuAssist(target, 'translate');
        if (action === 'rewrite') runMenuAssist(target, 'rewrite');
        if (action === 'edit') editMessageFromMenu(target);
        if (action === 'delete') deleteMessageFromMenu(target);
    });

    document.body.appendChild(menu);
    return menu;
}

function toggleMessageMenu(btn, wrap) {
    if (!btn || !wrap) return;

    const menu = getOrCreateGlobalMessageMenu();
    const shouldOpen = menu.classList.contains('hidden') || activeMessageMenu !== wrap;
    closeAllMessageMenus();

    if (!shouldOpen) return;

    const isOwn = wrap.closest('.message-row')?.dataset.groupSender === 'me';

    menu.querySelector('[data-menu-action="reply"]')?.classList.toggle('hidden', isOwn);
    menu.querySelector('[data-menu-action="explain"]')?.classList.toggle('hidden', isOwn);
    menu.querySelector('[data-menu-action="translate"]')?.classList.toggle('hidden', isOwn);
    menu.querySelector('[data-menu-action="rewrite"]')?.classList.toggle('hidden', !isOwn);
    menu.querySelector('[data-menu-action="edit"]')?.classList.toggle('hidden', !isOwn);
    menu.querySelector('[data-menu-action="delete"]')?.classList.toggle('hidden', !isOwn);
    
    
    const rect = btn.getBoundingClientRect();
    menu.style.top = (rect.bottom + 6) + 'px';
    menu.style.left = Math.max(8, rect.right - 120) + 'px';
    menu.classList.remove('hidden');
    btn.classList.add('open');
    activeMessageMenu = wrap;
    activeMessageMenuBtn = btn;
}

function buildMessageMenuButton(wrap) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'message-menu-btn';
    btn.setAttribute('aria-label', 'Message actions');
    btn.onclick = function (e) {
        e.stopPropagation();
        toggleMessageMenu(btn, wrap);
    };
    return btn;
}

function attachMessageMenu(wrap, mode = 'full') {
    if (!wrap) return;
    if (wrap.querySelector('.message-menu-btn')) return;

    wrap.dataset.menuMode = mode;

    const btn = buildMessageMenuButton(wrap);
    const bubble = wrap.querySelector(':scope > .bubble');

    if (bubble) {
        bubble.appendChild(btn);
    } else {
        wrap.appendChild(btn);
    }
}

function getMessageTextFromWrap(wrap) {
    const bubbleText = wrap?.querySelector('.bubble-text');
    if (!bubbleText) return '';

    const clone = bubbleText.cloneNode(true);
    clone.querySelector('label')?.remove();
    clone.querySelector('time')?.remove();

    return (clone.textContent || '').trim();
}

function updateMessageTextInWrap(wrap, text) {
    const bubbleText = wrap?.querySelector('.bubble-text');
    if (!bubbleText) return;
    bubbleText.textContent = text;
}

function findMessageById(messageId) {
    if (!messageId || !currentChatMeta || !Array.isArray(currentChatMeta.messages)) return null;
    return currentChatMeta.messages.find(msg => String(msg.id || '') === String(messageId)) || null;
}

function updateMessageInLocalState(messageId, newText) {
    const msg = findMessageById(messageId);
    if (!msg) return false;

    msg.content = newText;
    msg.text = newText;
    msg.edited_at = new Date().toISOString();

    const historyMsg = chatHistory.find(m =>
        String(m.id || '') === String(messageId)
    );
    if (historyMsg) {
        historyMsg.content = newText;
        historyMsg.text = newText;
        historyMsg.edited_at = msg.edited_at;
    }

    return true;
}

function removeMessageFromLocalState(messageId) {
    if (!messageId) return false;

    if (currentChatMeta && Array.isArray(currentChatMeta.messages)) {
        currentChatMeta.messages = currentChatMeta.messages.filter(
            msg => String(msg.id || '') !== String(messageId)
        );
    }

    if (Array.isArray(chatHistory)) {
        chatHistory = chatHistory.filter(
            msg => String(msg.id || '') !== String(messageId)
        );
    }

    return true;
}

function editMessageFromMenu(wrap) {
    closeAllMessageMenus();
    if (!wrap) return;

    const messageId = wrap.dataset.messageId || '';
    const currentText = getMessageTextFromWrap(wrap);
    const input = document.getElementById('input');

    if (!messageId || !currentText || !input) return;

    editingMessageId = messageId;
    editingMessageWrap = wrap;

    input.value = currentText;
    autoGrowTextarea.call(input);
    focusInput();

    document.getElementById('composerWrap')?.classList.add('is-editing');
}

async function deleteMessageFromMenu(wrap) {
    closeAllMessageMenus();
    if (!wrap) return;
    if (!window.confirm('Delete this message?')) return;

    const messageId = wrap.dataset.messageId || '';
    if (!messageId) return;

    try {
        await fetch('api.php<?= $nocache ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_message',
                chat_id: currentChatId,
                message_id: messageId
            })
        });

        removeMessageFromLocalState(messageId);

        const row = wrap.closest('.message-row') || wrap.closest('.assistant-info-row');
        row?.remove();

        if (editingMessageId === messageId) {
            editingMessageId = null;
            editingMessageWrap = null;
            document.getElementById('composerWrap')?.classList.remove('is-editing');
            resetTextarea(document.getElementById('input'));
        }
    } catch (err) {
        console.error('Could not delete message', err);
    }
}

async function runMenuAssist(wrap, intent = 'reply', sourceTextOverride = '') {
    closeAllMessageMenus();

    if (!isLoggedIn) {
        openAuthModal('login');
        return;
    }

    if (!canUseAssistantFeatures()) {
        renderSticky('API key is missing');
        return;
    }

    const sourceText = String(sourceTextOverride || getMessageTextFromWrap(wrap)).trim();
    if (!sourceText) return;

    const targetMessageId = wrap.dataset.messageId || '';
    await runIncomingAssistPlaceholder(targetMessageId, sourceText, intent);
}

function renderDay(value) {
    if (!value) return;

    const d = new Date(String(value).replace(' ', 'T'));
    const label = isNaN(d.getTime())
        ? String(value)
        : d.toLocaleDateString();

    if (label === lastRenderedDay) return;
    lastRenderedDay = label;

    const chat = document.getElementById('chat');
    const day = document.createElement('div');
    day.className = 'day';
    day.textContent = label;
    chat.appendChild(day);
}

function applyGroupedRowState(row) {
    if (!row) return;

    const prev = row.previousElementSibling;
    if (!prev) return;

    const sameKind = String(prev.dataset.groupKind || '') === String(row.dataset.groupKind || '');
    const sameSender = String(prev.dataset.groupSender || '') === String(row.dataset.groupSender || '');

    if (!sameKind || !sameSender) return;

    row.classList.add('is-grouped');
    prev.classList.add('has-group-next');
}

function renderMessage(msg, meta = {}) {
    if (msg.role === 'assistant') {
        renderAssistantMessage(msg.content, meta);
        return;
    }

    const chat = document.getElementById('chat');

    const row = document.createElement('div');
    row.className = 'message-row message-right';
    row.dataset.groupKind = 'message';
    row.dataset.groupSender = 'me';

    const wrap = document.createElement('div');
    wrap.className = 'message-wrap outgoing';
    if (meta?.storedIndex !== undefined && meta?.storedIndex !== null) wrap.dataset.storedIndex = String(meta.storedIndex);
    if (meta?.historyIndex !== undefined && meta?.historyIndex !== null) wrap.dataset.historyIndex = String(meta.historyIndex);

    if (meta?.messageId) wrap.dataset.messageId = String(meta.messageId);
    else if (msg?.id) wrap.dataset.messageId = String(msg.id);

    const bubble = document.createElement('div');
    bubble.className = 'bubble bubble-user';

    const textEl = document.createElement('div');
    textEl.className = 'bubble-text';
    textEl.textContent = msg.content;

    const footer = document.createElement('div');
    footer.className = 'bubble-footer';

    const time = document.createElement('time');
    time.className = 'bubble-time';
    time.textContent = formatBubbleTime(meta?.time || msg?.time || '');

    footer.appendChild(time);
    bubble.appendChild(textEl);
    bubble.appendChild(footer);

    wrap.appendChild(bubble);
    attachMessageMenu(wrap, 'full');

    if (msg.recipient_label) {
        const recipient = document.createElement('div');
        recipient.className = 'message-meta-label';
        recipient.textContent = msg.recipient_label;
        wrap.appendChild(recipient);
    }

    const labels = getMessageLabels(msg);
    labels.forEach(labelItem => {
        const assisted = document.createElement('div');
        assisted.className = 'message-meta-label';
        assisted.textContent = String(labelItem?.text || '');
        wrap.appendChild(assisted);
    });

    row.appendChild(wrap);

    const insertAfterMessageId = String(meta?.insertAfterMessageId || '').trim();
    
    if (insertAfterMessageId) {
        const targetWrap = chat.querySelector(`.message-wrap[data-message-id="${CSS.escape(insertAfterMessageId)}"]`);
        const targetRow = targetWrap?.closest('.message-row') || targetWrap?.closest('.assistant-info-row');
    
        if (targetRow && targetRow.parentNode) {
            let insertAfter = targetRow;
    
            while (
                insertAfter.nextElementSibling &&
                insertAfter.nextElementSibling.classList.contains('temp-flow-row') &&
                insertAfter.nextElementSibling.dataset.targetMessageId === insertAfterMessageId
            ) {
                insertAfter = insertAfter.nextElementSibling;
            }
    
            insertAfter.parentNode.insertBefore(row, insertAfter.nextElementSibling);
        } else {
            chat.appendChild(row);
        }
    } else {
        chat.appendChild(row);
    }
    
    applyGroupedRowState(row);
}

function renderAssistantMessage(text, meta = {}) {
    const chat = document.getElementById('chat');

    const row = document.createElement('div');
    row.className = 'assistant-info-row message';
    row.dataset.groupKind = 'assistant';
    row.dataset.groupSender = 'assistant';

    const box = document.createElement('div');
    box.className = 'assistant-info assistant-info-message';
    if (meta?.storedIndex !== undefined && meta?.storedIndex !== null) box.dataset.storedIndex = String(meta.storedIndex);
    if (meta?.historyIndex !== undefined && meta?.historyIndex !== null) box.dataset.historyIndex = String(meta.historyIndex);

    if (meta?.messageId) box.dataset.messageId = String(meta.messageId);

    const label = document.createElement('div');
    label.className = 'assistant-info-label';
    label.textContent = 'Assisted reply';

    const content = document.createElement('div');
    content.className = 'assistant-info-text bubble-text';

    if (meta?.trustedHtml) {
        content.innerHTML = String(text || '');
    } else {
        content.innerHTML = escapeHtml(text || '').replace(/\n/g, '<br>');
    }

    box.appendChild(label);
    box.appendChild(content);
    row.appendChild(box);

    chat.appendChild(row);
    applyGroupedRowState(row);
    
    
}

function renderThinking(targetMessageId = '') {
    const state = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__'));

    state.items = state.items.filter(item =>
        !(item.kind === 'thinking' && item.targetMessageId === targetMessageId)
    );

    state.items.push({
        kind: 'thinking',
        targetMessageId
    });

    renderTemporaryFlow();
}

function renderSticky(text) {
    const chat = document.getElementById('chat');
    const row = document.createElement('div');
    row.className = 'sticky-note-row';
    row.innerHTML = `<div class="sticky-note">${escapeHtml(text)}</div>`;
    chat.appendChild(row);
    
}

function renderIncomingMessage(name, text, meta = {}) {
    const chat = document.getElementById('chat');

    const row = document.createElement('div');
    row.className = 'message-row message-left';
    row.dataset.groupKind = 'message';
    row.dataset.groupSender = 'other';

    const wrap = document.createElement('div');
    wrap.className = 'message-wrap incoming';
    if (meta?.storedIndex !== undefined && meta?.storedIndex !== null) wrap.dataset.storedIndex = String(meta.storedIndex);
    if (meta?.historyIndex !== undefined && meta?.historyIndex !== null) wrap.dataset.historyIndex = String(meta.historyIndex);
    if (meta?.messageId) wrap.dataset.messageId = String(meta.messageId);

    const avatar = document.createElement('div');
    avatar.className = 'incoming-avatar';
    avatar.textContent = make_initials(name || '');

    const bubble = document.createElement('div');
    bubble.className = 'bubble bubble-other';

    const content = document.createElement('div');
    content.className = 'bubble-text';

    const label = document.createElement('label');
    label.textContent = (name || '').split(' ')[0];
    label.style.color = getNameColor(name || '');

    const message = document.createTextNode(' ' + text);

    content.appendChild(label);
    content.appendChild(message);

    const footer = document.createElement('div');
    footer.className = 'bubble-footer';

    const time = document.createElement('time');
    time.className = 'bubble-time';
    time.textContent = formatBubbleTime(meta?.time || '');

    footer.appendChild(time);

    bubble.appendChild(content);
    bubble.appendChild(footer);

    wrap.appendChild(avatar);
    wrap.appendChild(bubble);
    attachMessageMenu(wrap, 'assist');

    const labels = getMessageLabels(meta);
    labels.forEach(labelItem => {
        const assisted = document.createElement('div');
        assisted.className = 'message-meta-label';
        assisted.textContent = String(labelItem?.text || '');
        wrap.appendChild(assisted);
    });

    row.appendChild(wrap);
    chat.appendChild(row);
    applyGroupedRowState(row);
}

function formatBubbleTime(value = '') {
    if (!value) return '';
    const d = new Date(value.replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function getNameColor(seed = '') {
    const colors = [
        '#2563eb',
        '#16a34a',
        '#dc2626',
        '#9333ea',
        '#ea580c',
        '#0891b2',
        '#ca8a04'
    ];

    let hash = 0;
    const str = String(seed || '');
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
    }

    return colors[Math.abs(hash) % colors.length];
}

function getAvatarColor(seed = '') {
    const colors = [
        '#dbeafe',
        '#dcfce7',
        '#fef3c7',
        '#fce7f3',
        '#ede9fe',
        '#fee2e2',
        '#cffafe'
    ];

    let hash = 0;
    const str = String(seed || '');
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
    }

    return colors[Math.abs(hash) % colors.length];
}

function make_initials(name = '') {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'U';
    const first = parts[0]?.[0] || '';
    const second = parts[1]?.[0] || (parts[0]?.[1] || '');
    return (first + second).toUpperCase();
}

function renderHeaderParticipants(chat) {
    const avatars = document.getElementById('participantAvatars');
    const title = document.getElementById('headerTitle');
    const sub = document.getElementById('headerSubline');

    if (!avatars || !title || !sub) return;

    const participants = Array.isArray(chat?.participants) ? chat.participants : [];
    avatars.innerHTML = '';

    participants.forEach(p => {
        const span = document.createElement('span');
        span.className = 'participant-avatar';
        span.textContent = make_initials(p.name || p.email || 'U');
        span.title = p.name || p.email || '';
        avatars.appendChild(span);
    });

    const names = participants.map(p => p.name || p.email || 'User');
    title.textContent = names.length ? names.join(' · ') : (chat?.title || 'New chat');

    const firstNames = names.map(name => String(name).split(' ')[0]).slice(0, 4);
    sub.textContent = firstNames.length ? firstNames.join(', ') : '—';
}

function getAutoDraftState(chatId = currentChatId || '__new__') {
    const key = String(chatId || '__new__');
    if (!autoDraftHandledByChat[key]) {
        autoDraftHandledByChat[key] = { handledMessageIds: [] };
    }
    return autoDraftHandledByChat[key];
}

function markAutoDraftHandled(chatId, messageId) {
    if (!chatId || !messageId) return;
    const state = getAutoDraftState(chatId);
    if (!state.handledMessageIds.includes(String(messageId))) {
        state.handledMessageIds.push(String(messageId));
    }
}

function hasAutoDraftHandled(chatId, messageId) {
    if (!chatId || !messageId) return false;
    const state = getAutoDraftState(chatId);
    return state.handledMessageIds.includes(String(messageId));
}

function resetAutoDraftHandled(chatId = currentChatId || '__new__') {
    autoDraftHandledByChat[String(chatId || '__new__')] = { handledMessageIds: [] };
}

async function pollMessages() {
    if (!isLoggedIn) return;
    if (!currentChatId) return;

    try {
        const res = await fetch(
            `api.php<?= $nocache ?>&action=load_chat&chat_id=${currentChatId}`,
            { credentials: 'same-origin' }
        );

        const data = await res.json();
        if (!data.ok || !data.chat) return;

        const incomingMessages = Array.isArray(data.chat.messages) ? data.chat.messages : [];
        const currentMessages = Array.isArray(currentChatMeta?.messages) ? currentChatMeta.messages : [];

        const incomingSignature = JSON.stringify(
            incomingMessages.map(msg => ({
                id: msg.id || '',
                content: msg.content || msg.text || '',
                edited_at: msg.edited_at || '',
                deleted_at: msg.deleted_at || ''
            }))
        );

        const currentSignature = JSON.stringify(
            currentMessages.map(msg => ({
                id: msg.id || '',
                content: msg.content || msg.text || '',
                edited_at: msg.edited_at || '',
                deleted_at: msg.deleted_at || ''
            }))
        );

        if (incomingSignature === currentSignature) {
            await refreshChatList(currentChatId);
            return;
        }

        const previousMessageIds = new Set(
            currentMessages
                .map(msg => String(msg?.id || ''))
                .filter(Boolean)
        );

        const latestNewIncoming = [...incomingMessages].reverse().find(msg =>
            msg?.role === 'other' &&
            String(msg?.user_id || '') !== String(window.currentUserId || '') &&
            String(msg?.id || '') !== '' &&
            !previousMessageIds.has(String(msg.id || '')) &&
            !hasAutoDraftHandled(data.chat.id, String(msg.id || ''))
        ) || null;

        currentChatMeta = data.chat;
        updateInviteButtonsVisibility(data.chat);

        chatHistory = incomingMessages
            .filter(msg => msg.role === 'user' || msg.role === 'assistant')
            .map(msg => ({
                id: msg.id || '',
                role: msg.role,
                content: msg.content ?? msg.text ?? '',
                text: msg.content ?? msg.text ?? '',
                edited_at: msg.edited_at || ''
            }));

        lastMessageCount = incomingMessages.length;

        renderChatFromHistory(data.chat);
        renderChatContext(data.chat);
        renderHeaderParticipants(data.chat);
        renderSidebarChatDetails(data.chat);
        await refreshChatList(currentChatId);

        if (
            latestNewIncoming &&
            shouldAutoDraftIncomingReplies() &&
            !composerReplyLoading
        ) {
            markAutoDraftHandled(data.chat.id, latestNewIncoming.id);

            startReplyCountdown(
                String(latestNewIncoming.id || ''),
                String(latestNewIncoming.content || latestNewIncoming.text || '')
            );
        }
    } catch (e) {
        console.log('poll error', e);
    }
}

function addTimelineItem(title, text) {
    document.getElementById('timelineStrip').classList.remove('hidden');

    const items = document.getElementById('timelineItems');
    const div = document.createElement('div');
    div.className = 'timeline-item';
    div.innerHTML = `<strong>${escapeHtml(title)}</strong> — ${escapeHtml(text)}`;
    items.appendChild(div);
}

function showJoinNote(text) {
    const note = document.getElementById('joinNote');
    note.textContent = text;
    note.classList.remove('hidden');
}

function showCatchup(items) {
    if (!items || !items.length) return;

    const list = document.getElementById('catchupList');
    list.innerHTML = '';

    items.forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        list.appendChild(li);
    });

    document.getElementById('catchupCard').classList.remove('hidden');
}

function cancelEditingMessage() {
    editingMessageId = null;
    editingMessageWrap = null;
    document.getElementById('composerWrap')?.classList.remove('is-editing');
    resetTextarea(document.getElementById('input'));
}

function togglePanelBlock(id) {
    document.getElementById(id)?.classList.toggle('hidden');
}

function renderSidebarChatDetails(chat) {
    const title = document.getElementById('panelTitle');
    const participantsWrap = document.getElementById('panelParticipantsWrap');
    const metaWrap = document.getElementById('panelMetaWrap');

    if (title) title.textContent = chat?.title || 'Chat details';

    if (participantsWrap) {
        const participants = Array.isArray(chat?.participants) ? chat.participants : [];
        participantsWrap.innerHTML = participants.map(p => `
            <div class="panel-text-row">
                <strong>${escapeHtml(p.name || '')}</strong>
                <span>${escapeHtml(p.email || '')}</span>
            </div>
        `).join('') || '<div class="panel-text">No participants yet.</div>';
    }

    if (metaWrap) {
        metaWrap.innerHTML = `
            <div class="panel-text">Owner: ${escapeHtml(chat?.owner_user_id || '')}</div>
            <div class="panel-text">Messages: ${Array.isArray(chat?.messages) ? chat.messages.length : 0}</div>
            <div class="panel-text">Updated: ${escapeHtml(chat?.updated_at || '')}</div>
        `;
    }
}
// -------------------------
// ASSIST
// -------------------------
async function suggestReply(el) {
    const bubble = el.closest('.message-wrap').querySelector('.bubble-text');
    const text = bubble.innerText;

    el.innerText = '...';

    const reply = await sendToAPI([
        {
            role: 'user',
            content: `Write a short natural reply to this message:\n"${text}"`
        }
    ], 'suggest');

    el.innerText = 'Suggest';

    showAssist(
        'A short reply is ready.',
        'You can use it as it is or edit it first.',
        reply
    );
}

async function summarizeMessage(el) {
    const bubble = el.closest('.message-wrap').querySelector('.bubble-text');
    const text = bubble.innerText;

    el.innerText = '...';

    const reply = await sendToAPI([
        {
            role: 'user',
            content: `Summarize this message in one short useful line:\n"${text}"`
        }
    ], 'assist');

    el.innerText = 'Summarize';

    renderSticky(reply);
    addTimelineItem('Summary', reply);
}

function showAssist(line1, line2, draft) {
    currentSuggestionText = draft || '';

    const group = getOrCreateAssistGroup();
    if (!group) return;

    const line1El = group.querySelector('#assistLine1');
    const line2El = group.querySelector('#assistLine2');
    const draftEl = group.querySelector('#assistDraft');

    line1El.textContent = line1 || '';
    line2El.textContent = line2 || '';
    draftEl.textContent = draft || '';

    line1El.classList.toggle('hidden', !line1);
    line2El.classList.toggle('hidden', !line2);
    draftEl.classList.toggle('hidden', !draft);

    group.querySelector('#assistSendBtn').onclick = useAssistDraft;
    group.querySelector('#assistEditBtn').onclick = editAssistDraft;

    document.getElementById('chat').appendChild(group);
    
}

function hideAssist() {
    document.getElementById('assistGroup')?.remove();
}

function useAssistDraft() {
    if (!currentSuggestionText) return;
    const input = document.getElementById('input');
    input.value = currentSuggestionText;
    autoGrowTextarea.call(input);
    hideAssist();
    focusInput();
}

function editAssistDraft() {
    if (!currentSuggestionText) return;
    const input = document.getElementById('input');
    input.value = currentSuggestionText;
    autoGrowTextarea.call(input);
    hideAssist();
    input.focus();
}

function getOrCreateAssistGroup() {
    let group = document.getElementById('assistGroup');
    if (group) return group;

    group = document.createElement('div');
    group.className = 'assist-group';
    group.id = 'assistGroup';
    group.innerHTML = `
        <div class="assist-lane" id="assistLane">
            <div class="assist-line" id="assistLine1"></div>
            <div class="assist-line assist-gap" id="assistLine2"></div>
            <div class="assist-draft" id="assistDraft"></div>
            <div class="assist-actions">
                <button class="assist-action" type="button" id="assistSendBtn">Use</button>
                <button class="assist-action" type="button" id="assistEditBtn">Edit</button>
                <button class="assist-action" type="button" onclick="hideAssist()">Dismiss</button>
            </div>
        </div>
    `;

    return group;
}

//-------------------------
//INVITES
//-------------------------

let inviteContactsCache = [];
let selectedInviteContacts = [];

async function openInviteModal(chatId = null) {
    if (!isLoggedIn) {
        openAuthModal('login');
        return;
    }

    const targetChatId = chatId || currentChatId;
    if (!targetChatId) {
        alert('Open a chat first.');
        return;
    }

    selectedInviteContacts = [];

    const mount = document.getElementById('inviteModalMount');
    if (!mount) return;

    mount.innerHTML = `
        <div id="inviteModal" class="settings-modal">
            <div class="settings-modal-card">
                <button class="settings-modal-close" type="button" onclick="closeInviteModal()">×</button>
                <div class="settings-modal-title">Invite</div>
                <div class="settings-modal-sub">Search contacts or add a name and email.</div>

                <div class="settings-section">
                    <input class="settings-input" id="inviteSearch" type="text" placeholder="Search contacts">
                    <div id="inviteSearchResults" class="invite-results hidden"></div>

                    <div id="selectedInviteList" class="invite-selected-list"></div>

                    <input class="settings-input" id="inviteName" type="text" placeholder="Name">
                    <input class="settings-input" id="inviteEmail" type="email" placeholder="Email">
                    <div class="settings-notice">Share this link</div>
                    <input class="settings-input" id="inviteDirectLink" type="text" readonly value="" onclick="this.select();this.setSelectionRange(0,99999);">
                </div>

                <div class="settings-actions">
                    <button class="settings-save-btn" type="button" onclick="sendInvite('${escapeHtml(targetChatId)}')">Send invite</button>
                    <button class="settings-ghost-btn" type="button" onclick="closeInviteModal()">Cancel</button>
                </div>

                <div id="inviteNotice" class="settings-notice"></div>
            </div>
        </div>
    `;

    const modal = document.getElementById('inviteModal');
    if (modal) modal.classList.remove('hidden');
    
    const linkField = document.getElementById('inviteDirectLink');
    if (linkField) {
        linkField.value = `<?= $app_base_url ?>/?invite=${encodeURIComponent(targetChatId)}`;
    }
    
    try {
        const res = await fetch('api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=list_contacts' : '&action=list_contacts' ?>', {
            credentials: 'same-origin'
        });
        const data = await res.json();
        inviteContactsCache = data.ok ? (data.contacts || []) : [];
    } catch (err) {
        inviteContactsCache = [];
        console.error('Could not load contacts', err);
    }

    const search = document.getElementById('inviteSearch');
    if (search) {
        search.addEventListener('input', renderInviteResults);
        search.focus();
    }

    renderSelectedInviteContacts();
}

function closeInviteModal() {
 const mount = document.getElementById('inviteModalMount');
 if (mount) mount.innerHTML = '';
}

function renderInviteResults() {
    const search = document.getElementById('inviteSearch');
    const results = document.getElementById('inviteSearchResults');
    if (!search || !results) return;

    const q = (search.value || '').trim().toLowerCase();
    if (!q) {
        results.innerHTML = '';
        results.classList.add('hidden');
        return;
    }

    const matches = inviteContactsCache.filter(contact => {
        const name = (contact.name || '').toLowerCase();
        const email = (contact.email || '').toLowerCase();
        const alreadySelected = selectedInviteContacts.some(sel => (sel.email || '').toLowerCase() === email);
        return !alreadySelected && (name.includes(q) || email.includes(q));
    }).slice(0, 6);

    if (!matches.length) {
        results.innerHTML = '<div class="invite-result-empty">No contact found</div>';
        results.classList.remove('hidden');
        return;
    }

    results.innerHTML = matches.map(contact => `
    <button
        type="button"
        class="invite-result-item"
        onclick='pickInviteContact(${JSON.stringify(contact.name || '')}, ${JSON.stringify(contact.email || '')})'
    >
        <span>${escapeHtml(contact.name || '')}</span>
        <span class="invite-result-email">${escapeHtml(contact.email || '')}</span>
    </button>
`).join('');

    results.classList.remove('hidden');
}

function pickInviteContact(name, email) {
    const cleanName = (name || '').trim();
    const cleanEmail = (email || '').trim().toLowerCase();

    if (!cleanEmail) return;

    const exists = selectedInviteContacts.some(contact => (contact.email || '').toLowerCase() === cleanEmail);
    if (exists) return;

    selectedInviteContacts.push({
        name: cleanName,
        email: cleanEmail
    });

    const searchEl = document.getElementById('inviteSearch');
    const results = document.getElementById('inviteSearchResults');

    if (searchEl) searchEl.value = '';
    if (results) {
        results.innerHTML = '';
        results.classList.add('hidden');
    }

    renderSelectedInviteContacts();
}

async function sendInvite(chatId = null) {
    const manualName = document.getElementById('inviteName')?.value.trim() || '';
    const manualEmail = document.getElementById('inviteEmail')?.value.trim() || '';
    const notice = document.getElementById('inviteNotice');

    let recipients = [...selectedInviteContacts];

    if (manualName || manualEmail) {
        if (!manualName || !manualEmail) {
            if (notice) notice.textContent = 'Manual entry needs both name and email.';
            return;
        }

        const exists = recipients.some(contact => (contact.email || '').toLowerCase() === manualEmail.toLowerCase());
        if (!exists) {
            recipients.push({
                name: manualName,
                email: manualEmail
            });
        }
    }

    if (!recipients.length) {
        if (notice) notice.textContent = 'Please select or enter at least one contact.';
        return;
    }

    if (notice) notice.textContent = 'Sending invites...';

    let sentCount = 0;
    let failed = [];

    for (const recipient of recipients) {
        try {
             const res = await fetch('api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=create_invite' : '&action=create_invite' ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    chat_id: chatId,
                    name: recipient.name,
                    email: recipient.email
                })
            });

            const data = await res.json();

            if (data.ok) {
                if (data.email_sent === false) {
                    failed.push(recipient.email);
                } else {
                    sentCount += 1;
                }
            } else {
                failed.push(recipient.email);
            }
        } catch (err) {
            failed.push(recipient.email);
        }
    }

    if (failed.length === 0) {
        if (notice) notice.textContent = `Invite${sentCount === 1 ? '' : 's'} sent.`;
        selectedInviteContacts = [];
        renderSelectedInviteContacts();

        const nameEl = document.getElementById('inviteName');
        const emailEl = document.getElementById('inviteEmail');
        const searchEl = document.getElementById('inviteSearch');

        if (nameEl) nameEl.value = '';
        if (emailEl) emailEl.value = '';
        if (searchEl) searchEl.value = '';
        return;
    }

    if (notice) {
        notice.textContent = `Sent: ${sentCount}. Failed: ${failed.join(', ')}`;
    }
}

async function handlePendingInviteOnLoad() {
 if (!pendingInviteId) {
     if (!isLoggedIn) return;
     try {
         const consumeRes = await fetch('api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=consume_pending_invites' : '&action=consume_pending_invites' ?>', { credentials: 'same-origin' });
         const consumeData = await consumeRes.json();
         if (consumeData.ok && Array.isArray(consumeData.accepted) && consumeData.accepted.length) {
             const latest = consumeData.accepted[0];
             await refreshChatList(latest.chat_id);
             await loadChat(latest.chat_id);
         }
     } catch (err) {
         console.error('Could not consume pending invites', err);
     }
     return;
 }

 if (!isLoggedIn) {
     try {
         const res = await fetch(`api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=get_invite' : '&action=get_invite' ?>&invite_id=${encodeURIComponent(pendingInviteId)}`);
         const data = await res.json();
         if (data.ok && data.invite) {
             openAuthModal('signup');
             authState.email = data.invite.email || '';
             authState.name = data.invite.name || '';
             renderAuthView();
             showInlineNotice('This invite is for ' + (data.invite.email || 'this email') + '. Create an account or log in to join.');
         }
     } catch (err) {
         console.error('Could not load invite', err);
     }
     return;
 }

 const res = await fetch('api.php<?= $nocache ?><?= strpos($nocache ?? '', '?') === false ? '?action=accept_invite' : '&action=accept_invite' ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invite_id: pendingInviteId })
    });
    const data = await res.json();

    if (data.ok && data.chat_id) {
        await refreshChatList(data.chat_id);
        await loadChat(data.chat_id);
        return;
    }

    if (data.needs_verification) {
        alert('Please verify your email first, then open the invite again.');
        return;
    }

    if (data.expected_email) {
        const shouldSwitch = window.confirm(
            'This invite was sent to ' + data.expected_email + '.\n\n' +
            'You are currently logged in with a different account.\n\n' +
            'Click OK to log out and continue with the invited account.'
        );

        if (!shouldSwitch) return;

        try {
            await fetch('auth/logout.php<?= $nocache ?>', {
                method: 'POST',
                credentials: 'same-origin'
            });
        } catch (err) {
            console.error('Could not log out before invite switch', err);
        }

        openAuthModal('login');
        authState.email = data.expected_email || '';
        authState.name = '';
        renderAuthView();
        showInlineNotice('Log in with ' + (data.expected_email || 'the invited email') + ' to join this chat.');
        return;
    }

    if (data.error) {
        alert(data.error);
    }
}

function renderSelectedInviteContacts() {
    const list = document.getElementById('selectedInviteList');
    if (!list) return;

    if (!selectedInviteContacts.length) {
        list.innerHTML = '';
        return;
    }

    list.innerHTML = selectedInviteContacts.map((contact, index) => `
        <div class="invite-selected-item">
            <div class="invite-selected-text">
                <span>${escapeHtml(contact.name || '')}</span>
                <span class="invite-result-email">${escapeHtml(contact.email || '')}</span>
            </div>
            <button type="button" class="invite-selected-remove" onclick="removeInviteContact(${index})">×</button>
        </div>
    `).join('');
}

function removeInviteContact(index) {
    selectedInviteContacts.splice(index, 1);
    renderSelectedInviteContacts();
    renderInviteResults();
}

// -------------------------
// TYPEWRITER
// -------------------------
async function typeText(el, text, options = 20) {
    if (!el) {
        console.error('typeText: target element missing');
        return;
    }

    let speed = 20;
    let onTick = () => {};
    let shouldAbort = () => false;

    if (typeof options === 'number') {
        speed = options;
    } else if (typeof options === 'object' && options !== null) {
        speed = options.speed ?? 20;
        onTick = options.onTick || onTick;
        shouldAbort = options.shouldAbort || shouldAbort;
    }

    el.textContent = '';

    for (let i = 0; i < text.length; i++) {
        if (shouldAbort()) return;
        el.textContent += text[i];
        onTick();
        await wait(speed);
    }
}

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}
// -------------------------
// UTILS
// -------------------------
function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

//-------------------------
//AUTH MODAL
//-------------------------
let authState = {
    mode: 'login',
    step: 'start',
    email: '',
    name: ''
};

function openAuthModal(mode = 'login', reason = '') {
    authState = {
        mode,
        step: 'start',
        email: '',
        name: '',
        reason
    };

    document.getElementById('authModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    renderAuthView();
}

function closeAuthModal() {
    document.getElementById('authModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function renderAuthView() {
    const root = document.getElementById('authView');

    if (authState.step === 'start') {
        const isSignup = authState.mode === 'signup';
        const reasonText = authState.reason === 'trial_exhausted' ? '<div class="auth-inline-notice" style="display:block;margin-bottom:14px;">You\'ve used your test messages. Create a free account to continue.</div>' : '';

        root.innerHTML = `
            ${reasonText}
            <div class="auth-flow-title">${isSignup ? 'Sign up' : 'Log in'}</div>
            <div class="auth-flow-sub">
                ${isSignup
                    ? 'Create your profile to save chats, use your own assistant, and start conversations you can share.<br>Already have an account? <span onclick="openAuthModal(\'login\')">Log in</span>'
                    : 'Log in to your Tsjilp account.<br>New here? <span onclick="openAuthModal(\'signup\')">Sign up</span>'}
            </div>

            <button class="auth-provider-btn" type="button" onclick="startGoogleLogin()">
                Continue with Google
            </button>

            <button class="auth-provider-btn" type="button" onclick="showInlineNotice('Apple sign-in is not available yet')">
                Continue with Apple
            </button>

            <div class="auth-divider"><span>OR</span></div>

            ${isSignup ? `
                <input
                    class="auth-email-input"
                    id="authNameInput"
                    type="text"
                    name="name"
                    placeholder="Your name"
                    value="${escapeHtml(authState.name || '')}"
                />
            ` : ''}

            <input
                class="auth-email-input"
                id="authEmailInput"
                type="email"
                name="email"
                placeholder="Email address"
                value="${escapeHtml(authState.email)}"
                autocomplete="email"
            />

            <button class="auth-continue-btn" type="button" onclick="submitAuthEmail()">Continue</button>

            <div id="authInlineNotice" class="auth-inline-notice hidden"></div>
        `;
        return;
    }

    if (authState.step === 'password_login') {
        root.innerHTML = `
            <form id="authPasswordForm" autocomplete="on">
                <div class="auth-flow-title">Enter your password</div>
                <div class="auth-flow-sub">
                    You’ll use this password to log in to your Tsjilp account.
                </div>
    
                <div class="auth-email-pill">
                    <span>${escapeHtml(authState.email)}</span>
                    <button type="button" onclick="goBackToEmail()">Edit</button>
                </div>
    
                <label class="auth-field-label">Password</label>
                <div class="auth-password-wrap">
                    <input type="email" name="username" value="${escapeHtml(authState.email)}" autocomplete="username" hidden>
                    <input name="password" class="auth-email-input auth-password-input" id="authPasswordInput" type="password" autocomplete="current-password" autofocus>
                    <button class="auth-password-toggle" type="button" onclick="togglePasswordVisibility('authPasswordInput')">Show</button>
                    <button class="auth-link-btn" type="button" onclick="sendPasswordResetLink()">Forgot password?</button>
                </div>
                <button class="auth-continue-btn" type="button" onclick="submitPasswordLogin()">Continue</button>
    
                <div class="auth-divider"><span>OR</span></div>
    
                <button class="auth-provider-btn" type="button" onclick="switchToEmailCode()">
                    Log in with email code
                </button>
    
                <div id="authInlineNotice" class="auth-inline-notice hidden"></div>
            </form>
        `;
        return;
    }

    if (authState.step === 'password_signup') {
        root.innerHTML = `
            <form id="authSignupForm" onsubmit="submitPasswordSignup(event)">
                <div class="auth-flow-title">Create a password</div>
                <div class="auth-flow-sub">
                    You’ll use this password to log in to Tsjilp later.
                </div>

                <div class="auth-email-pill">
                    <input
                        type="email"
                        name="email"
                        id="authSignupEmail"
                        value="${escapeHtml(authState.email)}"
                        readonly
                        autocomplete="username"
                        class="auth-email-pill-input"
                    >
                    <button type="button" onclick="goBackToEmail()">Edit</button>
                </div>

                <label class="auth-field-label" for="authPasswordInput">Password</label>
                <div class="auth-password-wrap">
                    <input
                        name="password"
                        class="auth-email-input auth-password-input"
                        id="authPasswordInput"
                        type="password"
                        autocomplete="new-password"
                        required
                    >
                    <button class="auth-password-toggle" type="button" onclick="togglePasswordVisibility('authPasswordInput')">Show</button>
                </div>

                <button class="auth-continue-btn" type="submit">Continue</button>

                <div class="auth-divider"><span>OR</span></div>

                <button class="auth-provider-btn" type="button" onclick="switchToEmailCode()">
                    Sign up with email code
                </button>

                <div id="authInlineNotice" class="auth-inline-notice hidden"></div>
            </form>
        `;
        return;
    }

    if (authState.step === 'email_code') {
        root.innerHTML = `
            <div class="auth-flow-title">Sign in with email</div>
            <div class="auth-flow-sub">
                We’ll send a secure sign-in link to ${escapeHtml(authState.email)}.
            </div>

            <div class="auth-email-pill">
                <span>${escapeHtml(authState.email)}</span>
                <button type="button" onclick="goBackToEmail()">Edit</button>
            </div>

            <button class="auth-continue-btn" type="button" onclick="sendEmailMagicLink()">
                Send sign-in link
            </button>

            <div id="authInlineNotice" class="auth-inline-notice hidden"></div>
        `;
        return;
    }

    if (authState.step === 'email_code_sent') {
        root.innerHTML = `
            <div class="auth-flow-title">Check your email</div>
            <div class="auth-flow-sub">
                We sent a sign-in link to ${escapeHtml(authState.email)}.
            </div>

            <div class="auth-email-pill">
                <span>${escapeHtml(authState.email)}</span>
                <button type="button" onclick="goBackToEmail()">Edit</button>
            </div>

            <button class="auth-continue-btn" type="button" onclick="sendEmailMagicLink()">
                Send link again
            </button>

            <div id="authInlineNotice" class="auth-inline-notice">Magic link sent. Check your email.</div>
        `;
        return;
    }
    
    if (authState.step === 'verify_email_sent') {
        root.innerHTML = `
            <div class="auth-flow-title">Check your email</div>
            <div class="auth-flow-sub">
                We sent a verification link to ${escapeHtml(authState.email)}.
                Open it to activate your account.
            </div>

            <div class="auth-email-pill">
                <span>${escapeHtml(authState.email)}</span>
                <button type="button" onclick="goBackToEmail()">Edit</button>
            </div>

            <button class="auth-continue-btn" type="button" onclick="resendSignupVerification()">
                Send link again
            </button>

            <div id="authInlineNotice" class="auth-inline-notice"></div>
        `;
        return;
    }

    if (authState.step === 'password_reset_sent') {
        root.innerHTML = `
            <div class="auth-flow-title">Check your email</div>
            <div class="auth-flow-sub">
                We sent a password reset link to <strong>${escapeHtml(authState.email)}</strong>.
            </div>

            <button class="auth-provider-btn" type="button" onclick="sendPasswordResetLink()">
                Send link again
            </button>

            <button class="auth-continue-btn" type="button" onclick="openAuthModal('login')">
                Back to login
            </button>

            <div id="authInlineNotice" class="auth-inline-notice hidden"></div>
        `;
        return;
    }
    
}
function goBackToEmail() {
    authState.step = 'start';
    renderAuthView();
}

function switchToEmailCode() {
    authState.step = 'email_code';
    renderAuthView();
}

function togglePasswordVisibility(el) {
    const input = document.getElementById(el);
    if (!input) return;

    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';

    el.innerText = isHidden ? 'Hide' : 'Show';
}

function showInlineNotice(text) {
    const el = document.getElementById('authInlineNotice');
    if (!el) return;
    el.textContent = text;
    el.classList.remove('hidden');
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

async function submitAuthEmail() {
    const isSignup = authState.mode === 'signup';

    const nameInput = document.getElementById('authNameInput');
    const emailInput = document.getElementById('authEmailInput');

    const name = nameInput ? nameInput.value.trim() : '';
    const email = emailInput ? emailInput.value.trim() : '';

    if (isSignup && !name) {
        showInlineNotice('Please enter your name.');
        return;
    }

    if (!emailInput || !emailInput.checkValidity()) {
        showInlineNotice('Please enter a valid email address.');
        return;
    }

    authState.name = isSignup ? name : '';
    authState.email = email;

    const res = await fetch('auth/email-check.php<?= $nocache ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
    });

    const data = await res.json();

    if (!data.ok) {
        showInlineNotice(data.error || 'Something went wrong.');
        return;
    }

    if (authState.mode === 'login') {
        if (!data.exists) {
            showInlineNotice('No account found for this email.');
            return;
        }

        authState.step = 'password_login';
        renderAuthView();
        return;
    }

    // signup mode
    if (data.exists) {
        showInlineNotice('An account with this email already exists. Please log in.');
        return;
    }

    authState.step = 'password_signup';
    renderAuthView();
}

async function submitPasswordLogin() {
    const password = document.getElementById('authPasswordInput')?.value || '';

    const res = await fetch('auth/password-login.php<?= $nocache ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email: authState.email,
            password
        })
    });

    const data = await res.json();

    if (!data.ok) {
        showInlineNotice(data.error || 'Could not log in.');
        return;
    }

    window.location.reload();
}

async function submitPasswordSignup(event) {
    if (event) event.preventDefault();

    const form = document.getElementById('authSignupForm');
    const password = document.getElementById('authPasswordInput')?.value || '';
    const marketingOptIn = !!document.getElementById('authMarketingOptIn')?.checked;

    if (!authState.name) {
        showInlineNotice('Missing name. Please go back and enter your name.');
        return;
    }

    if (!password) {
        showInlineNotice('Please enter a password.');
        return;
    }

    const res = await fetch('auth/password-signup.php<?= $nocache ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: authState.name,
            email: authState.email,
            password,
            marketing_opt_in: marketingOptIn
        })
    });

    const data = await res.json();

    if (!data.ok) {
        showInlineNotice(data.error || 'Could not create account.');
        return;
    }

    // Optional extra browser hint
    if ('credentials' in navigator && window.PasswordCredential) {
        try {
            const cred = new PasswordCredential(form);
            await navigator.credentials.store(cred);
        } catch (e) {
            console.log('Credential store failed:', e);
        }
    }

    authState.step = 'verify_email_sent';
    renderAuthView();
}

async function resendSignupVerification() {
    const res = await fetch('auth/signup-send-verification.php<?= $nocache ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: authState.email })
    });

    const data = await res.json();

    if (!data.ok) {
        showInlineNotice(data.error || 'Could not send verification link.');
        return;
    }

    showInlineNotice('Verification link sent. Check your email.');
    if (data.debug_link) {
        console.log('Verification link:', data.debug_link);
    }
}
async function sendEmailMagicLink() {
    const res = await fetch('auth/email-start.php<?= $nocache ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: authState.email })
    });

    const data = await res.json();

    if (!data.ok) {
        showInlineNotice(data.error || 'Could not send email link.');
        return;
    }

    authState.step = 'email_code_sent';
    renderAuthView();

    if (data.debug_link) console.log('Magic link:', data.debug_link);
}
function showSoon(text = 'Apple sign-in is not available yet') {
    alert(text);
}

async function logoutUser() {
    await fetch('auth/logout.php<?= $nocache ?>', {
        method: 'POST',
        credentials: 'same-origin'
    });
    window.location.href = '/eidolon/?t=' + Date.now();
}

let settingsModalLoaded = false;

function applySettingsMeta(meta = {}) {
    userAssistantSettings.enabled = !!meta.assistant_enabled;
    userAssistantSettings.provider = meta.assistant_provider || 'openai';
    userAssistantSettings.hasKey = !!meta.assistant_has_key;
    userAssistantSettings.keyMasked = meta.assistant_key_masked || '';
    refreshAssistantUi();
}

async function openSettingsModal(preloadOnly = false) {

    if (!isLoggedIn) {
        openAuthModal('login');
        return;
    }
    
    const mount = document.getElementById('settingsModalMount');

    const res = await fetch('auth/settings-modal.php<?= $nocache ?>', {
        credentials: 'same-origin'
    });

    const html = await res.text();
    mount.innerHTML = html;
    settingsModalLoaded = true;

    const modal = mount.querySelector('#settingsModal');
    const meta = mount.querySelector('#settingsMeta');

    // move meta inside modal and hide it so it never renders visibly
    if (modal && meta) {
        meta.hidden = true;
        meta.textContent = '';
        const card = modal.querySelector('.settings-modal-card') || modal;
        card.prepend(meta);
    }

    const finalMeta = mount.querySelector('#settingsMeta');
    if (finalMeta) {
        applySettingsMeta({
            assistant_enabled: finalMeta.dataset.assistantEnabled === '1',
            assistant_provider: finalMeta.dataset.assistantProvider || 'openai',
            assistant_has_key: finalMeta.dataset.assistantHasKey === '1',
            assistant_key_masked: finalMeta.dataset.assistantKeyMasked || ''
        });
    }

    if (preloadOnly) return;

    if (modal) modal.classList.remove('hidden');

    const dropdown = document.getElementById('sidebarUserDropdown');
    if (dropdown) dropdown.classList.remove('open');
    quickLang();
}

function closeSettingsModal() {
    const modal = document.getElementById('settingsModal');
    if (modal) modal.classList.add('hidden');
}

async function saveSettings() {
    const name = document.getElementById('settingsName')?.value.trim() || '';
    const marketing = !!document.getElementById('settingsMarketing')?.checked;
    const assistantProvider = document.getElementById('settingsAssistantProvider')?.value || 'openai';
    const assistantApiKey = document.getElementById('settingsAssistantApiKey')?.value.trim() || '';
    const notice = document.getElementById('settingsNotice');
    const assistantEnabled = !!document.getElementById('settingsAssistantEnabled')?.checked;
    const communicationProfile = document.querySelector('input[name="communication_profile"]:checked')?.value || '';
    const communicationCustomPrompt = document.querySelector('textarea[name="communication_custom_prompt"]')?.value.trim() || '';
    const language = document.getElementById('settingsLanguage')?.value || 'en';

    let quick_languages = [];

    try {
        quick_languages = JSON.parse(
            document.getElementById('settingsQuickLanguagesInput')?.value || '[]'
        );
        if (!Array.isArray(quick_languages)) {
            quick_languages = [];
        }
    } catch (e) {
        quick_languages = [];
    }

    if (!name) {
        if (notice) notice.textContent = 'Please enter your name.';
        return;
    }

    const res = await fetch('auth/save-settings.php<?= $nocache ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name,
            marketing_opt_in: marketing,
            assistant_enabled: assistantEnabled,
            assistant_provider: assistantProvider,
            assistant_api_key: assistantApiKey,
            communication_profile: communicationProfile,
            communication_custom_prompt: communicationCustomPrompt,
            language,
            quick_languages
        })
    });

    const data = await res.json();

    if (notice) {
        notice.textContent = data.ok ? 'Settings saved.' : (data.error || 'Could not save settings.');
    }

    if (data.ok) {
        const sidebarName = document.querySelector('.sidebar-user-name');
        if (sidebarName) sidebarName.textContent = name;

        const avatar = document.querySelector('.sidebar-avatar');
        if (avatar && data.initials) avatar.textContent = data.initials;

        userAssistantSettings.enabled = assistantEnabled;
        userAssistantSettings.provider = assistantProvider;
        userAssistantSettings.hasKey = !!(assistantApiKey || userAssistantSettings.hasKey || data.assistant_has_key);
        userAssistantSettings.keyMasked = data.assistant_key_masked || userAssistantSettings.keyMasked;
        refreshAssistantUi();

        const keyField = document.getElementById('settingsAssistantApiKey');
        if (keyField) keyField.value = '';
        const keyStatus = document.getElementById('settingsAssistantKeyStatus');
        if (keyStatus) {
            keyStatus.textContent = userAssistantSettings.hasKey
                ? ('Saved key: ' + (userAssistantSettings.keyMasked || 'already stored'))
                : 'No API key stored yet. Leave this empty for normal chat.';
        }
        
        localStorage.setItem('quick_languages', JSON.stringify(quick_languages));
        renderComposerLanguages();
        
    }
}

const languages = {
        en: "English",
        nl: "Dutch",
        it: "Italian",
        de: "German",
        fr: "French",
        es: "Spanish",
        pt: "Portuguese",
        zh: "Chinese"
    };
    
window.setComposerLanguage = function(code) {
    const input = document.getElementById('input');
    if (!input) return;

    input.dataset.language = code;
    localStorage.setItem('composer_language', code);
    renderComposerLanguages();
    input.focus();
};

function renderComposerLanguages() {
    const el = document.getElementById('composerLanguages');
    if (!el) return;

    let list = [];

    try {
        list = JSON.parse(localStorage.getItem('quick_languages') || '[]');
    } catch (e) {}

    if (!list.length) {
        try {
            list = JSON.parse(
                document.getElementById('settingsQuickLanguagesInput')?.value || '[]'
            );
        } catch (e) {}
    }

    const selectedLanguage =
        localStorage.getItem('composer_language') ||
        document.getElementById('input')?.dataset.language ||
        '';

    el.innerHTML = list.map(code => `
        <button
            type="button"
            class="composer-lang ${selectedLanguage === code ? 'active' : ''}"
            onclick="setComposerLanguage('${code}')"
        >
            ${code.toUpperCase()}
        </button>
    `).join('');
}

window.quickLang = function(action, value) {
    const input = document.getElementById('settingsQuickLanguagesInput');
    const selected = document.getElementById('settingsQuickSelected');
    const dropdown = document.getElementById('settingsQuickDropdown');
    const multi = document.getElementById('settingsQuickMulti');
    const reading = document.getElementById('settingsLanguage');

    const labels = languages;

    if (!input || !selected) return;

    if (!dropdown.dataset.init) {
        dropdown.innerHTML = Object.entries(labels).map(([code, label]) => `
            <div class="settings-multi-option"
                 data-value="${code}"
                 onclick="quickLang('toggle','${code}')">
                ${label}
            </div>
        `).join('');

        if (reading && !reading.dataset.init) {
            reading.innerHTML = Object.entries(labels).map(([code, label]) => `
                <option value="${code}">${label}</option>
            `).join('');

            reading.value = reading.dataset.selected || 'en';
            reading.dataset.init = '1';
        }

        dropdown.dataset.init = '1';
    }

    function get() {
        try {
            const list = JSON.parse(input.value || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function set(list) {
        input.value = JSON.stringify(list);
        render();
    }

    function render() {
        const list = get();

        if (!list.length) {
            selected.innerHTML = '<span class="settings-multi-placeholder">Select languages…</span>';
        } else {
            selected.innerHTML = list.map(code => `
                <span class="settings-multi-pill">
                    ${labels[code] || code}
                    <button type="button" onclick="event.stopPropagation(); quickLang('toggle','${code}')">×</button>
                </span>
            `).join('');
        }

        dropdown.querySelectorAll('.settings-multi-option').forEach(el => {
            el.classList.toggle('selected', list.includes(el.dataset.value));
        });
    }

    switch (action) {
        case 'open':
            render();
            if (multi) multi.classList.toggle('open');
            break;

        case 'toggle': {
            let list = get();

            if (list.includes(value)) {
                list = list.filter(v => v !== value);
            } else {
                list.push(value);
            }

            set(list);
            break;
        }

        default:
            render();
            break;
    }
};

function switchSettingsTab(tabName, btn) {
    const modal = document.getElementById('settingsModal');
    if (!modal) return;

    modal.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    modal.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));

    if (btn) {
        btn.classList.add('active');
    }

    const panel = modal.querySelector('.settings-tab-panel[data-tab="' + tabName + '"]');
    if (panel) {
        panel.classList.add('active');
    }
};

function toggleUserMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('sidebarUserDropdown');
    if (!menu) return;
    menu.classList.toggle('open');
}

function handleSidebarSettings(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const menu = document.getElementById('sidebarUserDropdown');
    if (menu) menu.classList.remove('open');

    openSettingsModal();
}

function handleSidebarLogout(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const menu = document.getElementById('sidebarUserDropdown');
    if (menu) menu.classList.remove('open');

    logoutUser();
}

document.addEventListener('click', function (e) {
    const menu = document.getElementById('sidebarUserDropdown');
    const wrap = document.querySelector('.sidebar-user-menu-wrap');
    if (!menu || !wrap) return;

    if (wrap.contains(e.target)) return;

    menu.classList.remove('open');
});

document.getElementById('sidebarUserDropdown')?.addEventListener('click', function (e) {
    e.stopPropagation();
});

document.addEventListener('click', function (e) {
    const modal = document.getElementById('settingsModal');
    if (!modal || modal.classList.contains('hidden')) return;

    if (e.target === modal) {
        closeSettingsModal();
    }
});

document.addEventListener('click', function (e) {
    if (window.innerWidth >= 768) return;

    const sidebar = document.getElementById('sidebar');
    if (!sidebar || sidebar.classList.contains('hidden')) return;

    const menuBtn =
        document.getElementById('leftmenu') ||
        document.getElementById('leftmenuFloating');

    if (!sidebar.contains(e.target) && !menuBtn?.contains(e.target)) {
        sidebar.classList.add('hidden');
    }
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSettingsModal();
});

document.addEventListener('click', function (e) {
    const menu = document.getElementById('chatHeaderMenu');
    if (!menu) return;
    if (!e.target.closest('.chat-header-wa')) {
        menu.classList.add('hidden');
    }
});

async function changePassword() {
    const currentField = document.getElementById('settingsCurrentPassword');
    const current = currentField ? currentField.value : '';
    const next = document.getElementById('settingsNewPassword')?.value || '';
    const next2 = document.getElementById('settingsNewPassword2')?.value || '';
    const notice = document.getElementById('passwordNotice');

    if (!next || !next2) {
        if (notice) notice.textContent = 'Please fill all required fields.';
        return;
    }

    if (next !== next2) {
        if (notice) notice.textContent = 'Passwords do not match.';
        return;
    }

    if (next.length < 6) {
        if (notice) notice.textContent = 'Password must be at least 6 characters.';
        return;
    }

    const res = await fetch('auth/change-password.php<?= $nocache ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            current_password: current,
            new_password: next
        })
    });

    const data = await res.json();

    if (notice) {
        notice.textContent = data.ok
            ? (data.message || 'Password updated.')
            : (data.error || 'Could not update password.');
    }

    if (data.ok) {
        if (currentField) currentField.value = '';
        document.getElementById('settingsNewPassword').value = '';
        document.getElementById('settingsNewPassword2').value = '';
    }
}
async function sendPasswordResetLink() {
    const email = (authState.email || '').trim();

    if (!email) {
        showInlineNotice('Please enter your email first.');
        return;
    }

    const res = await fetch('auth/password-reset-start.php<?= $nocache ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
    });

    const data = await res.json();

    if (!data.ok) {
        showInlineNotice(data.error || 'Could not send reset link.');
        return;
    }

    authState.step = 'password_reset_sent';
    renderAuthView();
}

function toggleChatContext() {
    const card = document.getElementById('chatContextCard');
    if (!card) return;

    const willOpen = card.classList.contains('hidden');
    card.classList.toggle('hidden');

    if (willOpen) {
        runCatchupPlaceholder();
    }
}

function toggleChatHeaderMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('chatHeaderMenu');
    if (!menu) return;
    menu.classList.toggle('hidden');
}

function renderChatContext(chat) {
    const summaryEl = document.getElementById('chatSummaryText');
    const participantsEl = document.getElementById('chatParticipantsText');
    const updatedEl = document.getElementById('chatUpdatedText');

    if (!summaryEl || !participantsEl || !updatedEl) return;

    const participants = Array.isArray(chat?.participants) ? chat.participants : [];
    const messages = Array.isArray(chat?.messages) ? chat.messages : [];

    const summary =
        (chat?.summary?.text || '').trim() ||
        buildFallbackSummary(messages);

    summaryEl.textContent = summary || '—';
    participantsEl.textContent = participants.length
        ? participants.map(p => p.name || p.email || 'User').join(', ')
        : '—';

    updatedEl.textContent = chat?.updated_at || '—';
}

function buildFallbackSummary(messages) {
    const humanMessages = messages.filter(msg =>
        msg.role === 'other' || msg.role === 'user'
    );

    if (!humanMessages.length) return '';

    const last = humanMessages.slice(-3).map(msg => {
        const name = msg.name || 'User';
        const content = (msg.content || '').trim();
        return content ? `${name}: ${content}` : '';
    }).filter(Boolean);

    return last.join(' • ');
}

async function runIncomingAssistPlaceholder(targetMessageId, sourceText, intent = 'reply', options = {}) {
    if (!targetMessageId || !sourceText) return null;
    if (!canUseAssistantFeatures()) return null;

    const state = getTempUiState();

    state.items = state.items.filter(item => !(item.targetMessageId === targetMessageId));

    state.items.push({
        kind: 'thinking',
        targetMessageId
    });
    renderTemporaryFlow();

    try {
        const targetMessage = findMessageById(targetMessageId);
        const allMessages = Array.isArray(currentChatMeta?.messages) ? currentChatMeta.messages : [];

        let latestIncomingId = '';
        for (let i = allMessages.length - 1; i >= 0; i--) {
            const msg = allMessages[i];
            if (
                msg?.role === 'other' &&
                String(msg?.user_id || '') !== String(window.currentUserId || '')
            ) {
                latestIncomingId = String(msg.id || '');
                break;
            }
        }

        const isLatestIncoming =
            latestIncomingId !== '' &&
            String(targetMessageId) === latestIncomingId;

        const data = await sendToAPI(
            [{ role: 'user', content: sourceText }],
            'incoming_assist',
            {
                incoming_intent: intent,
                incoming_is_latest: isLatestIncoming ? '1' : '0',
                incoming_message_time: String(targetMessage?.time || '')
            }
        );

        state.items = state.items.filter(item =>
            !(item.kind === 'thinking' && item.targetMessageId === targetMessageId)
        );

        const replyType = data?.reply_type || 'passive';
        const answer = String(data?.reply || '').trim();

        if (intent === 'reply') {
            if (answer) {
                upsertInlineReplyDraft(targetMessageId, answer, 'assisted reply');
                return data;
            }
        
            state.items.push({
                kind: 'assistant_reply',
                content: 'Message not clear.',
                draft: '',
                targetMessageId,
                metaType: 'passive'
            });
        
            renderTemporaryFlow();
            // scrollChatToBottom(targetMessageId || true);
            return data;
        }

        if (replyType === 'passive' || !answer) {
            state.items.push({
                kind: 'assistant_reply',
                content: 'I could not help with that message.',
                draft: '',
                targetMessageId,
                metaType: 'passive'
            });
            renderTemporaryFlow();
            return data;
        }

        state.items.push({
            kind: 'assistant_reply',
            content: answer,
            draft: '',
            targetMessageId,
            metaType: replyType
        });

        renderTemporaryFlow();
        return data;
    } catch (err) {
        state.items = state.items.filter(item =>
            !(item.kind === 'thinking' && item.targetMessageId === targetMessageId)
        );

        renderTemporaryFlow();
        return null;
    }
}

async function runOutgoingCheckPlaceholder(rawMessage) {
    if (!rawMessage) {
        return { ok: false, reply_type: 'passive', reply: '' };
    }

    showComposerSuggestionLoading();

    try {
        const suggestion = await requestComposerSuggestion(rawMessage);

        return {
            ok: true,
            reply_type: suggestion && suggestion !== 'no_edits_suggested' ? 'draft' : 'passive',
            reply: suggestion || ''
        };
    } catch (err) {
        return {
            ok: false,
            reply_type: 'passive',
            reply: ''
        };
    }
}

async function runCatchupPlaceholder() {
    if (!currentChatMeta || !Array.isArray(currentChatMeta.messages)) return;

    const recent = currentChatMeta.messages.slice(-12).map(msg => ({
        role: msg.role || 'other',
        content: msg.content || msg.text || '',
        name: msg.name || ''
    }));

    try {
        const data = await sendToAPI(recent, 'catchup');
        renderCatchupPlaceholder(data);
    } catch (err) {
        renderCatchupPlaceholder({
            ok: true,
            reply_type: 'catchup',
            items: [],
            reply: 'Could not prepare a catch-up right now.'
        });
    }
}

function renderCatchupPlaceholder(data) {
    const card = document.getElementById('catchupCard');
    const list = document.getElementById('catchupList');
    if (!card || !list) return;

    const items = Array.isArray(data?.items) ? data.items : [];
    const fallback = String(data?.reply || '').trim();

    if (items.length) {
        list.innerHTML = items.map(item => `<li>${escapeHtml(String(item || ''))}</li>`).join('');
    } else if (fallback) {
        list.innerHTML = `<li>${escapeHtml(fallback)}</li>`;
    } else {
        list.innerHTML = `<li>No catch-up available yet.</li>`;
    }

    card.classList.remove('hidden');
}

async function runPrivateAssistantLanePlaceholder(text) {
    const state = getTempUiState();
    const actionBtn = document.getElementById('actionBtn');

    state.items.push({
        kind: 'assistant_user',
        label: 'to assistant',
        content: text
    });
    renderTemporaryFlow();

    renderThinking();
    if (actionBtn) actionBtn.disabled = true;

    try {
        const data = await sendToAPI([
            { role: 'user', content: text }
        ], 'assist');

        state.items = state.items.filter(item => item.kind !== 'thinking');

        state.items.push({
            kind: 'assistant_reply',
            content: data?.reply || data?.text || data?.message || 'I could not help with that right now.',
            draft: data?.reply || ''
        });
    } catch (err) {
        state.items = state.items.filter(item => item.kind !== 'thinking');
        state.items.push({
            kind: 'assistant_reply',
            content: 'I could not help with that right now.'
        });
    } finally {
        if (actionBtn) actionBtn.disabled = false;
    }

    renderTemporaryFlow();
}

function setComposerReplyLoading(isLoading, loadingText = '...') {
    const input = document.getElementById('input');
    const actionBtn = document.getElementById('actionBtn');

    composerReplyLoading = !!isLoading;

    if (!input) return;

    if (isLoading) {
        input.value = loadingText;
        input.disabled = true;
        autoGrowTextarea.call(input);
        if (actionBtn) actionBtn.disabled = true;
        focusInput();
        return;
    }

    input.disabled = false;
    if (actionBtn) actionBtn.disabled = false;
    autoGrowTextarea.call(input);
}

function resetComposerAfterReplyFailure(delay = 2000) {
    const input = document.getElementById('input');
    const actionBtn = document.getElementById('actionBtn');

    window.setTimeout(() => {
        if (input) {
            input.value = '';
            input.disabled = false;
            autoGrowTextarea.call(input);
            focusInput();
        }
        if (actionBtn) actionBtn.disabled = false;
        composerReplyLoading = false;
    }, delay);
}

function showComposerThinking() {
    const composer = document.querySelector('#composerWrap .composer-toolbar');
    if (!composer) return;

    composer.classList.add('composer-reply-thinking');

    if (!composer.querySelector('.thinking-dots')) {
        const dots = document.createElement('div');
        dots.className = 'thinking-dots';
        dots.innerHTML = `
            <span class="thinking-dot"></span>
            <span class="thinking-dot"></span>
            <span class="thinking-dot"></span>
        `;
        composer.appendChild(dots);
    }
}

function hideComposerThinking() {
    const composer = document.querySelector('#composerWrap .composer-toolbar');
    if (!composer) return;

    composer.classList.remove('composer-reply-thinking');

    const dots = composer.querySelector('.thinking-dots');
    if (dots) dots.remove();
}

function getMessageLabels(message = {}) {
    const labels = Array.isArray(message?.meta?.labels) ? message.meta.labels : [];

    if (labels.length) return labels;

    if (message?.assist_label) {
        return [{
            type: 'assisted_reply',
            text: String(message.assist_label)
        }];
    }

    return [];
}
</script>
</head>
<body>
    <div class="app-shell">
        <div id="appView" class="">
            <section id="chatPage">
                <div class="app two-pane-layout">
                    <button id="leftmenuFloating" class="icon-btn sidebar-toggle-floating" type="button" aria-label="Open menu">
                        <span class="hamburger"><span></span><span></span><span></span></span>
                    </button>
                    <aside id="sidebar" class="sidebar">
                        <div class="sidebar-top">
                            <div class="sidebar-top-left">
                                <div class="brand-stack">
                                    <div class="brand" id="brandTitle">Tsjilp.me</div>
                                    <div class="brand-sub">Assisted communication</div>
                                </div>
                            </div>
                            <div class="sidebar-top-right"></div>
                        </div>
                        <div class="sidebar-scroll">
                            <div class="sidebar-header">
                                <input class="sidebar-search" placeholder="Search conversations" />
                            </div>
                            <div class="sidebar-section">
                                <button class="new-chat-link" type="button" onclick="createNewChat()">+ New chat</button>
                            </div>
                            <div class="global-compass">
                                <div class="global-compass-head">
                                    <div class="global-compass-title">What happens next?</div>
                                    <div class="global-compass-sub">Global compass</div>
                                </div>
                                <div class="global-action-list" id="globalActionList">
                                    <div class="global-action">—</div>
                                </div>
                            </div>
                            <div class="conversation-list-wrap">
                                <div class="sidebar-label">Chat list</div>
                                <div id="chatList">—</div>
                                <div id="archivedChatsWrap" class="hidden">
                                    <div class="sidebar-label">Archived chats</div>
                                    <div id="archivedChatList"></div>
                                </div>
                            </div>
                            <div class="sidebar-user-block">
                                <?php if (empty($_SESSION['user_id'])): ?>
                                    <button class="sidebar-btn" type="button" onclick="openAuthModal('login')">Log in</button>
                                <?php else: ?>
                                    <div class="sidebar-user-menu-wrap">
                                    <button class="sidebar-user-trigger" type="button" onclick="toggleUserMenu(event)">
                                        <div class="sidebar-avatar">
                                                <?= htmlspecialchars(make_initials($_SESSION['user_name'] ?? 'U')) ?>
                                            </div>
                                        <div class="sidebar-user-text">
                                            <div class="sidebar-user-name">
                                                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                                                </div>
                                            <div class="sidebar-user-email">
                                                    <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
                                                </div>
                                        </div>
                                        <div class="sidebar-user-chevron">⋯</div>
                                    </button>
                                    <div class="sidebar-user-dropdown" id="sidebarUserDropdown">
                                        <button type="button" class="sidebar-user-dropdown-item" onclick="handleSidebarSettings(event)">Settings</button>
                                        <button type="button" class="sidebar-user-dropdown-item sidebar-user-dropdown-danger" onclick="handleSidebarLogout(event)">Log out</button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </aside>
                    <main class="main chat-pane">
                        <div class="chat-top">
                            <div class="chat-header-wa">
                                <button class="chat-header-main" type="button" onclick="toggleChatHeaderMenu(event)">
                                    <div class="chat-header-avatars" id="chatHeaderAvatars"></div>
                                    <div class="chat-header-copy">
                                        <div class="chat-header-title" id="headerTitle"></div>
                                        <div class="chat-header-sub" id="headerSubline"></div>
                                    </div>
                                </button>
                                <div class="chat-header-right">
                                    <button type="button" id="chatHeaderInviteTopBtn" class="recipient-pill" onclick="openInviteModal(currentChatId)">Invite</button>
                                    <button class="recipient-pill" type="button" onclick="toggleChatContext()">
                                        <span>Catch up</span>
                                    </button>
                                </div>
                                <div class="chat-header-menu hidden" id="chatHeaderMenu">
                                    <button type="button" id="chatHeaderInviteMenuBtn" onclick="openInviteModal(currentChatId)">Invite</button>
                                    <button type="button" onclick="toggleChatContext()">Catch up</button>
                                    <button type="button" disabled>Participants</button>
                                    <button type="button" onclick="openSettingsModal()">Settings</button>
                                </div>
                            </div>
                        </div>
                        <div class="chat-scroll-wrap" id="chatScroll">
                            <span id="headerSub" class="hidden"></span>
                            <div class="chat-context-card hidden" id="chatContextCard">
                                <div class="chat-context-body">
                                    <div class="chat-context-section">
                                        <div class="chat-context-text" id="chatSummaryText">—</div>
                                    </div>
                                    <div class="chat-context-section">
                                        <div class="chat-context-label">Participants</div>
                                        <div class="chat-context-text" id="chatParticipantsText">—</div>
                                    </div>
                                    <div class="chat-context-section">
                                        <div class="chat-context-label">Updated</div>
                                        <div class="chat-context-text" id="chatUpdatedText">—</div>
                                    </div>
                                </div>
                            </div>
                            <div class="content">
                                <div class="timeline-strip hidden" id="timelineStrip">
                                    <div class="timeline-card" id="timelineCard">
                                        <div class="timeline-head">
                                            <div class="timeline-title">Conversation timeline</div>
                                        </div>
                                        <div class="timeline-items" id="timelineItems"></div>
                                    </div>
                                </div>
                                <div class="join-note hidden" id="joinNote"></div>
                                <div class="catchup-card hidden" id="catchupCard">
                                    <div class="catchup-title">Catch up</div>
                                    <ul class="catchup-list" id="catchupList"></ul>
                                </div>
                                <div id="chat" class="chat">
                                    <div id="chatIntro" style="display: flex; justify-content: center; text-align: center; pointer-events: none; margin: 50px 0 0;">
                                        <div>
                                            <div style="font-size: 32px; line-height: 1.15; font-weight: 100; margin-bottom: 10px;">Assisted communication</div>
                                            <div style="font-size: 15px; color: #6b7280; margin-bottom: 26px;">Better conversations, not more messages.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button id="scrollToBottom" class="scroll-bottom-btn" onclick="scrollChatToBottom(true)">
                            <span></span>
                        </button>
                        <div class="composer-wrap" id="composerWrap">
                            <div class="composer-inner">
                                <div class="recipient-row">
                                    <div class="recipient-label">To:</div>
                                    <div class="recipient-pills" id="recipientPills"></div>
                                </div>
                                <div class="composer-toolbar">
                                    <button class="plus-btn" id="assistantToggleBtn" type="button" aria-label="Assistant options">+</button>
                                    <div class="assistant-menu hidden" id="assistantMenu">
                                        <div class="assistant-menu-section">
                                            <div class="assistant-menu-title">Assistant</div>
                                            <label class="assistant-row"> <input type="checkbox" id="assistEnabled" checked> <span>Enable assistance</span>
                                            </label>
                                        </div>
                                        <div class="assistant-menu-section">
                                            <div class="assistant-menu-subtitle">Mode</div>
                                            <label class="assistant-row"> <input type="radio" name="assistMode" value="adaptive" checked> <span>Adaptive</span>
                                            </label> <label class="assistant-row"> <input type="radio" name="assistMode" value="always"> <span>Always assist</span>
                                            </label> <label class="assistant-row"> <input type="radio" name="assistMode" value="manual"> <span>Manual only</span>
                                            </label>
                                        </div>
                                        <div class="assistant-menu-section">
                                            <div class="assistant-menu-subtitle">Options</div>
                                            <label class="assistant-row"> <input type="checkbox" id="optDraftReplies" checked> <span>Draft replies when messages arrive</span>
                                            </label> <label class="assistant-row"> <input type="checkbox" id="optCheckBeforeSend" checked> <span>Check before sending</span>
                                            </label> <label class="assistant-row"> <input type="checkbox" id="optToneSuggestions"> <span>Suggest tone improvements</span>
                                            </label> <label class="assistant-row"> <input type="checkbox" id="optTranslate"> <span>Translate automatically</span>
                                            </label> <label class="assistant-row"> <input type="checkbox" id="optVariations"> <span>Show multiple variations</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="composer-languages" id="composerLanguages"></div>
                                    <textarea class="chat-input" id="input" placeholder="Write a message…" rows="1" onfocus="scrollChatToBottom(true);"></textarea>
                                    <button class="action-btn" id="actionBtn" type="button" aria-label="Send">
                                        <span class="send-icon"></span>
                                    </button>
                                </div>
                                <div class="assistant-status" id="assistantStatus">Try Tsjilp assistant free · 10 messages left</div>
                            </div>
                        </div>
                    </main>
                </div>
            </section>
        </div>
    </div>
    <div id="settingsModalMount"></div>
    <div id="inviteModalMount"></div>
    <!-- auth modal -->
    <div id="authModal" class="auth-modal hidden">
        <div class="auth-modal-backdrop" onclick="closeAuthModal()"></div>
        <div class="auth-modal-card">
            <button class="auth-modal-close" type="button" aria-label="Close" onclick="closeAuthModal()">×</button>
            <div id="authView"></div>
        </div>
    </div>
</body>
</html>