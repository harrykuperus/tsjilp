const config = window.APP_CONFIG;

let chatHistory = [];
let currentChatId = null;
let currentChatMeta = null;
let composerRecipient = {type: 'all',recipients: ['all'],label: 'All'};
let temporaryChatUi = {};
let lastMessageCount = 0;
let lastRenderedDay = '';
let chatSearchQuery = '';
let contactsSearchQuery = '';
let contactsListCache = [];
let activeContactAccordionId = null;
let showAllContacts = false;
let composerReplyLoading = false;
let autoDraftHandledByChat = {};
let noAiNoticeShownByChat = {};
let currentCompassState = {openIssuesOffset: 0, openIssuesHasMore: false};
let allCompassState = {loaded: false, loading: false};
let chatCompassMetaCache = {};
let chatDetailsModalCache = {};
let chatDetailsModalRequestSeq = 0;
let compassRestoreParent = null;
let compassRestoreBefore = null;
let compassCurrentChatId = '';
let activeChatSummaryPopoverId = ''
let activeChatDetailsModalChatId = '';
let composerTargetMessageId = '';
let aiModalTimer = null;
let aiModalStreamItems = [];
let aiModalViewportHandler = null;
let askAiSessionMessages = [];
let sidebarTab = 'chats';

async function api(action, data = {}, options = {}) {
    const {
        method = 'POST',
        expectJson = true,
        headers = {}
    } = options;

    const payload = {
        action,
        ...data
    };

    const response = await fetch('api.php', {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            ...headers
        },
        body: method === 'GET' ? undefined : JSON.stringify(payload)
    });

    if (!response.ok) {
        throw new Error(`API request failed (${response.status})`);
    }

    const result = expectJson
        ? await response.json()
        : await response.text();

    if (expectJson) {
        if (result?.ok === false) {
            throw new Error(result.error || 'API request failed');
        }

        if (typeof result?.error === 'string' && result.error.trim() !== '') {
            throw new Error(result.error);
        }
    }

    return result;
}

function buildAskAiSessionPayload(userText = '') {
    const cleanText = String(userText || '').trim();

    const history = askAiSessionMessages
        .slice(-8)
        .filter(msg => msg && msg.role && msg.content);

    return [
        ...history,
        { role: 'user', content: cleanText }
    ];
}

function rememberAskAiExchange(userText = '', assistantText = '') {
    const cleanUser = String(userText || '').trim();
    const cleanAssistant = String(assistantText || '').trim();

    if (cleanUser) {
        askAiSessionMessages.push({ role: 'user', content: cleanUser });
    }

    if (cleanAssistant) {
        askAiSessionMessages.push({ role: 'assistant', content: cleanAssistant });
    }

    askAiSessionMessages = askAiSessionMessages.slice(-10);
}

function syncSidebarToggleButtonState() {
    const sidebar = document.getElementById('sidebar');
    const btn = document.getElementById('leftmenuFloating');

    if (!sidebar || !btn) return;

    const isOpen = !sidebar.classList.contains('hidden');
    const isMobile = window.innerWidth < 768;
    const arrowLeft = isMobile ? !isOpen : isOpen;

    btn.hidden = false;
    btn.classList.toggle('sidebar-toggle-open', arrowLeft);
    btn.setAttribute('aria-expanded', String(isOpen));
    updateMobileNewChatFab();
}

function updateMobileNewChatFab() {
    const btn = document.getElementById('mobileNewChatTopBtn');
    if (!btn) return;

    const sidebar = document.getElementById('sidebar');
    const isMobile = window.innerWidth < 768;
    const sidebarOpen = sidebar && !sidebar.classList.contains('hidden');
    const show = isMobile && sidebarOpen && sidebarTab === 'chats' && !isGuestUser();

    btn.classList.toggle('hidden', !show);
    btn.disabled = !show;
}

let profileAvatarPrefs = {
    name: String(config?.user?.name || '').trim(),
    initials: String(config?.user?.initials || '').trim(),
    avatar: String(config?.user?.avatar || '').trim(),
    avatarEnabled: Object.prototype.hasOwnProperty.call(config?.user || {}, 'avatar_enabled')
        ? !!config?.user?.avatar_enabled
        : true
};
let currentSuggestionText = '';
let introStopped = false;
let chatListCache = [];
let archivedChatListCache = [];
let chatMemberEntriesCache = {};
let chatMemberProfileRefreshSeq = 0;
let chatMemberProfileRefreshByChat = {};
let chatMemberProfileRefreshLastRunByChat = {};
const CHAT_MEMBER_PROFILE_REFRESH_THROTTLE_MS = 8000;
let loadedChatChunkFiles = [];
let hasOlderChatChunks = false;
let loadingOlderChatChunks = false;
let loadingMessageJump = false;
let pendingJumpMessageId = '';
let currentChatOpenIssues = {};
let assistantComposerMode = 'public';

let chatSearchRequestSeq = 0;
let sidebarChatSearchResults = [];
let sidebarChatSearchVisibleCount = 5;
let sidebarSearchLoadingMore = false;
const SIDEBAR_CHAT_SEARCH_PAGE_SIZE = 10;

const GUEST_TRIAL_LIMIT = 10;

let userAssistantSettings = {
    enabled: false,
    provider: 'openai',
    hasKey: false,
    betaActive: false,
    keyMasked: ''
};

let composerAssistantSettings = {
    enabled: true,
    mode: 'adaptive',
    draftReplies: true,
    checkBeforeSend: true,
    improveAndSend: false,
    translate: false,
};

let composerSuggestionState = {
    original: '',
    suggestion: '',
    loading: false
};

let composerMessagePolished = false;
let composerAssistBaselineText = '';
let composerAssistTouched = false;
let manualAssistantAction = false;
let recipientSelectorOpen = false;

let nextOutgoingAssistLabel = '';
let featherSourceMessageId = '';
let activeMessageMenu = null;
let activeMessageMenuBtn = null;
let editingMessageId = null;
let editingMessageWrap = null;
let globalHoverActiveWrap = null;
let globalHoverHideTimer = null;
const pendingTempClientMessageIds = new Set();
let replyCountdownTimers = {};
const REPLY_COUNTDOWN_SECONDS = 8;
    
const pendingInviteId = new URLSearchParams(window.location.search).get('invite') || '';
const pendingChatId = new URLSearchParams(window.location.search).get('chat') || '';
const pendingFromName = new URLSearchParams(window.location.search).get('from') || '';
const pendingUid = new URLSearchParams(window.location.search).get('uid') || '';
const pendingSignupComplete = new URLSearchParams(window.location.search).get('signup_complete') === '1';
const pendingMemberToken = new URLSearchParams(window.location.search).get('member') || '';
let currentUserMemberKind = String(config?.user?.member_kind || '').trim();
const TSJILP_STORAGE_KEY_PREFIX = 'tsjilp.user.';
const TSJILP_STORAGE_LEGACY_KEY = 'tsjilp';

function getTsjilpStorageScopeId() {
    const userId = String(window.currentUserId || '').trim().replace(/[^a-zA-Z0-9_-]/g, '');
    if (userId) {
        return `uid_${userId}`;
    }

    const memberToken = String(pendingMemberToken || '').trim().replace(/[^a-zA-Z0-9_-]/g, '');
    if (memberToken) {
        return `member_${memberToken}`;
    }

    return 'anon';
}

function getTsjilpStorageKey() {
    return TSJILP_STORAGE_KEY_PREFIX + getTsjilpStorageScopeId();
}

function createTsjilpDefaults() {
    return {
        version: 1,
        ui: {},
        settings: {},
        guests: {},
        chats: {}
    };
}

function loadTsjilp() {
    const defaults = createTsjilpDefaults();
    const key = getTsjilpStorageKey();

    try {
        const raw = window.localStorage.getItem(key);
        if (!raw) return defaults;

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return defaults;

        return {
            ...defaults,
            ...parsed,
            ui: (parsed?.ui && typeof parsed.ui === 'object') ? parsed.ui : {},
            settings: (parsed?.settings && typeof parsed.settings === 'object') ? parsed.settings : {},
            guests: (parsed?.guests && typeof parsed.guests === 'object') ? parsed.guests : {},
            chats: (parsed?.chats && typeof parsed.chats === 'object') ? parsed.chats : {}
        };
    } catch (err) {
        return defaults;
    }
}

function saveTsjilp(data) {
    const key = getTsjilpStorageKey();
    const normalized = {
        ...createTsjilpDefaults(),
        ...(data && typeof data === 'object' ? data : {}),
        version: 1
    };
    window.localStorage.setItem(key, JSON.stringify(normalized));
}

function getTsjilp(path = '') {
    const data = loadTsjilp();
    if (!path) return data;

    const parts = String(path || '').split('.').map(part => part.trim()).filter(Boolean);
    let node = data;
    for (const part of parts) {
        if (!node || typeof node !== 'object' || !Object.prototype.hasOwnProperty.call(node, part)) {
            return undefined;
        }
        node = node[part];
    }

    return node;
}

function setTsjilp(path, value) {
    const parts = String(path || '').split('.').map(part => part.trim()).filter(Boolean);
    if (!parts.length) return;

    const data = loadTsjilp();
    let node = data;

    for (let i = 0; i < parts.length - 1; i += 1) {
        const part = parts[i];
        if (!node[part] || typeof node[part] !== 'object') {
            node[part] = {};
        }
        node = node[part];
    }

    node[parts[parts.length - 1]] = value;
    saveTsjilp(data);
}

function deleteTsjilp(path) {
    const parts = String(path || '').split('.').map(part => part.trim()).filter(Boolean);
    if (!parts.length) return;

    const data = loadTsjilp();
    let node = data;

    for (let i = 0; i < parts.length - 1; i += 1) {
        const part = parts[i];
        if (!node[part] || typeof node[part] !== 'object') {
            return;
        }
        node = node[part];
    }

    delete node[parts[parts.length - 1]];
    saveTsjilp(data);
}

function migrateTsjilpStorage() {
    const scopedKey = getTsjilpStorageKey();
    const hasScopedData = !!window.localStorage.getItem(scopedKey);
    const data = loadTsjilp();
    const removeKeys = new Set();
    let changed = false;

    if (!hasScopedData) {
        const legacyRaw = window.localStorage.getItem(TSJILP_STORAGE_LEGACY_KEY);
        if (legacyRaw) {
            try {
                const legacyParsed = JSON.parse(legacyRaw);
                if (legacyParsed && typeof legacyParsed === 'object') {
                    Object.assign(data, {
                        ...createTsjilpDefaults(),
                        ...legacyParsed,
                        ui: (legacyParsed?.ui && typeof legacyParsed.ui === 'object') ? legacyParsed.ui : {},
                        settings: (legacyParsed?.settings && typeof legacyParsed.settings === 'object') ? legacyParsed.settings : {},
                        guests: (legacyParsed?.guests && typeof legacyParsed.guests === 'object') ? legacyParsed.guests : {},
                        chats: (legacyParsed?.chats && typeof legacyParsed.chats === 'object') ? legacyParsed.chats : {}
                    });
                    changed = true;
                }
            } catch (err) {}

            removeKeys.add(TSJILP_STORAGE_LEGACY_KEY);
        }
    }

    const legacyTheme = String(window.localStorage.getItem('theme') || '').trim() || String(window.localStorage.theme || '').trim();
    if (legacyTheme) {
        data.ui.theme = legacyTheme;
        removeKeys.add('theme');
        changed = true;
    }

    if (!hasScopedData && !String(data.ui.theme || '').trim() && getTsjilpStorageScopeId() !== 'anon') {
        const anonRaw = window.localStorage.getItem(`${TSJILP_STORAGE_KEY_PREFIX}anon`);
        if (anonRaw) {
            try {
                const anonParsed = JSON.parse(anonRaw);
                const anonTheme = String(anonParsed?.ui?.theme || '').trim();
                if (anonTheme) {
                    data.ui.theme = anonTheme;
                    changed = true;
                }
            } catch (err) {}
        }
    }

    const readingModeRaw = window.localStorage.getItem('reading-size-index');
    if (readingModeRaw !== null) {
        const parsed = Number.parseInt(String(readingModeRaw || '').trim(), 10);
        data.ui.readingSizeIndex = Number.isFinite(parsed) ? parsed : 0;
        removeKeys.add('reading-size-index');
        changed = true;
    }

    const memoryView = String(window.localStorage.getItem('memory-view') || '').trim();
    if (memoryView) {
        data.ui.memoryView = memoryView;
        removeKeys.add('memory-view');
        changed = true;
    }

    const assistantSettingsRaw = window.localStorage.getItem('tsjilp_assistant_settings');
    if (assistantSettingsRaw) {
        try {
            data.settings.assistant = JSON.parse(assistantSettingsRaw);
            removeKeys.add('tsjilp_assistant_settings');
            changed = true;
        } catch (err) {}
    }

    const quickLanguagesRaw = window.localStorage.getItem('quick_languages');
    if (quickLanguagesRaw) {
        try {
            const parsed = JSON.parse(quickLanguagesRaw);
            if (Array.isArray(parsed)) {
                data.settings.quickLanguages = parsed;
                removeKeys.add('quick_languages');
                changed = true;
            }
        } catch (err) {}
    }

    const memberToken = String(window.localStorage.getItem('tsjilp_member_token') || '').trim();
    if (memberToken) {
        data.settings.memberToken = memberToken;
        removeKeys.add('tsjilp_member_token');
        changed = true;
    }

    const pendingInviteRaw = window.localStorage.getItem('tsjilp_pending_invite_context');
    if (pendingInviteRaw) {
        try {
            const parsed = JSON.parse(pendingInviteRaw);
            if (parsed && typeof parsed === 'object') {
                data.chats.pendingInviteContext = {
                    invite_id: String(parsed?.invite_id || '').trim(),
                    chat_id: String(parsed?.chat_id || '').trim(),
                    from_name: String(parsed?.from_name || '').trim(),
                    uid: String(parsed?.uid || '').trim()
                };
                removeKeys.add('tsjilp_pending_invite_context');
                changed = true;
            }
        } catch (err) {}
    }

    for (let i = 0; i < window.localStorage.length; i += 1) {
        const key = String(window.localStorage.key(i) || '');
        if (!key) continue;

        if (key.startsWith('tsjilp_guest_chat_')) {
            const chatId = key.slice('tsjilp_guest_chat_'.length).trim();
            const uid = String(window.localStorage.getItem(key) || '').trim();
            if (chatId && uid) {
                const existing = (data.guests[chatId] && typeof data.guests[chatId] === 'object') ? data.guests[chatId] : {};
                data.guests[chatId] = {
                    ...existing,
                    uid,
                    name: String(existing?.name || '').trim(),
                    last_used: String(existing?.last_used || new Date().toISOString())
                };
                changed = true;
            }
            removeKeys.add(key);
            continue;
        }

        if (key.startsWith('tsjilp_guest_name_')) {
            const chatId = key.slice('tsjilp_guest_name_'.length).trim();
            const name = String(window.localStorage.getItem(key) || '').trim();
            if (chatId && name) {
                const existing = (data.guests[chatId] && typeof data.guests[chatId] === 'object') ? data.guests[chatId] : {};
                data.guests[chatId] = {
                    ...existing,
                    uid: String(existing?.uid || '').trim(),
                    name,
                    last_used: String(existing?.last_used || new Date().toISOString())
                };
                changed = true;
            }
            removeKeys.add(key);
            continue;
        }

        if (key.startsWith('tsjilp_') && key !== TSJILP_STORAGE_LEGACY_KEY) {
            data.chats.legacy = (data.chats.legacy && typeof data.chats.legacy === 'object') ? data.chats.legacy : {};
            data.chats.legacy[key] = String(window.localStorage.getItem(key) || '');
            removeKeys.add(key);
            changed = true;
        }
    }

    if (changed) {
        saveTsjilp(data);
    }

    removeKeys.forEach(key => {
        window.localStorage.removeItem(key);
    });
}

migrateTsjilpStorage();

function setAssistantComposerMode(mode) {
    const assistantPrivateModeToggle = document.getElementById('assistantPrivateModeToggle');
    if (!assistantPrivateModeToggle) return;

    assistantPrivateModeToggle.checked = mode === 'private';
    updateAssistantComposerMode();
}

function updateAssistantComposerMode() {
    const assistantPrivateModeToggle = document.getElementById('assistantPrivateModeToggle');
    const isPrivate = !!assistantPrivateModeToggle?.checked;

    assistantComposerMode = isPrivate ? 'private' : 'public';
    document.querySelector('.composer-private-label')?.classList.toggle('active', isPrivate);

    if (isPrivate) {
        openPrivateAssistantComposerStream();
    } else {
        closePrivateAssistantComposerStream();
    }
}

function openPrivateAssistantComposerStream() {
    document.body.classList.add('assistant-private-mode');

    openAiModal();
    positionPrivateAiModal();

    document.getElementById('aiModalResults')?.classList.remove('hidden');

    const chatInput = document.getElementById('input');

    if (chatInput) {
        chatInput.placeholder = 'Ask anything...';
        chatInput.focus();
    }
}

function closePrivateAssistantComposerStream() {
    document.body.classList.remove('assistant-private-mode');

    document.getElementById('aiModal')?.classList.add('hidden');
    document.getElementById('aiModalResults')?.classList.add('hidden');

    const chatInput = document.getElementById('input');

    if (chatInput) {
        chatInput.placeholder = 'Type a message...';
    }
}

function readPendingInviteContext() {
    const data = getTsjilp('chats.pendingInviteContext');
    if (!data || typeof data !== 'object') {
        return { invite_id: '', chat_id: '', from_name: '', uid: '' };
    }

    return {
        invite_id: String(data?.invite_id || '').trim(),
        chat_id: String(data?.chat_id || '').trim(),
        from_name: String(data?.from_name || '').trim(),
        uid: String(data?.uid || '').trim()
    };
}

function savePendingInviteContext(inviteId = '', chatId = '', fromName = '', uid = '') {
    const payload = {
        invite_id: String(inviteId || '').trim(),
        chat_id: String(chatId || '').trim(),
        from_name: String(fromName || '').trim(),
        uid: String(uid || '').trim()
    };

    if (!payload.invite_id && !payload.chat_id && !payload.from_name && !payload.uid) {
        deleteTsjilp('chats.pendingInviteContext');
        return;
    }

    setTsjilp('chats.pendingInviteContext', payload);
}

function clearPendingInviteContext() {
    deleteTsjilp('chats.pendingInviteContext');
}

function normalizeInviteDisplayName(value = '') {
    return String(value || '').trim().toLowerCase();
}

function readStoredGuestIdentityForChat(chatId = '') {
    const cleanChatId = String(chatId || '').trim();
    if (!cleanChatId) return '';
    return String(getTsjilp(`guests.${cleanChatId}.uid`) || '').trim();
}

function readStoredGuestNameForChat(chatId = '') {
    const cleanChatId = String(chatId || '').trim();
    if (!cleanChatId) return '';
    return String(getTsjilp(`guests.${cleanChatId}.name`) || '').trim();
}

function saveStoredGuestIdentityForChat(chatId = '', identity = '', displayName = '') {
    const cleanChatId = String(chatId || '').trim();
    const cleanIdentity = String(identity || '').trim();
    if (!cleanChatId || !cleanIdentity) return;

    const existing = getTsjilp(`guests.${cleanChatId}`);
    const cleanName = String(displayName || '').trim();
    setTsjilp(`guests.${cleanChatId}`, {
        ...(existing && typeof existing === 'object' ? existing : {}),
        uid: cleanIdentity,
        name: cleanName || String(existing?.name || '').trim(),
        last_used: new Date().toISOString()
    });
}

function clearStoredGuestIdentityForChat(chatId = '') {
    const cleanChatId = String(chatId || '').trim();
    if (!cleanChatId) return;
    deleteTsjilp(`guests.${cleanChatId}`);
}

async function fetchPublicChatParticipantNames(chatId) {
    const { names = [] } = await api('get_public_chat_participant_names', {
        chat_id: chatId
    });
    return names.map(String).map(s => s.trim()).filter(Boolean);
}

function clearSignupCompleteQueryFlag() {
    const url = new URL(window.location.href);
    if (!url.searchParams.has('signup_complete')) return;
    url.searchParams.delete('signup_complete');
    window.history.replaceState({}, '', url.toString());
}

function clearMemberQueryFlag() {
    const url = new URL(window.location.href);
    if (!url.searchParams.has('member')) return;
    url.searchParams.delete('member');
    window.history.replaceState({}, '', url.toString());
}

function openSignupCompleteModal(chatTitle = '', inviterName = '') {
    const mount = document.getElementById('guestJoinModalMount');
    if (!mount) {
        renderSticky('Your account is ready.');
        clearSignupCompleteQueryFlag();
        return;
    }

    const title = chatTitle ? escapeHtml(String(chatTitle)) : 'your invited chat';
    const inviter = String(inviterName || '').trim();
    const subtitle = inviter
        ? 'Your account is ready. ' + escapeHtml(inviter) + ' and your chat are waiting for you.'
        : 'Your account is ready. Your invited chat is waiting for you.';

    mount.innerHTML = `
    <div id="guestJoinModal" class="auth-modal">
        <div class="auth-modal-backdrop" onclick="closeSignupCompleteModal()"></div>
        <div class="auth-modal-card">
            <button class="auth-modal-close" type="button" aria-label="Close" onclick="closeSignupCompleteModal()">×</button>
            <div class="auth-flow-title">Account created</div>
            <div class="auth-flow-sub">${subtitle}</div>
            <div class="auth-inline-notice" style="display:block;color:inherit;">You can now continue in ${title}.</div>
            <button class="auth-continue-btn" type="button" onclick="closeSignupCompleteModal()">Continue</button>
        </div>
    </div>
    `;

    clearSignupCompleteQueryFlag();
}

function closeSignupCompleteModal() {
    const mount = document.getElementById('guestJoinModalMount');
    if (mount) mount.innerHTML = '';
}

if (pendingInviteId || pendingChatId || pendingFromName || pendingUid) {
    savePendingInviteContext(pendingInviteId, pendingChatId, pendingFromName, pendingUid);
}

function getCurrentInviterNameForLinks() {
    const profileName = String(profileAvatarPrefs?.name || '').trim();
    if (profileName) return profileName;
    return String(config?.user?.name || '').trim();
}

function buildInviteChatLink(chatId = '', fromName = '') {
    const cleanChatId = String(chatId || '').trim();
    if (!cleanChatId) return app_base_url
    const params = new URLSearchParams();
    params.set('chat', cleanChatId);
    const cleanFrom = String(fromName || '').trim();
    if (cleanFrom) params.set('from', cleanFrom);
    return app_base_url + '/?' + params.toString();
}

function buildInviteUidLink(chatId = '', uid = '') {
    const cleanChatId = String(chatId || '').trim();
    const cleanUid = String(uid || '').trim();
    if (!cleanChatId || !cleanUid) return app_base_url
    const params = new URLSearchParams();
    params.set('chat', cleanChatId);
    params.set('uid', cleanUid);
    return app_base_url + '/?' + params.toString();
}

function isGuestUser() {
    return !!(isLoggedIn && currentUserMemberKind === 'invited_member');
}

function guestSignupMessage() {
    return 'Intelligence layer off';
}

function goToSignupFromGuest() {
    if (!isGuestUser()) return;
    if (typeof openAuthModal === 'function') {
        openAuthModal('signup', 'guest_upgrade');
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Create your account to unlock assistant, settings, and full profile options.');
        }
        return;
    }
    window.location.href = '/?t=' + Date.now();
}

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
    catchup: { enabled: true, status: 'placeholder' }
    };

function getGuestCreditsLeft() {
    if (isLoggedIn) return GUEST_TRIAL_LIMIT;
    return typeof window.GUEST_TRIAL_USES_LEFT === 'number' ? window.GUEST_TRIAL_USES_LEFT : 0;
}

function setGuestCreditsLeft(value) {
    window.GUEST_TRIAL_USES_LEFT = Math.max(0, value);
}

function consumeGuestCredit() {
    const next = Math.max(0, getGuestCreditsLeft() - 1);
    setGuestCreditsLeft(next);
    return next;
}

function canUseGuestTrial() {
    return !isLoggedIn && getGuestCreditsLeft() > 0;
}

function updateGuestTrialUi() {
    const assistantStatus = document.getElementById('assistantStatus');
    if (!assistantStatus || isLoggedIn) return;
    const left = getGuestCreditsLeft();
    assistantStatus.textContent = left > 0
        ? `Try Tsjilp assistant free · ${left} ${left === 1 ? 'message' : 'messages'} left`
        : 'Free trial finished · Sign up to continue';
}

// -------------------------
// FEEDBACK
// -------------------------
const FEEDBACK_STORAGE_KEY = 'tsjilp_feedback_state';
const FEEDBACK_TRIGGER_COUNT = 10;
let feedbackMessagesSent = 0;

function getFeedbackState() {
    try { return JSON.parse(localStorage.getItem(FEEDBACK_STORAGE_KEY) || 'null') || {}; } catch (e) { return {}; }
}
function setFeedbackState(patch) {
    try { localStorage.setItem(FEEDBACK_STORAGE_KEY, JSON.stringify({ ...getFeedbackState(), ...patch })); } catch (e) {}
}

function maybeShowFeedbackBar() {
    const state = getFeedbackState();
    if (state.answered || state.dismissed) return;
    feedbackMessagesSent++;
    if (feedbackMessagesSent >= FEEDBACK_TRIGGER_COUNT) {
        document.getElementById('feedbackBar')?.classList.remove('hidden');
    }
}

function dismissFeedbackBar() {
    document.getElementById('feedbackBar')?.classList.add('hidden');
    setFeedbackState({ dismissed: true });
}

function handleFeedbackRating(rating) {
    document.getElementById('feedbackBar')?.classList.add('hidden');
    setFeedbackState({ answered: true, rating });
    openFeedbackModal(rating);
}

function showFeedbackToast(text) {
    const toast = document.createElement('div');
    toast.className = 'feedback-toast';
    toast.textContent = text;
    const composer = document.getElementById('composerWrap');
    const bar = document.getElementById('feedbackBar');
    const barH = (!bar || bar.classList.contains('hidden')) ? 0 : (bar.offsetHeight || 0);
    toast.style.bottom = ((composer?.offsetHeight || 60) + barH + 12) + 'px';
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('feedback-toast--visible'));
    setTimeout(() => {
        toast.classList.remove('feedback-toast--visible');
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

function openFeedbackModal(rating = 'down') {
    const mount = document.getElementById('feedbackModalMount');
    if (!mount) return;
    const placeholder = rating === 'up' ? 'What worked well? (optional)' : "What didn\u2019t feel right?";
    mount.innerHTML = `
        <div id="feedbackModal" class="settings-modal">
            <div class="settings-modal-card" style="min-height:auto;background: none;border: none;">
                <div class="settings-section" style="margin: 0;">
                    <textarea id="feedbackText" class="settings-input" rows="4" placeholder="${placeholder}" style="width:100%;resize:vertical;border-radius:10px;"></textarea>
                </div>
                <div class="settings-section" style="display:flex;justify-content:space-between;">
                    <div id="feedbackModalNotice" class="settings-notice"></div>
                    <button type="button" class="settings-save-btn" style="max-width:120px;border-radius: 0;white-space: nowrap;" onclick="submitFeedbackModal('${rating}')">Send feedback</button>
                </div>
            </div>
        </div>`;
    document.getElementById('feedbackModal')?.classList.remove('hidden');
    setTimeout(() => document.getElementById('feedbackText')?.focus(), 50);
}

function closeFeedbackModal() {
    document.getElementById('feedbackModalMount').innerHTML = '';
}

async function submitFeedbackModal(rating = 'down') {
    const msg = String(document.getElementById('feedbackText')?.value || '').trim();
    const notice = document.getElementById('feedbackModalNotice');
    if (notice) notice.textContent = 'Sending…';
    const ok = await sendFeedbackToServer(rating, msg);
    if (ok) {
        closeFeedbackModal();
        showFeedbackToast('Thanks for the feedback!');
    } else {
        if (notice) notice.textContent = 'Could not send. Please try again.';
    }
}

async function sendFeedbackToServer(rating, message) {
    await api('submit_feedback', {
        rating,
        message,
        url: window.location.href,
        ua: navigator.userAgent,
        user_id: String(window.currentUserId || '').trim()
    });
    return true;
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

function toggleAssistantMenu(force) {
    const menu = document.getElementById('assistantMenu');
    if (!menu) return;
    const open = typeof force === 'boolean'
        ? force
        : menu.classList.contains('hidden');
    menu.classList.toggle('hidden', !open);
}

// -------------------------
// SPLASH SCREEN
// -------------------------
function hideSplashScreen() {
    const splash = document.getElementById('splashScreen');
    document.body.classList.add('loaded');
    
    if (!splash) return;
    
    splash.classList.add('fade-out');
    setTimeout(() => {
        splash.remove();
    }, 300);
}

function showSplashError(retryCallback) {
    const splashText = document.getElementById('splashText');
    const splashError = document.getElementById('splashError');
    const retryBtn = document.getElementById('splashRetryBtn');
    
    if (splashText) splashText.textContent = 'Could not load chats';
    if (splashError) splashError.style.display = 'flex';
    
    if (retryBtn && retryCallback) {
        retryBtn.onclick = retryCallback;
    }
}

// -------------------------
// COOKIE CONSENT
// -------------------------
function getCookieConsent() {
    return localStorage.getItem('cookie_consent');
}

function setCookieConsent(choice) {
    localStorage.setItem('cookie_consent', choice);
    document.getElementById('cookieConsentBanner')?.classList.add('hidden');
    if (choice === 'accepted') {
        enableTracking();
    }
}

function initCookieConsent() {
    if (!getCookieConsent()) {
        const banner = document.getElementById('cookieConsentBanner');
        if (banner) banner.classList.remove('hidden');
    } else if (getCookieConsent() === 'accepted') {
        enableTracking();
    }
}

// Placeholder: load analytics/tracking scripts dynamically.
// Only called when the user has accepted non-essential cookies.
// Add script injection here when tracking is introduced.
function enableTracking() {
    // Example (uncomment and adapt when ready):
    // if (window.__trackingEnabled) return;
    // window.__trackingEnabled = true;
    // const script = document.createElement('script');
    // script.src = 'https://example.com/analytics.js';
    // script.async = true;
    // document.head.appendChild(script);
}

window.addEventListener('load', async () => {
    initCookieConsent();
    applyGuestSidebarRestrictions();

    // Keep scroll-to-bottom button sitting just above the composer at all times
    (function () {
        const scrollBtn = document.getElementById('scrollToBottom');
        const composer = document.getElementById('composerWrap');
        if (!scrollBtn || !composer) return;
        function syncScrollBtnBottom() {
            const h = composer.offsetHeight;
            scrollBtn.style.bottom = h > 0 ? (h + 16) + 'px' : '';
        }
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(syncScrollBtnBottom).observe(composer);
        }
        syncScrollBtnBottom();
    })();
    
    const sidebar = document.getElementById('sidebar');
    const btnInside = document.getElementById('leftmenu');
    const btnFloating = document.getElementById('leftmenuFloating');
    const chatScroll = document.getElementById('chatScroll');
    const scrollBtn = document.getElementById('scrollToBottom');
    
    chatScroll?.addEventListener('scroll', async () => {
        const isNearBottom = chatScroll.scrollHeight - chatScroll.scrollTop - chatScroll.clientHeight < 120;
        scrollBtn.style.display = isNearBottom ? 'none' : 'block';

        if (chatScroll.scrollTop < 120) {
            await loadOlderChatChunk();
        }
    });

    const assistantPrivateModeToggle = document.getElementById('assistantPrivateModeToggle');

    assistantPrivateModeToggle?.addEventListener('change', updateAssistantComposerMode);

    function toggleSidebar() {
        const willOpen = sidebar.classList.contains('hidden');
        sidebar.classList.toggle('hidden');

        document.body.classList.toggle('sidebar-collapsed');

        if (willOpen) {
            setSidebarTab('chats');
        } else {
            closeNewChatPopover();
        }

        syncSidebarToggleButtonState();

        requestAnimationFrame(() => {
            if (document.getElementById('aiModal')) {
                positionAiModal();
            }
        });

        syncSidebarToggleButtonState();
    }

    if (btnInside) btnInside.onclick = toggleSidebar;
    if (btnFloating) btnFloating.onclick = toggleSidebar;

    if (window.innerWidth < 768 && isLoggedIn) {
        sidebar.classList.remove('hidden');
        await setSidebarTab('chats');
    } else if (window.innerWidth < 768) {
        sidebar.classList.add('hidden');
    }

    syncSidebarToggleButtonState();
    window.addEventListener('resize', syncSidebarToggleButtonState);

    // Swipe-to-open / swipe-to-close sidebar on mobile
    let _swipeSidebarStartX = 0;
    document.addEventListener('touchstart', e => {
        _swipeSidebarStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    document.addEventListener('touchend', e => {
        if (window.innerWidth >= 768) return;
        const delta = e.changedTouches[0].screenX - _swipeSidebarStartX;
        const sidebarOpen = !sidebar.classList.contains('hidden');
        const sidebarWidth = sidebar.offsetWidth || 320;
        const startedInSidebar = sidebarOpen && _swipeSidebarStartX < sidebarWidth;
        if (!sidebarOpen && _swipeSidebarStartX < 25 && delta > 70) {
            sidebar.classList.remove('hidden');
            syncSidebarToggleButtonState();
        } else if (startedInSidebar && delta < -70) {
            sidebar.classList.add('hidden');
            syncSidebarToggleButtonState();
        }
    }, { passive: true });

    const input = document.getElementById('input');

    const focusModeBtn = document.getElementById('focusModeBtn');
    
    focusModeBtn?.addEventListener('click', async function (e) {
        e.preventDefault();
        await toggleFocusMode();
    });

    async function toggleFocusMode() {
        document.body.classList.toggle('focus-mode');
    
        if (document.body.classList.contains('focus-mode')) {
            if (document.documentElement.requestFullscreen) {
                try { await document.documentElement.requestFullscreen(); } catch(e){}
            }
        } else {
            if (document.fullscreenElement) {
                try { await document.exitFullscreen(); } catch(e){}
            }
        }
    
        scrollChatToBottom(true);
    }
    
    if (input) {
    
        input.addEventListener('input', function () {
            autoGrowTextarea.call(input);
            cancelAllReplyCountdowns();
            if (composerAssistBaselineText && !composerAssistTouched) {
                composerAssistTouched = String(input.value || '').trim() !== composerAssistBaselineText;
                if (composerAssistTouched) {
                    nextOutgoingAssistLabel = '';
                }
            }
        });
    
        input.addEventListener('keydown', async function(e) {
            if (window.innerWidth >= 768 && e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('actionBtn')?.click();
            }
        });
    
        autoGrowTextarea.call(input);
    
    }

    if (isLoggedIn) {
        await loadAssistantSettings();
        try {
            await bootChats();
            hideSplashScreen();
        } catch (error) {
            console.error('Failed to load chats:', error);
            showSplashError(async () => {
                const splashError = document.getElementById('splashError');
                const retryBtn = document.getElementById('splashRetryBtn');
                if (splashError) splashError.style.display = 'none';
                if (retryBtn) retryBtn.disabled = true;
                retryBtn.textContent = 'Retrying...';
                
                try {
                    await bootChats();
                    hideSplashScreen();
                } catch (retryError) {
                    console.error('Retry failed:', retryError);
                    showSplashError(() => location.reload());
                    if (retryBtn) {
                        retryBtn.textContent = 'Reload page';
                        retryBtn.disabled = false;
                    }
                }
            });
        }
    } else {
        // For non-logged-in users, hide splash immediately
        hideSplashScreen();
    }

    await handlePendingInviteOnLoad();

    if (isLoggedIn) {
        setInterval(pollChatIndex, 30000);
        setInterval(pollMessages, 150000);
    }

    const readingBtn = document.getElementById('readingModeBtn');
    const content = document.querySelector('.content');

    const zooms = [1, 1.06, 1.12, 1.18];
    const labels = [
        'Text size: Default',
        'Text size: Medium',
        'Text size: Large',
        'Text size: Extra large'
    ];

    function applyReading(index){

        if(content){
            content.style.zoom = zooms[index];
        }

        const aiModalResults = document.getElementById('aiModalResults');
        if (aiModalResults) {
            aiModalResults.style.zoom = zooms[index];
        }

        if(readingBtn){
            readingBtn.style.fontSize = (14 + index) + 'px';
            readingBtn.style.padding = (4 + index) + 'px ' + (6 + index) + 'px';
            readingBtn.title = labels[index];
        }

        setTsjilp('ui.readingSizeIndex', index);
    }

    // restore
    let index = parseInt(String(getTsjilp('ui.readingSizeIndex') ?? '0'), 10);
    if (!Number.isFinite(index) || index < 0 || index >= zooms.length) index = 0;
    applyReading(index);

    // click cycle
    readingBtn?.addEventListener('click', () => {
        index = (index + 1) % zooms.length;
        applyReading(index);
    });
    
    const assistantToggleBtn = document.getElementById('assistantToggleBtn');
    const assistantMenu = document.getElementById('assistantMenu');
    const assistantStatus = document.getElementById('assistantStatus');
    const actionBtn = document.getElementById('actionBtn');

    const assistEnabled = document.getElementById('assistEnabled');
    const optDraftReplies = document.getElementById('optDraftReplies');
    const optCheckBeforeSend = document.getElementById('optCheckBeforeSend');
    const optImproveAndSend = document.getElementById('optImproveAndSend');
    const optImproveAndSendWrap = document.getElementById('optImproveAndSendWrap');
    const optTranslate = document.getElementById('optTranslate');
    const composerActionStack = document.getElementById('composerActionStack');
    const composerActionTrigger = document.getElementById('composerActionTrigger');
    const composerActionMenu = document.getElementById('composerActionMenu');

    const root = document.documentElement;
    const btn = document.getElementById('contrastToggleBtn');
    const themeToggleIcon = document.getElementById('themeToggleIcon');

    const themeIcons = {
        light: {
            viewBox: '0 0 24 24',
            innerHTML: '<path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z"/>'
        },
        'berry-dark': {
             viewBox: '0 0 24 24',
            innerHTML: '<path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd"/>'
        },
        'moon-dark': {
            viewBox: '0 0 24 24',
            innerHTML: '<path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd"/>'
        }
    };

    const normalizeTheme = (value) => {
        if (value === 'moon-dark') return 'moon-dark';
        if (value === 'berry-dark' || value === 'dark') return 'berry-dark';
        return '';
    };

    const syncThemeToggleIcon = (theme) => {
        const icon = themeIcons[theme] || themeIcons.light;
        themeToggleIcon.setAttribute('viewBox', icon.viewBox);
        themeToggleIcon.innerHTML = icon.innerHTML;
    };

    const savedTheme = String(getTsjilp('ui.theme') || '').trim();

    root.dataset.theme = normalizeTheme(savedTheme);
    syncThemeToggleIcon(root.dataset.theme || '');
    btn.onclick = () => {
        root.classList.add('no-theme-transition');
        const themeCycle = ['', 'berry-dark', 'moon-dark'];
        const currentTheme = normalizeTheme(String(root.dataset.theme || '').trim());
        const nextTheme = themeCycle[(themeCycle.indexOf(currentTheme) + 1) % themeCycle.length];
        root.dataset.theme = nextTheme;
        setTsjilp('ui.theme', nextTheme || 'light');
        syncThemeToggleIcon(nextTheme);
        requestAnimationFrame(() =>
            requestAnimationFrame(() =>
                root.classList.remove('no-theme-transition')
            )
        );
    };
    
    let assistantSettings = { ...composerAssistantSettings };
    loadComposerAssistantSettings();

    const sidebarChatsPanel = document.getElementById('sidebarChatsPanel');

    if (sidebarChatsPanel) {
        sidebarChatsPanel.addEventListener('scroll', function () {
            maybeLoadMoreSidebarSearchResults();
        });
    }
    
    function loadComposerAssistantSettings() {
        if (isGuestUser()) {
            assistantSettings.enabled = false;
            composerAssistantSettings = { ...assistantSettings, enabled: false };
            updateAssistantStatus();
            return;
        }

        try {
            const saved = getTsjilp('settings.assistant');
            if (saved && typeof saved === 'object') {
                assistantSettings = { ...assistantSettings, ...saved };
            }
        } catch (e) {}

        if (!userAssistantSettings?.hasKey && !userAssistantSettings?.betaActive) {
            assistantSettings.enabled = false;
        }

        composerAssistantSettings = { ...assistantSettings };
        if (assistEnabled) {
            syncAssistantForm();
        }
        updateAssistantStatus();
    }

    function saveComposerAssistantSettings() {
        if (isGuestUser()) {
            assistantSettings.enabled = false;
            composerAssistantSettings = { ...assistantSettings, enabled: false };
            updateAssistantStatus();
            updateComposerSendButtonState();
            return;
        }

        const previousCheckBeforeSend = !!assistantSettings.checkBeforeSend;

        assistantSettings.enabled = !!assistEnabled.checked;
        assistantSettings.mode = document.querySelector('input[name="assistMode"]:checked')?.value || 'adaptive';
        assistantSettings.draftReplies = !!optDraftReplies.checked;
        assistantSettings.checkBeforeSend = !!optCheckBeforeSend.checked;
        assistantSettings.improveAndSend = !!optImproveAndSend?.checked;
        assistantSettings.translate = !!optTranslate.checked;

        composerAssistantSettings = { ...assistantSettings };
        setTsjilp('settings.assistant', assistantSettings);
        updateAssistantStatus();
        if (previousCheckBeforeSend !== assistantSettings.checkBeforeSend) {
            composerMessagePolished = false;
        }
        syncImproveAndSendToggleState();
        updateComposerSendButtonState();
        renderRecipientPills();
    }

    function syncImproveAndSendToggleState() {
        const improveEnabled = !!optCheckBeforeSend?.checked;

        if (optImproveAndSend) {
            optImproveAndSend.disabled = !improveEnabled;
        }

        optImproveAndSendWrap?.classList.toggle('is-muted', !improveEnabled);
        updateComposerSendButtonState();
    }

    function syncAssistantForm() {
        assistEnabled.checked = !!assistantSettings.enabled;
        optDraftReplies.checked = !!assistantSettings.draftReplies;
        optCheckBeforeSend.checked = !!assistantSettings.checkBeforeSend;
        if (optImproveAndSend) optImproveAndSend.checked = !!assistantSettings.improveAndSend;
        optTranslate.checked = !!assistantSettings.translate;
        syncImproveAndSendToggleState();

        const modeRadio = document.querySelector(`input[name="assistMode"][value="${assistantSettings.mode}"]`);
        if (modeRadio) modeRadio.checked = true;

        const label = assistEnabled.closest('label').querySelector('span');

        const canEditAssistant = !!isLoggedIn && !isGuestUser();

        assistEnabled.disabled = !canEditAssistant;

        label.textContent = canEditAssistant
            ? 'Enable assistance'
            : 'Sign in to enable assistant';

        syncImproveAndSendToggleState();

        assistEnabled.onchange = function () {
            if (!canEditAssistant) {
                this.checked = false;
                assistantSettings.enabled = false;
                label.textContent = 'Sign in to enable assistant';
                return;
            }

            if (this.checked && !userAssistantSettings?.hasKey && !userAssistantSettings?.betaActive) {
                this.checked = false;
                assistantSettings.enabled = false;
                label.innerHTML = '<span style="color:#b91c1c;">API key missing</span>';
                updateAssistantStatus();
                return;
            }

            label.textContent = 'Enable assistance';
            assistantSettings.enabled = this.checked;
            saveComposerAssistantSettings();
        };

        optCheckBeforeSend?.addEventListener('change', saveComposerAssistantSettings);
        optImproveAndSend?.addEventListener('change', saveComposerAssistantSettings);
    }

    function updateAssistantStatus() {
        if (!assistantStatus) return;

        const dot = assistantSettings.enabled ? '<span class="assistant-dot on"></span>' : '<span class="assistant-dot off"></span>';

        if (isGuestUser()) {
            assistantStatus.innerHTML = `<span class="assistant-dot off"></span>${guestSignupMessage()}`;
            return;
        }

        if (!userAssistantSettings?.hasKey && !userAssistantSettings?.betaActive) {
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
        if (assistantSettings.draftReplies) activeOptions.push('CoWriter');
        if (assistantSettings.checkBeforeSend) activeOptions.push('Improve It');
        if (assistantSettings.improveAndSend) activeOptions.push('Send');
        if (assistantSettings.translate) activeOptions.push('Translator');

        const summary = activeOptions.length ? activeOptions.join(' · ') : 'No extra options';

        assistantStatus.innerHTML =
            `${dot}Assistant on · ${modeLabelMap[assistantSettings.mode]} · ${summary}`;
    }


    function toggleAssistantMenu(force) {
        const menu = document.getElementById('assistantMenu');
        if (!menu) return;
    
        const open = typeof force === 'boolean'
            ? force
            : menu.classList.contains('hidden');
    
        menu.classList.toggle('hidden', !open);
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
        syncSidebarToggleButtonState();
        closeNewChatPopover();
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

    document.addEventListener('click', e => {
        const btn = e.target.closest('#chatHeaderMenu button:not([disabled])');
        if (btn) {
            document.getElementById('chatHeaderMenu')?.classList.add('hidden');
        }
    });

    assistantMenu?.addEventListener('change', function () {
        saveComposerAssistantSettings();
        refreshAssistantUi();
        renderRecipientPills();
    });

    const assistantComposerBtn = document.getElementById('assistantComposerBtn');
    const assistantComposerInput = document.getElementById('assistantComposerInput');
    const assistantComposerForm = document.getElementById('assistantComposerBubble');

    document.getElementById('composerReplyClose')?.addEventListener('click', function () {
        clearComposerReplyPreview();
        document.getElementById('input')?.focus();
    });

    let suppressNextAssistantComposerClick = false;

    function handleAssistantComposerAction() {
        runComposerAutoReply();
    }

    assistantComposerBtn?.addEventListener('pointerdown', function (e) {
        const inputFocused = document.activeElement === document.getElementById('input');
        if (!inputFocused) return;
        e.preventDefault();
        document.getElementById('input')?.focus();
        suppressNextAssistantComposerClick = true;
        handleAssistantComposerAction();
    });

    assistantComposerBtn?.addEventListener('click', async function (e) {
        if (suppressNextAssistantComposerClick) {
            suppressNextAssistantComposerClick = false;
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        await handleAssistantComposerAction();
    });
    
    assistantComposerForm?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const instruction = String(assistantComposerInput?.value || '').trim();
        if (!instruction) return;
        if (!canUseComposerAssistant(true)) return;

        await runComposerAssist(instruction);

        assistantComposerInput.value = '';
        assistantComposerInput?.focus();
    });

    loadComposerAssistantSettings();
    refreshAssistantUi();
    updateComposerSendButtonState();
    renderRecipientPills();

    input?.addEventListener('input', function () {
        autosizeTextarea(input);
        cancelAllReplyCountdowns();

        if (manualAssistantAction) {
            manualAssistantAction = false;
        }

        if (composerMessagePolished) {
            composerAssistTouched = true;
            nextOutgoingAssistLabel = '';
        }

        // If user clears the composer, release any pending feather draft
        if (featherSourceMessageId && this.value === '') {
            cancelFeatherDraft();
        }

        // Auto-enter private/Ask AI mode for logged-out users when they start typing
        if (!isLoggedIn && !isGuestUser() && this.value.trim() && assistantComposerMode !== 'private') {
            setAssistantComposerMode('private');
        }

        updateComposerSendButtonState();
    });

    actionBtn?.addEventListener('click', async function () {
        await handleComposerSend({ normalSendClicked: true });
    });

    document.getElementById('recipientPills')?.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-recipient-type]');
        if (!btn) return;
        closeRecipientSelector();
        setComposerRecipient(
            btn.getAttribute('data-recipient-type') || 'all',
            btn.getAttribute('data-recipient-value') || 'all',
            btn.getAttribute('data-recipient-label') || 'All'
        );
    });

    document.getElementById('recipientPills')?.addEventListener('click', function (e) {
        const overflowBtn = e.target.closest('[data-recipient-overflow]');
        if (!overflowBtn) return;

        e.preventDefault();
        e.stopPropagation();
        recipientSelectorOpen = !recipientSelectorOpen;
        renderRecipientPills();
    });

    document.addEventListener('click', function (e) {
        const recipientPills = document.getElementById('recipientPills');
        if (!recipientPills) return;
        if (recipientPills.contains(e.target)) return;
        if (!recipientSelectorOpen) return;
        closeRecipientSelector();
        renderRecipientPills();
    });
    
    autosizeTextarea(input);

    const memoryTabs = document.querySelectorAll('.memory-tab');

    memoryTabs.forEach(btn => {
        btn.addEventListener('click', function () {
            applyMemoryView(this.dataset.view || 'current');
        });
    });

    document.getElementById('refreshMemoryBtn')?.addEventListener('click', async function () {
        if (this.classList.contains('loading')) return;

        this.classList.add('loading');

        try {
            if ((String(getTsjilp('ui.memoryView') || 'current')) === 'all') {
                allCompassState.loaded = false;
                await loadAllOpenIssues();
            } else {
                await loadSidebarMemory();
            }
        } finally {
            this.classList.remove('loading');
        }
    });

    applyMemoryView(String(getTsjilp('ui.memoryView') || 'current'));

    initGlobalHoverActions();
    
    composerActionTrigger?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        composerActionStack?.classList.toggle('menu-open');
    });
    
    composerActionMenu?.addEventListener('click', async function(e) {
        const btn = e.target.closest('.composer-action-btn[data-action]');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const action = btn.dataset.action || 'polish';

        if (action === 'custom_edit') {
            if (assistantComposerBtn) {
                assistantComposerBtn.classList.remove('is-writing');
                void assistantComposerBtn.offsetWidth;
                assistantComposerBtn.classList.add('is-writing');
                window.setTimeout(() => assistantComposerBtn.classList.remove('is-writing'), 1400);
            }
            assistantComposerInput?.focus();
            return;
        }

        composerActionStack?.classList.remove('menu-open');
        assistantComposerBtn?.classList.add('is-writing');

        try {
            await runComposerAction(action);
        } finally {
            assistantComposerBtn?.classList.remove('is-writing');
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!composerActionStack) return;
        if (composerActionStack.contains(e.target)) return;
        composerActionStack.classList.remove('menu-open');
    });

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
    closeRecipientSelector();
    renderRecipientPills();
    updateGuestTrialUi();
}

function closeRecipientSelector() {
    if (!recipientSelectorOpen) return;
    recipientSelectorOpen = false;
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

    return options;
}

function renderRecipientPills() {
    const wrap = document.getElementById('recipientPills');
    if (!wrap) return;

    const transformWrap = document.getElementById('composerTransformWrap');
    if (transformWrap) {
        transformWrap.classList.remove('hidden');
    }

    const options = getRecipientOptions();
    const maxVisibleRecipients = 3;
    
    // Only show participant pills if there are at least 2 participants (more than 2 people chat)
    const participantCount = options.filter(opt => opt.type === 'participant').length;
    const filteredOptions = options.filter(opt => 
        opt.type !== 'participant' || participantCount >= 2
    );

    if (filteredOptions.length <= maxVisibleRecipients) {
        recipientSelectorOpen = false;
    }

    const baseVisibleOptions = filteredOptions.slice(0, maxVisibleRecipients);
    const baseOverflowOptions = filteredOptions.slice(maxVisibleRecipients);
    const activeRecipientIndex = filteredOptions.findIndex(opt => 
        composerRecipient.type === opt.type &&
        composerRecipient.recipients?.[0] === opt.value
    );
    const selectedOverflowOption = activeRecipientIndex >= maxVisibleRecipients
        ? filteredOptions[activeRecipientIndex]
        : null;

    const visibleOptions = [...baseVisibleOptions];
    let overflowOptions = [...baseOverflowOptions];

    if (selectedOverflowOption && visibleOptions.length > 1) {
        const swapIndex = 1;
        const swappedOutOption = visibleOptions[swapIndex];

        visibleOptions[swapIndex] = selectedOverflowOption;
        overflowOptions = [
            swappedOutOption,
            ...overflowOptions.filter(opt => !(
                opt.type === selectedOverflowOption.type &&
                opt.value === selectedOverflowOption.value
            ))
        ];
    }

    const overflowIsActive = activeRecipientIndex >= maxVisibleRecipients && !visibleOptions.some(opt => (
        composerRecipient.type === opt.type &&
        composerRecipient.recipients?.[0] === opt.value
    ));

    wrap.innerHTML = visibleOptions.map(opt => {
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
    }).join('') + (overflowOptions.length ? `
        <span class="recipient-selector-wrap">
            <button
                type="button"
                class="recipient-pill recipient-pill-overflow ${overflowIsActive ? 'active' : ''}"
                data-recipient-overflow="true"
                aria-haspopup="dialog"
                aria-expanded="${recipientSelectorOpen ? 'true' : 'false'}"
                aria-label="Show ${overflowOptions.length} more recipients"
            >
                +${overflowOptions.length}
            </button>
            <div class="recipient-selector-menu ${recipientSelectorOpen ? '' : 'hidden'}" id="recipientSelectorMenu">
                ${overflowOptions.map(opt => {
                    const active =
                        composerRecipient.type === opt.type &&
                        composerRecipient.recipients?.[0] === opt.value;

                    return `
                        <button
                            type="button"
                            class="recipient-pill recipient-selector-pill ${active ? 'active' : ''}"
                            data-recipient-type="${escapeHtml(opt.type)}"
                            data-recipient-value="${escapeHtml(opt.value)}"
                            data-recipient-label="${escapeHtml(opt.label)}"
                        >
                            ${escapeHtml(opt.label)}
                        </button>
                    `;
                }).join('')}
            </div>
        </span>
    ` : '');
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
            item.kind === 'assistant_notice' ||
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

        if (item.kind === 'assistant_user' && item.label !== 'guest') {
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

            if (item.kind === 'assistant_notice') {
                bubble.className = 'temp-flow-item temp-flow-assistant temp-flow-notice';
                bubble.textContent = item.content || '';
            } else if (item.kind === 'assistant_reply') {
                bubble.className = 'temp-flow-item temp-flow-assistant';
            
                if (targetMessageId) {
                    bubble.innerHTML = `
                        <div>${escapeHtml(item.content || '')}</div>
                    `;
                } else {
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
               sendInlineReply(this.dataset.targetMessageId || '', !!item.isManualReply);
            });
        }
        
        const dismissBtn = bubble.querySelector('[data-action="dismiss-inline-reply"]');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                removeInlineReplyDraft(this.dataset.targetMessageId || '');
            });
        }

        const replyRefBtn = bubble.querySelector('[data-jump-message-id]');
        if (replyRefBtn) {
            replyRefBtn.addEventListener('click', async function () {
                await jumpToMessage(this.dataset.jumpMessageId || '');
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

        if (targetWrap) {
            const anchorRow = targetWrap.closest('.message-row');
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

function upsertInlineReplyDraft(targetMessageId, draftText, labelText = 'unedited', isManualReply = false) {
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
        replyLabel: labelText,
        isManualReply
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

    if (String(composerTargetMessageId) === String(targetMessageId)) {
        clearComposerReplyPreview();
    }
    
    renderTemporaryFlow();
}

async function sendInlineReply(targetMessageId, isManualReply = false) {
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
                text: 'unedited'
            }
        ]
    };

    const provisionalMessageId = 'tmp_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

    const userMsg = {
        id: provisionalMessageId,
        role: 'user',
        content: text,
        text: text,
        reply_to: isManualReply ? (targetMessageId || null) : '',
        recipient_label: recipientLabel
    };

    chatHistory.push(userMsg);
    renderMessage(userMsg, {
        historyIndex: chatHistory.length - 1,
        messageId: provisionalMessageId
    });
    scrollChatToBottom(currentChatId || true);

    removeInlineReplyDraft(targetMessageId);

    const participants = Array.isArray(currentChatMeta?.participants) ? currentChatMeta.participants : [];
    const isAloneChat = participants.length <= 1;

    if (isAloneChat) {
        const data = await sendToAPI(chatHistory, 'send_message', {
            meta: messageMeta,
            recipient_label: recipientLabel,
            reply_to: isManualReply ? (targetMessageId || '') : ''
        });

        if (!data?.ok) {
            rollbackProvisionalOutgoingMessage(provisionalMessageId);
            console.warn('Send failed for inline reply', data);
            if (currentChatId) {
                await loadChat(currentChatId, { scrollToBottom: false, focusComposer: true });
            }
            return;
        }

        await refreshChatList(currentChatId);
        return;
    }

    const sendAction = canUseAssistantFeatures() ? 'chat' : 'send_message';
    const data = await sendToAPI(chatHistory, sendAction, {
        meta: messageMeta,
        recipient_label: recipientLabel,
        reply_to: isManualReply ? (targetMessageId || '') : ''
    });

    if (!data?.ok) {
        rollbackProvisionalOutgoingMessage(provisionalMessageId);
        console.warn('Send failed for inline reply', data);
        if (currentChatId) {
            await loadChat(currentChatId, { scrollToBottom: false, focusComposer: true });
        }
        return;
    }

    if (data.multi) {
        lastMessageCount = data.message_count || lastMessageCount;
        await refreshChatList(currentChatId);
        return;
    }

    if (!canUseAssistantFeatures()) {
        showNoAiNoticeOnceForChat(currentChatId);
        return;
    }

    const state = getTempUiState();
    state.items = state.items.filter(item => item.kind !== 'thinking');
    renderTemporaryFlow();

    if (data.chat_id) currentChatId = data.chat_id;
    await refreshChatList(currentChatId);
}

function shouldAutoDraftIncomingReplies() {
    const settings = composerAssistantSettings || {};
    if (!isLoggedIn) return false;
    if (!currentChatId) return false;
    if (!settings.enabled) return false;
    if (!settings.draftReplies) return false;
    if (settings.mode === 'manual') return false;
    if (!userAssistantSettings.enabled || (!userAssistantSettings.hasKey && !userAssistantSettings.betaActive)) return false;
    return true;
}

function canUseAssistantFeatures() {
    return !!(
        isLoggedIn &&
        !isGuestUser() &&
        userAssistantSettings?.enabled &&
        (userAssistantSettings?.hasKey || userAssistantSettings?.betaActive)
    );
}

function showNoAiNoticeOnceForChat(chatId = currentChatId) {
    const key = String(chatId || '__new__').trim() || '__new__';
    if (noAiNoticeShownByChat[key]) return;

    noAiNoticeShownByChat[key] = true;
    renderSticky('Sign up to unlock the intelligence layer.');
}

function getSelectedAssistantProviderLabel() {
    const selectedInSettings = document.querySelector('input[name="settings_assistant_provider"]:checked')?.value;
    const fromUserSettings = String(userAssistantSettings?.provider || '').trim();
    const fromSettingsMeta = String(document.getElementById('settingsMeta')?.dataset?.assistantProvider || '').trim();

    return String(selectedInSettings || fromUserSettings || fromSettingsMeta || 'Assistant').trim();
}

function getAssistantModeLabel(mode = 'adaptive') {
    const labels = {
        adaptive: 'Adaptive',
        always: 'Always assist',
        manual: 'Manual only'
    };
    return labels[String(mode || 'adaptive')] || 'Adaptive';
}

function getAssistantStatusText() {
    if (!isLoggedIn) {
        const left = getGuestCreditsLeft();
        return left > 0
            ? `Try Tsjilp assistant free · ${left} ${left === 1 ? 'message' : 'messages'} left`
            : 'Free trial finished · Sign up to continue';
    }
    if (isGuestUser()) return guestSignupMessage();
    if (!userAssistantSettings.enabled) return 'No assistance';
    if (!userAssistantSettings.hasKey && !userAssistantSettings.betaActive) return 'No assistance · Add API key';
    return getSelectedAssistantProviderLabel() + ' connected';
}

function refreshAssistantUi() {
    const canAssist = !!(
        isLoggedIn &&
        !isGuestUser() &&
        userAssistantSettings?.enabled &&
        (userAssistantSettings?.hasKey || userAssistantSettings?.betaActive) &&
        composerAssistantSettings?.enabled
    );

    const statusEls = document.querySelectorAll('[data-assistant-status]');
    statusEls.forEach(el => {
        el.textContent = getAssistantStatusText();
        el.classList.toggle(
            'assistant-status-live',
            canAssist
        );
    });

    // only update visible labels, not metadata nodes
    const providerEls = document.querySelectorAll('[data-assistant-provider-label]');
    providerEls.forEach(el => {
        el.textContent = getSelectedAssistantProviderLabel();
    });

    const assistantMenuProvider = document.getElementById('assistantMenuProviderLabel');
    if (assistantMenuProvider) {
        assistantMenuProvider.textContent = (!isLoggedIn || isGuestUser())
            ? ''
            : ('Model ' + getSelectedAssistantProviderLabel());
    }

    const assistantGuestNotice = document.getElementById('assistantGuestNotice');
    if (assistantGuestNotice) {
        assistantGuestNotice.classList.toggle('hidden', !isGuestUser());
    }

    const settingsProviderLine = document.getElementById('settingsAssistantProviderLabel');
    if (settingsProviderLine) {
        settingsProviderLine.textContent = 'Model ' + getSelectedAssistantProviderLabel();
    }

    const assistantComposerBtn = document.getElementById('assistantComposerBtn');
    if (assistantComposerBtn) {
        assistantComposerBtn.classList.remove('hidden');
        assistantComposerBtn.disabled = !canAssist;
        assistantComposerBtn.setAttribute('aria-disabled', canAssist ? 'false' : 'true');
        assistantComposerBtn.title = canAssist ? 'Auto reply' : 'Assistant off';
    }

    updateComposerSendButtonState();

    const composerActionTrigger = document.getElementById('composerActionTrigger');
    if (composerActionTrigger) {
        const disableComposerAction = !canAssist;
        composerActionTrigger.disabled = disableComposerAction;
        composerActionTrigger.setAttribute('aria-disabled', canAssist ? 'false' : 'true');
        composerActionTrigger.title = canAssist ? 'Ask assistant' : (!isLoggedIn ? 'Sign up to use assistant tools' : (userAssistantSettings?.betaActive ? 'No assistance' : 'No assistance · Add API key'));
    }

    const assistantPrivateModeToggle = document.getElementById('assistantPrivateModeToggle');
    if (assistantPrivateModeToggle) {
        const disablePrivateToggle = isGuestUser() || (isLoggedIn && !canAssist);
        assistantPrivateModeToggle.checked = false;
        assistantPrivateModeToggle.disabled = disablePrivateToggle;
        assistantPrivateModeToggle.setAttribute('aria-disabled', disablePrivateToggle ? 'true' : 'false');
    }

    const assistantStatusEl = document.getElementById('assistantStatus');
    if (assistantStatusEl) {
        if (!isLoggedIn) {
            assistantStatusEl.textContent = getAssistantStatusText();
        } else if (isGuestUser()) {
            assistantStatusEl.innerHTML = '<span class="assistant-dot off"></span>' + guestSignupMessage();
        } else if (!userAssistantSettings?.hasKey && !userAssistantSettings?.betaActive) {
            assistantStatusEl.innerHTML = '<span class="assistant-dot off"></span>Assistant off · API key missing';
        } else if (!userAssistantSettings?.enabled || !composerAssistantSettings?.enabled) {
            assistantStatusEl.innerHTML = '<span class="assistant-dot off"></span>Assistant off';
        } else {
            assistantStatusEl.innerHTML = `<span class="assistant-dot on"></span>Assistant on · Mode: ${getAssistantModeLabel(composerAssistantSettings?.mode || 'adaptive')}`;
        }
        assistantStatusEl.classList.toggle('assistant-status-live', canAssist);
    }

    const assistantMenu = document.getElementById('assistantMenu');
    if (assistantMenu) {
        const disableMenu = isGuestUser();

        assistantMenu.querySelectorAll('input, button, textarea, select').forEach(el => {
            if (el.id === 'assistantToggleBtn') return;
            el.disabled = disableMenu;
            el.setAttribute('aria-disabled', disableMenu ? 'true' : 'false');
        });
    }

    renderRecipientPills();
    updateFeatherLatest();
}

async function loadAssistantSettings() {
    try {
        await openSettingsTab(true);
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

    const chatTitle = String(chat?.title || 'New chat');
    const names = getChatListParticipants(chat).join(', ');

    titleEl.textContent = chatTitle;
    subEl.textContent = names;
    avatarsEl.innerHTML = renderHeaderAvatars(chat);
}

function isOwnerChat(chat = null) {
    if (!chat) return false;

    const me = String(window.currentUserId || '');

    if (typeof chat.is_owner !== 'undefined') {
        return !!chat.is_owner;
    }

    const ownerUserId = String(chat?.owner_user_id || '').trim();
    if (ownerUserId && me && ownerUserId === me) {
        return true;
    }

    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object' ? chat.participant_snapshots : {};
    return Object.values(snapshots).some(p => String(p?.user_id || '') === me && String(p?.role || '') === 'owner');
}

function canCurrentUserManageChatMembers(chat = null) {
    return !!chat && isLoggedIn && !isGuestUser();
}

function canCurrentUserManageChatMember(chat = null, member = null) {
    if (!chat || !member || !canCurrentUserManageChatMembers(chat)) return false;
    if (isOwnerChat(chat)) return true;

    const currentUserId = String(window.currentUserId || '').trim();
    const addedByUserId = String(member?.added_by_user_id || '').trim();
    return currentUserId !== '' && addedByUserId !== '' && currentUserId === addedByUserId;
}

function updateInviteButtonsVisibility(chat = null) {
    const menuBtn = document.getElementById('chatHeaderInviteMenuBtn');

    const effectiveChat = chat
        || currentChatMeta
        || chatListCache.find(c => String(c?.id || '') === String(currentChatId || ''))
        || (Array.isArray(archivedChatListCache) ? archivedChatListCache.find(c => String(c?.id || '') === String(currentChatId || '')) : null);

    const visible = !!effectiveChat && isLoggedIn && !isGuestUser();

    if (menuBtn) {
        menuBtn.classList.toggle('hidden', !visible);
        menuBtn.disabled = !visible;
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
    syncChatSearchInput();

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
    
}

const fetchChatList = () => api('list_chats');

function mergeChatIntoListCaches(chat = null) {
    const chatId = String(chat?.id || chat?.chat_id || '').trim();
    if (!chatId) return;

    const members = getChatMemberEntries(chat);
    if (members.length) {
        chatMemberEntriesCache[chatId] = members;
    }

    const mergeItem = (item = {}) => {
        if (String(item?.id || '') !== chatId) {
            return item;
        }

        return {
            ...item,
            participant_ids: Array.isArray(chat?.participant_ids) ? chat.participant_ids : (item.participant_ids || []),
            participant_snapshots: chat?.participant_snapshots && typeof chat.participant_snapshots === 'object'
                ? chat.participant_snapshots
                : (item.participant_snapshots || {}),
            participants: Array.isArray(chat?.participants) ? chat.participants : (item.participants || []),
            participant_names: Array.isArray(chat?.participant_names) ? chat.participant_names : (item.participant_names || [])
        };
    };

    chatListCache = (chatListCache || []).map(mergeItem);
    archivedChatListCache = (archivedChatListCache || []).map(mergeItem);
}

function normalizeProfileVersion(value = 0) {
    const parsed = Number.parseInt(String(value ?? '').trim(), 10);
    if (!Number.isFinite(parsed) || parsed < 0) return 0;
    return parsed;
}

function getChatFromCaches(chatId = '') {
    const id = String(chatId || '').trim();
    if (!id) return null;

    if (currentChatMeta && String(currentChatMeta?.id || currentChatMeta?.chat_id || '').trim() === id) {
        return currentChatMeta;
    }

    return (chatListCache || []).find(item => String(item?.id || item?.chat_id || '').trim() === id)
        || (archivedChatListCache || []).find(item => String(item?.id || item?.chat_id || '').trim() === id)
        || null;
}

function getForeignUserIdsForChat(chat = null) {
    const ids = new Set();
    const me = String(window.currentUserId || '').trim();
    if (!chat) return [];

    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object'
        ? chat.participant_snapshots
        : {};

    Object.values(snapshots).forEach(snapshot => {
        const userId = String(snapshot?.user_id || '').trim();
        if (!userId || userId === me) return;
        ids.add(userId);
    });

    const participants = Array.isArray(chat?.participants) ? chat.participants : [];
    participants.forEach(participant => {
        const userId = String(participant?.user_id || '').trim();
        if (!userId || userId === me) return;
        ids.add(userId);
    });

    return Array.from(ids);
}

function getCachedProfileVersionForUser(userId = '', chat = null) {
    const id = String(userId || '').trim();
    if (!id) return 0;

    let version = 0;

    const fromContact = (contactsListCache || []).find(item => String(item?.user_id || '').trim() === id);
    if (fromContact) {
        version = Math.max(version, normalizeProfileVersion(fromContact?.profile_version ?? 0));
    }

    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object'
        ? chat.participant_snapshots
        : {};
    Object.values(snapshots).forEach(snapshot => {
        if (String(snapshot?.user_id || '').trim() !== id) return;
        version = Math.max(version, normalizeProfileVersion(snapshot?.profile_version ?? 0));
    });

    return version;
}

function patchProfileOnChat(chat = null, profile = null) {
    if (!chat || !profile) return false;

    const userId = String(profile?.user_id || '').trim();
    if (!userId) return false;

    const nextName = String(profile?.name || '').trim();
    const nextAvatar = String(profile?.avatar || '').trim();
    const nextInitials = String(profile?.initials || make_initials(nextName || 'User')).trim();
    const nextVersion = normalizeProfileVersion(profile?.profile_version ?? 0);
    let changed = false;

    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object'
        ? chat.participant_snapshots
        : {};

    Object.keys(snapshots).forEach(key => {
        const snapshot = snapshots[key];
        if (!snapshot || String(snapshot?.user_id || '').trim() !== userId) return;

        const patched = {
            ...snapshot,
            display_name: nextName || String(snapshot?.display_name || snapshot?.name || '').trim(),
            name: nextName || String(snapshot?.name || snapshot?.display_name || '').trim(),
            initials: nextInitials || String(snapshot?.initials || '').trim(),
            avatar: nextAvatar,
            profile_version: nextVersion
        };

        if (
            String(snapshot?.display_name || '') !== String(patched.display_name || '')
            || String(snapshot?.name || '') !== String(patched.name || '')
            || String(snapshot?.initials || '') !== String(patched.initials || '')
            || String(snapshot?.avatar || '') !== String(patched.avatar || '')
            || normalizeProfileVersion(snapshot?.profile_version ?? 0) !== nextVersion
        ) {
            snapshots[key] = patched;
            changed = true;
        }
    });

    const participants = Array.isArray(chat?.participants) ? chat.participants : [];
    for (let i = 0; i < participants.length; i += 1) {
        const participant = participants[i];
        if (String(participant?.user_id || '').trim() !== userId) continue;

        const patched = {
            ...participant,
            display_name: nextName || String(participant?.display_name || participant?.name || '').trim(),
            name: nextName || String(participant?.name || participant?.display_name || '').trim(),
            initials: nextInitials || String(participant?.initials || '').trim(),
            avatar: nextAvatar,
            profile_version: nextVersion
        };

        if (
            String(participant?.display_name || '') !== String(patched.display_name || '')
            || String(participant?.name || '') !== String(patched.name || '')
            || String(participant?.initials || '') !== String(patched.initials || '')
            || String(participant?.avatar || '') !== String(patched.avatar || '')
            || normalizeProfileVersion(participant?.profile_version ?? 0) !== nextVersion
        ) {
            participants[i] = patched;
            changed = true;
        }
    }

    if (changed && Array.isArray(chat?.participant_names)) {
        const nextNames = participants
            .map(item => String(item?.display_name || item?.name || '').trim())
            .filter(Boolean);
        chat.participant_names = Array.from(new Set(nextNames));
    }

    return changed;
}

function patchProfileOnAllChatCaches(chatId = '', profile = null) {
    const id = String(chatId || '').trim();
    if (!id || !profile) return false;

    let changed = false;

    if (currentChatMeta && String(currentChatMeta?.id || currentChatMeta?.chat_id || '').trim() === id) {
        changed = patchProfileOnChat(currentChatMeta, profile) || changed;
    }

    chatListCache = (chatListCache || []).map(item => {
        if (String(item?.id || item?.chat_id || '').trim() !== id) return item;
        const patched = { ...item };
        if (patchProfileOnChat(patched, profile)) {
            changed = true;
        }
        return patched;
    });

    archivedChatListCache = (archivedChatListCache || []).map(item => {
        if (String(item?.id || item?.chat_id || '').trim() !== id) return item;
        const patched = { ...item };
        if (patchProfileOnChat(patched, profile)) {
            changed = true;
        }
        return patched;
    });

    if (changed) {
        delete chatMemberEntriesCache[id];
    }

    return changed;
}

function upsertProfileInContactsCache(profile = null) {
    if (!profile) return null;

    const userId = String(profile?.user_id || '').trim();
    if (!userId) return null;

    const nextName = String(profile?.name || '').trim();
    const nextAvatar = String(profile?.avatar || '').trim();
    const nextVersion = normalizeProfileVersion(profile?.profile_version ?? 0);

    let updated = null;

    contactsListCache = (contactsListCache || []).map(item => {
        if (String(item?.user_id || '').trim() !== userId) {
            return item;
        }

        const patched = {
            ...item,
            display_name: nextName || String(item?.display_name || item?.name || '').trim(),
            name: nextName || String(item?.name || item?.display_name || '').trim(),
            initials: String(profile?.initials || item?.initials || make_initials(nextName || item?.display_name || item?.name || 'User')).trim(),
            avatar: nextAvatar,
            profile_version: nextVersion
        };
        updated = patched;
        return patched;
    });

    return updated;
}

async function saveProfileCacheForContact(profile = null, existingContact = null) {
    if (!isLoggedIn || isGuestUser() || !profile) return null;

    const userId = String(profile?.user_id || '').trim();
    if (!userId) return null;

    const contact = existingContact || (contactsListCache || []).find(item => String(item?.user_id || '').trim() === userId) || null;
    const displayName = String(profile?.name || contact?.display_name || contact?.name || '').trim();
    if (!displayName) return null;

    const payload = {
        id: String(contact?.id || '').trim(),
        user_id: userId,
        display_name: displayName,
        name: displayName,
        initials: String(profile?.initials || contact?.initials || make_initials(displayName)).trim(),
        email: String(contact?.email || '').trim(),
        avatar: String(profile?.avatar || '').trim(),
        preferred_language: String(contact?.preferred_language || '').trim(),
        relation: getContactDistance(contact || {}),
        tone: getContactTone(contact || {}),
        status: getContactBlocked(contact || {}) ? 'blocked' : 'active',
        topics: Array.isArray(contact?.topics)
            ? contact.topics.map(item => String(item || '').trim()).filter(Boolean)
            : splitContactList(String(contact?.topics || '')),
        notes: String(contact?.notes || '').trim(),
        profile_version: normalizeProfileVersion(profile?.profile_version ?? 0)
    };

    const res = await fetch('auth/save-contact.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (!data?.ok || !data?.contact) {
        return null;
    }

    const savedContact = data.contact;
    const savedId = String(savedContact?.id || '').trim();
    if (savedId) {
        let found = false;
        contactsListCache = (contactsListCache || []).map(item => {
            if (String(item?.id || '').trim() !== savedId) return item;
            found = true;
            return { ...item, ...savedContact };
        });
        if (!found) {
            contactsListCache = [...(contactsListCache || []), savedContact];
        }
    }

    return savedContact;
}

async function refreshChatMemberProfiles(chatId = '') {
    const id = String(chatId || '').trim();
    if (!id || !isLoggedIn || isGuestUser()) return;

    const now = Date.now();
    const lastRunAt = Number(chatMemberProfileRefreshLastRunByChat[id] || 0);
    if (lastRunAt > 0 && (now - lastRunAt) < CHAT_MEMBER_PROFILE_REFRESH_THROTTLE_MS) {
        return;
    }
    chatMemberProfileRefreshLastRunByChat[id] = now;

    const chat = getChatFromCaches(id);
    if (!chat) return;

    const userIds = getForeignUserIdsForChat(chat);
    if (!userIds.length) return;

    const refreshSeq = ++chatMemberProfileRefreshSeq;
    chatMemberProfileRefreshByChat[id] = refreshSeq;

    const data = await api('get_public_profiles', {
        ids: userIds.join(',')
    });

    if (!data?.ok || !data?.profiles || chatMemberProfileRefreshByChat[id] !== refreshSeq) {
        return;
    }

    let hasVisibleChatChanges = false;
    let hasContactsChanges = false;

    for (const userId of userIds) {
        const profile = data.profiles[userId];
        if (!profile) continue;

        const remoteVersion = normalizeProfileVersion(profile?.profile_version ?? 0);
        const localVersion = getCachedProfileVersionForUser(userId, chat);
        if (remoteVersion <= localVersion) {
            continue;
        }

        const chatPatched = patchProfileOnAllChatCaches(id, profile);
        const contactPatched = upsertProfileInContactsCache(profile);

        try {
            const saved = await saveProfileCacheForContact(profile, contactPatched);
            if (saved) {
                hasContactsChanges = true;
            }
        } catch (err) {
            console.debug('Could not persist contact profile cache', err);
        }

        if (chatPatched) {
            hasVisibleChatChanges = true;
        }
        if (contactPatched) {
            hasContactsChanges = true;
        }
    }

    if (currentChatMeta && String(currentChatMeta?.id || '').trim() === id && hasVisibleChatChanges) {
        mergeChatIntoListCaches(currentChatMeta);
        updateHeaderForChat(currentChatMeta);
        renderSidebarChatDetails(currentChatMeta);
    }

    if (hasVisibleChatChanges) {
        applyChatSearch();
        syncChatSearchInput();
    }

    if (hasContactsChanges) {
        renderSidebarContacts();
    }
}

async function refreshChatList(preferredChatId = null) {
    const data = await fetchChatList();
    if (!data?.ok) return;

    applyChatIndexUpdate(data, { preferredChatId });
}

function getOwnerInitials(name = '') {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'U';
    return parts.slice(0, 2).map(part => part[0] || '').join('').toUpperCase();
}

function isImageAvatarData(value = '') {
    return /^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+/=\s]+$/i.test(String(value || '').trim());
}

function normalizePersonName(name = '') {
    return String(name || '').trim().toLowerCase();
}

function isCurrentUserName(name = '') {
    const profileName = normalizePersonName(profileAvatarPrefs?.name || '');
    const incomingName = normalizePersonName(name);
    if (!profileName || !incomingName) return false;
    return profileName === incomingName;
}

function getCurrentUserInitialsFallback() {
    const initials = String(profileAvatarPrefs?.initials || '').trim();
    if (initials) return initials;
    return getOwnerInitials(profileAvatarPrefs?.name || 'You');
}

function renderProfileAwareAvatar(className = '', seedName = '') {
    const safeClass = String(className || '').trim();
    const safeName = String(seedName || '').trim();
    const profileAvatar = String(profileAvatarPrefs?.avatar || '').trim();
    const isCurrent = isCurrentUserName(safeName);

    if (isCurrent) {
        if (!profileAvatarPrefs?.avatarEnabled) {
            return `<span class="${safeClass} profile-avatar-fallback" style="${getChatListAvatarStyle(safeName || profileAvatarPrefs?.name || 'You')}">${escapeHtml(getCurrentUserInitialsFallback())}</span>`;
        }

        if (profileAvatar) {
            if (isImageAvatarData(profileAvatar)) {
                return `<span class="${safeClass} profile-avatar-image" style="background-image:url('${escapeHtml(profileAvatar)}')"></span>`;
            }

            return `<span class="${safeClass} profile-avatar-fallback" style="${getChatListAvatarStyle(safeName || profileAvatarPrefs?.name || 'You')}">${escapeHtml(profileAvatar)}</span>`;
        }

        return `<span class="${safeClass} profile-avatar-fallback" style="${getChatListAvatarStyle(safeName || profileAvatarPrefs?.name || 'You')}">${escapeHtml(getCurrentUserInitialsFallback())}</span>`;
    }

    return `<span class="${safeClass}" style="${getChatListAvatarStyle(safeName)}">${escapeHtml(getOwnerInitials(safeName))}</span>`;
}

function getChatListAvatarStyle(seed = '') {
    return `background:${escapeHtml(getAvatarColor(seed))};color:${escapeHtml(getNameColor(seed))};`;
}

function getChatListParticipants(chat) {
    return getChatMemberEntries(chat).map(member => member.name).filter(Boolean);
}

function getChatMemberEntries(chat) {
    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object' ? chat.participant_snapshots : {};
    const ids = Array.isArray(chat?.participant_ids) ? chat.participant_ids : [];
    const participantNames = Array.isArray(chat?.participant_names) ? chat.participant_names : [];
    const participants = Array.isArray(chat?.participants) ? chat.participants : [];
    const items = [];
    const seen = new Set();

    function rememberKeys(idValue = '', nameValue = '') {
        const idKey = String(idValue || '').trim().toLowerCase();
        const nameKey = String(nameValue || '').trim().toLowerCase();
        if (idKey) seen.add('id:' + idKey);
        if (nameKey) seen.add('name:' + nameKey);
    }

    function hasSeen(idValue = '', nameValue = '') {
        const idKey = String(idValue || '').trim().toLowerCase();
        const nameKey = String(nameValue || '').trim().toLowerCase();
        return (!!idKey && seen.has('id:' + idKey)) || (!!nameKey && seen.has('name:' + nameKey));
    }

    ids.forEach(id => {
        const snapshot = snapshots[id] || {};
        const name = String(snapshot?.display_name || snapshot?.name || snapshot?.email || '').trim();
        const avatar = String(snapshot?.avatar || '').trim();
        const initials = String(snapshot?.initials || make_initials(name || String(id || 'member'))).trim();
        const addedByUserId = String(snapshot?.added_by_user_id || '').trim();
        const label = name || initials || String(id || '').trim();
        if (!label) return;
        if (hasSeen(id, label)) return;
        rememberKeys(id, label);
        items.push({
            id: String(id || '').trim(),
            name: label,
            initials,
            avatar,
            added_by_user_id: addedByUserId
        });
    });

    participants.forEach(participant => {
        const id = String(participant?.contact_id || participant?.id || '').trim();
        const name = String(participant?.display_name || participant?.name || participant?.email || '').trim();
        const avatar = String(participant?.avatar || '').trim();
        const initials = String(participant?.initials || make_initials(name || id || 'member')).trim();
        const addedByUserId = String(participant?.added_by_user_id || '').trim();
        const label = name || initials || id;
        if (!label) return;
        if (hasSeen(id, label)) return;
        rememberKeys(id, label);
        items.push({
            id,
            name: label,
            initials,
            avatar,
            added_by_user_id: addedByUserId
        });
    });

    participantNames.forEach(nameValue => {
        const name = String(nameValue || '').trim();
        if (!name) return;
        if (hasSeen('', name)) return;
        rememberKeys('', name);
        items.push({
            id: '',
            name,
            initials: make_initials(name),
            avatar: ''
        });
    });

    if (!items.length) {
        const messages = Array.isArray(chat?.messages) ? chat.messages : [];
        messages.forEach(msg => {
            const id = String(msg?.user_id || '').trim();
            const name = String(msg?.name || '').trim();
            if (!name) return;
            if (hasSeen(id, name)) return;
            rememberKeys(id, name);
            items.push({
                id,
                name,
                initials: make_initials(name),
                avatar: ''
            });
        });
    }

    return items;
}

function renderMemberAvatar(className, member) {
    const safeClass = String(className || '').trim();
    const name = String(member?.name || '').trim();
    const avatar = String(member?.avatar || '').trim();
    const initials = String(member?.initials || make_initials(name)).trim() || 'U';

    if (isCurrentUserName(name)) {
        return renderProfileAwareAvatar(safeClass, name || profileAvatarPrefs?.name || 'You');
    }

    if (avatar) {
        if (isImageAvatarData(avatar)) {
            return `<span class="${safeClass} profile-avatar-image" style="background-image:url('${escapeHtml(avatar)}')"></span>`;
        }
        return `<span class="${safeClass}" style="${getChatListAvatarStyle(name)}">${escapeHtml(avatar)}</span>`;
    }

    return `<span class="${safeClass}" style="${getChatListAvatarStyle(name)}">${escapeHtml(initials)}</span>`;
}

function getChatMemberNames(chat) {
    const combined = [
        ...getChatListParticipants(chat)
    ];

    const unique = [];
    const seen = new Set();

    combined.forEach(name => {
        const cleaned = String(name || '').trim();
        if (!cleaned) return;
        if (/^you$/i.test(cleaned)) return;
        const key = cleaned.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        unique.push(cleaned);
    });

    return unique;
}

let chatSearchTimer = null;

function normalizeChatSearchValue(value = '') {
    return String(value || '').trim().toLowerCase();
}

function updateChatSearchClearButton() {
    const btn = document.getElementById('sidebarChatSearchClear');
    if (!btn) return;
    btn.classList.toggle('hidden', !normalizeChatSearchValue(chatSearchQuery));
}

function getChatSearchResultsBox() {
    return document.getElementById('chatSearchResults');
}

function updateChatSearchLayout() {
    const hasSearch = normalizeChatSearchValue(chatSearchQuery).length >= 2;
    const archivedJumpBtn = document.getElementById('archivedJumpBtn');
    const archivedSection = document.getElementById('archivedSection');
    const guest = isGuestUser();

    if (archivedJumpBtn) archivedJumpBtn.classList.toggle('hidden', guest || hasSearch || !(archivedChatListCache || []).length);
    if (archivedSection) archivedSection.classList.toggle('hidden', guest || hasSearch || !(archivedChatListCache || []).length);
}

function clearChatSearchResults() {
    const box = getChatSearchResultsBox();
    if (box) {
        box.classList.add('hidden');
        box.innerHTML = '';
    }
    updateChatSearchLayout();
}

function clearChatSearch() {
    clearTimeout(chatSearchTimer);
    chatSearchTimer = null;
    chatSearchRequestSeq++;

    chatSearchQuery = '';
    sidebarChatSearchResults = [];
    sidebarChatSearchVisibleCount = SIDEBAR_CHAT_SEARCH_PAGE_SIZE;
    sidebarSearchLoadingMore = false;

    syncChatSearchInput();
    updateChatSearchClearButton();
    clearChatSearchResults();

    renderChatList(chatListCache, currentChatId, archivedChatListCache);
}

function formatSidebarSearchResultDate(value = '') {
    if (!value) return '';

    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';

    const now = new Date();
    const isSameDay =
        d.getFullYear() === now.getFullYear() &&
        d.getMonth() === now.getMonth() &&
        d.getDate() === now.getDate();

    if (isSameDay) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    return d.toLocaleDateString([], { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function groupSidebarChatSearchResults(items = []) {
    const groups = [];
    const map = new Map();

    items.forEach(item => {
        const key = String(item?.id || '').trim();
        if (!key) return;

        if (!map.has(key)) {
            const group = {
                id: key,
                title: String(item?.title || 'New chat'),
                chatState: String(item?.chat_state || ''),
                items: []
            };
            map.set(key, group);
            groups.push(group);
        }

        map.get(key).items.push(item);
    });

    return groups;
}

function renderSidebarChatSearchResultItems(items = []) {
    return groupSidebarChatSearchResults(items).map(group => {
        const title = escapeHtml(String(group.title || 'New chat'));
        const isArchived = String(group.chatState || '').toLowerCase() === 'archived';
        const rows = group.items.map(item => {
            const chatId = escapeHtml(String(item?.id || ''));
            const messageId = escapeHtml(String(item?.search_message_id || ''));
            const dateText = escapeHtml(formatSidebarSearchResultDate(String(item?.updated_at || '')));
            const message = escapeHtml(String(item?.preview || 'No message preview'));

            return `
                <button
                    type="button"
                    class="chat-search-result-row"
                    data-action="open-chat"
                    data-chat-id="${chatId}"
                    data-message-id="${messageId}"
                >
                    <span class="chat-search-result-message">${message}</span>
                    <span class="chat-search-result-date">${dateText}</span>
                </button>
            `;
        }).join('');

        return `
            <div class="chat-search-result-group">
                <div class="chat-search-result-group-head">
                    <span class="chat-search-result-title">
                        <span class="chat-search-result-title-text">${title}</span>
                        ${isArchived ? '<span class="chat-search-result-state">Archived</span>' : ''}
                    </span>
                </div>
                <div class="chat-search-result-items">${rows}</div>
            </div>
        `;
    }).join('');
}

async function runChatSearch(query = '') {
    const normalized = normalizeChatSearchValue(query);
    const requestId = ++chatSearchRequestSeq;

    if (normalized.length < 2) {
        sidebarChatSearchResults = [];
        sidebarChatSearchVisibleCount = SIDEBAR_CHAT_SEARCH_PAGE_SIZE;
        clearChatSearchResults();
        renderChatList(chatListCache, currentChatId, archivedChatListCache);
        return;
    }

    try {
        const data = await api('search_sidebar_chats', {
            q: query
        });

        if (requestId !== chatSearchRequestSeq) return;

        sidebarChatSearchResults = Array.isArray(data.items) ? data.items : [];
        sidebarChatSearchVisibleCount = SIDEBAR_CHAT_SEARCH_PAGE_SIZE;
        renderVisibleSidebarChatSearchResults();
    } catch (err) {
        if (requestId !== chatSearchRequestSeq) return;

        console.error('runChatSearch failed', err);
        sidebarChatSearchResults = [];
        sidebarChatSearchVisibleCount = SIDEBAR_CHAT_SEARCH_PAGE_SIZE;
        clearChatSearchResults();
        renderChatList([], currentChatId, []);
    }
}

function renderVisibleSidebarChatSearchResults() {
    const box = getChatSearchResultsBox();
    const list = document.getElementById('chatList');
    const archivedList = document.getElementById('archivedChatList');
    const visible = sidebarChatSearchResults.slice(
        0,
        sidebarChatSearchVisibleCount
    );

    placeCompassInContext(null);
    updateChatSearchLayout();

    if (list) {
        list.innerHTML = '';
    }

    if (archivedList) {
        archivedList.innerHTML = '';
    }

    if (!box) return;

    box.classList.remove('hidden');

    if (!visible.length) {
        box.innerHTML = '<div class="chat-search-empty"><div class="conversation-empty">No matching chats</div></div>';
        return;
    }

    const hasMore = sidebarChatSearchVisibleCount < sidebarChatSearchResults.length;
    box.innerHTML = `
        <div class="chat-search-results-list">${renderSidebarChatSearchResultItems(visible)}</div>
        ${hasMore ? '<button type="button" class="chat-search-show-more" onclick="maybeLoadMoreSidebarSearchResults(true)">Show more results</button>' : ''}
    `;

    requestAnimationFrame(maybeLoadMoreSidebarSearchResults);
}

function maybeLoadMoreSidebarSearchResults(force = false) {
    const normalized = normalizeChatSearchValue(chatSearchQuery);
    if (normalized.length < 2) return;
    if (sidebarSearchLoadingMore) return;

    if (sidebarChatSearchVisibleCount >= sidebarChatSearchResults.length) {
        return;
    }

    const panel = document.getElementById('sidebarChatsPanel');
    if (!panel) return;

    const remainingScroll =
        panel.scrollHeight - panel.scrollTop - panel.clientHeight;

    if (!force && remainingScroll > 180) return;

    sidebarSearchLoadingMore = true;

    sidebarChatSearchVisibleCount += SIDEBAR_CHAT_SEARCH_PAGE_SIZE;

    requestAnimationFrame(() => {
        renderVisibleSidebarChatSearchResults();
        sidebarSearchLoadingMore = false;
    });
}

function applyChatSearch() {
    syncChatSearchInput();
    updateChatSearchClearButton();

    if (normalizeChatSearchValue(chatSearchQuery).length >= 2) {
        runChatSearch(chatSearchQuery);
    } else {
        clearChatSearchResults();
        renderChatList(chatListCache, currentChatId, archivedChatListCache);
    }

    updateChatSearchLayout();
}

function updateChatSearchFromInput() {
    const input = document.getElementById('sidebarChatSearch');
    chatSearchQuery = String(input?.value || '').trim();
    updateChatSearchClearButton();
    updateChatSearchLayout();

    clearTimeout(chatSearchTimer);
    chatSearchTimer = setTimeout(() => {
        runChatSearch(chatSearchQuery);
    }, 180);
}

function syncChatSearchInput() {
    const input = document.getElementById('sidebarChatSearch');
    if (input && input.value !== chatSearchQuery) {
        input.value = chatSearchQuery;
    }
    updateChatSearchClearButton();
}

function normalizeContactsSearchValue(value = '') {
    return String(value || '').trim().toLowerCase();
}

function contactMatchesSearch(contact, query) {
    const q = normalizeContactsSearchValue(query);
    if (q.length < 1) return true;
    const haystack = [
        String(contact?.name || contact?.display_name || ''),
        String(contact?.email || ''),
        String(contact?.preferred_language || ''),
        Array.isArray(contact?.tone) ? contact.tone.join(' ') : String(contact?.tone || '')
    ].join(' ').toLowerCase();
    return haystack.includes(q);
}

function updateContactsSearchClearButton() {
    const btn = document.getElementById('sidebarContactsSearchClear');
    if (!btn) return;
    btn.classList.toggle('hidden', !normalizeContactsSearchValue(contactsSearchQuery));
}

function clearContactsSearch() {
    contactsSearchQuery = '';
    const input = document.getElementById('sidebarContactsSearch');
    if (input) input.value = '';
    updateContactsSearchClearButton();
    renderSidebarContacts();
    input?.focus();
}

function updateContactsSearchFromInput() {
    const input = document.getElementById('sidebarContactsSearch');
    contactsSearchQuery = String(input?.value || '').trim();
    updateContactsSearchClearButton();
    renderSidebarContacts();
}

function getContactAvatarConfig(contact = {}) {
    const name = String(contact?.name || contact?.display_name || 'Unnamed').trim() || 'Unnamed';
    const avatar = String(contact?.avatar || '').trim();
    const style = getChatListAvatarStyle(name);

    if (avatar) {
        if (isImageAvatarData(avatar) || /^https?:\/\//i.test(avatar)) {
            return {
                className: 'profile-avatar-image',
                content: '',
                style: `${style}background-image:url('${escapeHtml(avatar)}');`
            };
        }

        return {
            className: '',
            content: escapeHtml(avatar),
            style
        };
    }

    return {
        className: '',
        content: escapeHtml(getOwnerInitials(name)),
        style
    };
}

function formatContactMeta(contact = {}) {
    const parts = [];
    if (contact?.last_seen_at) {
        parts.push('seen ' + formatChatListTime(contact.last_seen_at));
    }
    if (contact?.preferred_language) {
        parts.push(String(contact.preferred_language).toUpperCase());
    }
    return parts.filter(Boolean).join(' · ');
}

function clampContactScale(value, fallback = 3) {
    const parsed = Number.parseInt(String(value ?? '').trim(), 10);
    if (!Number.isFinite(parsed)) return fallback;
    return Math.max(1, Math.min(5, parsed));
}

function getContactDistance(contact = {}) {
    const relationRaw = String(contact?.relation ?? '').trim();
    if (relationRaw !== '') {
        return clampContactScale(relationRaw, 3);
    }
    return clampContactScale(contact?.distance, 3);
}

function getContactTone(contact = {}) {
    if (Array.isArray(contact?.tone)) {
        return clampContactScale(contact.tone[0], 3);
    }
    return clampContactScale(contact?.tone, 3);
}

function getContactBlocked(contact = {}) {
    const statusRaw = String(contact?.status || '').trim().toLowerCase();
    if (statusRaw === 'blocked') return 1;
    if (statusRaw === 'active') return 0;

    const blockedRaw = String(contact?.blocked ?? '').trim().toLowerCase();
    if (blockedRaw === '1' || blockedRaw === 'true') return 1;
    return 0;
}

function getContactStatus(contact = {}) {
    if (getContactBlocked(contact)) return 'blocked';
    return 'active';
}

function renderContactMiniMeta(contact = {}) {
    const distance = getContactDistance(contact);
    const tone = getContactTone(contact);
    const blocked = getContactBlocked(contact);

    if (blocked) return '✕';
    if (distance === 3 && tone === 3) return '';
    return `R${distance} T${tone}`;
}

function cloneContact(contact = null) {
    if (!contact || typeof contact !== 'object') return null;
    try {
        return JSON.parse(JSON.stringify(contact));
    } catch (e) {
        return { ...contact };
    }
}

function renderContactScaleDots(action = '', contactId = '', selectedValue = 3) {
    const id = String(contactId || '').trim();
    const value = clampContactScale(selectedValue, 3);
    return [1, 2, 3, 4, 5].map(step => `
        <button
            type="button"
            class="contact-scale-dot ${step <= value ? 'active' : ''}"
            data-action="${escapeHtml(action)}"
            data-contact-id="${escapeHtml(id)}"
            data-value="${step}"
            aria-label="${escapeHtml(`${action} ${step}`)}"></button>
    `).join('');
}

function renderContactAccordion(contact = {}) {
    const contactId = String(contact?.id || contact?.user_id || '').trim();
    if (!contactId) return '';

    const distance = getContactDistance(contact);
    const tone = getContactTone(contact);
    const blocked = getContactBlocked(contact) === 1;
    const status = getContactStatus(contact);

    return `
        <div class="contact-accordion" data-contact-accordion-for="${escapeHtml(contactId)}">
            <div class="contact-accordion-row">
                <div class="contact-accordion-label">Proximity</div>
                <div class="contact-scale-wrapper">
                    <div class="contact-scale-dots">${renderContactScaleDots('contact-distance', contactId, distance)}</div>
                    <div class="contact-scale-labels">
                        <span class="contact-scale-label-left">New</span>
                        <span class="contact-scale-label-right">Family</span>
                    </div>
                </div>
            </div>
            <div class="contact-accordion-row">
                <div class="contact-accordion-label">Tone of voice</div>
                <div class="contact-scale-wrapper">
                    <div class="contact-scale-dots">${renderContactScaleDots('contact-tone', contactId, tone)}</div>
                    <div class="contact-scale-labels">
                        <span class="contact-scale-label-left">Cold</span>
                        <span class="contact-scale-label-right">Warm</span>
                    </div>
                </div>
            </div>
            <div class="contact-accordion-row">
                <div class="contact-accordion-label">Status</div>
                <div class="contact-status-actions">
                    <button type="button" class="contact-status-btn ${status === 'active' ? 'active' : ''} ${blocked ? 'is-blocked' : ''}" data-action="contact-blocked-toggle" data-contact-id="${escapeHtml(contactId)}">${blocked ? '✕ Blocked' : '✓ Active'}</button>
                </div>
            </div>
        </div>
    `;
}

async function persistContactSettings(contactId = '') {
    if (isGuestUser()) return false;
    const id = String(contactId || '').trim();
    if (!id) return false;

    const contact = getContactById(id);
    if (!contact) return false;

    const distance = getContactDistance(contact);
    const tone = getContactTone(contact);
    const blocked = getContactBlocked(contact);
    const topicsArray = Array.isArray(contact?.topics)
        ? contact.topics.map(item => String(item || '').trim()).filter(Boolean)
        : splitContactList(String(contact?.topics || ''));

    const payload = {
        id,
        display_name: String(contact?.display_name || contact?.name || '').trim(),
        email: String(contact?.email || '').trim(),
        avatar: String(contact?.avatar || '').trim(),
        preferred_language: String(contact?.preferred_language || '').trim(),
        relation: distance,
        tone,
        status: blocked ? 'blocked' : 'active',
        topics: topicsArray,
        notes: String(contact?.notes || '').trim()
    };

    if (!payload.display_name) {
        return false;
    }

    const res = await fetch('auth/save-contact.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!data?.ok) {
        throw new Error(String(data?.error || 'Could not save contact.'));
    }

    return true;
}

function closeContactAccordion() {
    activeContactAccordionId = null;
}

function toggleContactAccordion(contactId = '') {
    const id = String(contactId || '').trim();
    if (!id) return;
    activeContactAccordionId = activeContactAccordionId === id ? null : id;
    renderSidebarContacts();
}

function toggleShowAllContacts(nextValue = null) {
    showAllContacts = nextValue === null ? !showAllContacts : !!nextValue;

    if (!showAllContacts && activeContactAccordionId) {
        const activeContact = getContactById(activeContactAccordionId);
        if (activeContact && getContactStatus(activeContact) !== 'active') {
            closeContactAccordion();
        }
    }

    renderSidebarContacts();
}

async function updateContactStatus(contactId = '', status = 'active') {
    const id = String(contactId || '').trim();
    if (!id) return;

    const previous = cloneContact(getContactById(id));
    if (!previous) return;

    const nextBlocked = String(status || '').trim().toLowerCase() === 'blocked' ? 1 : 0;
    contactsListCache = (contactsListCache || []).map(item => {
        if (String(item?.id || item?.user_id || '').trim() !== id) return item;
        return {
            ...item,
            blocked: nextBlocked,
            status: nextBlocked ? 'blocked' : 'active'
        };
    });

    if (!showAllContacts && nextBlocked === 1 && activeContactAccordionId === id) {
        closeContactAccordion();
    }

    renderSidebarContacts();

    try {
        await persistContactSettings(id);
    } catch (err) {
        contactsListCache = (contactsListCache || []).map(item =>
            String(item?.id || item?.user_id || '').trim() === id ? previous : item
        );
        renderSidebarContacts();
        alert(err?.message || 'Could not update contact status.');
    }
}

async function updateContactDistance(contactId = '', value = 3) {
    const id = String(contactId || '').trim();
    if (!id) return;

    const previous = cloneContact(getContactById(id));
    if (!previous) return;

    const nextValue = clampContactScale(value, 3);
    contactsListCache = (contactsListCache || []).map(item => {
        if (String(item?.id || item?.user_id || '').trim() !== id) return item;
        return {
            ...item,
            relation: nextValue,
            distance: nextValue
        };
    });
    renderSidebarContacts();

    try {
        await persistContactSettings(id);
    } catch (err) {
        contactsListCache = (contactsListCache || []).map(item =>
            String(item?.id || item?.user_id || '').trim() === id ? previous : item
        );
        renderSidebarContacts();
        alert(err?.message || 'Could not update distance.');
    }
}

async function updateContactTone(contactId = '', value = 3) {
    const id = String(contactId || '').trim();
    if (!id) return;

    const previous = cloneContact(getContactById(id));
    if (!previous) return;

    const nextValue = clampContactScale(value, 3);

    contactsListCache = (contactsListCache || []).map(item => {
        if (String(item?.id || item?.user_id || '').trim() !== id) return item;
        return {
            ...item,
            tone: nextValue
        };
    });
    renderSidebarContacts();

    try {
        await persistContactSettings(id);
    } catch (err) {
        contactsListCache = (contactsListCache || []).map(item =>
            String(item?.id || item?.user_id || '').trim() === id ? previous : item
        );
        renderSidebarContacts();
        alert(err?.message || 'Could not update tone.');
    }
}

async function toggleContactBlocked(contactId = '') {
    const id = String(contactId || '').trim();
    if (!id) return;
    const contact = getContactById(id);
    if (!contact) return;
    const nextStatus = getContactBlocked(contact) ? 'active' : 'blocked';
    await updateContactStatus(id, nextStatus);
}

function buildGuestContactsFromInvitedChats() {
    const contacts = [];
    const seen = new Set();
    const myUserId = String(window.currentUserId || '').trim();
    const chats = [...(chatListCache || []), ...(archivedChatListCache || [])];

    chats.forEach(chat => {
        const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object'
            ? chat.participant_snapshots
            : {};

        Object.entries(snapshots).forEach(([snapshotId, snapshot]) => {
            const userId = String(snapshot?.user_id || '').trim();
            if (userId && myUserId && userId === myUserId) return;

            const displayName = String(snapshot?.display_name || snapshot?.name || snapshot?.email || '').trim();
            const email = String(snapshot?.email || '').trim();
            const key = (userId || email || snapshotId || displayName).toLowerCase();

            if (!key || seen.has(key)) return;
            seen.add(key);

            contacts.push({
                id: String(userId || snapshotId || key),
                user_id: userId,
                display_name: displayName || 'Member',
                name: displayName || 'Member',
                email,
                avatar: String(snapshot?.avatar || '').trim(),
                preferred_language: String(snapshot?.preferred_language || '').trim(),
                status: 'active',
                relation: String(snapshot?.role || 'member').trim()
            });
        });
    });

    return contacts;
}

function renderSidebarContacts() {
    updateContactsSearchClearButton();

    const showAllToggle = document.getElementById('contactsShowAllToggle');
    if (showAllToggle) {
        showAllToggle.checked = !!showAllContacts;
    }

    const mount = document.getElementById('sidebarContactsList');
    if (!mount) return;

    const guest = isGuestUser();
    const activeStatusOnly = !showAllContacts;
    const visible = (contactsListCache || [])
        .filter(contact => {
            const status = getContactStatus(contact);
            if (!showAllContacts && status !== 'active') return false;
            return true;
        })
        .filter(contact => contactMatchesSearch(contact, contactsSearchQuery));

    if (activeContactAccordionId && !visible.some(contact => String(contact?.id || contact?.user_id || '').trim() === String(activeContactAccordionId))) {
        closeContactAccordion();
    }

    if (!visible.length) {
        const emptyLabel = contactsSearchQuery
            ? 'No matching contacts'
            : (activeStatusOnly ? 'No active contacts' : 'No contacts yet');
        mount.innerHTML = `<div class="sidebar-contact-empty">${emptyLabel}</div>`;
        return;
    }

    mount.innerHTML = visible.map(contact => {
        const contactId = String(contact?.id || contact?.user_id || '').trim();
        const name = String(contact?.name || contact?.display_name || 'Unnamed').trim() || 'Unnamed';
        const meta = formatContactMeta(contact);
        const miniMeta = renderContactMiniMeta(contact);
        const miniMetaClass = getContactBlocked(contact) ? 'contact-mini-meta is-alert' : 'contact-mini-meta';
        const avatarConfig = getContactAvatarConfig(contact);
        const avatarClass = avatarConfig.className ? ` ${avatarConfig.className}` : '';
        const avatarStyle = avatarConfig.style;
        const avatarContent = avatarConfig.content;
        const isOpen = !guest && String(activeContactAccordionId || '') === contactId;

        const linkedChatId = findDirectChatIdForContact(contactId);
        const linkedChat = linkedChatId
            ? ((chatListCache || []).find(c => String(c?.id || '').trim() === linkedChatId)
               || (archivedChatListCache || []).find(c => String(c?.id || '').trim() === linkedChatId))
            : null;
        const previewText = linkedChat?.preview ? String(linkedChat.preview).trim() : '';
        const previewTime = (previewText && linkedChat?.updated_at) ? formatChatListTime(linkedChat.updated_at) : '';
        const subtitleText = previewText || meta || String(contact?.email || '');
        const timeHtml = previewTime ? `<div class="chat-history-meta sidebar-contact-time">${escapeHtml(previewTime)}</div>` : '';

        return `
            <div class="sidebar-contact-item chat-history-item" data-contact-id="${escapeHtml(contactId)}">
                <button type="button" class="sidebar-contact-main chat-history-main" data-action="open-contact-chat" data-contact-id="${escapeHtml(contactId)}">
                    <div class="sidebar-contact-avatar${avatarClass}" style="${avatarStyle}">${avatarContent}</div>
                    <div class="sidebar-contact-copy chat-history-copy">
                        <div class="sidebar-contact-top">
                            <div class="sidebar-contact-name chat-history-title">
                            ${escapeHtml(name)}  
                            ${miniMeta ? ` <span class="${miniMetaClass}">${escapeHtml(miniMeta)}</span>` : ''}
                            </div>
                        </div>
                        <div class="sidebar-contact-meta chat-history-sub">${escapeHtml(subtitleText)}</div>
                    </div>
                    ${timeHtml}
                </button>
                ${guest ? '' : `
                <div class="chat-history-actions">
                    <button
                        class="chat-history-menu-btn"
                        type="button"
                        aria-label="Contact settings"
                        data-action="toggle-contact-accordion"
                        data-contact-id="${escapeHtml(contactId)}">⋮</button>
                </div>
                `}
                ${isOpen ? renderContactAccordion(contact) : ''}
            </div>
        `;
    }).join('');

    mount.querySelectorAll('[data-action="open-contact-chat"]').forEach(button => {
        button.addEventListener('click', function () {
            if (guest) {
                openAuthModal('login', 'private_message_guest');
                return;
            }
            openOrCreatePrivateChatWithContact(this.dataset.contactId || '');
        });
    });

    if (guest) return;

    mount.querySelectorAll('[data-action="toggle-contact-accordion"]').forEach(button => {
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleContactAccordion(this.dataset.contactId || '');
        });
    });

    mount.querySelectorAll('[data-action="contact-distance"]').forEach(button => {
        button.addEventListener('click', async function (e) {
            e.stopPropagation();
            await updateContactDistance(this.dataset.contactId || '', this.dataset.value || '3');
        });
    });

    mount.querySelectorAll('[data-action="contact-tone"]').forEach(button => {
        button.addEventListener('click', async function (e) {
            e.stopPropagation();
            await updateContactTone(this.dataset.contactId || '', this.dataset.value || '3');
        });
    });

    mount.querySelectorAll('[data-action="contact-blocked-toggle"]').forEach(button => {
        button.addEventListener('click', async function (e) {
            e.stopPropagation();
            await toggleContactBlocked(this.dataset.contactId || '');
        });
    });
}

function getChatParticipantContactIds(chat = null) {
    const ids = new Set();

    const fromParticipantIds = Array.isArray(chat?.participant_ids) ? chat.participant_ids : [];
    fromParticipantIds.forEach(id => {
        const clean = String(id || '').trim();
        if (clean) ids.add(clean);
    });

    const participants = Array.isArray(chat?.participants) ? chat.participants : [];
    participants.forEach(participant => {
        const clean = String(participant?.contact_id || participant?.id || '').trim();
        if (clean) ids.add(clean);
    });

    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object'
        ? chat.participant_snapshots
        : {};
    Object.keys(snapshots).forEach(id => {
        const clean = String(id || '').trim();
        if (clean) ids.add(clean);
    });

    return Array.from(ids);
}

function getSelfContactIdFromContacts() {
    const currentUserId = String(window.currentUserId || '').trim();
    if (!currentUserId) return '';

    const me = (contactsListCache || []).find(contact => String(contact?.user_id || '').trim() === currentUserId);
    return String(me?.id || '').trim();
}

function findDirectChatIdForContact(contactId = '') {
    const target = String(contactId || '').trim();
    if (!target) return '';

    const selfContactId = getSelfContactIdFromContacts();
    if (!selfContactId) return '';

    const expectedIds = Array.from(new Set([selfContactId, target].filter(Boolean))).sort();
    if (!expectedIds.length) return '';

    for (const chat of (chatListCache || [])) {
        const chatId = String(chat?.id || '').trim();
        if (!chatId) continue;

        const participantIds = getChatParticipantContactIds(chat);
        const normalized = Array.from(new Set(participantIds.map(id => String(id || '').trim()).filter(Boolean))).sort();

        if (normalized.length === expectedIds.length && normalized.every((id, idx) => id === expectedIds[idx])) {
            return chatId;
        }
    }

    return '';
}

async function openOrCreatePrivateChatWithContact(contactId = '') {
    const targetContactId = String(contactId || '').trim();
    if (!targetContactId) return;

    const contact = getContactById(targetContactId);
    if (!contact) return;

    const existingChatId = findDirectChatIdForContact(targetContactId);
    if (existingChatId) {
        await loadChat(existingChatId, { scrollToBottom: true, focusComposer: true });
        closeSidebarOnMobile();
        return;
    }

    try {
        const data = await api('open_or_create_private_chat', {
            contact_id: targetContactId
        });

        if (data.status === 401) {
            openAuthModal('login');
            return;
        }

        if (!data?.ok) {
            const msg = String(data?.error || 'Could not open private chat.');
            if (msg.toLowerCase().includes('not logged')) {
                openAuthModal('login');
            } else if (typeof showInlineNotice === 'function') {
                showInlineNotice(msg);
            } else {
                renderSticky(msg);
            }
            return;
        }

        const chatId = String(data?.chat_id || data?.chat?.id || '').trim();
        if (!chatId) {
            if (typeof showInlineNotice === 'function') {
                showInlineNotice('Could not open private chat.');
            } else {
                renderSticky('Could not open private chat.');
            }
            return;
        }

        await loadChat(chatId, { scrollToBottom: true, focusComposer: true });
        closeSidebarOnMobile();
    } catch (err) {
        console.error('Could not open private chat for contact', err);
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Could not open private chat.');
        } else {
            renderSticky('Could not open private chat.');
        }
    }
}

async function inviteContactToCurrentChat(contactId = '') {
    if (isGuestUser()) {
        renderSticky('Guests can only view contacts in invited chats.');
        return;
    }

    if (!currentChatId) {
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Open a chat first.');
        } else {
            renderSticky('Open a chat first.');
        }
        return;
    }

    const contact = getContactById(contactId);
    if (!contact) return;

    const uid = String(contact?.id || '').trim();
    if (!uid) return;

    try {        
        const data = await api('prepare_uid_invite', {
            chat_id: currentChatId,
            uid
        });

        if (!data?.ok) {
            const msg = String(data?.error || 'Could not prepare invite link.');
            if (msg.toLowerCase().includes('not logged')) {
                openAuthModal('login');
            } else if (typeof showInlineNotice === 'function') {
                showInlineNotice(msg);
            } else {
                renderSticky(msg);
            }
            return;
        }

        const inviteLink = String(data?.invite_link || buildInviteUidLink(currentChatId, uid)).trim();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(inviteLink);
        }

        renderSticky('Invite link copied.');
    } catch (err) {
        console.error('Could not send contact invite', err);
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Could not prepare invite link.');
        } else {
            renderSticky('Could not prepare invite link.');
        }
    }
}

async function removeContactFromCurrentChat(contactId = '') {
    if (isGuestUser()) {
        renderSticky('Guests can only view contacts in invited chats.');
        return;
    }

    if (!currentChatId) {
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Open a chat first.');
        } else {
            renderSticky('Open a chat first.');
        }
        return;
    }

    const contact = getContactById(contactId);
    if (!contact) return;

    const name = String(contact?.display_name || contact?.name || 'this contact').trim() || 'this contact';
    const ok = window.confirm('Remove ' + name + ' from this chat?');
    if (!ok) return;

    try {
        
        const data = await api('remove_chat_participant', {
            chat_id: currentChatId,
            contact_id: contactId
        });

        if (!data?.ok) {
            const msg = String(data?.error || 'Could not remove participant.');
            if (msg.toLowerCase().includes('not logged')) {
                openAuthModal('login');
            } else if (typeof showInlineNotice === 'function') {
                showInlineNotice(msg);
            } else {
                renderSticky(msg);
            }
            return;
        }

        await loadChat(currentChatId);
        await loadSidebarContacts(true);
    } catch (err) {
        console.error('Could not remove participant', err);
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Could not remove participant.');
        } else {
            renderSticky('Could not remove participant.');
        }
    }
}

async function loadSidebarContacts(force = false) {
    if (!isLoggedIn) {
        contactsListCache = [];
        renderSidebarContacts();
        return;
    }

    if (isGuestUser()) {
        contactsListCache = buildGuestContactsFromInvitedChats();
        renderSidebarContacts();
        return;
    }

    if (!force && Array.isArray(contactsListCache) && contactsListCache.length) {
        renderSidebarContacts();
        return;
    }
    const mount = document.getElementById('sidebarContactsList');
    if (mount) {
        mount.innerHTML = '<div class="sidebar-contact-empty">Loading contacts...</div>';
    }
    try {
        const data = await api('list_contacts');
        contactsListCache = data.ok ? (data.contacts || []) : [];
    } catch (err) {
        console.error('Could not load sidebar contacts', err);
        contactsListCache = [];
    }
    renderSidebarContacts();
}

function getContactById(contactId) {
    const id = String(contactId || '').trim();
    return (contactsListCache || []).find(contact => String(contact?.id || '').trim() === id) || null;
}

function splitContactList(value = '') {
    return String(value || '')
        .split(',')
        .map(item => item.trim())
        .filter(Boolean);
}

function renderChatListAvatars(chat) {
    const chatId = String(chat?.id || chat?.chat_id || '').trim();
    let members = getChatMemberEntries(chat);

    if (!members.length && chatId && currentChatMeta && String(currentChatMeta?.id || '') === chatId) {
        members = getChatMemberEntries(currentChatMeta);
    }

    if (!members.length && chatId) {
        const cached = chatMemberEntriesCache[chatId];
        if (Array.isArray(cached) && cached.length) {
            members = cached;
        }
    }

    if (members.length && chatId) {
        chatMemberEntriesCache[chatId] = members;
    }

    if (!members.length) {
        return '';
    }

    const visibleMembers = members.slice(0, 4);
    const extraCount = Math.max(0, members.length - visibleMembers.length);

    return `    
        ${extraCount > 0 ? `<span class="chat-history-avatar-count">+${extraCount}</span>` : ''}
        <span class="chat-history-avatars">
            ${visibleMembers.map(member => renderMemberAvatar('chat-history-avatar', member)).join('')}
        </span>
    `;
}


function renderHeaderAvatars(chat) {
    const chatId = String(chat?.id || chat?.chat_id || '').trim();
    const members = getChatMemberEntries(chat);

    if (members.length && chatId) {
        chatMemberEntriesCache[chatId] = members;
    }

    if (!members.length) {
        return '';
    }

    const visibleMembers = members.slice(0, 2);
    const extraCount = Math.max(0, members.length - visibleMembers.length);

    return `
        ${visibleMembers.map(member => renderMemberAvatar('chat-header-avatar', member)).join('')}
        ${extraCount > 0 ? `<span class="chat-header-avatar-count">+${extraCount}</span>` : ''}
    `;
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

function formatOpenIssueDate(value = '') {
    if (!value) return '';

    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';

    return d.toLocaleDateString([], {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function getOpenIssueTypeLabel(type = 1) {
    const labels = {
        1: 'Pinned',
        2: 'Unanswered',
        3: 'Action',
        4: 'Important',
        5: 'Follow-up'
    };
    return labels[Number(type) || 1] || 'Pinned';
}

function getOpenIssueDisplayText(item = {}) {
    return String(item?.text || '').trim() || 'Needs attention';
}

async function saveChatWritingPersonality(chatId, value) {
    const key = String(chatId || '').trim();
    if (!key) return;
    try {
        const data = await api('update_chat_writing_personality', {
            chat_id: key,
            writing_personality: value || ''
        });
        if (data.ok) {
            if (currentChatMeta && String(currentChatMeta.id || '').trim() === key) {
                if (value) {
                    currentChatMeta.writing_personality = value;
                } else {
                    delete currentChatMeta.writing_personality;
                }
            }
            if (chatDetailsModalCache[key]?.chat) {
                if (value) {
                    chatDetailsModalCache[key].chat.writing_personality = value;
                } else {
                    delete chatDetailsModalCache[key].chat.writing_personality;
                }
            }
        }
    } catch (err) {
        console.error('Failed to save writing personality', err);
    }
}

async function renameChat(chatId, nextTitle = null) {
    const chat = (chatListCache || []).find(item => String(item.id || '') === String(chatId));
    const currentTitle = String(chat?.title || 'New chat');

    const rawTitle = nextTitle === null ? window.prompt('Rename chat', currentTitle) : nextTitle;
    if (rawTitle === null) return;

    const cleanedTitle = String(rawTitle || '').trim();
    if (!cleanedTitle) return;

    const data = await api('rename_chat', {
        chat_id: chatId,
        title: cleanedTitle
    });

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
    const data = await api('restore_chat', {
        chat_id: chatId
    });

    if (!data?.ok) {
        alert(data?.error || 'Could not restore chat');
        return;
    }

    await refreshChatList(data.current_chat_id || chatId);
    await loadChat(data.current_chat_id || chatId);
}


function getChatCompassMeta(chatId) {
    return chatCompassMetaCache[String(chatId || '')] || null;
}

function getChatSummaryPreview(chatId) {
    const meta = getChatCompassMeta(chatId);
    return String(meta?.summary || '').trim();
}

function getChatIssueCount(chatId) {
    const meta = getChatCompassMeta(chatId);
    return Number(meta?.issueCount || 0);
}

function getChatPinnedCount(chatId) {
    const meta = getChatCompassMeta(chatId);
    return Number(meta?.pinnedCount || 0);
}

function renderChatCompassStatus(chatId) {
    const meta = getChatCompassMeta(chatId);
    const issueCount = Number(meta?.issueCount || 0);
    const badgeClass = issueCount > 0 ? 'has-issues' : 'no-issues';
    const badgeText = issueCount > 0 ? String(issueCount) : '0';
    const badgeLabel = issueCount > 0
        ? `${issueCount} open ${issueCount === 1 ? 'issue' : 'issues'}`
        : 'No open issues';

    return `
        <span class="chat-history-status-wrap" data-chat-status-wrap="${escapeHtml(String(chatId || ''))}">
            <button type="button" class="chat-history-status-badge ${badgeClass}" data-action="open-chat-details" data-chat-id="${escapeHtml(String(chatId || ''))}" aria-label="${escapeHtml(badgeLabel)}">${escapeHtml(badgeText)}</button>
            <button type="button" class="chat-history-status-toggle" data-action="open-chat-details" data-chat-id="${escapeHtml(String(chatId || ''))}" aria-label="Open chat details">⋮</button>
        </span>
    `;
}

async function fetchChatCompassMeta(chatId, force = false) {
    const key = String(chatId || '').trim();
    if (!key) return null;
    if (!force && chatCompassMetaCache[key]?.loaded) return chatCompassMetaCache[key];

    try {
       const [issuesData, memoryData] = await Promise.all([
            api('get_chat_open_issues', {
                chat_id: key,
                offset: 0,
                limit: 50
            }),
            api('get_chat_memory', {
                chat_id: key
            })
        ]);
        
        const latest = memoryData?.summary_blocks?.slice(-1)?.[0] || null;
        const topics = Array.isArray(latest?.topics) ? latest.topics.filter(Boolean) : [];
        const summary = topics.slice(0, 3).join(' · ');
        const issueItems = Array.isArray(issuesData?.items) ? issuesData.items : [];
        const pinnedCount = issueItems.filter(item => Number(item?.open_issue_type || item?.type || 0) === 1).length;

        chatCompassMetaCache[key] = {
            loaded: true,
            issueCount: Number(issuesData?.total || issuesData?.count || (Array.isArray(issuesData?.items) ? issuesData.items.length : 0) || 0),
            pinnedCount,
            summary,
            summaryTopics: topics.slice(0, 3)
        };
    } catch (e) {
        chatCompassMetaCache[key] = {
            loaded: true,
            issueCount: 0,
            pinnedCount: 0,
            summary: '',
            summaryTopics: []
        };
    }

    return chatCompassMetaCache[key];
}

function closeChatSummaryPopovers(exceptChatId = '') {
    document.querySelectorAll('.chat-history-summary-popover').forEach(pop => {
        const chatId = String(pop.dataset.chatId || '');
        if (exceptChatId && chatId === String(exceptChatId)) return;
        pop.remove();
    });

    activeChatSummaryPopoverId = exceptChatId ? String(exceptChatId) : '';
}

async function toggleChatSummary(chatId) {
    const key = String(chatId || '').trim();
    if (!key) return;

    const btn = document.querySelector(`[data-action="toggle-chat-summary"][data-chat-id="${CSS.escape(key)}"]`);
    if (!btn) return;

    if (activeChatSummaryPopoverId === key) {
        closeChatSummaryPopovers();
        return;
    }

    closeChatSummaryPopovers();

    const rect = btn.getBoundingClientRect();

    const pop = document.createElement('div');
    pop.className = 'chat-history-summary-popover is-loading';
    pop.dataset.chatId = key;
    pop.innerHTML = '<div class="chat-history-summary-title">Summary</div>Loading…';

    pop.style.position = 'fixed';
    pop.style.top = (rect.bottom + 8) + 'px';
    pop.style.left = Math.max(12, rect.right - 220) + 'px';

    document.body.appendChild(pop);
    activeChatSummaryPopoverId = key;

    const meta = await fetchChatCompassMeta(key);
    if (!pop.isConnected) return;

    const summary = String(meta?.summary || '').trim();
    pop.classList.remove('is-loading');
    pop.innerHTML =
        `<div class="chat-history-summary-title">Summary</div>${
            summary ? escapeHtml(summary) : '<div class="chat-history-summary-empty">No summary yet</div>'
        }`;

    const popRect = pop.getBoundingClientRect();
    const maxLeft = window.innerWidth - popRect.width - 12;
    pop.style.left = Math.max(12, Math.min(parseFloat(pop.style.left), maxLeft)) + 'px';
}

async function openChatCompass(chatId) {
    return openChatDetailsModal(chatId);
}

async function toggleChatSummary(chatId) {
    return openChatDetailsModal(chatId);
}

function getChatDetailsSourceChat(chatId = '') {
    const key = String(chatId || '').trim();
    if (!key) return null;

    if (currentChatMeta && String(currentChatMeta?.id || '').trim() === key) {
        return currentChatMeta;
    }

    return (
        chatListCache.find(chat => String(chat?.id || '').trim() === key)
        || (Array.isArray(archivedChatListCache)
            ? archivedChatListCache.find(chat => String(chat?.id || '').trim() === key)
            : null)
    );
}

function getChatDetailsParticipantsText(chat = null) {
    const names = getChatMemberEntries(chat)
        .map(member => String(member?.name || '').trim())
        .filter(Boolean);

    if (names.length) {
        return names.join(', ');
    }

    const participantCount = Array.isArray(chat?.participant_ids)
        ? chat.participant_ids.length
        : 0;

    return participantCount > 0 ? `${participantCount} participants` : 'No participants';
}

function getChatDetailsSummaryText(chat = null, memoryData = null) {
    const latest = memoryData?.summary_blocks?.slice(-1)?.[0] || null;
    const topics = Array.isArray(latest?.topics) ? latest.topics.filter(Boolean) : [];

    if (topics.length) {
        return topics.slice(0, 3).join(' · ');
    }

    const summaryText = String(chat?.summary?.text || '').trim();
    if (summaryText) {
        return summaryText;
    }

    return buildFallbackSummary(Array.isArray(chat?.messages) ? chat.messages : []);
}

function renderChatDetailsList(listEl, items = [], emptyText = 'Nothing here yet') {
    if (!listEl) return;

    listEl.innerHTML = '';

    if (!Array.isArray(items) || !items.length) {
        listEl.innerHTML = `<div class="global-action global-action-muted">${escapeHtml(emptyText)}</div>`;
        return;
    }

    items.forEach(item => {
        if (!item) return;
        if (item.nodeType === 1) {
            listEl.appendChild(item);
            return;
        }

        const el = document.createElement('div');
        el.className = String(item.className || 'chat-details-topic-chip');
        el.textContent = String(item.text || item || '');
        listEl.appendChild(el);
    });
}

function renderChatDetailsIssueSection(listEl, items = [], emptyText = 'Nothing here yet', chatId = '') {
    if (!listEl) return;

    listEl.innerHTML = '';

    if (!Array.isArray(items) || !items.length) {
        listEl.innerHTML = `<div class="global-action global-action-muted">${escapeHtml(emptyText)}</div>`;
        return;
    }

    const grouped = {};
    items.forEach(item => {
        const type = Number(item?.type || 1);
        if (!grouped[type]) grouped[type] = [];
        grouped[type].push(item);
    });

    const titles = {
        1: 'Reminders',
        2: 'Unanswered questions',
        3: 'Assistant actions',
        4: 'Important',
        5: 'Follow-up'
    };

    Object.keys(grouped).sort().forEach(typeKey => {
        const type = Number(typeKey || 1);

        const header = document.createElement('div');
        header.className = 'compass-chat-title';
        header.textContent = titles[type] || 'Open issues';
        listEl.appendChild(header);

        grouped[type].forEach(item => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chat-details-issue-item compass-click open-issue-item open-issue-item-type-' + type;

            const meta = document.createElement('div');
            meta.className = 'open-issue-meta';
            meta.textContent = getOpenIssueTypeLabel(type) + ' · ' + formatOpenIssueDate(item?.time || '');

            const text = document.createElement('div');
            text.className = 'open-issue-text';
            text.textContent = getOpenIssueDisplayText(item);

            btn.appendChild(meta);
            btn.appendChild(text);

            btn.addEventListener('click', async function () {
                await openCompassItem(item.chat_id || chatId || currentChatId, item.message_id || '');
                closeChatDetailsModal();
                closeSidebarOnMobile();
            });

            listEl.appendChild(btn);
        });
    });
}

function renderChatDetailsModalContent(chatId = '', chat = null, memoryData = null, issuesData = null) {
    const key = String(chatId || '').trim();
    const panel = document.getElementById('chatDetailsAccordion');
    if (!panel || !key) return;

    const resolvedChat = chat || getChatDetailsSourceChat(key) || { id: key, title: 'Chat details' };
    const summaryList = document.getElementById('chatDetailsSummaryList');
    const pinsList = document.getElementById('chatDetailsPinsList');
    const remindersList = document.getElementById('chatDetailsRemindersList');
    const memoryList = document.getElementById('chatDetailsMemoryList');
    const titleEl = document.getElementById('chatDetailsTitle');
    const subtitleEl = document.getElementById('chatDetailsSubtitle');
    const summaryTextEl = document.getElementById('chatDetailsSummaryText');

    if (titleEl) titleEl.textContent = String(resolvedChat?.title || 'Chat details');
    if (subtitleEl) subtitleEl.textContent = getChatDetailsParticipantsText(resolvedChat);

    const personalityEl = document.getElementById('chatDetailsPersonality');
    if (personalityEl) personalityEl.value = String(resolvedChat?.writing_personality || '');

    const latest = memoryData?.summary_blocks?.slice(-1)?.[0] || null;
    const topics = Array.isArray(latest?.topics) ? latest.topics.filter(Boolean).slice(0, 3) : [];
    const summaryText = getChatDetailsSummaryText(resolvedChat, memoryData);
    if (summaryTextEl) summaryTextEl.textContent = summaryText || 'No summary yet';

    const stable = memoryData?.stable_memory || {};
    const memoryItems = [];
    ['priority', 'facts', 'people', 'open_loops'].forEach(section => {
        (stable[section] || []).forEach(item => {
            const text = typeof item === 'string' ? item : String(item?.text || '').trim();
            if (text) memoryItems.push(text);
        });
    });

    const issues = Array.isArray(issuesData?.items) ? issuesData.items : [];
    const pinnedItems = issues.filter(item => Number(item?.type || 1) === 1);
    const reminderItems = issues.filter(item => Number(item?.type || 1) !== 1);

    renderChatDetailsList(summaryList, topics.length ? topics.map(topic => ({ text: topic, className: 'global-action global-action-compact' })) : [], 'No summary yet');

    if (pinsList) {
        pinsList.innerHTML = '';
        if (!pinnedItems.length) {
            pinsList.innerHTML = '<div class="global-action global-action-muted">No pinned messages yet</div>';
        } else {
            pinnedItems.forEach(item => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'global-action global-action-compact compass-click open-issue-item open-issue-item-type-1';
                btn.innerHTML = `
                    <div class="open-issue-meta">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pin-angle" viewBox="0 0 16 16">
                        <path d="M9.828.722a.5.5 0 0 1 .354.146l4.95 4.95a.5.5 0 0 1 0 .707c-.48.48-1.072.588-1.503.588-.177 0-.335-.018-.46-.039l-3.134 3.134a6 6 0 0 1 .16 1.013c.046.702-.032 1.687-.72 2.375a.5.5 0 0 1-.707 0l-2.829-2.828-3.182 3.182c-.195.195-1.219.902-1.414.707s.512-1.22.707-1.414l3.182-3.182-2.828-2.829a.5.5 0 0 1 0-.707c.688-.688 1.673-.767 2.375-.72a6 6 0 0 1 1.013.16l3.134-3.133a3 3 0 0 1-.04-.461c0-.43.108-1.022.589-1.503a.5.5 0 0 1 .353-.146m.122 2.112v-.002zm0-.002v.002a.5.5 0 0 1-.122.51L6.293 6.878a.5.5 0 0 1-.511.12H5.78l-.014-.004a5 5 0 0 0-.288-.076 5 5 0 0 0-.765-.116c-.422-.028-.836.008-1.175.15l5.51 5.509c.141-.34.177-.753.149-1.175a5 5 0 0 0-.192-1.054l-.004-.013v-.001a.5.5 0 0 1 .12-.512l3.536-3.535a.5.5 0 0 1 .532-.115l.096.022c.087.017.208.034.344.034q.172.002.343-.04L9.927 2.028q-.042.172-.04.343a1.8 1.8 0 0 0 .062.46z"></path>
                    </svg> · ${escapeHtml(formatOpenIssueDate(item?.time || ''))}</div>
                    <div class="open-issue-text">${escapeHtml(getOpenIssueDisplayText(item))}</div>
                `;
                btn.addEventListener('click', async function () {
                    await openCompassItem(item.chat_id || key, item.message_id || '');
                    closeSidebarOnMobile();
                });
                pinsList.appendChild(btn);
            });
        }
    }

    renderChatDetailsIssueSection(remindersList, reminderItems, 'No reminders or open loops yet', key);

    if (memoryList) {
        memoryList.innerHTML = '';
        const uniqueMemoryItems = [...new Set(memoryItems)].slice(0, 6);

        if (!uniqueMemoryItems.length) {
            memoryList.innerHTML = '<div class="global-action global-action-muted">No memory yet</div>';
        } else {
            uniqueMemoryItems.forEach(text => {
                const el = document.createElement('div');
                el.className = 'global-action global-action-compact global-action-soft';
                el.textContent = text;
                memoryList.appendChild(el);
            });
        }
    }

    chatCompassMetaCache[key] = {
        ...(chatCompassMetaCache[key] || {}),
        loaded: true,
        issueCount: issues.length,
        pinnedCount: pinnedItems.length,
        summary: summaryText || '',
        summaryTopics: topics
    };

    if (String(currentChatId || '').trim() === key) {
        currentChatOpenIssues = {};
        issues.forEach(item => {
            const messageId = String(item?.message_id || '').trim();
            if (!messageId) return;
            currentChatOpenIssues[messageId] = {
                text: String(item?.text || '').trim(),
                type: Number(item?.type || 1)
            };
        });

        document.querySelectorAll('.message-wrap[data-message-id]').forEach(wrap => {
            const messageId = String(wrap.dataset.messageId || '').trim();
            setWrapOpenIssueState(wrap, !!currentChatOpenIssues[messageId]);
        });

        updateChatRowCompassStatus(key);
    }

    chatDetailsModalCache[key] = {
        ...(chatDetailsModalCache[key] || {}),
        chat: resolvedChat,
        memoryData: memoryData || chatDetailsModalCache[key]?.memoryData || null,
        issuesData: issuesData || chatDetailsModalCache[key]?.issuesData || null
    };
}

async function loadChatDetailsModalData(chatId = '', force = false) {
    const key = String(chatId || '').trim();
    if (!key) return;

    const seq = (chatDetailsModalRequestSeq = (chatDetailsModalRequestSeq || 0) + 1);
    const cached = chatDetailsModalCache[key] || {};

    const needsMemory = force || !cached.memoryData;
    const needsIssues = force || !cached.issuesData;

    const fetches = [];
    if (needsMemory) {fetches.push(api('get_chat_memory', {chat_id: key}).then(data => ({kind: 'memory',data})));}
    if (needsIssues) {fetches.push(api('get_chat_open_issues', {chat_id: key,offset: 0,limit: 20}).then(data => ({kind: 'issues',data})));}

    if (!fetches.length) {
        renderChatDetailsModalContent(key, cached.chat || null, cached.memoryData || null, cached.issuesData || null);
        return;
    }

    const results = await Promise.all(fetches);
    if (seq !== chatDetailsModalRequestSeq || activeChatDetailsModalChatId !== key) return;

    const nextCache = { ...(chatDetailsModalCache[key] || {}), chat: getChatDetailsSourceChat(key) || cached.chat || null };
    let memoryData = cached.memoryData || null;
    let issuesData = cached.issuesData || null;

    results.forEach(result => {
        if (result.kind === 'memory') memoryData = result.data || null;
        if (result.kind === 'issues') issuesData = result.data || null;
    });

    nextCache.memoryData = memoryData;
    nextCache.issuesData = issuesData;
    chatDetailsModalCache[key] = nextCache;

    renderChatDetailsModalContent(key, nextCache.chat || null, memoryData, issuesData);
}

async function openChatDetailsModal(chatId = currentChatId, force = false) {
    const key = String(chatId || currentChatId || '').trim();
    if (!key) return;

    if (activeChatDetailsModalChatId === key && !force) {
        closeChatDetailsModal();
        return;
    }

    closeChatSummaryPopovers();

    activeChatDetailsModalChatId = key;
    const chat = getChatDetailsSourceChat(key) || { id: key, title: 'Chat details' };

    renderChatList(chatListCache, currentChatId, archivedChatListCache);

    const cached = chatDetailsModalCache[key] || {};
    renderChatDetailsModalContent(key, cached.chat || chat, cached.memoryData || null, cached.issuesData || null);

    await loadChatDetailsModalData(key, force);
}

function closeChatDetailsModal() {
    activeChatDetailsModalChatId = '';
    renderChatList(chatListCache, currentChatId, archivedChatListCache);
}

function scrollChatDetailsSection(sectionId = '') {
    const el = document.getElementById(String(sectionId || '').trim());
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function getSidebarScrollContainer() {
    return document.getElementById('sidebarChatsPanel') || document.getElementById('sidebar');
}

function getChatRowSignature(chat = {}) {
    const participantIds = Array.isArray(chat?.participant_ids) ? chat.participant_ids.join(',') : '';
    const participantNames = Array.isArray(chat?.participant_names) ? chat.participant_names.join(',') : '';
    const participants = Array.isArray(chat?.participants)
        ? chat.participants.map(participant => [
            String(participant?.contact_id || participant?.id || '').trim(),
            String(participant?.display_name || participant?.name || participant?.email || '').trim(),
            String(participant?.avatar || '').trim(),
            String(participant?.initials || '').trim()
        ].join(':')).join('|')
        : '';

    return [
        String(chat?.updated_at || '').trim(),
        String(chat?.title || '').trim(),
        String(chat?.preview || '').trim(),
        String(chat?.unread_count || 0),
        String(chat?.search_message_id || '').trim(),
        participantIds,
        participantNames,
        participants,
        String(chat?.chat_state || chat?.state || '').trim()
    ].join('||');
}

function patchChatRowElement(row, chat = {}, activeChatId = null, isArchived = false) {
    if (!row) return false;

    const chatId = String(chat.id || chat.chat_id || '').trim();
    const isDetailsOpen = !isArchived && String(activeChatDetailsModalChatId || '') === chatId;
    const active = String(chatId) === String(activeChatId || '');
    const titleText = String(chat.title || 'New chat');
    const previewText = String(chat.preview || 'No messages yet');
    const updatedAtText = formatChatListTime(chat.updated_at || '');
    const unreadCount = Math.max(0, Number(chat.unread_count || 0));
    const avatarsHtml = renderChatListAvatars(chat);

    const restoreIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 36 36"><title xmlns="">Restore</title><path fill="currentColor" d="M28 8H14a2 2 0 0 0-2 2v2h2v-2h14v10h-2v2h2a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2" class="clr-i-outline clr-i-outline-path-1"/><path fill="currentColor" d="M22 14H8a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V16a2 2 0 0 0-2-2M8 26V16h14v10Z" class="clr-i-outline clr-i-outline-path-2"/><path fill="none" d="M0 0h36v36H0z"/></svg>`;
    const deleteIcon  = `<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 40 40"><title xmlns="">Delete</title><path fill="currentColor" d="M21.499 19.994L32.755 8.727a1.064 1.064 0 0 0-.001-1.502c-.398-.396-1.099-.398-1.501.002L20 18.494L8.743 7.224c-.4-.395-1.101-.393-1.499.002a1.05 1.05 0 0 0-.309.751c0 .284.11.55.309.747L18.5 19.993L7.245 31.263a1.064 1.064 0 0 0 .003 1.503c.193.191.466.301.748.301h.006c.283-.001.556-.112.745-.305L20 21.495l11.257 11.27c.199.198.465.308.747.308a1.06 1.06 0 0 0 1.061-1.061c0-.283-.11-.55-.31-.747z"/></svg>`;


    const actionButtonsHtml = isArchived
        ? ` <button class="chat-history-icon-btn" type="button" title="Restore" aria-label="Restore" data-action="restore-chat" data-chat-id="${escapeHtml(chatId)}">${restoreIcon}</button>
            <button class="chat-history-icon-btn" type="button" title="Delete permanently" aria-label="Delete permanently" data-action="delete-chat-permanently" data-chat-id="${escapeHtml(chatId)}">${deleteIcon}</button>
            `
        : isDetailsOpen ? '' : `
            <button class="chat-history-menu-btn" type="button" title="Open details" aria-label="Open details" data-action="open-chat-details" data-chat-id="${escapeHtml(chatId)}">⋮</button>
            `;

    row.classList.toggle('active', active);
    row.dataset.chatId = chatId;
    row.dataset.chatState = isArchived ? 'archived' : 'active';

    const mainButton = row.querySelector('.chat-history-main');
    if (mainButton) {
        mainButton.dataset.chatId = chatId;
        mainButton.dataset.messageId = String(chat.search_message_id || '');
    }

    const avatarsEl = row.querySelector('.chat-history-avatars');
    if (avatarsEl && avatarsEl.innerHTML !== avatarsHtml) {
        avatarsEl.innerHTML = avatarsHtml;
    }

    const titleEl = row.querySelector('.chat-history-title');
    if (titleEl && titleEl.textContent !== titleText) {
        titleEl.textContent = titleText;
    }

    const subEl = row.querySelector('.chat-history-sub');
    if (subEl && subEl.textContent !== previewText) {
        subEl.textContent = previewText;
    }

    const timeEl = row.querySelector('.chat-history-time');
    if (timeEl && timeEl.textContent !== updatedAtText) {
        timeEl.textContent = updatedAtText;
    }

    const badgeWrapEl = row.querySelector('.chat-history-badge-wrap');
    if (badgeWrapEl) {
        const nextBadge = unreadCount > 0 ? formatUnreadBadge(unreadCount) : '';
        if (badgeWrapEl.innerHTML !== nextBadge) {
            badgeWrapEl.innerHTML = nextBadge;
        }
    }

    const metaEl = row.querySelector('.chat-history-meta');
    if (metaEl) {
        const nextMeta = `<span class="chat-history-time">${escapeHtml(updatedAtText)}</span>`;
        if (metaEl.innerHTML !== nextMeta) {
            metaEl.innerHTML = nextMeta;
        }
    }

    const actionsEl = row.querySelector('.chat-history-actions');
    if (actionsEl && actionsEl.innerHTML !== actionButtonsHtml) {
        actionsEl.innerHTML = actionButtonsHtml;
    }

    const detailsAccordion = row.querySelector('.chat-details-accordion');
    if (!isDetailsOpen && detailsAccordion) {
        detailsAccordion.remove();
        return true;
    }

    if (isDetailsOpen) {
        const titleInput = row.querySelector('#chatDetailsTitle');
        if (titleInput && document.activeElement !== titleInput && titleInput.value !== titleText) {
            titleInput.value = titleText;
        }

        const subtitleEl = row.querySelector('#chatDetailsSubtitle');
        if (subtitleEl) {
            const subtitleText = getChatDetailsParticipantsText(chat);
            if (subtitleEl.textContent !== subtitleText) {
                subtitleEl.textContent = subtitleText;
            }
        }
    }

    return true;
}

function syncChatListSection(listEl, nextChats = [], activeChatId = null, isArchived = false, previousChats = []) {
    if (!listEl) return false;

    const previousMap = new Map((Array.isArray(previousChats) ? previousChats : []).map(chat => [String(chat?.id || chat?.chat_id || '').trim(), chat]).filter(([id]) => !!id));
    const desiredIds = new Set();
    const previousScrollTop = getSidebarScrollContainer()?.scrollTop ?? null;
    let changed = false;

    if (!nextChats.length) {
        const rows = listEl.querySelectorAll('.chat-history-item[data-chat-id]');
        if (rows.length) {
            listEl.innerHTML = '';
            return true;
        }
        return false;
    }

    const rowsById = new Map();
    listEl.querySelectorAll('.chat-history-item[data-chat-id]').forEach(row => {
        rowsById.set(String(row.dataset.chatId || ''), row);
    });

    if (!rowsById.size) {
        listEl.innerHTML = renderChatSectionItems(nextChats, activeChatId, isArchived);
        return true;
    }

    nextChats.forEach((chat, index) => {
        const chatId = String(chat?.id || chat?.chat_id || '').trim();
        if (!chatId) return;
        desiredIds.add(chatId);

        const row = rowsById.get(chatId);
        const prev = previousMap.get(chatId) || null;
        const current = row || null;
        const active = String(chatId) === String(activeChatId || '');
        const currentSignature = getChatRowSignature(chat);
        const previousSignature = prev ? getChatRowSignature(prev) : '';
        const needsPatch = !current || !prev || currentSignature !== previousSignature || current.classList.contains('active') !== active || String(current?.dataset?.chatState || '') !== (isArchived ? 'archived' : 'active');

        let targetRow = current;
        if (!targetRow) {
            const template = document.createElement('template');
            template.innerHTML = renderChatSectionItems([chat], activeChatId, isArchived).trim();
            targetRow = template.content.firstElementChild;
            if (targetRow) {
                const beforeNode = listEl.children[index] || null;
                listEl.insertBefore(targetRow, beforeNode);
                changed = true;
            }
            return;
        }

        if (needsPatch && patchChatRowElement(targetRow, chat, activeChatId, isArchived)) {
            changed = true;
        }

        const beforeNode = listEl.children[index] || null;
        if (targetRow !== beforeNode) {
            listEl.insertBefore(targetRow, beforeNode);
            changed = true;
        }
    });

    listEl.querySelectorAll('.chat-history-item[data-chat-id]').forEach(row => {
        const id = String(row.dataset.chatId || '').trim();
        if (!id || desiredIds.has(id)) return;
        row.remove();
        changed = true;
    });

    if (changed && previousScrollTop !== null) {
        const scrollContainer = getSidebarScrollContainer();
        if (scrollContainer) {
            scrollContainer.scrollTop = previousScrollTop;
        }
    }

    return changed;
}

function applyChatIndexUpdate(data = {}, options = {}) {
    const previousActiveChats = Array.isArray(chatListCache) ? chatListCache.slice() : [];
    const previousArchivedChats = Array.isArray(archivedChatListCache) ? archivedChatListCache.slice() : [];
    const nextActiveChats = Array.isArray(data?.chats) ? data.chats : [];
    const nextArchivedChats = Array.isArray(data?.archived_chats) ? data.archived_chats : [];
    const preferredChatId = String(options?.preferredChatId || data?.current_chat_id || currentChatId || '').trim();
    const forceFullRender = !!options?.forceFullRender;

    chatListCache = nextActiveChats;
    archivedChatListCache = nextArchivedChats;

    if (preferredChatId) {
        currentChatId = preferredChatId;
    }

    if (currentChatMeta && preferredChatId && String(currentChatMeta?.id || currentChatMeta?.chat_id || '').trim() === preferredChatId) {
        const currentIndexChat = (chatListCache || []).find(chat => String(chat?.id || chat?.chat_id || '').trim() === preferredChatId)
            || (archivedChatListCache || []).find(chat => String(chat?.id || chat?.chat_id || '').trim() === preferredChatId)
            || null;

        if (currentIndexChat) {
            currentChatMeta = {
                ...currentChatMeta,
                title: String(currentIndexChat?.title || currentChatMeta.title || 'New chat'),
                updated_at: String(currentIndexChat?.updated_at || currentChatMeta.updated_at || ''),
                preview: String(currentIndexChat?.preview || currentChatMeta.preview || ''),
                unread_count: currentIndexChat?.unread_count ?? currentChatMeta.unread_count,
                participant_ids: Array.isArray(currentIndexChat?.participant_ids) ? currentIndexChat.participant_ids : (currentChatMeta.participant_ids || []),
                participant_names: Array.isArray(currentIndexChat?.participant_names) ? currentIndexChat.participant_names : (currentChatMeta.participant_names || []),
                participant_snapshots: currentIndexChat?.participant_snapshots && typeof currentIndexChat.participant_snapshots === 'object'
                    ? currentIndexChat.participant_snapshots
                    : (currentChatMeta.participant_snapshots || {}),
                participants: Array.isArray(currentIndexChat?.participants) ? currentIndexChat.participants : (currentChatMeta.participants || [])
            };
            updateHeaderForChat(currentChatMeta);
        }
    }

    const list = document.getElementById('chatList');
    const archivedList = document.getElementById('archivedChatList');
    const archivedSection = document.getElementById('archivedSection');
    const archivedJumpBtn = document.getElementById('archivedJumpBtn');
    const activeDetailChatId = String(activeChatDetailsModalChatId || '').trim();

    if (activeDetailChatId) {
        const stillVisible = nextActiveChats.some(chat => String(chat?.id || chat?.chat_id || '').trim() === activeDetailChatId);
        if (!stillVisible) {
            closeChatDetailsModal();
        }
    }

    if (forceFullRender || !list) {
        renderChatList(chatListCache, currentChatId, archivedChatListCache);
        return;
    }

    const activeChanged = syncChatListSection(list, nextActiveChats, currentChatId, false, previousActiveChats);
    const archivedChanged = syncChatListSection(archivedList, nextArchivedChats, currentChatId, true, previousArchivedChats);

    if (archivedSection) {
        archivedSection.classList.toggle('hidden', !archivedChatListCache.length);
    }

    if (archivedJumpBtn) {
        archivedJumpBtn.classList.toggle('hidden', !archivedChatListCache.length);
        archivedJumpBtn.textContent = archivedChatListCache.length
            ? `Archived (${archivedChatListCache.length})`
            : 'Archived';
    }

    if (activeChanged || archivedChanged) {
        placeCompassInContext(currentChatId);
    }
}

function updateChatRowCompassStatus(chatId) {
    const badge = document.querySelector(`.chat-history-status-badge[data-chat-id="${CSS.escape(String(chatId || ''))}"]`);
    if (!badge) return;

    const issueCount = getChatIssueCount(chatId);
    badge.textContent = issueCount > 0 ? String(issueCount) : '0';
    badge.classList.remove('has-issues', 'no-issues');
    badge.classList.add(issueCount > 0 ? 'has-issues' : 'no-issues');
    badge.setAttribute('aria-label', issueCount > 0 ? `${issueCount} open ${issueCount === 1 ? 'issue' : 'issues'}` : 'No open issues');
}

function renderChatSectionItems(chats, activeChatId = null, isArchived = false) {
    return chats.map(chat => {
        const isActive = chat.id === activeChatId ? 'active' : '';
        const chatId = String(chat.id || '');
        const isDetailsOpen = !isArchived && String(activeChatDetailsModalChatId || '') === chatId;
        const title = escapeHtml(chat.title || 'New chat');
        const avatarsHtml = renderChatListAvatars(chat);
        const updatedAt = escapeHtml(formatChatListTime(chat.updated_at || ''));
        const unreadHtml = Number(chat.unread_count || 0) > 0
            ? formatUnreadBadge(chat.unread_count || 0)
            : '';
        const metaLine = escapeHtml(chat.preview || 'No messages yet');

        const restoreIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 36 36"><title xmlns="">Restore</title><path fill="currentColor" d="M28 8H14a2 2 0 0 0-2 2v2h2v-2h14v10h-2v2h2a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2" class="clr-i-outline clr-i-outline-path-1"/><path fill="currentColor" d="M22 14H8a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V16a2 2 0 0 0-2-2M8 26V16h14v10Z" class="clr-i-outline clr-i-outline-path-2"/><path fill="none" d="M0 0h36v36H0z"/></svg>`;
        const deleteIcon  = `<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 40 40"><title xmlns="">Delete</title><path fill="currentColor" d="M21.499 19.994L32.755 8.727a1.064 1.064 0 0 0-.001-1.502c-.398-.396-1.099-.398-1.501.002L20 18.494L8.743 7.224c-.4-.395-1.101-.393-1.499.002a1.05 1.05 0 0 0-.309.751c0 .284.11.55.309.747L18.5 19.993L7.245 31.263a1.064 1.064 0 0 0 .003 1.503c.193.191.466.301.748.301h.006c.283-.001.556-.112.745-.305L20 21.495l11.257 11.27c.199.198.465.308.747.308a1.06 1.06 0 0 0 1.061-1.061c0-.283-.11-.55-.31-.747z"/></svg>`;

        const actionButtons = isArchived
            ? `
                <button class="chat-history-icon-btn" type="button" title="Restore" aria-label="Restore" data-action="restore-chat" data-chat-id="${escapeHtml(chatId)}">${restoreIcon}</button>
                <button class="chat-history-icon-btn" type="button" title="Delete permanently" aria-label="Delete permanently" data-action="delete-chat-permanently" data-chat-id="${escapeHtml(chatId)}">${deleteIcon}</button>
            `
            : `
                <button class="chat-history-menu-btn" type="button" title="${isDetailsOpen ? 'Close details' : 'Open details'}" aria-label="${isDetailsOpen ? 'Close details' : 'Open details'}" data-action="open-chat-details" data-chat-id="${escapeHtml(chatId)}">⋮</button>
            `;

        const detailsAccordion = isDetailsOpen ? `
                <div class="chat-details-accordion" id="chatDetailsAccordion" data-chat-id="${escapeHtml(chatId)}">
                    <div class="chat-details-accordion-head">
                        <div class="chat-details-title-group">
                            <input class="chat-details-title-input" id="chatDetailsTitle" type="text" value="${escapeHtml(String(chat.title || 'Chat details'))}" onblur="renameChat(activeChatDetailsModalChatId, this.value)" maxlength="160" autocomplete="off">
                            <div class="chat-details-personality-row">
                                <select id="chatDetailsPersonality" class="chat-details-personality-select" onchange="saveChatWritingPersonality(activeChatDetailsModalChatId, this.value)">
                                    <option value="">Basic personality</option>
                                    <option value="corporate_friendly">Corporate &amp; friendly</option>
                                    <option value="corporate_direct">Corporate &amp; direct</option>
                                    <option value="polite_thoughtful">Polite &amp; thoughtful</option>
                                    <option value="neutral_practical">Neutral &amp; practical</option>
                                    <option value="casual_friendly">Casual &amp; friendly</option>
                                    <option value="casual_direct">Casual &amp; direct</option>
                                    <option value="playful_light">Playful &amp; light</option>
                                    <option value="bold_confident">Bold &amp; confident</option>
                                </select>
                            </div>
                            <button type="button" class="chat-details-subtitle chat-details-subtitle-link" id="chatDetailsSubtitle" onclick="openInviteModal(activeChatDetailsModalChatId)">${escapeHtml(getChatDetailsParticipantsText(chat))}</button>
                        </div>
                        <div class="chat-details-accordion-actions">
                           <button type="button" class="chat-details-accordion-close" onclick="closeChatDetailsModal()" aria-label="Close details" title="Close details">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-square" viewBox="0 0 16 16">
                                    <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"></path>
                                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"></path>
                                </svg>
                            </button><button class="message-hover-action archive-action" type="button" onclick="archiveChat(activeChatDetailsModalChatId)" aria-label="Archive chat" title="Archive chat">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-archive" viewBox="0 0 16 16">
                                    <path d="M0 2a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 1 12.5V5a1 1 0 0 1-1-1zm2 3v7.5A1.5 1.5 0 0 0 3.5 14h9a1.5 1.5 0 0 0 1.5-1.5V5zm13-3H1v2h14zM5 7.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="chat-details-anchor-chips" role="navigation" aria-label="Chat details sections">
                        <button type="button" class="chat-details-anchor-chip" onclick="scrollChatDetailsSection('chatDetailsSummaryCard')">Summary</button>
                        <button type="button" class="chat-details-anchor-chip" onclick="scrollChatDetailsSection('chatDetailsPinsCard')">Pins</button>
                        <button type="button" class="chat-details-anchor-chip" onclick="scrollChatDetailsSection('chatDetailsRemindersCard')">Reminders</button>
                        <button type="button" class="chat-details-anchor-chip" onclick="scrollChatDetailsSection('chatDetailsMemoryCard')">Memory</button>
                    </div>
                    <div class="chat-details-sections">
                        <section class="chat-details-section" id="chatDetailsSummaryCard">
                            <div class="chat-details-section-body">
                                <div class="chat-details-summary-text" id="chatDetailsSummaryText">Loading...</div>
                                <div class="global-action-list chat-details-topic-list" id="chatDetailsSummaryList">
                                    <div class="chat-details-empty">Loading summary...</div>
                                </div>
                            </div>
                        </section>
                        <section class="chat-details-section" id="chatDetailsPinsCard">
                            <div class="chat-details-section-body">
                                <div class="chat-details-item-list" id="chatDetailsPinsList">
                                    <div class="chat-details-empty">Loading pins...</div>
                                </div>
                            </div>
                        </section>
                        <section class="chat-details-section" id="chatDetailsRemindersCard">
                            <div class="chat-details-section-body">
                                <div class="chat-details-item-list" id="chatDetailsRemindersList">
                                    <div class="chat-details-empty">Loading reminders...</div>
                                </div>
                            </div>
                        </section>
                        <section class="chat-details-section" id="chatDetailsMemoryCard">
                            <div class="chat-details-section-body">
                                <div class="chat-details-memory-list" id="chatDetailsMemoryList">
                                    <div class="chat-details-empty">Loading memory...</div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
        ` : '';

        return `
            <div class="chat-history-item ${isActive}" data-chat-id="${escapeHtml(chatId)}" data-chat-state="${isArchived ? 'archived' : 'active'}">

                <button class="chat-history-main"
                        type="button"
                        data-action="open-chat"
                        data-chat-id="${escapeHtml(chatId)}"
                        data-message-id="${escapeHtml(String(chat.search_message_id || ''))}">

                    <span class="chat-history-avatar-cell">
                        ${avatarsHtml}
                        <span class="chat-history-badge-wrap">${unreadHtml}</span>
                    </span>

                    <span class="chat-history-copy">
                        <span class="chat-history-title">${title}</span>
                        <span class="chat-history-sub">${metaLine}</span>
                    </span>

                    <span class="chat-history-meta">
                        <span class="chat-history-time">${updatedAt}</span>
                    </span>
                </button>

                <div class="chat-history-actions">
                    ${actionButtons}
                </div>
                ${detailsAccordion}
            </div>
        `;
    }).join('');
}

function renderChatList(chats, activeChatId = null, archivedChats = []) {
    syncChatSearchInput();
    placeCompassInContext(null);
    const list = document.getElementById('chatList');
    const archivedList = document.getElementById('archivedChatList');
    const archivedSection = document.getElementById('archivedSection');
    const archivedJumpBtn = document.getElementById('archivedJumpBtn');

    if (!list) return;

    const activeItems = Array.isArray(chats) ? chats : [];
    const archivedItems = Array.isArray(archivedChats) ? archivedChats : [];
    const hasSearch = normalizeChatSearchValue(chatSearchQuery).length >= 2;

    if (activeItems.length) {
        list.innerHTML = renderChatSectionItems(activeItems, activeChatId, false);
    } else if (!archivedItems.length) {
        list.innerHTML = `<div class="conversation-empty">${hasSearch ? 'No matching chats' : 'No chats yet'}</div>`;
    } else {
        list.innerHTML = '';
    }

    if (archivedList) {
        archivedList.innerHTML = archivedItems.length
            ? renderChatSectionItems(archivedItems, activeChatId, true)
            : '';
    }

    if (archivedSection) {
        archivedSection.classList.toggle('hidden', !archivedItems.length);
    }

    if (archivedJumpBtn) {
        archivedJumpBtn.classList.toggle('hidden', !archivedItems.length);
        archivedJumpBtn.textContent = archivedItems.length
            ? `Archived (${archivedItems.length})`
            : 'Archived';
    }

    placeCompassInContext(activeChatId);
}

function placeCompassInContext(chatId) {
    const gc = document.getElementById('globalCompass');
    if (!gc) return;

    if (!compassRestoreParent && gc.parentNode) {
        compassRestoreParent = gc.parentNode;
        compassRestoreBefore = gc.nextSibling;
    }

    const key = String(chatId || '').trim();
    if (key && !isGuestUser()) {
        const item = document.querySelector(`.chat-history-item[data-chat-id="${CSS.escape(key)}"]`);
        if (item && item.parentNode) {
            // Only move the node if it isn't already the next sibling
            if (gc.previousSibling !== item) {
                item.parentNode.insertBefore(gc, item.nextSibling);
            }
            gc.classList.remove('hidden');
            if (key !== compassCurrentChatId) {
                compassCurrentChatId = key;
                loadSidebarMemory();
            }
            return;
        }
    }

    if (gc.parentNode) gc.parentNode.removeChild(gc);
    compassCurrentChatId = '';
    if (compassRestoreParent) {
        compassRestoreParent.insertBefore(gc, compassRestoreBefore || null);
    }
    gc.classList.add('hidden');
}

let chatListEventsBound = false;

function bindChatListEvents() {
    if (chatListEventsBound) return;
    chatListEventsBound = true;

    const lists = [
        document.getElementById('chatList'),
        document.getElementById('archivedChatList'),
        document.getElementById('chatSearchResults')
    ].filter(Boolean);

    if (!lists.length) return;

    lists.forEach(list => {
        list.addEventListener('click', async (e) => {
            const detailsBtn = e.target.closest('[data-action="open-chat-details"]');
            if (detailsBtn) {
                e.stopPropagation();
                await openChatDetailsModal(detailsBtn.dataset.chatId);
                return;
            }

            const archiveBtn = e.target.closest('[data-action="archive-chat"]');
            if (archiveBtn) {
                e.stopPropagation();
                await archiveChat(archiveBtn.dataset.chatId);
                return;
            }

            const leaveBtn = e.target.closest('[data-action="leave-chat"]');
            if (leaveBtn) {
                e.stopPropagation();
                await leaveChat(leaveBtn.dataset.chatId);
                return;
            }

            const renameBtn = e.target.closest('[data-action="rename-chat"]');
            if (renameBtn) {
                e.stopPropagation();
                await renameChat(renameBtn.dataset.chatId);
                return;
            }

            const restoreBtn = e.target.closest('[data-action="restore-chat"]');
            if (restoreBtn) {
                e.stopPropagation();
                await restoreChat(restoreBtn.dataset.chatId);
                return;
            }

            const openBtn = e.target.closest('[data-action="open-chat"]');
            if (openBtn) {
                closeChatSummaryPopovers();
                closeChatDetailsModal();

                const targetChatId = String(openBtn.dataset.chatId || '').trim();
                const targetMessageId = String(openBtn.dataset.messageId || '').trim();

                await loadChat(targetChatId, {
                    scrollToBottom: !targetMessageId,
                    focusComposer: !targetMessageId
                });

                if (targetMessageId) {
                    await jumpToMessage(targetMessageId);
                }

                closeSidebarOnMobile();
                return;
            }

            const deletePermanentBtn = e.target.closest('[data-action="delete-chat-permanently"]');
            if (deletePermanentBtn) {
                e.stopPropagation();
                await deleteChatPermanently(deletePermanentBtn.dataset.chatId);
                return;
            }
        });
    });

}

async function createNewChat(title = '') {
    const data = await api('create_chat', {
        title: String(title || '').trim()
    });

    if (data.status === 401) {
        openAuthModal('login');
        return null;
    }

    if (!data?.ok) return null;

    currentChatId = data.chat_id;
    currentChatMeta = data.chat || null;
    updateInviteButtonsVisibility(data.chat || null);

    chatHistory = [];
    renderRecipientPills();

    clearConversationUI();
    updateHeaderForChat(data.chat || { title: 'New chat', participants: [] });
    renderAssistantMessage("<b>Hmm… looks like you're alone here.</b><br>Click Invite at the top to start a conversation.", { trustedHtml: true });

    const createdChatForList = {
        ...(data.chat || {}),
        id: data.chat_id,
        title: data.chat?.title || title || 'New chat',
        updated_at: data.chat?.updated_at || ''
    };
    chatListCache = [createdChatForList, ...chatListCache.filter(chat => chat.id !== data.chat_id)];

    renderChatList(chatListCache, data.chat_id, archivedChatListCache);
    await refreshChatList(data.chat_id);
    focusInput();

    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.add('hidden');
    syncSidebarToggleButtonState();

    closeNewChatPopover();
    
    return data;
}

async function loadChat(chatId, options = {}) {
    const opts = {
        scrollToBottom: true,
        focusComposer: true,
        ...options
    };

    const data = await api('load_chat', {
        chat_id: chatId
    });

    if (!data?.ok || !data.chat) return;

    const nextChatId = String(data.chat.id || '').trim();
    const previousChatId = String(currentChatId || '').trim();
    if (nextChatId && nextChatId !== previousChatId) {
        setAssistantComposerMode('public');
    }

    currentChatId = data.chat.id;
    currentChatMeta = data.chat;
    mergeChatIntoListCaches(data.chat);
    loadedChatChunkFiles = Array.isArray(data.chat.loaded_chunk_files) ? data.chat.loaded_chunk_files.slice() : [];
    hasOlderChatChunks = !!data.chat.has_older_chunks;
    loadingOlderChatChunks = false;
    clearComposerReplyPreview();
    loadSidebarMemory();
    resetAutoDraftHandled(data.chat.id);

    updateInviteButtonsVisibility(data.chat);

    chatHistory = Array.isArray(data.chat.messages)
        ? data.chat.messages.filter(msg => msg.role === 'user').map(msg => ({
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
            (msg?.role === 'other' || msg?.role === 'user') &&
            String(msg?.user_id || '') !== String(window.currentUserId || '') &&
            String(msg?.id || '') !== ''
        )
        .map(msg => String(msg.id || ''));

    const autoDraftState = getAutoDraftState(data.chat.id);
    autoDraftState.handledMessageIds = existingIncomingIds.slice();

    await refreshChatList(currentChatId);

    if (opts.scrollToBottom) {
        scrollChatToBottom(currentChatId || true);
    }

    if (opts.focusComposer) {
        focusInput();
    }

    refreshChatMemberProfiles(currentChatId).catch(err => {
        console.debug('Could not refresh chat member profiles', err);
    });
}

async function loadOlderChatChunk() {
    if (!isLoggedIn || !currentChatId || !hasOlderChatChunks || loadingOlderChatChunks) return;
    if (!Array.isArray(loadedChatChunkFiles) || !loadedChatChunkFiles.length) return;

    const beforeChunkFile = String(loadedChatChunkFiles[0] || '').trim();
    if (!beforeChunkFile) return;

    const chatScroll = document.getElementById('chatScroll');
    const oldScrollHeight = chatScroll ? chatScroll.scrollHeight : 0;
    const oldScrollTop = chatScroll ? chatScroll.scrollTop : 0;

    loadingOlderChatChunks = true;

    try {
        const data = await api('load_chat_chunk', {
            chat_id: currentChatId,
            before_chunk_file: beforeChunkFile
        });

        if (!data?.ok) return;

        const olderMessages = Array.isArray(data.messages) ? data.messages : [];
        const loadedChunkFile = String(data.loaded_chunk_file || '').trim();
        if (!loadedChunkFile || !olderMessages.length) {
            hasOlderChatChunks = !!data?.has_older_chunks;
            return;
        }

        const currentMessages = Array.isArray(currentChatMeta?.messages) ? currentChatMeta.messages : [];
        currentChatMeta.messages = [...olderMessages, ...currentMessages];
        currentChatMeta.loaded_chunk_files = [loadedChunkFile, ...loadedChatChunkFiles];
        currentChatMeta.has_older_chunks = !!data.has_older_chunks;

        loadedChatChunkFiles = currentChatMeta.loaded_chunk_files.slice();
        hasOlderChatChunks = !!data.has_older_chunks;

        renderChatFromHistory(currentChatMeta);
        renderChatContext(currentChatMeta);
        renderHeaderParticipants(currentChatMeta);
        renderSidebarChatDetails(currentChatMeta);

        if (chatScroll) {
            const newScrollHeight = chatScroll.scrollHeight;
            chatScroll.scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);
        }
    } catch (e) {
        console.log('older chunk load error', e);
    } finally {
        loadingOlderChatChunks = false;
    }
}

function findRenderedMessageRow(messageId) {
    if (!messageId) return null;

    const wrap = document.querySelector(`.message-wrap[data-message-id="${CSS.escape(String(messageId))}"]`);
    if (wrap) {
        return wrap.closest('.message-row') || wrap.closest('.assistant-info-row') || null;
    }

    const info = document.querySelector(`.assistant-info[data-message-id="${CSS.escape(String(messageId))}"]`);
    if (info) {
        return info.closest('.assistant-info-row') || null;
    }

    return null;
}

function highlightJumpTarget(messageId) {
    const row = findRenderedMessageRow(messageId);
    if (!row) return false;

    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    setTimeout(() => {
        row.classList.add('message-jump-highlight');
        setTimeout(() => row.classList.remove('message-jump-highlight'), 1400);
    }, 450);
        
    return true;
}

async function loadChunkContainingMessage(messageId) {
    const targetId = String(messageId || '').trim();
    if (!targetId || !isLoggedIn || !currentChatId) return false;

    try {
        const data = await api('load_chat_chunk_by_message_id', {
            chat_id: currentChatId,
            message_id: targetId
        });

        if (!data?.ok) return false;

        const chunkMessages = Array.isArray(data.messages) ? data.messages : [];
        const loadedChunkFile = String(data.loaded_chunk_file || '').trim();

        if (!loadedChunkFile || !chunkMessages.length) return false;

        const currentMessages = Array.isArray(currentChatMeta?.messages) ? currentChatMeta.messages : [];
        const merged = [...chunkMessages, ...currentMessages];
        const seen = new Set();

        currentChatMeta.messages = merged.filter(msg => {
            const id = String(msg?.id || '').trim();
            if (!id || seen.has(id)) return false;
            seen.add(id);
            return true;
        }).sort((a, b) => {
            const ta = new Date(String(a?.time || a?.created_at || a?.sent_at || '').replace(' ', 'T')).getTime() || 0;
            const tb = new Date(String(b?.time || b?.created_at || b?.sent_at || '').replace(' ', 'T')).getTime() || 0;
            return ta - tb;
        });

        const knownChunkFiles = Array.isArray(loadedChatChunkFiles) ? loadedChatChunkFiles.slice() : [];
        currentChatMeta.loaded_chunk_files = knownChunkFiles.includes(loadedChunkFile)
            ? knownChunkFiles
            : [loadedChunkFile, ...knownChunkFiles];

        loadedChatChunkFiles = currentChatMeta.loaded_chunk_files.slice();
        hasOlderChatChunks = !!data.has_older_chunks;
        currentChatMeta.has_older_chunks = !!data.has_older_chunks;

        renderChatFromHistory(currentChatMeta);
        renderChatContext(currentChatMeta);
        renderHeaderParticipants(currentChatMeta);
        renderSidebarChatDetails(currentChatMeta);

        return !!findRenderedMessageRow(targetId);
    } catch (e) {
        console.log('message jump chunk load error', e);
        return false;
    }
}

function getCurrentUserMembershipPeriods(chat = null) {
    const currentUserId = String(window.currentUserId || '').trim();
    if (!chat || !currentUserId) return [];

    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object'
        ? chat.participant_snapshots
        : {};

    for (const snapshot of Object.values(snapshots)) {
        if (!snapshot || typeof snapshot !== 'object') continue;
        if (String(snapshot?.user_id || '').trim() !== currentUserId) continue;

        const raw = Array.isArray(snapshot?.membership_periods)
            ? snapshot.membership_periods
            : (Array.isArray(snapshot?.periods) ? snapshot.periods : []);

        return raw
            .map(period => {
                const startAt = String(period?.start_at || period?.from || '').trim();
                const endAt = String(period?.end_at || period?.to || '').trim();
                return {
                    start_at: startAt,
                    end_at: endAt || null
                };
            })
            .filter(period => period.start_at)
            .sort((a, b) => String(a.start_at).localeCompare(String(b.start_at)));
    }

    return [];
}

function getMessageMembershipPeriodIndex(periods = [], message = null) {
    if (!Array.isArray(periods) || !periods.length || !message) return -1;

    const rawMessageTime = String(message?.created_at || message?.time || message?.sent_at || '').trim();
    if (!rawMessageTime) return -1;
    const messageTs = Date.parse(rawMessageTime.replace(' ', 'T'));
    if (!Number.isFinite(messageTs)) return -1;

    for (let i = 0; i < periods.length; i += 1) {
        const period = periods[i] || {};
        const startTs = Date.parse(String(period?.start_at || '').replace(' ', 'T'));
        if (!Number.isFinite(startTs) || messageTs < startTs) continue;

        const endRaw = period?.end_at;
        if (endRaw === null || String(endRaw || '').trim() === '') {
            return i;
        }

        const endTs = Date.parse(String(endRaw).replace(' ', 'T'));
        if (!Number.isFinite(endTs) || messageTs <= endTs) {
            return i;
        }
    }

    return -1;
}

function formatMembershipGapRange(startAt = '', endAt = '') {
    const start = new Date(String(startAt || '').replace(' ', 'T'));
    const end = new Date(String(endAt || '').replace(' ', 'T'));
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return '';

    const opts = { day: '2-digit', month: 'short' };
    const startText = start.toLocaleDateString([], opts);
    const endText = end.toLocaleDateString([], opts);
    return `${startText} - ${endText}`;
}

function renderMembershipGapSeparator(previousPeriod = null, nextPeriod = null) {
    const from = String(previousPeriod?.end_at || '').trim();
    const to = String(nextPeriod?.start_at || '').trim();
    if (!from || !to) return;

    const fromTs = Date.parse(from.replace(' ', 'T'));
    const toTs = Date.parse(to.replace(' ', 'T'));
    if (!Number.isFinite(fromTs) || !Number.isFinite(toTs) || toTs <= fromTs) return;

    const range = formatMembershipGapRange(from, to);
    const chat = document.getElementById('chat');
    if (!chat) return;

    const row = document.createElement('div');
    row.className = 'sticky-note-row';

    const note = document.createElement('div');
    note.className = 'sticky-note';
    note.innerHTML = `
        <div>Inactive during this period</div>
        ${range ? `<div>${escapeHtml(range)}</div>` : ''}
    `;

    row.appendChild(note);
    chat.appendChild(row);
}

function renderChatFromHistory(chat) {
    clearConversationUI();
    lastRenderedDay = '';
    updateHeaderForChat(chat || 'New chat');

    const periods = getCurrentUserMembershipPeriods(chat);
    let previousPeriodIndex = -1;

    chat.messages.forEach((message, index) => {
        const currentPeriodIndex = getMessageMembershipPeriodIndex(periods, message);
        if (
            currentPeriodIndex >= 0
            && previousPeriodIndex >= 0
            && currentPeriodIndex !== previousPeriodIndex
            && periods[previousPeriodIndex]
            && periods[currentPeriodIndex]
        ) {
            renderMembershipGapSeparator(periods[previousPeriodIndex], periods[currentPeriodIndex]);
        }

        renderStoredMessage(message, index);

        if (currentPeriodIndex >= 0) {
            previousPeriodIndex = currentPeriodIndex;
        }
    });
    renderTemporaryFlow();
    renderRecipientPills();
    updateFeatherLatest();
    
}

function renderStoredMessage(message, index = null) {
    const meta = {
        storedIndex: index,
        messageId: message.id || '',
        time: message?.time
    };

    const messageUserId = String(message?.user_id || '').trim();
    const currentUserId = String(window.currentUserId || '').trim();
    const isFromCurrentUser = messageUserId !== '' && currentUserId !== '' && messageUserId === currentUserId;
    const renderAsIncoming = messageUserId !== ''
        ? !isFromCurrentUser
        : (message.role === 'other');

    renderDay(message?.time || message?.created_at || message?.sent_at || '');

    if (renderAsIncoming) {
        return renderIncomingMessage(
            message.name || 'Other',
            message.content ?? message.text ?? '',
            {
                ...meta,
                user_id: message.user_id || '',
                meta: message.meta || {},
                recipient_label: message.recipient_label || ''
            }
        );
    }

    if (message.role === 'user' || (message.role === 'other' && isFromCurrentUser)) {
        return renderMessage({
            role: 'user',
            content: message.content ?? message.text ?? '',
            meta: message.meta || {},
            recipient_label: message.recipient_label || '',
            reply_to: message.reply_to || ''
        }, meta);
    }

    if (message.role === 'sticky') return renderSticky(message.content ?? '');
    if (message.role === 'timeline') return addTimelineItem(message.title || 'Timeline', message.content ?? '');
    if (message.role === 'catchup') return showCatchup(Array.isArray(message.items) ? message.items : []);
    if (message.role === 'join_note') return showJoinNote(message.content ?? '');
    if (message.role === 'assist') return showAssist(message.line1 || '', message.line2 || '', message.draft || '');
}

async function archiveChat(chatId) {
    const data = await api('archive_chat', {
        chat_id: chatId
    });

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

    const data = await api('delete_chat_permanently', {
        chat_id: chatId
    });

    if (!data?.ok) {
        alert(data?.error || 'Could not delete chat');
        return;
    }

    await refreshChatList(data.current_chat_id || null);
}

async function leaveChat(chatId) {
    const ok = window.confirm('Leave this chat?\n\nYou will no longer have access to it.\nTo join again, you must be invited again.');
    if (!ok) return;

    const data = await api('leave_chat', {
        chat_id: chatId
    });

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
    featherSourceMessageId = '';

    document.getElementById('timelineStrip').classList.add('hidden');
    document.getElementById('joinNote').classList.add('hidden');
    document.getElementById('catchupCard').classList.add('hidden');
}

function getComposerAssistantName() {
    return config?.assistants?.[config?.user?.assistant]?.name || 'Assistant';
}

function normalizeComposerSuggestionText(text = '') {
    return String(text || '').trim().replace(/\s+/g, ' ');
}

function isMeaningfulComposerSuggestion(original = '', suggestion = '') {
    const originalText = normalizeComposerSuggestionText(original);
    const suggestionText = normalizeComposerSuggestionText(suggestion);

    if (!suggestionText) return false;
    if (suggestionText.toLowerCase() === 'no_edits_suggested') return false;
    return suggestionText !== originalText;
}

function showComposerSuggestionLoading(original = '') {
    composerSuggestionState.original = String(original || '').trim();
    composerSuggestionState.suggestion = '';
    composerSuggestionState.loading = true;
    composerMessagePolished = false;
    composerAssistBaselineText = '';
    composerAssistTouched = false;
    nextOutgoingAssistLabel = '';

    const input = document.getElementById('input');
    if (input) {
        input.classList.add('composer-polishing');
    }

    document.getElementById('assistantComposerBtn')?.classList.add('is-writing');

    const status = document.getElementById('composerWriteStatus');
    if (status) {
        status.textContent = 'Writing…';
        status.classList.remove('hidden');
    }

    updateComposerSendButtonState();

    const replyPreview = document.getElementById('composerReplyPreview');
    if (replyPreview && composerSuggestionState.original) {
        replyPreview.classList.add('hidden');
    }

    const actionBtn = document.getElementById('actionBtn');
    if (actionBtn) actionBtn.disabled = true;
}

function clearComposerPolishingState() {
    const input = document.getElementById('input');
    if (input) {
        input.classList.remove('composer-polishing');
    }

    document.getElementById('assistantComposerBtn')?.classList.remove('is-writing');

    const status = document.getElementById('composerWriteStatus');
    if (status) {
        status.textContent = '';
        status.classList.add('hidden');
    }

    updateComposerSendButtonState();
}

function showComposerSuggestion(original = '', suggestion = '') {
    composerSuggestionState.original = String(original || '').trim();
    composerSuggestionState.suggestion = String(suggestion || '').trim();
    composerSuggestionState.loading = false;
    composerMessagePolished = true;
    composerAssistBaselineText = composerSuggestionState.suggestion;
    composerAssistTouched = false;
    nextOutgoingAssistLabel = composerAssistBaselineText ? 'unedited' : '';

    clearComposerPolishingState();

    const status = document.getElementById('composerWriteStatus');
    if (status) {
        status.textContent = 'Done';
        status.classList.remove('hidden');
    }

    const input = document.getElementById('input');
    if (input && composerSuggestionState.suggestion) {
        input.value = composerSuggestionState.suggestion;
        autoGrowTextarea.call(input);
        focusInput();
    }

    const actionBtn = document.getElementById('actionBtn');
    if (actionBtn) actionBtn.disabled = false;

    updateComposerSendButtonState();
}

function hideComposerSuggestion() {
    composerSuggestionState.original = '';
    composerSuggestionState.suggestion = '';
    composerSuggestionState.loading = false;
    composerMessagePolished = false;
    composerAssistBaselineText = '';
    composerAssistTouched = false;
    nextOutgoingAssistLabel = '';

    clearComposerPolishingState();

    const actionBtn = document.getElementById('actionBtn');
    if (actionBtn) actionBtn.disabled = false;

    updateComposerSendButtonState();
}

function editComposerSuggestion() {
    const input = document.getElementById('input');
    if (!input) return;

    if (composerSuggestionState.suggestion) {
        input.value = composerSuggestionState.suggestion;
        autoGrowTextarea.call(input);
    }

    focusInput();
}

function getComposerAssistSourceText() {
    const input = document.getElementById('input');
    const composerText = String(input?.value || '').trim();
    if (composerText) return composerText;

    const targetMessageId = String(composerTargetMessageId || '').trim();
    if (targetMessageId) {
        const targetMessage = findMessageById(targetMessageId);
        const targetText = String(targetMessage?.content || targetMessage?.text || '').trim();
        if (targetText) return targetText;
    }

    return '';
}

async function runComposerCustomEdit(instruction) {
    const input = document.getElementById('input');
    const composerText = getComposerAssistSourceText();
    const userInstruction = String(instruction || '').trim();

    if (!composerText) {
        input?.focus();
        return;
    }

    if (!userInstruction) return;
    if (!canUseComposerAssistant(true)) return;

    const payloadText = `Instruction:
${userInstruction}

Input:
${composerText}`;

    setComposerLoading(true);

    try {
        const data = await sendToAPI(
            [{ role: 'user', content: payloadText }],
            'freetext'
        );

        const result = getAssistantText(data, '');

        if (result && input) {
            input.value = result;
            autoGrowTextarea.call(input);
            focusInput();
        }
    } catch (err) {
        console.error('Could not run composer custom edit', err);
    } finally {
        setComposerLoading(false);
    }
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

async function runComposerAssist(instruction, sourceText = '') {
    const input = document.getElementById('input');
    const currentText = String(sourceText || input?.value || '').trim();
    const userInstruction = String(instruction || '').trim();

    if (!userInstruction) return;
    if (!canUseComposerAssistant(true)) return;

    setComposerLoading(true);

    try {
        const payloadText = currentText
            ? `Instruction:
${userInstruction}

Input:
${currentText}`
            : userInstruction;

        const data = await sendToAPI(
            [{ role: 'user', content: payloadText }],
            'freetext'
        );

        const nextText = getAssistantText(data, '');

        if (nextText && input) {
            input.value = nextText;
            autoGrowTextarea.call(input);
            focusInput();
            manualAssistantAction = true;
            composerMessagePolished = true;
            composerAssistBaselineText = String(nextText || '').trim();
            composerAssistTouched = false;
            nextOutgoingAssistLabel = 'unedited';
            updateComposerSendButtonState();
            const _birdBtn = document.getElementById('assistantComposerBtn');
            if (_birdBtn) { _birdBtn.classList.remove('is-ready'); void _birdBtn.offsetWidth; _birdBtn.classList.add('is-ready'); setTimeout(() => _birdBtn.classList.remove('is-ready'), 600); }
        }
    } catch (err) {
        console.error('Could not run composer assist', err);
    } finally {
        setComposerLoading(false);
    }
}

function cancelFeatherDraft() {
    if (!featherSourceMessageId) return;
    const featherWrap = document.querySelector(`.message-wrap[data-message-id="${CSS.escape(featherSourceMessageId)}"]`);
    if (featherWrap) featherWrap.classList.remove('feather-generating');
    document.querySelectorAll('.feather-options-panel').forEach(p => p.remove());
    featherSourceMessageId = '';
    updateFeatherLatest();
}

function updateFeatherLatest() {
    const chat = document.getElementById('chat');
    if (!chat) return;
    const allMessages = Array.from(chat.querySelectorAll('.message-wrap'));
    const allIncoming = allMessages.filter(w => w.classList.contains('incoming'));
    // Clear all markers
    allIncoming.forEach(w => { w.classList.remove('feather-latest'); w.classList.remove('feather-handled'); });
    // If assistance is off, leave all feathers hidden
    if (!canUseAssistantFeatures()) return;
    if (allIncoming.length === 0) return;
    const last = allIncoming[allIncoming.length - 1];
    const lastIndex = allMessages.indexOf(last);
    // If any outgoing message follows the last incoming, it was already answered
    const hasOutgoingAfter = allMessages.slice(lastIndex + 1).some(w => w.classList.contains('outgoing'));
    if (hasOutgoingAfter) {
        last.classList.add('feather-handled');
        last.querySelector('.incoming-feather-btn')?.classList.add('hidden');
    } else {
        last.classList.add('feather-latest');
        last.querySelector('.incoming-feather-btn')?.classList.remove('hidden');
    }
}

async function runBubbleFeatherAssist(wrap) {
    if (!isLoggedIn) {
        openAuthModal('login');
        return;
    }

    if (!canUseComposerAssistant(true)) return;

    const targetMessageId = wrap ? resolveWrapMessageId(wrap) : '';
    if (!targetMessageId) return;

    // Cancel any previous pending feather draft before starting a new one
    cancelFeatherDraft();

    const targetMessage = findMessageById(targetMessageId);
    const sourceText = String(targetMessage?.content || targetMessage?.text || getMessageTextFromWrap(wrap) || '').trim();
    if (!sourceText) return;

    wrap.classList.add('feather-generating');

    // Trigger brief peck animation on the composer bird button
    const _birdBtnPeck = document.getElementById('assistantComposerBtn');
    if (_birdBtnPeck) {
        _birdBtnPeck.classList.remove('bird-pecking');
        void _birdBtnPeck.offsetWidth; // reflow to restart animation
        _birdBtnPeck.classList.add('bird-pecking');
        setTimeout(() => _birdBtnPeck.classList.remove('bird-pecking'), 560);
    }

    showComposerThinking();
    setComposerLoading(true);

    try {
        const data = await sendToAPI(
            [{ role: 'user', content: sourceText }],
            'incoming_analyze',
            {
                incoming_is_latest: '1',
                incoming_message_time: String(targetMessage?.time || ''),
                message_direction: 'incoming_reply'
            }
        );

        wrap.classList.remove('feather-generating');

        const replyType = String(data?.reply_type || '').trim();
        const options = Array.isArray(data?.options) ? data.options.map(o => String(o || '').trim()).filter(Boolean) : [];

        // Multiple intent options → show chips near the composer button
        if (replyType === 'options' && options.length >= 2) {
            featherSourceMessageId = targetMessageId;
            showFeatherOptionsInComposer(options, targetMessageId);
            updateComposerSendButtonState();
            return;
        }

        // Single reply → place directly in composer
        const answer = replyType === 'options' && options.length === 1
            ? options[0]
            : getAssistantText(data);

        if (!answer || replyType === 'passive' || replyType === 'error') {
            return;
        }

        const input = document.getElementById('input');
        if (!input) {
            return;
        }

        // Feather is NOT a reply — clear any existing reply target
        composerTargetMessageId = '';
        clearComposerReplyPreview();

        input.value = answer;
        input.disabled = false;
        autoGrowTextarea.call(input);
        focusInput();
        manualAssistantAction = true;
        composerMessagePolished = true;
        composerAssistBaselineText = answer;
        composerAssistTouched = false;
        nextOutgoingAssistLabel = 'unedited';
        featherSourceMessageId = targetMessageId;
        updateComposerSendButtonState();
    } catch (err) {
        wrap.classList.remove('feather-generating');
        console.error('Could not run bubble feather assist', err);
    } finally {
        hideComposerThinking();
        setComposerLoading(false);
    }
}

function showFeatherOptions(wrap, options, sourceMessageId) {
    // Remove any existing chips panel first
    dismissFeatherOptions();

    const panel = document.createElement('div');
    panel.className = 'feather-options-panel';
    panel.dataset.sourceMessageId = String(sourceMessageId || '');

    options.forEach((text) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'feather-option-chip';
        chip.textContent = text;
        chip.addEventListener('click', function () {
            selectFeatherOption(text, sourceMessageId);
        });
        panel.appendChild(chip);
    });

    // Insert the panel after the message row
    const row = wrap.closest('.message-row') || wrap;
    row.after(panel);
}

function showFeatherOptionsInComposer(options, sourceMessageId) {
    dismissFeatherOptions();

    const panel = document.createElement('div');
    panel.className = 'feather-options-panel feather-options-panel--composer';
    panel.dataset.sourceMessageId = String(sourceMessageId || '');

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'feather-options-close';
    closeBtn.setAttribute('aria-label', 'Dismiss');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', function () {
        dismissFeatherOptions();
    });
    panel.appendChild(closeBtn);

    options.forEach((text) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'feather-option-chip';
        chip.textContent = text;
        chip.addEventListener('click', function () {
            selectFeatherOption(text, sourceMessageId);
        });
        panel.appendChild(chip);
    });

    // Insert above the composer toolbar row
    const composerWrap = document.getElementById('composerWrap');
    if (composerWrap) {
        composerWrap.prepend(panel);
    } else {
        const toolbar = document.querySelector('.composer-toolbar');
        if (toolbar) toolbar.insertBefore(panel, toolbar.firstChild);
    }
}

function dismissFeatherOptions() {
    document.querySelectorAll('.feather-options-panel').forEach(p => p.remove());
    if (!document.getElementById('input')?.value) {
        cancelFeatherDraft();
    }
}

async function selectFeatherOption(intentLabel, sourceMessageId) {
    // Mark the chips panel as loading to prevent double-clicks
    document.querySelectorAll('.feather-options-panel').forEach(p => p.classList.add('feather-options-loading'));

    showComposerThinking();
    setComposerLoading(true);

    try {
        const targetMessage = findMessageById(String(sourceMessageId || ''));
        const sourceText = String(targetMessage?.content || targetMessage?.text || '').trim();

        const data = await sendToAPI(
            [{ role: 'user', content: sourceText }],
            'incoming_assist',
            {
                incoming_intent: 'reply',
                selected_intent: intentLabel,
                incoming_is_latest: '1',
                incoming_message_time: String(targetMessage?.time || ''),
                message_direction: 'incoming_reply'
            }
        );

        const reply = String(data?.reply || '').trim();

        // Re-enable chips so user can try another option
        document.querySelectorAll('.feather-options-panel').forEach(p => p.classList.remove('feather-options-loading'));

        if (!reply) return;

        const input = document.getElementById('input');
        if (!input) return;

        input.value = reply;
        input.disabled = false;
        autoGrowTextarea.call(input);
        focusInput();
        manualAssistantAction = true;
        composerMessagePolished = true;
        composerAssistBaselineText = reply;
        composerAssistTouched = false;
        nextOutgoingAssistLabel = 'unedited';
        featherSourceMessageId = String(sourceMessageId || '');
        updateComposerSendButtonState();

        if (composerAssistantSettings?.improveAndSend) {
            document.getElementById('actionBtn')?.click();
        }
    } catch (err) {
        dismissFeatherOptions();
        console.error('Could not generate reply for selected option', err);
    } finally {
        hideComposerThinking();
        setComposerLoading(false);
    }
}

async function runComposerAutoReply() {
    const targetMessageId =
        String(composerTargetMessageId || '').trim() ||
        String(getLatestIncomingMessageId() || '').trim();

    if (!targetMessageId) return;
    if (!canUseComposerAssistant(true)) return;

    const targetMessage = findMessageById(targetMessageId);
    const sourceText = String(targetMessage?.content || targetMessage?.text || '').trim();
    if (!sourceText) return;

    showComposerThinking();
    setComposerLoading(true);

    try {
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
            'incoming_analyze',
            {
                incoming_is_latest: isLatestIncoming ? '1' : '0',
                incoming_message_time: String(targetMessage?.time || ''),
                message_direction: 'incoming_reply'
            }
        );

        const replyType = String(data?.reply_type || '').trim();
        const options = Array.isArray(data?.options) ? data.options.map(o => String(o || '').trim()).filter(Boolean) : [];

        // Multiple intent options → show chips in composer
        if (replyType === 'options' && options.length >= 2) {
            featherSourceMessageId = targetMessageId;
            showFeatherOptionsInComposer(options, targetMessageId);
            updateComposerSendButtonState();
            return;
        }

        const answer = replyType === 'options' && options.length === 1
            ? options[0]
            : getAssistantText(data);
        if (!answer || replyType === 'passive' || replyType === 'error') return;

        const input = document.getElementById('input');
        if (!input) return;

        input.value = answer;
        input.disabled = false;
        autoGrowTextarea.call(input);
        focusInput();
        manualAssistantAction = true;
        composerMessagePolished = true;
        composerAssistBaselineText = answer;
        composerAssistTouched = false;
        nextOutgoingAssistLabel = 'unedited';
        updateComposerSendButtonState();
        const _birdBtn = document.getElementById('assistantComposerBtn');
        if (_birdBtn) { _birdBtn.classList.remove('is-ready'); void _birdBtn.offsetWidth; _birdBtn.classList.add('is-ready'); setTimeout(() => _birdBtn.classList.remove('is-ready'), 600); }
    } catch (err) {
        console.error('Could not run composer auto reply', err);
    } finally {
        hideComposerThinking();
        setComposerLoading(false);
    }
}

async function runComposerAction(action = 'polish') {
    const input = document.getElementById('input');
    const composerText = getComposerAssistSourceText();

    if (!composerText) {
        input?.focus();
        return;
    }

    if (!canUseComposerAssistant()) {
        return;
    }

    let sourceText = '';
    const mode = 'freetext';

    const promptMap = {
        polish: 'Improve this text. Fix spelling, grammar, and wording. Keep the same meaning and language.',
        summarize: 'Summarize this text clearly and briefly. Keep the same language. Prefer a very short result.',
        translate: 'Translate this text into my preferred language from settings. Keep the meaning natural and clear.',
        write_more: 'Expand this text naturally. Keep the same meaning, tone, and language, but make it a bit fuller and more complete.',
        simplify: 'Rewrite this text in a simpler, clearer way. Keep the same meaning and language.',
        humor: 'Rewrite this text with a light and natural sense of humor. Keep the same meaning and language. Make it friendly and playful, but still appropriate for the conversation.',
        cold: 'Rewrite this text in a more formal, concise, and emotionally neutral way. Keep the same meaning and language. Make it sound professional and direct.',
        warm: 'Rewrite this text in a warmer, friendlier, and more personal tone. Keep the same meaning and language. Make it sound kind, natural, and empathetic.'
    };

    const instruction = promptMap[action];

    if (instruction) {
        sourceText = `Instruction:
    ${instruction}

    Input:
    ${composerText}`;
    } else {
        sourceText = composerText;
    }

    setComposerLoading(true);

    try {
        const data = await sendToAPI(
            [{ role: 'user', content: sourceText }],
            mode
        );

        const result =
            data?.message ||
            data?.text ||
            data?.reply ||
            '';

        if (result && input) {
            input.value = result;
            autoGrowTextarea.call(input);
            focusInput();
            manualAssistantAction = true;
            composerMessagePolished = true;
            composerAssistBaselineText = String(result || '').trim();
            composerAssistTouched = false;
            nextOutgoingAssistLabel = 'unedited';
            updateComposerSendButtonState();
            const _birdBtn = document.getElementById('assistantComposerBtn');
            if (_birdBtn) { _birdBtn.classList.remove('is-ready'); void _birdBtn.offsetWidth; _birdBtn.classList.add('is-ready'); setTimeout(() => _birdBtn.classList.remove('is-ready'), 600); }
        }
    } catch (err) {
        console.error('Composer action failed:', err);
    } finally {
        setComposerLoading(false);
    }
}

function setComposerLoading(isLoading) {
    const wrap = document.getElementById('composerWrap');
    const input = document.getElementById('input');
    const sendBtn = document.getElementById('actionBtn');
    const assistBtn = document.getElementById('assistantToggleBtn');
    const assistantComposerBtn = document.getElementById('assistantComposerBtn');
    const assistantComposerInput = document.getElementById('assistantComposerInput');

    wrap?.classList.toggle('is-polishing', !!isLoading);

    if (input) input.disabled = !!isLoading;
    if (sendBtn) sendBtn.disabled = !!isLoading;
    if (assistantComposerInput) assistantComposerInput.disabled = !!isLoading;

    if (assistBtn) {
        assistBtn.disabled = !!isLoading;
    }

    if (assistantComposerBtn) {
        assistantComposerBtn.disabled = !!isLoading;
    }
}

window.assistantTools = {

    languages: {
        en: 'English',
        nl: 'Nederlands',
        it: 'Italiano',
        de: 'Deutsch',
        fr: 'Français',
        es: 'Español',
        pt: 'Português',
        zh: '中文'
    },
    tones: {
        corporate_friendly: "Corporate & friendly",
        corporate_direct: "Corporate & direct",
        polite_thoughtful: "Polite & thoughtful",
        neutral_practical: "Neutral & practical",
        casual_friendly: "Casual & friendly",
        casual_direct: "Casual & direct",
        playful_light: "Playful & light",
        bold_confident: "Bold & confident"
    }
};

function shouldCheckBeforeSend() {
    if (isGuestUser()) return false;
    const settings = composerAssistantSettings || {};
    if (!settings.enabled) return false;
    if (!settings.checkBeforeSend && !settings.improveAndSend) return false;
    if (settings.mode === 'manual') return false;
    if (!userAssistantSettings.enabled || (!userAssistantSettings.hasKey && !userAssistantSettings.betaActive)) return false;
    return true;
}

function updateComposerSendButtonState() {
    const actionBtn = document.getElementById('actionBtn');
    if (!actionBtn) return;

    const isLoading = !!composerSuggestionState.loading;
    const shouldPolish = shouldCheckBeforeSend();
    const isReadyToSend = !isLoading && (!shouldPolish || !!composerMessagePolished);
    const isAssistantMode = shouldPolish && !isReadyToSend && !isLoading;

    actionBtn.classList.toggle('is-polishing', isLoading);

    const title = isLoading
        ? 'Polishing message'
        : (isAssistantMode ? 'Polish message first' : (composerMessagePolished ? 'Send polished message' : 'Send message'));

    actionBtn.title = title;
    actionBtn.setAttribute('aria-label', title);
}

async function handleComposerSend({ normalSendClicked = false } = {}) {
    const input = document.getElementById('input');
    const actionBtn = document.getElementById('actionBtn');
    if (!input) return;

    const rawMessage = (input.value || '').trim();
    if (!rawMessage) return;

    const replyTargetActive = !!String(composerTargetMessageId || '').trim();

    if (assistantComposerMode === 'private') {
        await submitPrivateComposerAi(rawMessage);
        return;
    }

    if (!normalSendClicked || manualAssistantAction || replyTargetActive || composerMessagePolished) {
        await handleSend();
        return;
    }

    if (!shouldCheckBeforeSend()) {
        await handleSend();
        return;
    }

    if (composerSuggestionState.loading) return;

    if (actionBtn) actionBtn.disabled = true;
    showComposerSuggestionLoading(rawMessage);

    const shouldAutoSendImprovedText = !!composerAssistantSettings?.improveAndSend;

    try {
        const suggestion = await requestComposerSuggestion(rawMessage);
        const cleanedSuggestion = String(suggestion || '').trim();

        if (!isMeaningfulComposerSuggestion(rawMessage, cleanedSuggestion)) {
            composerSuggestionState.original = rawMessage;
            composerSuggestionState.suggestion = rawMessage;
            composerSuggestionState.loading = false;

            composerMessagePolished = true;
            composerAssistBaselineText = rawMessage;
            composerAssistTouched = false;
            nextOutgoingAssistLabel = 'unedited';

            clearComposerPolishingState();

            const status = document.getElementById('composerWriteStatus');
            if (status) {
                status.textContent = 'Done';
                status.classList.remove('hidden');
            }

            if (actionBtn) actionBtn.disabled = false;

            updateComposerSendButtonState();

            if (shouldAutoSendImprovedText) {
                await new Promise(resolve => window.requestAnimationFrame(() => resolve()));
                await handleSend();
            }

            return;
        }

        showComposerSuggestion(rawMessage, cleanedSuggestion);

        if (shouldAutoSendImprovedText) {
            await new Promise(resolve => window.requestAnimationFrame(() => resolve()));
            await handleSend();
        }
    } catch (err) {
        console.error('Could not generate composer suggestion', err);
        hideComposerSuggestion();
    }
}

// -------------------------
// SEND FLOW
// -------------------------
async function handleSend() {
    const input = document.getElementById('input');

    const text = input.value.trim();

    if (composerReplyLoading) return false;
    
    if (!text) return false;

    dismissFeatherOptions();
    clearComposerPolishingState();
    
    cancelAllReplyCountdowns();

    if (!isLoggedIn) {
        if (!canUseGuestTrial()) {
        setTimeout(() => {
            openAuthModal('signup', 'trial_exhausted');
            }, 400);
            return false;
        }

        await submitPrivateComposerAi(text);
        hideComposerSuggestion();
        return true;
    }
    
    composerWrap?.classList.add('active');
    document.querySelector('.chat-header-right')?.classList.remove('hidden');
    
    if (!currentChatId) {
        await createNewChat();
    }

    if (editingMessageId) {
        try {

            await api('edit_message', {
                chat_id: currentChatId,
                message_id: editingMessageId,
                content: text
            });

            updateMessageInLocalState(editingMessageId, text);

            if (editingMessageWrap) {
                updateMessageTextInWrap(editingMessageWrap, text, true);
            }

            editingMessageId = null;
            editingMessageWrap = null;
            document.getElementById('composerWrap')?.classList.remove('is-editing');

            resetTextarea(input);
            hideComposerSuggestion();
            return true;
        } catch (err) {
            console.error('Could not save edited message', err);
            return false;
        }
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
    
    const replyToMessageId = String(composerTargetMessageId || '').trim();
    
    const provisionalMessageId = 'tmp_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

    // Defensive: ignore accidental duplicate temp IDs on rapid submit events.
    const hasDuplicateTempId = pendingTempClientMessageIds.has(provisionalMessageId) ||
        chatHistory.some(msg => String(msg?.id || '') === provisionalMessageId);
    if (hasDuplicateTempId) {
        return;
    }
    pendingTempClientMessageIds.add(provisionalMessageId);
    
    const userMsg = {
        id: provisionalMessageId,
        role: 'user',
        content: text,
        text: text,
        recipient_label: recipientLabel,
        reply_to: replyToMessageId
    };
    
    chatHistory.push(userMsg);
    renderMessage(userMsg, {
        historyIndex: chatHistory.length - 1,
        messageId: provisionalMessageId
    });
    scrollChatToBottom(currentChatId || true);
    updateFeatherLatest();
    
    nextOutgoingAssistLabel = '';
    clearComposerReplyPreview();

    featherSourceMessageId = ''
    
    const participants = Array.isArray(currentChatMeta?.participants) ? currentChatMeta.participants : [];
    const isAloneChat = participants.length <= 1;

    if (isAloneChat) {
        try {
            const data = await sendToAPI(chatHistory, 'send_message', {
                meta: messageMeta,
                recipient_label: recipientLabel,
                reply_to: replyToMessageId
            });

            if (!data?.ok) {
                rollbackProvisionalOutgoingMessage(provisionalMessageId);
                console.warn('Send failed for composer message', data);
                if (currentChatId) {
                    await loadChat(currentChatId, { scrollToBottom: false, focusComposer: true });
                }
                return false;
            }

            await refreshChatList(currentChatId);
            hideComposerSuggestion();
        } finally {
            pendingTempClientMessageIds.delete(provisionalMessageId);
        }
        return true;
    }
    
    const canAssist = canUseAssistantFeatures();

    let data;
    try {
        data = await sendToAPI(chatHistory, canAssist ? 'chat' : 'send_message', {
            meta: messageMeta,
            recipient_label: recipientLabel,
            reply_to: replyToMessageId
        });
    } finally {
        pendingTempClientMessageIds.delete(provisionalMessageId);
    }

    if (!data?.ok) {
        rollbackProvisionalOutgoingMessage(provisionalMessageId);
        console.warn('Send failed for composer message', data);
        if (currentChatId) {
            await loadChat(currentChatId, { scrollToBottom: false, focusComposer: true });
        }
        return false;
    }

    if (replyToMessageId) {
        const repliedMsg = findMessageById(replyToMessageId);
    
        if (repliedMsg && shouldAutoResolveOpenIssueType(repliedMsg.open_issue_type)) {
            try {
                await resolveOpenIssue(currentChatId, replyToMessageId);
                repliedMsg.open_issue = '';
                repliedMsg.open_issue_type = 0;
    
                const repliedWrap = document.querySelector(`.message-wrap[data-message-id="${CSS.escape(replyToMessageId)}"]`);
                if (repliedWrap) {
                    setWrapOpenIssueState(repliedWrap, false, 0);
                }
            } catch (err) {
                console.error('Could not auto-resolve replied open issue', err);
            }
        }
    }

    if (data.multi) {
        lastMessageCount = data.message_count || lastMessageCount;
        await refreshChatList(currentChatId);
        hideComposerSuggestion();
        maybeShowFeedbackBar();
        return true;
    }

    if (!canAssist) {
        showNoAiNoticeOnceForChat(currentChatId);
        hideComposerSuggestion();
        maybeShowFeedbackBar();
        return true;
    }

    const state = getTempUiState();
    state.items = state.items.filter(item => item.kind !== 'thinking');
    renderTemporaryFlow();

    if (data.chat_id) currentChatId = data.chat_id;
    await refreshChatList(currentChatId);
    hideComposerSuggestion();
    maybeShowFeedbackBar();
    return true;
    
}

function autoGrowTextarea() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 220) + 'px';
}

function resetTextarea(input) {
    input.value = '';
    input.style.height = 'auto';
}

// Keep scroll button above the composer — set up after DOM is ready (see load handler)

// -------------------------
// API
// -------------------------
async function sendToAPI(messages, action = 'chat', extra = {}) {
    const payload = {
        action,
        messages,
        chat_id: currentChatId,
        ...extra
    };

    const { action: actionName, ...data } = payload;

    return await api(actionName, data);
}

// -------------------------
// RENDER HELPERS
// -------------------------

function setWrapOpenIssueState(wrap, enabled) {
    if (!wrap) return;

    wrap.classList.toggle('has-open-issue', !!enabled);

    wrap.classList.remove(
        'open-issue-type-1',
        'open-issue-type-2',
        'open-issue-type-3',
        'open-issue-type-4',
        'open-issue-type-5'
    );

    // Sync the global floating bar flag button when this wrap is active
    const globalBar = document.getElementById('messageHoverActionsGlobal');
    const btn = (globalBar && globalHoverActiveWrap === wrap)
        ? globalBar.querySelector('.message-flag-btn')
        : null;
    if (btn) {
        btn.classList.toggle('is-active', !!enabled);
        btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        btn.title = enabled ? 'Remove attention flag' : 'Needs attention';
    }
}

async function resolveOpenIssue(chatId, messageId) {
    await api('resolve_open_issue', {
        chat_id: chatId,
        message_id: messageId
    });

    await loadSidebarMemory();
    await loadChat(currentChatId);
}

function canUseComposerAssistant(showMessage = false, targetMessageId = '') {
    const hasAccess = !!(
        isLoggedIn &&
        !isGuestUser() &&
        composerAssistantSettings?.enabled &&
        userAssistantSettings?.enabled &&
        (userAssistantSettings?.hasKey || userAssistantSettings?.betaActive)
    );

    if (!hasAccess && showMessage) {
        let message = 'Assistant is off';

        if (!isLoggedIn) {
            message = 'Please log in to use assistant features.';
        } else if (isGuestUser()) {
            message = guestSignupMessage();
        } else if (!userAssistantSettings?.enabled) {
            message = 'Assistant is off';
        } else if (!composerAssistantSettings?.enabled) {
            message = 'Assistant is off';
        } else if (!userAssistantSettings?.hasKey && !userAssistantSettings?.betaActive) {
            message = 'API key is missing';
        }

        if (targetMessageId) {
            const state = getTempUiState(!isLoggedIn ? '__guest__' : (currentChatId || '__new__'));

            state.items = state.items.filter(item =>
                !(item.targetMessageId === targetMessageId && item.kind === 'assistant_reply')
            );

            state.items.push({
                kind: 'assistant_notice',
                targetMessageId,
                content: message
            });

            renderTemporaryFlow();
        } else {
            renderSticky(message);
        }
    }

    return hasAccess;
}

function closeAllMessageMenus() {
    activeMessageMenu = null;
    activeMessageMenuBtn = null;
}

function isOwnMessageWrap(wrap) {
    if (!wrap) return false;

    if (wrap.classList.contains('outgoing')) return true;
    if (wrap.classList.contains('incoming')) return false;

    const row = wrap.closest('.message-row');
    if (row?.dataset?.groupSender === 'me') return true;
    if (row?.dataset?.groupSender === 'other') return false;

    const messageId = String(wrap.dataset.messageId || '').trim();
    const msg = messageId ? findMessageById(messageId) : null;

    return String(msg?.role || '') === 'user';
}

function getMessageOpenIssueState(messageId) {
    const id = String(messageId || '').trim();
    if (!id) return false;
    return !!currentChatOpenIssues[id];
}

async function toggleMessageOpenIssue(wrap, forcedType = 1) {
    if (!wrap) return;

    const messageId = String(wrap.dataset.messageId || '').trim();
    if (!messageId) return;

    const msg = findMessageById(messageId);
    if (!msg) return;

    const nextValue = !getMessageOpenIssueState(messageId);
    const nextType = nextValue ? Number(forcedType || 1) : 0;

    try {
       const data = await api('toggle_open_issue', {
            chat_id: currentChatId,
            message_id: messageId,
            open_issue: nextValue ? 1 : 0,
            open_issue_type: nextType
        });

        if (!data?.ok) return;

        if (data?.open_issue) {
            currentChatOpenIssues[messageId] = {
                text: String(data.open_issue_text || '').trim(),
                type: Number(data.open_issue_type || nextType || 1)
            };
        } else {
            delete currentChatOpenIssues[messageId];
        }

        msg.open_issue = String(data.open_issue_text || '').trim();
        msg.open_issue_type = Number(data.open_issue_type || 0);

        setWrapOpenIssueState(wrap, !!currentChatOpenIssues[messageId]);
        wrap.classList.remove('show-actions');

        chatCompassMetaCache[String(currentChatId || '')] = {
            ...(chatCompassMetaCache[String(currentChatId || '')] || {}),
            loaded: true,
            issueCount: Number(data?.total || data?.count || 0)
        };

        await refreshChatList(currentChatId);

        if ((String(getTsjilp('ui.memoryView') || 'current')) === 'current') {
            await loadSidebarMemory();
        }
    } catch (err) {
        console.error('Could not toggle open issue', err);
    }
}

function shouldAutoResolveOpenIssueType(type) {
    const n = Number(type || 0);
    return n === 2 || n === 3;
}

function attachMessageMenu(wrap) {
    if (!wrap) return;
    const messageId = String(wrap.dataset.messageId || '').trim();
    if (messageId) {
        setWrapOpenIssueState(wrap, getMessageOpenIssueState(messageId));
    }
}

function initGlobalHoverActions() {
    const bar = document.getElementById('messageHoverActionsGlobal');
    const chatScroll = document.getElementById('chatScroll');
    const chat = document.getElementById('chat');
    if (!bar || !chat) return;

    // Delegated click handler on the global bar
    bar.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-hover-action]');
        if (!btn) return;
        e.stopPropagation();

        const wrap = globalHoverActiveWrap;
        if (!wrap) return;

        const action = btn.getAttribute('data-hover-action');

        if (action === 'reply') {
            composerTargetMessageId = wrap.dataset.messageId || '';
            updateComposerReplyPreview();
            const input = document.getElementById('input');
            if (input) {
                input.value = '';
                autoGrowTextarea?.call(input);
                input.focus();
            }
            hideGlobalHoverBar();
            return;
        }

        if (action === 'understand') {
            hideGlobalHoverBar();
            await runMenuAssist(wrap, 'explain');
            return;
        }

        if (action === 'edit') {
            hideGlobalHoverBar();
            editMessageFromMenu(wrap);
            return;
        }

        if (action === 'delete') {
            hideGlobalHoverBar();
            await deleteMessageFromMenu(wrap);
            return;
        }

        if (action === 'issue') {
            await toggleMessageOpenIssue(wrap);
        }
    });

    // Keep bar visible when pointer is over it
    bar.addEventListener('pointerenter', function () {
        clearTimeout(globalHoverHideTimer);
    });
    bar.addEventListener('pointerleave', function () {
        scheduleHideGlobalHoverBar();
    });

    // Show bar when entering a message wrap (desktop hover)
    chat.addEventListener('mouseover', function (e) {
        if (e.target.closest('.incoming-feather-btn')) return;
        const wrap = e.target.closest('.message-wrap');
        if (!wrap) return;
        clearTimeout(globalHoverHideTimer);
        showGlobalHoverBar(wrap);
    });

    // Hide bar when leaving a message wrap (unless entering bar or same wrap)
    chat.addEventListener('mouseout', function (e) {
        const wrap = e.target.closest('.message-wrap');
        if (!wrap) return;
        if (wrap.contains(e.relatedTarget)) return;
        if (bar.contains(e.relatedTarget)) return;
        scheduleHideGlobalHoverBar();
    });

    // Mobile: first tap on a bubble opens the bar; second tap elsewhere closes it.
    // A tap directly on an action button inside the bar is handled by the bar's click listener above.
    chat.addEventListener('touchend', function (e) {
        // If the tap is on an action button, let the bar's click handler deal with it
        if (e.target.closest('#messageHoverActionsGlobal')) return;
        // Feather button is always visible and executes immediately — don't intercept it
        if (e.target.closest('.incoming-feather-btn')) return;

        const wrap = e.target.closest('.message-wrap');

        // Tap outside any bubble → hide bar
        if (!wrap) {
            hideGlobalHoverBar();
            return;
        }

        // Tap on the bubble that already has the bar open → hide bar (toggle off)
        if (wrap === globalHoverActiveWrap) {
            hideGlobalHoverBar();
            return;
        }

        // First tap on a new bubble → show bar and stop so the tap doesn't also trigger links etc.
        e.preventDefault();
        clearTimeout(globalHoverHideTimer);
        showGlobalHoverBar(wrap);
    }, { passive: false });

    // Tapping outside both bubble and bar closes the bar on mobile
    document.addEventListener('touchend', function (e) {
        if (!globalHoverActiveWrap) return;
        if (e.target.closest('#messageHoverActionsGlobal')) return;
        if (e.target.closest('.message-wrap')) return;
        hideGlobalHoverBar();
    }, { passive: true });

    // Hide on scroll so stale position is never shown
    if (chatScroll) {
        chatScroll.addEventListener('scroll', function () {
            clearTimeout(globalHoverHideTimer);
            hideGlobalHoverBar();
        }, { passive: true });
    }
}

function showGlobalHoverBar(wrap) {
    const bar = document.getElementById('messageHoverActionsGlobal');
    if (!bar || !wrap) return;

    const isSameWrap = globalHoverActiveWrap === wrap;
    globalHoverActiveWrap = wrap;
    const isOwn = isOwnMessageWrap(wrap);

    // Toggle own-only buttons
    const editBtn = bar.querySelector('.edit-action');
    const deleteBtn = bar.querySelector('.delete-action');
    if (editBtn) editBtn.hidden = !isOwn;
    if (deleteBtn) deleteBtn.hidden = !isOwn;

    // Sync flag button state
    const messageId = String(wrap.dataset.messageId || '').trim();
    const flagActive = messageId ? getMessageOpenIssueState(messageId) : false;
    const flagBtn = bar.querySelector('.message-flag-btn');
    if (flagBtn) {
        flagBtn.classList.toggle('is-active', !!flagActive);
        flagBtn.setAttribute('aria-pressed', flagActive ? 'true' : 'false');
        flagBtn.title = flagActive ? 'Remove attention flag' : 'Needs attention';
    }

    if (!isSameWrap) {
        positionGlobalHoverBar(wrap, isOwn);
    }
    bar.style.display = 'flex';
    bar.removeAttribute('aria-hidden');
}

function positionGlobalHoverBar(wrap, isOwn) {
    const bar = document.getElementById('messageHoverActionsGlobal');
    if (!bar || !wrap) return;

    const bubble =
        wrap.querySelector(':scope > .bubble') ||
        wrap.querySelector(':scope > .message-content > .bubble') ||
        wrap.querySelector('.bubble') ||
        wrap;

    const rect = bubble.getBoundingClientRect();
    const barH = bar.offsetHeight || 46;
    const overlap = 20; // px the bar dips into the top of the bubble

    let top = rect.top - barH + overlap;
    if (top < 4) top = rect.bottom - overlap; // flip below if too close to top

    bar.style.top = top + 'px';

    if (isOwn) {
        bar.style.left = '';
        bar.style.right = (window.innerWidth - rect.right) + 'px';
    } else {
        bar.style.right = '';
        bar.style.left = rect.left + 'px';
    }
}

function scheduleHideGlobalHoverBar() {
    clearTimeout(globalHoverHideTimer);
    globalHoverHideTimer = setTimeout(hideGlobalHoverBar, 150);
}

function hideGlobalHoverBar() {
    const bar = document.getElementById('messageHoverActionsGlobal');
    if (!bar) return;
    bar.style.display = 'none';
    bar.setAttribute('aria-hidden', 'true');
    globalHoverActiveWrap = null;
}

function closeSidebarOnMobile() {
    if (window.innerWidth < 768) {
        document.getElementById('sidebar')?.classList.add('hidden');
        syncSidebarToggleButtonState();
        const scrollBtn = document.getElementById('scrollToBottom');
        if (scrollBtn) scrollBtn.style.bottom = '';
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

function updateMessageTextInWrap(wrap, text, edited = false) {
    const bubbleText = wrap?.querySelector('.bubble-text');
    if (!bubbleText || !wrap) return;

    bubbleText.textContent = text;

    wrap.querySelectorAll('.message-meta-label').forEach(el => el.remove());

    if (edited) {
        const editedLabel = document.createElement('div');
        editedLabel.className = 'message-meta-label';
        editedLabel.textContent = 'edited';
        wrap.appendChild(editedLabel);
    }
}

function getLatestIncomingMessageId() {
    if (!currentChatMeta || !Array.isArray(currentChatMeta.messages)) return '';

    for (let i = currentChatMeta.messages.length - 1; i >= 0; i--) {
        const msg = currentChatMeta.messages[i];
        if (
            msg?.role === 'other' &&
            String(msg?.user_id || '') !== String(window.currentUserId || '')
        ) {
            return String(msg.id || '');
        }
    }

    return '';
}

function findMessageById(messageId) {
    if (!messageId || !currentChatMeta || !Array.isArray(currentChatMeta.messages)) return null;
    return currentChatMeta.messages.find(msg => String(msg.id || '') === String(messageId)) || null;
}
function resolveWrapMessageId(wrap) {
    if (!wrap) return '';

    const directId = String(wrap?.dataset?.messageId || '').trim();
    if (directId) return directId;

    const storedIndex = Number.parseInt(String(wrap?.dataset?.storedIndex || ''), 10);
    if (Number.isFinite(storedIndex) && storedIndex >= 0 && Array.isArray(currentChatMeta?.messages)) {
        const fromStored = String(currentChatMeta.messages[storedIndex]?.id || '').trim();
        if (fromStored) {
            wrap.dataset.messageId = fromStored;
            return fromStored;
        }
    }

    const historyIndex = Number.parseInt(String(wrap?.dataset?.historyIndex || ''), 10);
    if (Number.isFinite(historyIndex) && historyIndex >= 0 && Array.isArray(chatHistory)) {
        const fromHistory = String(chatHistory[historyIndex]?.id || '').trim();
        if (fromHistory) {
            wrap.dataset.messageId = fromHistory;
            return fromHistory;
        }
    }

    return '';
}

function getReplySourceMessage(messageId) {
    if (!messageId || !currentChatMeta || !Array.isArray(currentChatMeta.messages)) return null;
    return currentChatMeta.messages.find(msg => String(msg.id || '') === String(messageId)) || null;
}

function getReplySnippet(messageId, maxLen = 90) {
    const msg = getReplySourceMessage(messageId);
    const text = String(msg?.content || msg?.text || '').trim().replace(/\s+/g, ' ');
    if (!text) return '';
    return text.length > maxLen ? text.slice(0, maxLen) + '…' : text;
}

function hideComposerReplyPreview() {
    const preview = document.getElementById('composerReplyPreview');
    const textEl = document.getElementById('composerReplyText');
    const titleEl = document.getElementById('composerReplyTitle');

    if (preview) preview.classList.add('hidden');
    if (textEl) textEl.textContent = '';
    if (titleEl) titleEl.textContent = 'Replying to';
}

function updateComposerReplyPreview() {
    const preview = document.getElementById('composerReplyPreview');
    const textEl = document.getElementById('composerReplyText');
    const titleEl = document.getElementById('composerReplyTitle');
    const targetMessageId = String(composerTargetMessageId || '').trim();

    if (!preview || !textEl) return;

    if (!targetMessageId) {
        hideComposerReplyPreview();
        return;
    }

    const msg = getReplySourceMessage(targetMessageId);
    const text = String(msg?.content || msg?.text || '').trim().replace(/\s+/g, ' ');

    if (!text) {
        hideComposerReplyPreview();
        return;
    }

    const senderName = String(msg?.name || '').trim() || 'Someone';
    if (titleEl) titleEl.textContent = 'Replying to ' + senderName;
    textEl.textContent = text.length > 140 ? text.slice(0, 140) + '\u2026' : text;
    preview.classList.remove('hidden');
}

function clearComposerReplyPreview() {
    composerTargetMessageId = '';
    hideComposerReplyPreview();
}
async function jumpToMessage(messageId) {
    const targetId = String(messageId || '').trim();
    if (!targetId) return false;

    if (highlightJumpTarget(targetId)) {
        return true;
    }

    if (loadingMessageJump && pendingJumpMessageId === targetId) {
        return false;
    }

    loadingMessageJump = true;
    pendingJumpMessageId = targetId;

    try {
        const loaded = await loadChunkContainingMessage(targetId);
        if (!loaded) return false;

        return highlightJumpTarget(targetId);
    } finally {
        loadingMessageJump = false;
        pendingJumpMessageId = '';
    }
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

function rollbackProvisionalOutgoingMessage(messageId) {
    if (!messageId) return;

    removeMessageFromLocalState(messageId);

    const wrap = document.querySelector(`.message-wrap[data-message-id="${CSS.escape(String(messageId))}"]`);
    const row = wrap?.closest('.message-row') || wrap?.closest('.assistant-info-row');
    row?.remove();
}

function handleSendNotJoinedError(data, options = {}) {
    if (String(data?.error || '').trim() !== 'not_joined') {
        return false;
    }

    renderSticky(String(data?.message || '').trim() || 'Not joined');

    const restoreText = String(options?.restoreText || '').trim();
    const composerInput = document.getElementById('input');
    if (restoreText && composerInput && String(composerInput.value || '').trim() === '') {
        composerInput.value = restoreText;
        autoGrowTextarea.call(composerInput);
        focusInput();
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
        await api('delete_message', {
            chat_id: currentChatId,
            message_id: messageId
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

    const targetMessageId = wrap ? resolveWrapMessageId(wrap) : '';
    if (!targetMessageId) return;

    if (!canUseComposerAssistant(true, targetMessageId)) return;

    const sourceText = String(sourceTextOverride || getMessageTextFromWrap(wrap)).trim();
    if (!sourceText) return;

    if (intent === 'reply') {
        composerTargetMessageId = targetMessageId;
        updateComposerReplyPreview();
        setComposerLoading(true);
    
        try {
            await runIncomingAssistPlaceholder(targetMessageId, sourceText, 'reply', {
                isManualReply: true
            });
        } finally {
            setComposerLoading(false);
        }
    
        return;
    }

    await runIncomingAssistPlaceholder(targetMessageId, sourceText, 'explain', {
        isManualReply: false
    });
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

    const messageId = String(meta?.messageId || msg?.id || '').trim();
    if (messageId) wrap.dataset.messageId = messageId;

    const bubble = document.createElement('div');
    bubble.className = 'bubble bubble-user';

    const replyToId = String(msg.reply_to || '').trim();
    const replySnippet = replyToId ? getReplySnippet(replyToId) : '';

    if (replyToId && replySnippet) {
        const replyChip = document.createElement('button');
        replyChip.type = 'button';
        replyChip.className = 'reply-reference-chip';
        replyChip.innerHTML = `
            <span class="reply-reference-label">Replying to</span>
            <span class="reply-reference-text">${escapeHtml(replySnippet)}</span>
        `;
        replyChip.addEventListener('click', async function (e) {
            e.stopPropagation();
            await jumpToMessage(replyToId);
        });
        bubble.appendChild(replyChip);
    }

    const textEl = document.createElement('div');
    textEl.className = 'bubble-text';
    textEl.textContent = msg.content;

    const time = document.createElement('time');
    time.className = 'bubble-time';
    time.textContent = formatBubbleTime(meta?.time || msg?.time || '');

    textEl.appendChild(time);
    bubble.appendChild(textEl);

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

    if (msg?.edited_at) {
        const edited = document.createElement('div');
        edited.className = 'message-meta-label';
        edited.textContent = 'edited';
        wrap.appendChild(edited);
    }
    
    row.appendChild(wrap);
    chat.appendChild(row);
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
    label.textContent = 'Unedited';

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
    row.dataset.groupSender = String(meta?.userId || meta?.user_id || name || 'other');

    const wrap = document.createElement('div');
    wrap.className = 'message-wrap incoming';
    if (meta?.storedIndex !== undefined && meta?.storedIndex !== null) wrap.dataset.storedIndex = String(meta.storedIndex);
    if (meta?.historyIndex !== undefined && meta?.historyIndex !== null) wrap.dataset.historyIndex = String(meta.historyIndex);
    if (meta?.messageId) wrap.dataset.messageId = String(meta.messageId);

    const avatar = document.createElement('div');
    avatar.className = 'incoming-avatar';
    const senderId = String(meta?.userId || meta?.user_id || '').trim();
    const isCurrentSender =
        (senderId && senderId === String(window.currentUserId || '')) ||
        isCurrentUserName(name || '');

    if (isCurrentSender && profileAvatarPrefs.avatarEnabled) {
        const profileAvatar = String(profileAvatarPrefs.avatar || '').trim();
        if (isImageAvatarData(profileAvatar)) {
            avatar.classList.add('profile-avatar-image');
            avatar.style.backgroundImage = 'url("' + profileAvatar + '")';
            avatar.textContent = '';
        } else {
            avatar.textContent = profileAvatar || getCurrentUserInitialsFallback();
        }
    } else if (isCurrentSender) {
        avatar.textContent = getCurrentUserInitialsFallback();
    } else {
        avatar.textContent = make_initials(name || '');
    }

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

    const time = document.createElement('time');
    time.className = 'bubble-time';
    time.textContent = formatBubbleTime(meta?.time || '');

    bubble.appendChild(content);
    content.appendChild(time);

    const contentWrap = document.createElement('div');
    contentWrap.className = 'message-content';
    
    contentWrap.appendChild(bubble);
    
    const labels = getMessageLabels(meta);
    labels.forEach(labelItem => {
        const assisted = document.createElement('div');
        assisted.className = 'message-meta-label';
        assisted.textContent = String(labelItem?.text || '');
        contentWrap.appendChild(assisted);
    });
    
    const avatarCol = document.createElement('div');
    avatarCol.className = 'incoming-avatar-col';
    avatarCol.appendChild(avatar);

    const featherBtn = document.createElement('button');
    featherBtn.type = 'button';
    featherBtn.className = 'incoming-feather-btn';
    featherBtn.setAttribute('aria-label', 'Draft response');
    featherBtn.setAttribute('title', 'Draft response');
    featherBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-reply-fill" viewBox="0 0 16 16" aria-hidden="true" style="transform:scaleX(-1)"><path d="M5.921 11.9 1.353 8.62a.72.72 0 0 1 0-1.238L5.921 4.1A.716.716 0 0 1 7 4.719V6c1.5 0 6 0 7 8-2.5-4.5-7-4-7-4v1.281c0 .56-.606.898-1.079.62z"/></svg>';
    featherBtn.addEventListener('pointerdown', function (e) {
        const inputFocused = document.activeElement === document.getElementById('input');
        if (!inputFocused) return;
        e.preventDefault();
        document.getElementById('input')?.focus();
        featherBtn._suppressNextClick = true;
        if (wrap.classList.contains('feather-handled') || wrap.classList.contains('feather-generating')) return;
        runBubbleFeatherAssist(wrap);
    });
    featherBtn.addEventListener('click', async function (e) {
        if (featherBtn._suppressNextClick) {
            featherBtn._suppressNextClick = false;
            return;
        }
        e.stopPropagation();
        if (wrap.classList.contains('feather-handled') || wrap.classList.contains('feather-generating')) return;
        await runBubbleFeatherAssist(wrap);
    });

    const featherUsed = document.createElement('span');
    featherUsed.className = 'incoming-feather-handled';
    featherUsed.textContent = '\u2713';
    featherUsed.setAttribute('aria-hidden', 'true');

    const featherCol = document.createElement('div');
    featherCol.className = 'incoming-feather-col';
    featherCol.appendChild(featherBtn);
    featherCol.appendChild(featherUsed);

    wrap.appendChild(avatarCol);
    wrap.appendChild(contentWrap);
    wrap.appendChild(featherCol);
    
    attachMessageMenu(wrap, 'assist');

    row.appendChild(wrap);
    chat.appendChild(row);
    applyGroupedRowState(row);
    updateFeatherLatest();
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
    updateHeaderForChat(chat || { title: 'New chat', participant_ids: [], participant_snapshots: {} });
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
        const data = await api('load_chat', {
            chat_id: currentChatId
        });

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
            !hasAutoDraftHandled(data.chat.id, String(msg.id || '')) &&
            !msg?.reply_to
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
        
    } catch (e) {
        console.log('poll error', e);
    }
}

async function pollChatIndex() {
    if (!isLoggedIn) return;

    try {
        const data = await fetchChatList();
        if (!data?.ok) return;
        applyChatIndexUpdate(data, { preferredChatId: currentChatId });
    } catch (err) {
        console.log('chat index poll error', err);
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
        const members = getChatMemberEntries(chat);
        const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object' ? chat.participant_snapshots : {};
        participantsWrap.innerHTML = members.map(member => {
            const snap = snapshots[member.id] || {};
            const email = String(snap?.email || '').trim();
            return `
            <div class="panel-text-row">
                <strong>${escapeHtml(member.name)}</strong>
                <span>${escapeHtml(email)}</span>
            </div>
        `;
        }).join('') || '<div class="panel-text">No participants yet.</div>';
    }

    if (metaWrap) {
        metaWrap.innerHTML = `
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

async function openInviteModal(chatId = null) {
    if (!isLoggedIn) {
        openAuthModal('login');
        return;
    }

    const targetChatId = chatId || currentChatId;
    if (!targetChatId) {
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Open a chat first.');
        } else {
            renderSticky('Open a chat first.');
        }
        return;
    }

    const targetChat = chatListCache.find(c => c.id === targetChatId)
        || (Array.isArray(archivedChatListCache) ? archivedChatListCache.find(c => c.id === targetChatId) : null)
        || (currentChatMeta && currentChatMeta.id === targetChatId ? currentChatMeta : null);

    if (!isGuestUser()) {
        try {
            await loadSidebarContacts(true);
        } catch (err) {
            console.error('Could not refresh contacts for invite modal', err);
        }
    }

    const mount = document.getElementById('inviteModalMount');
    if (!mount) return;
    const canManageMembers = canCurrentUserManageChatMembers(targetChat);
    const canCreateInviteLink = isOwnerChat(targetChat) && !isGuestUser();

    mount.innerHTML = `
        <div id="inviteModal" class="settings-modal" data-chat-id="${escapeHtml(String(targetChatId || ''))}">
            <div class="settings-modal-card">
                <button class="settings-modal-close" type="button" onclick="closeInviteModal()">×</button>
                <div class="settings-modal-sub">People in this chat</div>                
                ${canManageMembers ? `
                        <div class="sidebar-search-wrap">
                            <input
                                class="sidebar-inline-search"
                                id="peopleSidebarContactsSearch"
                                type="search"
                                placeholder="Find people"
                                oninput="renderPeopleInviteContactsList('${escapeHtml(String(targetChatId || ''))}')">
                    <div id="peopleInviteContactsList"></div>
                </div>
                ` : ''}
                <div class="settings-section" style="margin-top:10px;">
                    <div id="peopleMemberList" data-can-manage="${canManageMembers ? '1' : '0'}"></div>
                </div>
                ${!canManageMembers && isGuestUser() ? `
                <div class="settings-section">
                    <div class="sidebar-simple-card" style="margin-top:6px;">
                        <div class="sidebar-simple-title">You need to sign up to invite people</div>
                    </div>
                </div>
                ` : ''}
                ${canCreateInviteLink ? `
                <div class="settings-section">
                    <div class="invite-share-row">
                        <div class="sidebar-contact-empty" style="text-align:left;padding:0;">Invite a contact via</div>
                        <a class="share-btn share-btn-wa" href="#" onclick="shareInviteVia('whatsapp','${escapeHtml(String(targetChatId || ''))}');return false;" title="Share via WhatsApp">
                            <img src="/assets/images/whatsapp.png" alt="WhatsApp" width="48" height="48">
                        </a>
                        <a class="share-btn share-btn-tg" href="#" onclick="shareInviteVia('telegram','${escapeHtml(String(targetChatId || ''))}');return false;" title="Share via Telegram">
                            <img src="/assets/images/telegram.png" alt="Telegram" width="48" height="48">
                        </a>
                        <a class="share-btn share-btn-teams" href="#" onclick="shareInviteVia('teams','${escapeHtml(String(targetChatId || ''))}');return false;" title="Share via Teams">
                            <img src="/assets/images/teams.png" alt="Teams" width="48" height="48">
                        </a>
                        <a class="share-btn share-btn-x" href="#" onclick="shareInviteVia('x','${escapeHtml(String(targetChatId || ''))}');return false;" title="Share via X">
                            <img src="/assets/images/x.png" alt="X" width="48" height="48">
                        </a>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:space-between;">
                        <div class="sidebar-contact-empty" style="text-align:left;padding:0;">Or send them a link to join</div>
                        <button type="button" class="settings-ghost-btn" onclick="copyDirectInviteLinkFromPeopleModal('${escapeHtml(String(targetChatId || ''))}')">Copy link</button>
                    </div>
                    <div id="inviteNotice" style="text-align:center;" class="settings-notice"></div>
                </div>
                ` : ''}
            </div>
        </div>
    `;

    const modal = document.getElementById('inviteModal');
    if (modal) modal.classList.remove('hidden');

    renderPeopleMembersList(targetChatId);
    renderPeopleInviteContactsList(targetChatId);
}

async function copyDirectInviteLinkFromPeopleModal(chatId = '') {
    const notice = document.getElementById('inviteNotice');
    const modalChatId = String(document.getElementById('inviteModal')?.dataset?.chatId || '').trim();
    const targetChatId = String(chatId || modalChatId || currentChatId || '').trim();
    if (!targetChatId) return;

    const targetChat = chatListCache.find(c => String(c?.id || '').trim() === targetChatId)
        || (Array.isArray(archivedChatListCache) ? archivedChatListCache.find(c => String(c?.id || '').trim() === targetChatId) : null)
        || (currentChatMeta && String(currentChatMeta?.id || '').trim() === targetChatId ? currentChatMeta : null);

    if (!isOwnerChat(targetChat) || isGuestUser()) {
        if (notice) notice.textContent = 'Only the owner can copy invite links.';
        return;
    }

    try {
        const data = await api('create_invite', {
            chat_id: targetChatId,
            name: 'Guest',
            email: ''
        });

        if (!data?.ok) {
            if (notice) notice.textContent = String(data?.error || 'Could not create invite link.');
            return;
        }

        const link = String(data?.invite_link || data?.invite_path || '').trim();
        if (!link) {
            if (notice) notice.textContent = 'Could not create invite link.';
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(link);
        }
        if (notice) notice.textContent = 'Invite link copied.';
    } catch (err) {
        console.error('Could not copy direct invite link', err);
        if (notice) notice.textContent = 'Could not copy invite link.';
    }
}

async function shareInviteVia(platform, chatId = '') {
    const notice = document.getElementById('inviteNotice');
    const targetChatId = String(chatId || document.getElementById('inviteModal')?.dataset?.chatId || currentChatId || '').trim();
    if (!targetChatId) return;
    const link = app_base_url + '/?chat=' + encodeURIComponent(targetChatId);
    if (notice) notice.textContent = '';
    const text = encodeURIComponent('Join my conversation on Tsjilp: ');
    const url = encodeURIComponent(link);
    const targets = {
        whatsapp: `https://wa.me/?text=${text}${url}`,
        telegram: `https://t.me/share/url?url=${url}&text=${text}`,
        teams:    `https://teams.microsoft.com/share?href=${url}`,
        x:        `https://x.com/intent/tweet?url=${url}&text=${text}`,
    };
    const shareUrl = targets[platform];
    if (shareUrl) window.open(shareUrl, '_blank', 'noopener,noreferrer');
}

function getContactStableId(contact = null) {
    return String(contact?.id || contact?.contact_id || contact?.user_id || '').trim();
}

function getPeopleContactsSearchValue() {
    return String(document.getElementById('peopleSidebarContactsSearch')?.value || '').trim().toLowerCase();
}

function getPeopleInviteCandidates(chat = null) {
    const participants = new Set(getChatParticipantContactIds(chat));
    const q = getPeopleContactsSearchValue();

    if (q.length < 1) {
        return [];
    }

    return (contactsListCache || [])
        .map(contact => {
            const id = getContactStableId(contact);
            const name = String(contact?.display_name || contact?.name || id).trim() || id;
            return { contact, id, name };
        })
        .filter(item => {
            if (!item.id || participants.has(item.id)) return false;
            const status = getContactStatus(item.contact);
            if (!showAllContacts && status !== 'active') return false;
            return contactMatchesSearch(item.contact, q);
        })
        .sort((a, b) => {
            const lenDiff = a.name.length - b.name.length;
            if (lenDiff !== 0) return lenDiff;
            return a.name.localeCompare(b.name);
        })
        .slice(0, 5)
        .map(item => item.contact);
}

function renderPeopleInviteContactsList(chatId = null) {
    const mount = document.getElementById('peopleInviteContactsList');
    if (!mount) return;

    const targetChatId = String(chatId || document.getElementById('inviteModal')?.dataset?.chatId || currentChatId || '').trim();
    const chat = (targetChatId && currentChatMeta && String(currentChatMeta?.id || '').trim() === targetChatId)
        ? currentChatMeta
        : (chatListCache.find(c => String(c?.id || '').trim() === targetChatId)
            || (Array.isArray(archivedChatListCache) ? archivedChatListCache.find(c => String(c?.id || '').trim() === targetChatId) : null));

    if (!chat) {
        mount.innerHTML = '<div class="sidebar-contact-empty">Could not load contacts.</div>';
        return;
    }

    const query = getPeopleContactsSearchValue();
    const candidates = getPeopleInviteCandidates(chat);

    if (!query) {
        mount.innerHTML = '';
        return;
    }

    if (!candidates.length) {
        mount.innerHTML = '<div class="sidebar-contact-empty">No matching contacts.</div>';
        return;
    }

    mount.innerHTML = candidates.map(contact => {
        const id = getContactStableId(contact);
        const name = String(contact?.display_name || contact?.name || id).trim() || id;
        const avatarHtml = renderMemberAvatar('chat-history-avatar', {
            name,
            avatar: String(contact?.avatar || '').trim(),
            initials: String(contact?.initials || make_initials(name)).trim()
        });

        return `
            <div class="people-member-row">
                <div class="people-member-main">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        ${avatarHtml}
                        <div class="people-member-name">${escapeHtml(name)}</div>
                    </div>
                </div>
                <button type="button" class="settings-ghost-btn" onclick="addContactToPeopleModal('${escapeHtml(id)}')">+ Add</button>
            </div>
        `;
    }).join('');
}

function getPeopleMembersFromChat(chat = null) {
    if (!chat) return [];

    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object'
        ? chat.participant_snapshots
        : {};
    const ids = Array.isArray(chat?.participant_ids) ? chat.participant_ids : [];
    const members = [];
    const seen = new Set();

    ids.forEach(rawId => {
        const id = String(rawId || '').trim();
        if (!id || seen.has(id)) return;
        seen.add(id);

        const snap = snapshots[id] || {};
        const name = String(snap?.display_name || snap?.name || snap?.email || '').trim();
        if (!name) return;

        const membershipPeriods = Array.isArray(snap?.membership_periods)
            ? snap.membership_periods
            : (Array.isArray(snap?.periods) ? snap.periods : []);
        const hasOpenPeriod = membershipPeriods.some(period => {
            if (!period || typeof period !== 'object') return false;
            const endAtValue = Object.prototype.hasOwnProperty.call(period, 'end_at')
                ? period.end_at
                : period.to;
            return endAtValue === null || String(endAtValue || '').trim() === '';
        });
        const derivedActive = membershipPeriods.length
            ? hasOpenPeriod
            : true;

        members.push({
            member_id: id,
            participant_id: id,
            user_id: String(snap?.user_id || '').trim(),
            added_by_user_id: String(snap?.added_by_user_id || '').trim(),
            avatar: String(snap?.avatar || '').trim(),
            initials: String(snap?.initials || '').trim(),
            is_active: !!derivedActive,
            name,
            role: String(snap?.role || 'member').trim().toLowerCase() || 'member'
        });
    });

    if (!members.length && !ids.length && !Object.keys(snapshots).length) {
        const fallbackMembers = getChatMemberEntries(chat);
        fallbackMembers.forEach(member => {
            const id = String(member?.id || '').trim();
            if (!id || seen.has(id)) return;
            seen.add(id);
            members.push({
                member_id: id,
                participant_id: id,
                user_id: '',
                added_by_user_id: String(member?.added_by_user_id || '').trim(),
                avatar: String(member?.avatar || '').trim(),
                initials: String(member?.initials || '').trim(),
                is_active: true,
                name: String(member?.name || '').trim(),
                role: 'member'
            });
        });
    }

    return members;
}

function renderPeopleMembersList(chatId = null) {
    const list = document.getElementById('peopleMemberList');
    if (!list) return;

    const targetChatId = String(chatId || document.getElementById('inviteModal')?.dataset?.chatId || currentChatId || '').trim();
    const chat = (targetChatId && currentChatMeta && String(currentChatMeta?.id || '').trim() === targetChatId)
        ? currentChatMeta
        : (chatListCache.find(c => String(c?.id || '').trim() === targetChatId)
            || (Array.isArray(archivedChatListCache) ? archivedChatListCache.find(c => String(c?.id || '').trim() === targetChatId) : null));

    if (!chat) {
        list.innerHTML = '<div class="sidebar-contact-empty">Could not load members.</div>';
        return;
    }

    const members = getPeopleMembersFromChat(chat);
    const canManageMembers = String(list.dataset.canManage || '0') === '1';
    const currentUserId = String(window.currentUserId || '').trim();
    const totalMembers = Array.isArray(chat?.participant_ids)
        ? chat.participant_ids.length
        : members.length;
    const isGroupChat = totalMembers > 2;
    const otherMembersCount = members.filter(member => String(member?.user_id || '').trim() !== currentUserId).length;

    if (!otherMembersCount) {
        list.innerHTML = '<div class="sidebar-contact-empty" style="text-align:left;">Only you are in this chat.</div>';
        return;
    }

    list.innerHTML = members.map(member => {
        const memberId = String(member.member_id || member.participant_id || '').trim();
        const memberUserId = String(member.user_id || '').trim();
        const memberAddedByUserId = String(member.added_by_user_id || '').trim();
        const isOwnerMember = String(member.role || '').toLowerCase() === 'owner' || (memberUserId && memberUserId === String(chat?.owner_user_id || '').trim());
        const isCurrentUserMember = memberUserId !== '' && memberUserId === currentUserId;
        const isActive = !!member.is_active;
        const canManageThisMember = !!(canManageMembers && !isCurrentUserMember && (isOwnerChat(chat) || (currentUserId !== '' && memberAddedByUserId !== '' && memberAddedByUserId === currentUserId)) && memberId);
        const canToggle = !!(canManageThisMember && isGroupChat && !isOwnerMember);
        const canCopyInvite = !!(canManageThisMember && !isOwnerMember);
        const copyInviteBtn = canCopyInvite
            ? `<button type="button" class="settings-ghost-btn" onclick="copyMemberInviteLinkFromPeopleModal('${escapeHtml(memberId)}')"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5m-8.5 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3m11 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3"/></svg></button>`
            : '';

        const trailingAction = isOwnerMember
            ? '<span class="people-member-role">Owner</span>'
            : canToggle
                ? `<div style="display:flex;gap:8px;align-items:center;"><button type="button" class="recipient-pill ${isActive ? 'active' : ''}" onclick="toggleMemberAccessFromPeopleModal('${escapeHtml(memberId)}', ${isActive ? '0' : '1'})">${isActive ? 'ON' : 'OFF'}</button>${copyInviteBtn}</div>`
                : `<div style="display:flex;gap:8px;align-items:center;"><span class="people-member-role">${isGroupChat ? (isActive ? 'Active' : 'Inactive') : 'Active'}</span>${copyInviteBtn}</div>`;

        const avatarHtml = renderMemberAvatar('chat-history-avatar', {
            name: member.name,
            avatar: member.avatar,
            initials: member.initials
        });

        return `
            <div class="people-member-row">
                <div class="people-member-main">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        ${avatarHtml}
                        <div class="people-member-name">${escapeHtml(member.name)}</div>
                    </div>
                </div>
                ${trailingAction}
            </div>
        `;
    }).join('');
}

async function addContactToPeopleModal(contactId = '') {
    const id = String(contactId || '').trim();
    const notice = document.getElementById('inviteNotice');
    const modalChatId = String(document.getElementById('inviteModal')?.dataset?.chatId || '').trim();
    const targetChatId = modalChatId || String(currentChatId || '').trim();
    if (!id || !targetChatId) return;

    try {
        const data = await api('prepare_uid_invite', {
            chat_id: targetChatId,
            uid: id
        });

        if (!data?.ok) {
            const msg = String(data?.error || 'Could not add member.');
            if (notice) notice.textContent = msg;
            if (msg.toLowerCase().includes('not logged')) {
                openAuthModal('login');
            }
            return;
        }

        const resolvedChat = data?.chat || null;
        if (resolvedChat && String(resolvedChat?.id || '') === targetChatId) {
            currentChatMeta = resolvedChat;
            mergeChatIntoListCaches(resolvedChat);
            updateHeaderForChat(resolvedChat);
            renderSidebarChatDetails(resolvedChat);
            renderPeopleMembersList(targetChatId);
            renderPeopleInviteContactsList(targetChatId);
            if (String(currentChatId || '').trim() === targetChatId) {
                await loadChat(targetChatId, { scrollToBottom: false, focusComposer: false });
            }
        }

        if (notice) notice.textContent = 'Member added.';
    } catch (err) {
        console.error('Could not add member from people modal', err);
        if (notice) notice.textContent = 'Could not add member.';
    }
}

async function copyMemberInviteLinkFromPeopleModal(memberId = '') {
    const id = String(memberId || '').trim();
    if (!id) return;
    await copyUidInviteFromPeopleModal(id);
}

async function copyUidInviteFromPeopleModal(uid = '') {
    const id = String(uid || '').trim();
    const notice = document.getElementById('inviteNotice');
    const modalChatId = String(document.getElementById('inviteModal')?.dataset?.chatId || '').trim();
    const targetChatId = modalChatId || String(currentChatId || '').trim();
    if (!id || !targetChatId) return;

    try {
        const data = await api('prepare_uid_invite', {
            chat_id: targetChatId,
            uid: id
        });

        if (!data?.ok) {
            if (notice) notice.textContent = String(data?.error || 'Could not prepare invite link.');
            return;
        }

        const link = String(data?.invite_link || buildInviteUidLink(targetChatId, id)).trim();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(link);
        }
        if (notice) notice.textContent = 'Invite link copied.';

        if (data?.chat && String(data?.chat?.id || '') === targetChatId) {
            currentChatMeta = data.chat;
            mergeChatIntoListCaches(data.chat);
            renderPeopleMembersList(targetChatId);
        }
    } catch (err) {
        console.error('Could not copy uid invite link', err);
        if (notice) notice.textContent = 'Could not copy invite link.';
    }
}

async function toggleMemberAccessFromPeopleModal(memberId = '', memberActive = 1) {
    const id = String(memberId || '').trim();
    const modalChatId = String(document.getElementById('inviteModal')?.dataset?.chatId || '').trim();
    const targetChatId = modalChatId || String(currentChatId || '').trim();
    if (!id || !targetChatId) return;
    const nextActive = memberActive ? 1 : 0;

    try {
        const data = await api('toggle_chat_participant_access', {
            chat_id: targetChatId,
            member_id: id,
            participant_id: id,
            target_active: nextActive
        });

        if (!data?.ok) {
            const msg = String(data?.error || 'Could not update member access.');
            if (msg.toLowerCase().includes('not logged')) {
                openAuthModal('login');
            } else if (typeof showInlineNotice === 'function') {
                showInlineNotice(msg);
            } else {
                renderSticky(msg);
            }
            return;
        }

        if (data?.chat && String(data?.chat?.id || '') === targetChatId) {
            currentChatMeta = data.chat;
        }

        mergeChatIntoListCaches(currentChatMeta || data?.chat || {});
        updateHeaderForChat(currentChatMeta || data?.chat || {});
        renderSidebarChatDetails(currentChatMeta || data?.chat || {});
        renderPeopleMembersList(targetChatId);
        applyChatSearch();
        syncChatSearchInput();

        if (String(currentChatId || '').trim() === targetChatId) {
            await loadChat(targetChatId, { scrollToBottom: false, focusComposer: false });
        }
        renderSticky(nextActive ? 'Member access restored.' : 'Member access revoked.');
    } catch (err) {
        console.error('Could not toggle participant access from people modal', err);
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Could not update member access.');
        } else {
            renderSticky('Could not update member access.');
        }
    }
}

function closeInviteModal() {
 const mount = document.getElementById('inviteModalMount');
 if (mount) mount.innerHTML = '';
}

async function handlePendingInviteOnLoad() {
 const hasInviteRouteContext = !!(pendingInviteId || pendingChatId || pendingFromName || pendingUid || pendingMemberToken || pendingSignupComplete);
 const storedInviteContext = hasInviteRouteContext ? readPendingInviteContext() : null;
 const effectivePendingInviteId = pendingInviteId || storedInviteContext?.invite_id || '';
 const effectivePendingChatId = pendingChatId || storedInviteContext?.chat_id || '';
 const effectivePendingFromName = pendingFromName || storedInviteContext?.from_name || '';
 const effectivePendingUid = pendingUid || storedInviteContext?.uid || '';
 const storedGuestUid = effectivePendingChatId ? readStoredGuestIdentityForChat(effectivePendingChatId) : '';
 const storedGuestName = effectivePendingChatId ? readStoredGuestNameForChat(effectivePendingChatId) : '';

 if (pendingMemberToken) {
     try {
         const data = await api('resolve_member_token', {
            member_token: pendingMemberToken
        });

         if (data.ok) {
             const wasLoggedIn = isLoggedIn;
            handleSendNotJoinedError(data);
             isLoggedIn = true;
             currentUserMemberKind = String(data.member_kind || 'invited_member');
             if (data.user_id) {
                 window.currentUserId = String(data.user_id);
             }
             setTsjilp('settings.memberToken', pendingMemberToken);

             if (!wasLoggedIn && data.member_path) {
                 window.location.href = data.member_path + (String(data.member_path).includes('?') ? '&' : '?') + 't=' + Date.now();
                 return;
             }

             applyGuestSidebarRestrictions();
             refreshAssistantUi();
             if (data.chat_id) {
                 currentChatId = data.chat_id;
             }
             if (!wasLoggedIn && data.chat_title) {
                 showInlineNotice('Saved your return link for ' + data.chat_title + '.');
             }
             if (data.chat_id) {
                 try {
                     await refreshChatList(data.chat_id);
                     await loadChat(data.chat_id);
                 } catch (err) {
                     console.error('Could not load resolved member chat', err);
                 }
             }
             return;
         }
     } catch (err) {
         console.error('Could not resolve member token', err);
     }
 }

 if (effectivePendingChatId) {
     if (!isLoggedIn) {
         if (!effectivePendingUid) {
             openGuestJoinModal(effectivePendingChatId, effectivePendingFromName, {
                suggestedUid: storedGuestUid,
                suggestedName: storedGuestName
             });
             return;
         }

         try {
             const joinData = await api('accept_guest_join', {
                chat_id: effectivePendingChatId,
                uid: effectivePendingUid
            });

             if (joinData?.ok) {
                 isLoggedIn = true;
                 if (joinData.user_id) {
                     window.currentUserId = String(joinData.user_id);
                 }
                 if (joinData.guest_token) {
                     setTsjilp('settings.memberToken', String(joinData.guest_token));
                 }
                 saveStoredGuestIdentityForChat(
                    effectivePendingChatId,
                          String(joinData.contact_id || joinData.user_id || effectivePendingUid || '').trim(),
                    String(joinData.display_name || '').trim()
                 );
                 currentUserMemberKind = String(joinData.member_kind || 'invited_member');
                 applyGuestSidebarRestrictions();
                 refreshAssistantUi();
                 await refreshChatList(joinData.chat_id || effectivePendingChatId);
                 await loadChat(joinData.chat_id || effectivePendingChatId);
                 clearPendingInviteContext();
                 return;
             }

             if (joinData?.error) {
                if (String(joinData.error || '').toLowerCase().includes('not logged')) {
                    openAuthModal('login');
                } else if (typeof showInlineNotice === 'function') {
                    showInlineNotice(String(joinData.error || 'Could not open invite link.'));
                } else {
                    renderSticky(String(joinData.error || 'Could not open invite link.'));
                }
             }

             return;
         } catch (err) {
             console.error('Could not resolve uid invite for guest', err);
            openAuthModal('login');
             return;
         }
     }

     try {
         await refreshChatList(effectivePendingChatId);
         await loadChat(effectivePendingChatId);

         const loadedChatId = String(currentChatMeta?.id || currentChatId || '');
         if (loadedChatId === String(effectivePendingChatId)) {
             if (pendingSignupComplete) {
                 openSignupCompleteModal(currentChatMeta?.title || '', effectivePendingFromName);
             }
             clearPendingInviteContext();
             return;
         }

         if (isGuestUser()) {
             const displayName = String(profileAvatarPrefs?.name || config?.user?.name || '').trim();
             const joinData = await api('accept_guest_join', {
                chat_id: effectivePendingChatId,
                display_name: displayName
            });

             if (joinData?.ok) {
                 if (joinData.guest_token) {
                     setTsjilp('settings.memberToken', String(joinData.guest_token));
                 }
                 if (joinData.user_id) {
                     window.currentUserId = String(joinData.user_id);
                 }
                 saveStoredGuestIdentityForChat(
                    effectivePendingChatId,
                    String(joinData.contact_id || joinData.user_id || '').trim(),
                    String(joinData.display_name || displayName || '').trim()
                 );
                 currentUserMemberKind = String(joinData.member_kind || 'invited_member');
                 await refreshChatList(joinData.chat_id || effectivePendingChatId);
                 await loadChat(joinData.chat_id || effectivePendingChatId);
                 if (pendingSignupComplete) {
                     openSignupCompleteModal(currentChatMeta?.title || joinData.chat_title || '', effectivePendingFromName);
                 }
                 clearPendingInviteContext();
                 renderSticky('Invite accepted. You were added to this chat.');
                 return;
             }
         }

         const joinData = await api('join_shared_chat', {
            chat_id: effectivePendingChatId,
            uid: effectivePendingUid
         });

         if (joinData?.ok && joinData.chat_id) {
             await refreshChatList(joinData.chat_id);
             await loadChat(joinData.chat_id);
             if (pendingSignupComplete) {
                 openSignupCompleteModal(currentChatMeta?.title || joinData.chat_title || '', effectivePendingFromName);
             }
             clearPendingInviteContext();
             return;
         }

         if (joinData?.error) {
            if (String(joinData.error || '').toLowerCase().includes('not logged')) {
                openAuthModal('login');
            } else if (typeof showInlineNotice === 'function') {
                showInlineNotice(String(joinData.error || 'Could not open shared chat.'));
            } else {
                renderSticky(String(joinData.error || 'Could not open shared chat.'));
            }
         }
     } catch (err) {
         console.error('Could not open shared chat', err);
     }
     return;
 }

 if (!effectivePendingInviteId) {
     if (!isLoggedIn) return;
     try {
         const consumeData = await api('consume_pending_invites');
         if (consumeData.ok && Array.isArray(consumeData.accepted) && consumeData.accepted.length) {
             const latest = consumeData.accepted[0];
             await refreshChatList(latest.chat_id);
             await loadChat(latest.chat_id);
             if (pendingSignupComplete) {
                 openSignupCompleteModal(currentChatMeta?.title || latest.chat_title || '', pendingFromName || storedInviteContext.from_name || '');
             }
             clearPendingInviteContext();
         }
     } catch (err) {
         console.error('Could not consume pending invites', err);
     }
     return;
 }

 try {
     const data = await api('get_invite', { invite_id: effectivePendingInviteId });
     if (data.ok && data.invite) {
         const inviteStatus = String(data.invite.status || 'pending');
         const inviteChatId = String(data.invite.chat_id || '').trim();
         const inviteKind   = String(data.invite.invite_kind || 'account');

         // Logged-in (non-guest) user: join the chat directly regardless of invite kind
         if (isLoggedIn && !isGuestUser()) {
             if (inviteStatus !== 'pending' && inviteStatus !== 'accepted') {
                 showInlineNotice('This invite link has already been used.');
                 return;
             }
             if (inviteChatId) {
                 try {
                     const joinData = await api('join_shared_chat', { chat_id: inviteChatId });
                     if (joinData?.ok && joinData.chat_id) {
                         await refreshChatList(joinData.chat_id);
                         await loadChat(joinData.chat_id);
                         if (pendingSignupComplete) {
                             openSignupCompleteModal(currentChatMeta?.title || joinData.chat_title || '', effectivePendingFromName);
                         }
                         clearPendingInviteContext();
                         return;
                     }
                     if (joinData?.error && typeof showInlineNotice === 'function') {
                         showInlineNotice(String(joinData.error));
                     }
                 } catch (err) {
                     console.error('Could not join chat from invite', err);
                 }
             }
             return;
         }

         if (inviteStatus !== 'pending') {
             showInlineNotice('This invite link has already been used.');
             return;
         }

         if (inviteKind === 'guest') {
             pendingGuestJoinChatId = inviteChatId;
             pendingGuestJoinSuggestedUid = '';
             pendingGuestJoinSuggestedName = '';
             openGuestJoinModal(inviteChatId, data.invite.owner_name || data.invite.name || '', {
                 disableRememberedGuest: true
             });
             return;
         }
     }
 } catch (err) {
     console.error('Could not load invite', err);
 }

 if (!isLoggedIn) {
     try {
         const data = await api('get_invite', { invite_id: effectivePendingInviteId });
         if (data.ok && data.invite) {
             if (String(data.invite.status || 'pending') !== 'pending') {
                 showInlineNotice('This invite link has already been used.');
                 return;
             }

             if (String(data.invite.invite_kind || 'account') === 'guest') {
                 pendingGuestJoinChatId = String(data.invite.chat_id || '').trim();
                 pendingGuestJoinSuggestedUid = '';
                 pendingGuestJoinSuggestedName = '';
                 openGuestJoinModal(data.invite.chat_id || '', data.invite.owner_name || data.invite.name || '', {
                     disableRememberedGuest: true
                 });
                 return;
             }

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

    const data = await api('accept_invite', { invite_id: effectivePendingInviteId });

    if (data.ok && data.chat_id) {
        await refreshChatList(data.chat_id);
        await loadChat(data.chat_id);
        if (pendingSignupComplete) {
            openSignupCompleteModal(currentChatMeta?.title || data.chat_title || '', effectivePendingFromName);
        }
        clearPendingInviteContext();
        return;
    }

    if (data.needs_verification) {
        openAuthModal('login');
        if (typeof showInlineNotice === 'function') {
            showInlineNotice('Please verify your email first, then open the invite again.');
        } else {
            renderSticky('Please verify your email first, then open the invite again.');
        }
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
            await fetch('auth/logout.php', {
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
        const msg = String(data.error || 'Could not accept invite.');
        if (msg.toLowerCase().includes('not logged')) {
            openAuthModal('login');
        }
        if (typeof showInlineNotice === 'function') {
            showInlineNotice(msg);
        } else {
            renderSticky(msg);
        }
    }
}

let pendingGuestJoinChatId = '';
let pendingGuestJoinSuggestedUid = '';
let pendingGuestJoinSuggestedName = '';

async function openGuestJoinModal(chatId = '', inviterName = '', options = {}) {
    pendingGuestJoinChatId = chatId;
    pendingGuestJoinSuggestedUid = String(options?.suggestedUid || '').trim();
    pendingGuestJoinSuggestedName = String(options?.suggestedName || '').trim();
    const cleanInviterName = String(inviterName || pendingFromName || '').trim();
    savePendingInviteContext(pendingInviteId, chatId || pendingChatId, cleanInviterName);
    const mount = document.getElementById('guestJoinModalMount');
    if (!mount) return;

    const inviteSubtitle = cleanInviterName
        ? 'Join the conversation with ' + escapeHtml(cleanInviterName)
        : 'Join the conversation';
    const hasRememberedGuest = pendingGuestJoinSuggestedUid !== '' && !options?.disableRememberedGuest;
    const suggestedLabel = pendingGuestJoinSuggestedName || 'this guest';

    mount.innerHTML = `
    <div id="guestJoinModal" class="auth-modal">
        <div class="auth-modal-backdrop" onclick="closeGuestJoinModal()"></div>
        <div class="auth-modal-card">
            <button class="auth-modal-close" type="button" aria-label="Close" onclick="closeGuestJoinModal()">×</button>
            <div class="auth-flow-title">You're invited</div>
            <div class="auth-flow-sub">${inviteSubtitle}</div>
            ${hasRememberedGuest ? `
                <div id="guestJoinRememberedBlock">
                    <div class="auth-flow-sub" style="margin-top:0;">Continue as ${escapeHtml(suggestedLabel)}?</div>
                    <button class="auth-continue-btn" type="button" onclick="continueGuestJoinAsRemembered()">Continue as ${escapeHtml(suggestedLabel)}</button>
                    <button class="auth-continue-btn guest-secondary-btn" type="button" onclick="showGuestJoinNameInput()">Use another name</button>
                </div>
            ` : ''}
            <div id="guestJoinNameBlock" class="${hasRememberedGuest ? 'hidden' : ''}">
                <input class="auth-email-input" id="guestJoinNameInput" type="text" placeholder="Your name" autocomplete="name">
                <button class="auth-continue-btn" type="button" onclick="submitGuestJoin()">Continue as guest</button>
            </div>
            <div class="guest-join-divider">or</div>
            <div class="guest-join-actions">
                <button class="auth-continue-btn guest-secondary-btn" type="button" onclick="closeGuestJoinModal(); openAuthModal('login');">
                    Log in
                </button>
                <button class="auth-continue-btn guest-secondary-btn" type="button" onclick="closeGuestJoinModal(); openAuthModal('signup');">
                    Sign up
                </button>
            </div>
            <div id="guestJoinNotice" class="auth-inline-notice hidden"></div>
        </div>
    </div>
`;
    const modal = document.getElementById('guestJoinModal');
    if (modal) modal.classList.remove('hidden');

    const input = document.getElementById('guestJoinNameInput');
    if (input && !hasRememberedGuest) {
        input.focus();
        input.addEventListener('keydown', async function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            await submitGuestJoin();
        });
    } else if (input) {
        input.addEventListener('keydown', async function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            await submitGuestJoin();
        });
    }
}

function showGuestJoinNameInput() {
    const remembered = document.getElementById('guestJoinRememberedBlock');
    const nameBlock = document.getElementById('guestJoinNameBlock');
    if (remembered) remembered.classList.add('hidden');
    if (nameBlock) nameBlock.classList.remove('hidden');

    const input = document.getElementById('guestJoinNameInput');
    if (input) input.focus();
}

function closeGuestJoinModal() {
    const mount = document.getElementById('guestJoinModalMount');
    if (mount) mount.innerHTML = '';
    pendingGuestJoinChatId = '';
    pendingGuestJoinSuggestedUid = '';
    pendingGuestJoinSuggestedName = '';
}

async function handleGuestJoinSuccess(data, fallbackDisplayName = '') {
    const chatId = pendingGuestJoinChatId || pendingChatId;
    const notice = document.getElementById('guestJoinNotice');
    const resolvedDisplayName = String(data?.display_name || fallbackDisplayName || '').trim();

    if (data?.guest_token) {
        setTsjilp('settings.memberToken', String(data.guest_token));
    }

    saveStoredGuestIdentityForChat(
        chatId,
        String(data?.contact_id || data?.user_id || '').trim(),
        resolvedDisplayName
    );

    isLoggedIn = true;
    currentUserMemberKind = String(data?.member_kind || 'invited_member');
    if (data?.user_id) {
        window.currentUserId = String(data.user_id);
    }
    applyGuestSidebarRestrictions();
    refreshAssistantUi();

    if (notice) {
        const shownName = resolvedDisplayName || 'guest';
        notice.innerHTML = 'Joined as ' + escapeHtml(shownName) + '.<br>Your return link is <a href="' + escapeHtml(String(data?.member_path || '')) + '">' + escapeHtml(String(data?.member_path || '')) + '</a>';
        notice.classList.remove('hidden');
    }

    if (data?.member_path) {
        window.location.href = data.member_path + (String(data.member_path).includes('?') ? '&' : '?') + 't=' + Date.now();
        return;
    }

    if (data?.chat_id) {
        try {
            await refreshChatList(data.chat_id);
            await loadChat(data.chat_id);
        } catch (err) {
            console.error('Could not load joined guest chat', err);
        }
    }

    setTimeout(() => {
        closeGuestJoinModal();
    }, 1000);
}

async function continueGuestJoinAsRemembered() {
    const chatId = pendingGuestJoinChatId || pendingChatId;
    const notice = document.getElementById('guestJoinNotice');
    const uid = String(pendingGuestJoinSuggestedUid || '').trim();

    if (!chatId || !uid) {
        showGuestJoinNameInput();
        return;
    }

    if (notice) {
        notice.textContent = 'Opening chat...';
        notice.classList.remove('hidden');
    }

    try {
       const data = await api('accept_guest_join', {
            chat_id: chatId,
            uid
        });

        if (!data?.ok) {
            clearStoredGuestIdentityForChat(chatId);
            pendingGuestJoinSuggestedUid = '';
            pendingGuestJoinSuggestedName = '';
            showGuestJoinNameInput();
            if (notice) {
                notice.textContent = data?.error || 'Could not continue as remembered guest.';
                notice.classList.remove('hidden');
            }
            return;
        }

        await handleGuestJoinSuccess(data, pendingGuestJoinSuggestedName || '');
    } catch (err) {
        if (notice) {
            notice.textContent = 'Could not continue as remembered guest.';
            notice.classList.remove('hidden');
        }
        console.error('Could not continue as remembered guest', err);
    }
}

function openTsjilpInfoModal() {
    const mount = document.getElementById('tsjilpInfoModalMount');
    if (!mount) return;

    mount.innerHTML = `
    <div id="tsjilpInfoModal" class="auth-modal">
        <div class="auth-modal-backdrop" onclick="closeTsjilpInfoModal()"></div>
        <div class="auth-modal-card">
            <button class="auth-modal-close" type="button" aria-label="Close" onclick="closeTsjilpInfoModal()">x</button>
            <div class="tsjilp-info-modal-body">
                <h3>What is Tsjilp?</h3>
                <p>A silent ghostwriter for human conversations. It works quietly in the background while you chat.</p>
                <p>You write messages like you always do. Tsjilp helps you say things more clearly, understand others better, and avoid misunderstandings.</p>
                <p>Because people don't always communicate well. We write too fast, too emotional, or just wrong. That creates friction.</p>
                <p>Tsjilp makes conversations calmer, clearer, and easier.</p>
                <p>With friends, family, work, or clients.</p>
                <p>It doesn't replace people.<br>It helps people communicate better.</p>
                <p><strong>Better conversations. Not more messages.</strong></p>
            </div>
        </div>
    </div>
`;
}

function closeTsjilpInfoModal() {
    const mount = document.getElementById('tsjilpInfoModalMount');
    if (mount) mount.innerHTML = '';
}

async function submitGuestJoin() {
    const chatId = pendingGuestJoinChatId || pendingChatId;
    const input = document.getElementById('guestJoinNameInput');
    const notice = document.getElementById('guestJoinNotice');
    const displayName = input ? input.value.trim() : '';
    const inviteId = String(pendingInviteId || '').trim();

    if (!chatId) {
        if (notice) {
            notice.textContent = 'Missing chat link.';
            notice.classList.remove('hidden');
        }
        return;
    }

    if (!displayName) {
        if (notice) {
            notice.textContent = 'Enter a display name.';
            notice.classList.remove('hidden');
        }
        return;
    }

    const takenNames = await fetchPublicChatParticipantNames(chatId);
    const normalizedDisplayName = normalizeInviteDisplayName(displayName);
    if (normalizedDisplayName && takenNames.some(name => normalizeInviteDisplayName(name) === normalizedDisplayName)) {
        if (notice) {
            notice.textContent = 'This name is already used in this chat. Please use another name.';
            notice.classList.remove('hidden');
        }
        return;
    }

    if (notice) {
        notice.textContent = 'Joining chat...';
        notice.classList.remove('hidden');
    }

    try {
       const data = await api('accept_guest_join', {
            chat_id: chatId,
            display_name: displayName,
            invite_id: inviteId
        });

        if (!data.ok) {
            if (notice) {
                notice.textContent = data.error || 'Could not join this chat.';
                notice.classList.remove('hidden');
            }
            return;
        }

        await handleGuestJoinSuccess(data, displayName);
    } catch (err) {
        if (notice) {
            notice.textContent = 'Could not join this chat.';
            notice.classList.remove('hidden');
        }
        console.error('Could not join guest chat', err);
    }
}

function openAiModal() {
    const mount = document.getElementById('aiModalMount');
    if (!mount) return;

    const selectedProvider = String(userAssistantSettings?.provider || 'Assistant').trim() || 'Assistant';
    const aiModalTitle = 'Ask ' + selectedProvider + '...';

    mount.innerHTML = `
        <div id="aiModal" class="ai-popover" style="
            position: fixed;
            top: 92px;
            right: 16px;
            width: min(360px, calc(100vw - 28px));
            z-index: 19;
            min-height: 65px
        ">
            <div class="ai-popover-card">
                <div id="aiModalResults" class="ai-stream hidden">
                </div>
            </div>
        </div>
    `;

    renderAiModalStream();

    const aiModalResults = document.getElementById('aiModalResults');
    if (aiModalResults) {
        const savedIndex = parseInt(String(getTsjilp('ui.readingSizeIndex') ?? '0'), 10);
        const zooms = [1, 1.06, 1.12, 1.18];
        const i = Number.isFinite(savedIndex) && savedIndex >= 0 && savedIndex < zooms.length ? savedIndex : 0;
        aiModalResults.style.zoom = zooms[i];
    }

    const input = document.getElementById('aiModalInput');
    if (!input) return;

    input.addEventListener('keydown', async function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        await submitAiModal();
    });

    input.addEventListener('focus', function () {
        requestAnimationFrame(positionAiModal);
    });

    setTimeout(() => {
        try {
            input.focus({ preventScroll: true });
        } catch (e) {
            input.focus();
        }
        positionAiModal();
    }, 0);

    aiModalViewportHandler = () => {
        if (assistantComposerMode === 'private') {
            positionPrivateAiModal();
        } else {
            positionAiModal();
        }
    };
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', aiModalViewportHandler);
        window.visualViewport.addEventListener('scroll', aiModalViewportHandler);
    }
    window.addEventListener('resize', aiModalViewportHandler);

    document.addEventListener('click', handleAiModalOutsideClick, true);

}

function positionAiModal() {
    const modal = document.getElementById('aiModal');
    if (!modal) return;

    const chatPaneRect = document.getElementById('chatScroll')?.getBoundingClientRect() || null;
    const leftPos = Math.max(3, Math.round((chatPaneRect?.left || 0) + 3));

    modal.style.left = `${leftPos}px`;
    modal.style.right = 'auto';
}

function renderAiModalStream() {
    const results = document.getElementById('aiModalResults');
    if (!results) return;

    if (!Array.isArray(aiModalStreamItems) || !aiModalStreamItems.length) {
        results.classList.add('hidden');
        results.innerHTML = '<div class="ai-modal-empty"></div>';
        return;
    }

    results.classList.remove('hidden');

    results.innerHTML = aiModalStreamItems.map((item, idx) => {
        const type = String(item?.type || 'assistant').trim();
        const text = String(item?.text || '').trim();
        const isPending = !!item?.pending;
        if (!text) return '';

        if (type === 'user') {
            return `<div class="ai-modal-result is-user">${escapeHtml(text).replace(/\n/g, '<br>')}</div>`;
        }

        const assistantClasses = `ai-modal-result is-assistant${isPending ? ' is-thinking' : ' is-clickable'}`;
        const clickAttr = isPending ? '' : ` onclick="useAiModalResultInComposer(${idx})"`;
        return `<div class="${assistantClasses}"${clickAttr}>${escapeHtml(text).replace(/\n/g, '<br>')}</div>`;
    }).join('');

    results.scrollTop = results.scrollHeight;
}

function closeAiModal() {
    const mount = document.getElementById('aiModalMount');
    if (mount) mount.innerHTML = '';
    if (aiModalViewportHandler) {
        if (window.visualViewport) {
            window.visualViewport.removeEventListener('resize', aiModalViewportHandler);
            window.visualViewport.removeEventListener('scroll', aiModalViewportHandler);
        }
        window.removeEventListener('resize', aiModalViewportHandler);
        aiModalViewportHandler = null;
    }
    
    document.removeEventListener('click', handleAiModalOutsideClick, true);
}

function handleAiModalOutsideClick(e) {
    const modal = document.getElementById('aiModal');

    if (!modal || !modal.isConnected) return;
    if (e.target.closest('#aiModal')) return;
    if (e.target.closest('#composerWrap')) return;

    if (assistantComposerMode === 'private') {
        setAssistantComposerMode('public');
        return;
    }

    closeAiModal();
}

async function submitAiModal() {
    const modalInput = document.getElementById('aiModalInput');
    const composerInput = document.getElementById('input');

    const instruction = String(modalInput?.value || '').trim();
    if (!instruction) return;
    if (!canUseComposerAssistant(true)) return;

    // Behave like chat composer: clear field immediately after sending.
    if (modalInput) {
        modalInput.value = '';
    }

    aiModalStreamItems.push({
        type: 'user',
        text: instruction
    });

    const composerText = String(composerInput?.value || '').trim();
    const targetMessage = findMessageById(composerTargetMessageId || '');
    const targetText = String(targetMessage?.content || targetMessage?.text || '').trim();

    let contextBlock = '';
    if (composerText) {
        contextBlock += `Current composer text:\n${composerText}\n\n`;
    }
    if (targetText && targetText !== composerText) {
        contextBlock += `Current selected message:\n${targetText}\n\n`;
    }

    const assistantEntry = {
        type: 'assistant',
        text: 'Thinking...',
        pending: true
    };
    aiModalStreamItems.push(assistantEntry);
    renderAiModalStream();

    try {
        const payloadText = contextBlock
            ? `Instruction:
    ${instruction}

    Optional context:
    ${contextBlock}Answer the instruction directly. Use the optional context only if it helps.`
            : instruction;

        const historyMessages = askAiSessionMessages.slice(-9);
 
        const askAiMessages = buildAskAiSessionPayload(payloadText);

        const data = await sendToAPI(
            askAiMessages,
            'freetext'
        );

        const answer = getAssistantText(data, '') || 'No result';

        assistantEntry.text = answer;
        rememberAskAiExchange(payloadText, answer);

        assistantEntry.pending = false;

        askAiSessionMessages.push({ role: 'user', content: payloadText });
        askAiSessionMessages.push({ role: 'assistant', content: answer || 'No result' });

        renderAiModalStream();
    } catch (err) {
        console.error('AI modal failed', err);
        assistantEntry.text = 'No result';
        assistantEntry.pending = false;
        renderAiModalStream();
    }
}

async function submitPrivateComposerAi(instruction = '') {
    const composerInput = document.getElementById('input');
    const actionBtn = document.getElementById('actionBtn');
    const text = String(instruction || composerInput?.value || '').trim();

    if (!text) return;

    if (!isLoggedIn) {
        if (!canUseGuestTrial()) {
            setTimeout(() => { openAuthModal('signup', 'trial_exhausted'); }, 400);
            return;
        }
    } else if (!canUseComposerAssistant(true)) {
        return;
    }

    if (composerInput) {
        composerInput.value = '';
        autoGrowTextarea.call(composerInput);
    }
    if (actionBtn) actionBtn.disabled = true;

    openAiModal();
    positionPrivateAiModal();

    const results = document.getElementById('aiModalResults');
    results?.classList.remove('hidden');

    aiModalStreamItems.push({
        type: 'user',
        text
    });

    const assistantEntry = {
        type: 'assistant',
        text: 'Thinking...',
        pending: true
    };

    aiModalStreamItems.push(assistantEntry);
    renderAiModalStream();

    try {
        if (!isLoggedIn) {
            const prompt = [
                'You are the Tsjilp assistant in guest trial mode.',
                'Answer briefly, clearly, and helpfully.',
                'Show the value of conversation intelligence.',
                'Do not mention API keys or settings unless the user asks.',
                '',
                text
            ].join('\n');
            const data = await sendToAPI([{ role: 'user', content: prompt }], 'assist_guest');
            const answer = data?.reply || data?.text || data?.message || data?.error || 'I could not help with that right now.';
            assistantEntry.text = answer;
            consumeGuestCredit();
            updateGuestTrialUi();
        } else {
            const askAiMessages = buildAskAiSessionPayload(text);

            const data = await sendToAPI(
                askAiMessages,
                'freetext'
            );

            const answer = getAssistantText(data, '') || 'No result';

            assistantEntry.text = answer;
            rememberAskAiExchange(text, answer);
        }

        assistantEntry.pending = false;
        renderAiModalStream();
    } catch (err) {
        console.error('Private composer AI failed', err);
        assistantEntry.text = 'No result';
        assistantEntry.pending = false;
        renderAiModalStream();
    } finally {
        if (actionBtn) actionBtn.disabled = false;
        focusInput();
    }
}

function positionPrivateAiModal() {
    const modal = document.getElementById('aiModal');
    const composer = document.getElementById('composerWrap');
    if (!modal || !composer) return;

    const rect = composer.getBoundingClientRect();
    const chatTop = document.querySelector('.chat-top');
    const chatTopBottom = chatTop ? chatTop.getBoundingClientRect().bottom : 0;

    const vv = window.visualViewport;
    const vvHeight = vv ? vv.height : window.innerHeight;
    const vvOffsetTop = vv ? vv.offsetTop : 0;

    const bottomVal = vvHeight - (rect.top - vvOffsetTop) + 8;

    modal.style.position = 'fixed';
    modal.style.left = (rect.left + 8) + 'px';
    modal.style.right = 'auto';
    modal.style.top = 'auto';
    modal.style.bottom = bottomVal + 'px';
    modal.style.width = Math.min(420, rect.width - 16) + 'px';
}

function useAiModalResultInComposer(index = -1) {
    const input = document.getElementById('input');
    if (!input) return;

    const idx = Number(index);
    if (!Number.isInteger(idx) || idx < 0 || idx >= aiModalStreamItems.length) return;

    const item = aiModalStreamItems[idx];
    if (!item || String(item?.type || '') !== 'assistant') return;
    if (item?.pending) return;

    const text = String(item?.text || '').trim();
    if (!text) return;

    input.value = text;
    autoGrowTextarea.call(input);
    focusInput();
    closeAiModal();
    setAssistantComposerMode('public');
}


// -------------------------
// MOBILE KEYBOARD LAYOUT
// -------------------------
(function initMobileKeyboardLayout() {
    if (!window.visualViewport) return;
    if (!window.matchMedia('(hover: none) and (pointer: coarse)').matches) return;

    const vv = window.visualViewport;

    function update() {
        const kb = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
        document.documentElement.style.setProperty('--keyboard-height', kb + 'px');
    }

    update();
    vv.addEventListener('resize', update);
    vv.addEventListener('scroll', update);
    window.addEventListener('orientationchange', function () {
        setTimeout(update, 300);
    }, { passive: true });
})();


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
        const reasonMessages = {
            trial_exhausted: 'You\'ve used your test messages. Create a free account to continue.',
            private_message_guest: 'Sign in to send private messages'
        };
        const reasonClass = authState.reason === 'private_message_guest'
            ? 'auth-inline-notice auth-inline-notice-friendly'
            : 'auth-inline-notice';
        const reasonText = reasonMessages[authState.reason]
            ? `<div class="${reasonClass}" style="display:block;margin-bottom:14px;">${reasonMessages[authState.reason]}</div>`
            : '';

        root.innerHTML = `
            ${reasonText}
            <div class="auth-flow-title">${isSignup ? 'Sign up' : 'Log in'}</div>
            <div class="auth-flow-sub">
                ${isSignup
                    ? '<b>Communicate like a pro with Tsjilp</b><br>Already have an account? <span class="text-link" onclick="openAuthModal(\'login\')">Log in</span>'
                    : 'Log in to your Tsjilp account.<br>New here? <span class="text-link" onclick="openAuthModal(\'signup\')">Sign up</span>'}
            </div>

            <button class="auth-provider-btn hidden" type="button" onclick="startGoogleLogin()">
                Continue with Google
            </button>

            <button class="auth-provider-btn hidden" type="button" onclick="startAppleLogin()">
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
            <form id="authPasswordForm" autocomplete="on" onsubmit="submitPasswordLogin(event)">
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
                <button class="auth-continue-btn" type="submit">Continue</button>
    
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

    const res = await fetch('auth/email-check.php', {
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

async function submitPasswordLogin(event) {
    if (event) event.preventDefault();
    const password = document.getElementById('authPasswordInput')?.value || '';

    const res = await fetch('auth/password-login.php', {
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

    clearMemberQueryFlag();

    if (window.PasswordCredential) {
        try {
            const cred = new PasswordCredential({ id: authState.email, password });
            await navigator.credentials.store(cred);
        } catch (e) {}
    }

    window.location.reload();
}

async function submitPasswordSignup(event) {
    if (event) event.preventDefault();

    const form = document.getElementById('authSignupForm');
    const password = document.getElementById('authPasswordInput')?.value || '';
    const marketingOptIn = !!document.getElementById('authMarketingOptIn')?.checked;
    const pendingContext = typeof readPendingInviteContext === 'function'
        ? readPendingInviteContext()
        : { invite_id: '', chat_id: '', from_name: '' };

    if (!authState.name) {
        showInlineNotice('Missing name. Please go back and enter your name.');
        return;
    }

    if (!password) {
        showInlineNotice('Please enter a password.');
        return;
    }

    const res = await fetch('auth/password-signup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: authState.name,
            email: authState.email,
            password,
            marketing_opt_in: marketingOptIn,
            invite_id: pendingInviteId || pendingContext.invite_id || '',
            chat_id: pendingChatId || pendingContext.chat_id || '',
            from_name: pendingFromName || pendingContext.from_name || ''
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
    const res = await fetch('auth/signup-send-verification.php', {
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
    const pendingContext = typeof readPendingInviteContext === 'function' ? readPendingInviteContext() : {};
    const res = await fetch('auth/email-start.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email: authState.email,
            invite_id: pendingInviteId || String(pendingContext.invite_id || '').trim(),
            chat_id:   pendingChatId   || String(pendingContext.chat_id   || '').trim(),
            from_name: pendingFromName || String(pendingContext.from_name || '').trim()
        })
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

function startGoogleLogin() {
    const pendingContext = typeof readPendingInviteContext === 'function' ? readPendingInviteContext() : {};
    const inviteParams = new URLSearchParams();
    const iid = pendingInviteId || String(pendingContext.invite_id || '').trim();
    const cid = pendingChatId   || String(pendingContext.chat_id   || '').trim();
    const fn  = pendingFromName || String(pendingContext.from_name || '').trim();
    if (iid) inviteParams.set('invite', iid);
    if (cid) inviteParams.set('chat', cid);
    if (fn)  inviteParams.set('from', fn);
    const qs = inviteParams.toString();
    const base = 'auth/google-start.php';
    window.location.href = base + (qs ? (base.includes('?') ? '&' : '?') + qs : '');
}

function startAppleLogin() {
    const pendingContext = typeof readPendingInviteContext === 'function' ? readPendingInviteContext() : {};
    const inviteParams = new URLSearchParams();
    const iid = pendingInviteId || String(pendingContext.invite_id || '').trim();
    const cid = pendingChatId   || String(pendingContext.chat_id   || '').trim();
    const fn  = pendingFromName || String(pendingContext.from_name || '').trim();
    if (iid) inviteParams.set('invite', iid);
    if (cid) inviteParams.set('chat', cid);
    if (fn)  inviteParams.set('from', fn);
    const qs = inviteParams.toString();
    const base = 'auth/apple-start.php';
    window.location.href = base + (qs ? (base.includes('?') ? '&' : '?') + qs : '');
}

async function logoutUser() {
    if (isGuestUser()) {
        openGuestLogoutModal();
        return;
    }

    await fetch('auth/logout.php', {
        method: 'POST',
        credentials: 'same-origin'
    });
    currentUserMemberKind = '';
    window.location.href = '/?t=' + Date.now();
}

function buildGuestPersonalLink() {
    const token = String(getTsjilp('settings.memberToken') || '').trim();
    if (!token) return '';
    return window.location.origin + '/?member=' + encodeURIComponent(token);
}

function openGuestLogoutModal() {
    const mount = document.getElementById('guestLogoutModalMount');
    if (!mount) return;

    const personalLink = buildGuestPersonalLink();
    const linkHtml = personalLink
        ? `<a class="guest-logout-personal-link" href="${escapeHtml(personalLink)}">${escapeHtml(personalLink)}</a>`
        : '';

    mount.innerHTML = `
    <div id="guestLogoutModal" class="auth-modal">
        <div class="auth-modal-backdrop" onclick="closeGuestLogoutModal()"></div>
        <div class="auth-modal-card">
            <button class="auth-modal-close" type="button" aria-label="Close" onclick="closeGuestLogoutModal()">×</button>
            <div class="auth-flow-title">You&#8217;re leaving this session</div>
            <div class="auth-flow-sub">You can come back anytime using your personal link.</div>
            ${linkHtml ? `<div class="guest-logout-link-block">${linkHtml}</div>` : ''}
            <div class="guest-logout-actions">
                ${personalLink ? `<button class="auth-continue-btn" type="button" id="guestLogoutCopyBtn" onclick="copyGuestPersonalLink()">Copy personal link</button>` : ''}
                <button class="auth-continue-btn guest-secondary-btn" type="button" onclick="closeGuestLogoutModal()">Close</button>
            </div>
            <div id="guestLogoutCopyFeedback" class="auth-inline-notice hidden" style="display:none;"></div>
        </div>
    </div>
    `;
}

async function closeGuestLogoutModal() {
    const mount = document.getElementById('guestLogoutModalMount');
    if (mount) mount.innerHTML = '';

    await fetch('auth/logout.php', {
        method: 'POST',
        credentials: 'same-origin'
    });
    currentUserMemberKind = '';
    window.location.href = '/?t=' + Date.now();
}

async function copyGuestPersonalLink() {
    const personalLink = buildGuestPersonalLink();
    if (!personalLink) return;

    const btn = document.getElementById('guestLogoutCopyBtn');
    const feedback = document.getElementById('guestLogoutCopyFeedback');

    try {
        await navigator.clipboard.writeText(personalLink);
    } catch (err) {
        // fallback for older browsers / non-https
        const ta = document.createElement('textarea');
        ta.value = personalLink;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (_) {}
        document.body.removeChild(ta);
    }

    if (btn) {
        btn.textContent = 'Copied \u2713';
        btn.disabled = true;
    }
    if (feedback) {
        feedback.textContent = 'Copied \u2713';
        feedback.style.display = 'block';
        feedback.classList.remove('hidden');
    }
}

function applySettingsMeta(meta = {}) {
    userAssistantSettings.enabled = !!meta.assistant_enabled;
    userAssistantSettings.provider = meta.assistant_provider || 'ChatGPT';
    userAssistantSettings.hasKey = !!meta.assistant_has_key;
    userAssistantSettings.betaActive = !!meta.beta_active;
    userAssistantSettings.keyMasked = meta.assistant_key_masked || '';
    refreshAssistantUi();
}

let settingsSidebarLoaded = false;
let settingsAutosaveTimer = null;
let settingsAutosaveSaving = false;
let settingsAutosaveRequested = false;
let profileSidebarLoaded = false;
let profileAutosaveTimer = null;
let profileAutosaveSaving = false;
let profileAutosaveRequested = false;

function queueProfileAutoSave(delay = 260) {
    clearTimeout(profileAutosaveTimer);
    profileAutosaveTimer = setTimeout(() => {
        void flushProfileAutoSave();
    }, delay);
}

async function flushProfileAutoSave() {
    if (profileAutosaveSaving) {
        profileAutosaveRequested = true;
        return;
    }

    profileAutosaveSaving = true;

    try {
        do {
            profileAutosaveRequested = false;
            await saveProfile({ silent: true });
        } while (profileAutosaveRequested);
    } finally {
        profileAutosaveSaving = false;
    }
}

function applyProfileToSidebarUi(meta = {}) {
    const avatarEl = document.querySelector('.sidebar-avatar');
    const nameEl = document.querySelector('.sidebar-user-name');

    if (Object.prototype.hasOwnProperty.call(meta, 'name')) {
        profileAvatarPrefs.name = String(meta.name || '').trim();
    }
    if (Object.prototype.hasOwnProperty.call(meta, 'initials')) {
        profileAvatarPrefs.initials = String(meta.initials || '').trim();
    }
    if (Object.prototype.hasOwnProperty.call(meta, 'avatar')) {
        profileAvatarPrefs.avatar = String(meta.avatar || '').trim();
    }
    if (Object.prototype.hasOwnProperty.call(meta, 'avatarEnabled')) {
        profileAvatarPrefs.avatarEnabled = !!meta.avatarEnabled;
    }

    const activeAvatar = profileAvatarPrefs.avatarEnabled ? String(profileAvatarPrefs.avatar || '').trim() : '';
    const initials = String(profileAvatarPrefs.initials || '').trim() || String(meta.initials || '').trim();
    const isImageAvatar = isImageAvatarData(activeAvatar);

    if (avatarEl && (activeAvatar || initials)) {
        avatarEl.classList.toggle('has-image', isImageAvatar);

        if (isImageAvatar) {
            avatarEl.style.backgroundImage = 'url("' + activeAvatar + '")';
            avatarEl.textContent = '';
        } else {
            avatarEl.style.backgroundImage = '';
            avatarEl.textContent = activeAvatar || initials;
        }
    }

    if (nameEl && meta.name) {
        nameEl.textContent = String(meta.name);
    }

    if (chatListCache.length || archivedChatListCache.length) {
        renderChatList(chatListCache, currentChatId, archivedChatListCache);
    }

    if (currentChatMeta) {
        updateHeaderForChat(currentChatMeta);
    }
}

function initProfileAutoSave(root = document) {
    const form = root.querySelector('#profileForm');
    if (!form || form.dataset.autosaveBound === '1') return;

    form.dataset.autosaveBound = '1';

    const nonPasswordFields = form.querySelectorAll('#profileFirstName, #profileLastName, #profileAvatar');
    nonPasswordFields.forEach(el => {
        el.addEventListener('blur', () => queueProfileAutoSave());
        el.addEventListener('change', () => queueProfileAutoSave());
    });

    const visibilityToggle = root.querySelector('#profileVisibilityToggle');
    if (visibilityToggle) {
        visibilityToggle.addEventListener('change', () => queueProfileAutoSave(0));
    }

    const passwordFields = form.querySelectorAll('#profileCurrentPassword, #profileNewPassword, #profileNewPassword2');
    passwordFields.forEach(el => {
        el.addEventListener('blur', () => {
            const next = document.getElementById('profileNewPassword')?.value || '';
            const next2 = document.getElementById('profileNewPassword2')?.value || '';
            const current = document.getElementById('profileCurrentPassword')?.value || '';

            if (!next && !next2 && !current) return;
            queueProfileAutoSave();
        });
    });
}

function initEmbeddedProfileUi(root = document) {
    const avatarMaxBytes = 1024 * 1024;
    const picker = root.querySelector('#profileAvatarPicker');
    const input = root.querySelector('#profileAvatar');
    const upload = root.querySelector('#profileAvatarUpload');
    const uploadTrigger = root.querySelector('#profileAvatarUploadTrigger');
    const avatarEnabledInput = root.querySelector('#profileAvatarEnabled');
    const avatarInlineError = root.querySelector('#profileAvatarInlineError');
    const currentAvatar = root.querySelector('#profileAvatarCurrent');
    const currentAvatarEmoji = root.querySelector('#profileAvatarCurrentEmoji');
    const currentAvatarImage = root.querySelector('#profileAvatarCurrentImage');

    function setAvatarInlineError(message = '') {
        if (!avatarInlineError) return;
        const text = String(message || '').trim();
        avatarInlineError.textContent = text;
        avatarInlineError.classList.toggle('hidden', text === '');
    }

    function setAvatarEnabled(enabled) {
        if (!avatarEnabledInput) return;

        if (avatarEnabledInput.type === 'checkbox') {
            avatarEnabledInput.checked = !!enabled;
            return;
        }

        avatarEnabledInput.value = enabled ? '1' : '0';
    }

    function updateCurrentAvatarPreview(value = '') {
        if (!currentAvatar || !currentAvatarEmoji || !currentAvatarImage) return;

        const cleaned = String(value || '').trim();
        const isImage = isImageAvatarValue(cleaned);
        const firstName = String(root.querySelector('#profileFirstName')?.value || '').trim();
        const lastName = String(root.querySelector('#profileLastName')?.value || '').trim();
        const fullName = [firstName, lastName].filter(Boolean).join(' ').trim();
        let fallback = '';

        if (fullName && typeof getOwnerInitials === 'function') {
            fallback = String(getOwnerInitials(fullName) || '').trim();
        }

        if (!fallback) {
            fallback = String(root.querySelector('#profileMeta')?.dataset?.initials || '').trim();
        }

        if (!fallback && typeof getCurrentUserInitialsFallback === 'function') {
            fallback = String(getCurrentUserInitialsFallback() || '').trim();
        }

        if (!fallback) {
            fallback = 'U';
        }

        currentAvatar.classList.toggle('has-image', isImage);

        if (isImage) {
            currentAvatarImage.src = cleaned;
            currentAvatarImage.classList.remove('hidden');
            currentAvatarEmoji.classList.add('hidden');
            return;
        }

        currentAvatarEmoji.textContent = cleaned || fallback;
        currentAvatarEmoji.classList.remove('hidden');
        currentAvatarImage.classList.add('hidden');
        currentAvatarImage.src = '';
    }

    function clearEmojiSelection() {
        if (!picker) return;
        picker.querySelectorAll('.profile-avatar-option').forEach(node => node.classList.remove('active'));
    }

    function isImageAvatarValue(value = '') {
        return /^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+/=\s]+$/i.test(String(value || '').trim());
    }

    function getDataUrlByteSize(value = '') {
        const cleaned = String(value || '').trim();
        const comma = cleaned.indexOf(',');
        if (comma < 0) return 0;
        const base64 = cleaned.slice(comma + 1).replace(/\s+/g, '');
        try {
            const binary = atob(base64);
            return binary.length;
        } catch (e) {
            return 0;
        }
    }

    function readFileAsDataUrl(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result || ''));
            reader.onerror = () => reject(new Error('Could not read image file'));
            reader.readAsDataURL(file);
        });
    }

    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('Could not load selected image'));
            img.src = src;
        });
    }

    async function resizeAvatarDataUrl(file) {
        const sourceDataUrl = await readFileAsDataUrl(file);
        const image = await loadImage(sourceDataUrl);

        const sourceWidth = Number(image.naturalWidth || image.width || 0);
        const sourceHeight = Number(image.naturalHeight || image.height || 0);
        if (!sourceWidth || !sourceHeight) return sourceDataUrl;

        const maxSize = 220;
        const scale = Math.min(1, maxSize / Math.max(sourceWidth, sourceHeight));
        const targetWidth = Math.max(1, Math.round(sourceWidth * scale));
        const targetHeight = Math.max(1, Math.round(sourceHeight * scale));

        const canvas = document.createElement('canvas');
        canvas.width = targetWidth;
        canvas.height = targetHeight;

        const context = canvas.getContext('2d');
        if (!context) return sourceDataUrl;

        context.drawImage(image, 0, 0, targetWidth, targetHeight);
        return canvas.toDataURL('image/jpeg', 0.86);
    }

    if (picker && input && picker.dataset.boundUi !== '1') {
        picker.dataset.boundUi = '1';

        picker.querySelectorAll('.profile-avatar-option').forEach(btn => {
            btn.addEventListener('click', () => {
                const value = String(btn.dataset.avatar ?? '').trim();

                input.value = value;
                picker.querySelectorAll('.profile-avatar-option').forEach(node => node.classList.remove('active'));
                btn.classList.add('active');
                if (upload) upload.value = '';
                setAvatarInlineError('');
                updateCurrentAvatarPreview(value);
                setAvatarEnabled(value !== '');
                queueProfileAutoSave(120);
            });
        });
    }

    if (upload && input && upload.dataset.boundUi !== '1') {
        upload.addEventListener('change', async () => {
            const file = upload.files && upload.files[0] ? upload.files[0] : null;
            if (!file) return;

            const notice = root.querySelector('#profileNotice');
            setAvatarInlineError('');

            if (!String(file.type || '').toLowerCase().startsWith('image/')) {
                setAvatarInlineError('Please upload an image file.');
                upload.value = '';
                return;
            }

            if (file.size > avatarMaxBytes) {
                setAvatarInlineError('Image is too large. Max 1 MB.');
                upload.value = '';
                return;
            }

            try {
                const imageDataUrl = await resizeAvatarDataUrl(file);
                if (getDataUrlByteSize(imageDataUrl) > avatarMaxBytes) {
                    setAvatarInlineError('Image is too large. Max 1 MB.');
                    upload.value = '';
                    return;
                }

                input.value = imageDataUrl;
                clearEmojiSelection();
                updateCurrentAvatarPreview(imageDataUrl);
                setAvatarEnabled(true);
                setAvatarInlineError('');

                const saved = await saveProfile({ silent: true });
                if (!saved && notice && notice.textContent === '') {
                    notice.textContent = 'Could not save profile.';
                }
            } catch (e) {
                setAvatarInlineError('Could not process this image. Please try a different one.');
            }
        });
        upload.dataset.boundUi = '1';
    }

    if (uploadTrigger && upload && uploadTrigger.dataset.boundUi !== '1') {
        uploadTrigger.addEventListener('click', () => {
            upload.click();
        });
        uploadTrigger.dataset.boundUi = '1';
    }

    if (input) {
        setAvatarInlineError('');
        updateCurrentAvatarPreview(input.value);
        setAvatarEnabled(isImageAvatarValue(input.value) || String(input.value || '').trim() !== '');
    }

    const firstNameField = root.querySelector('#profileFirstName');
    const lastNameField = root.querySelector('#profileLastName');

    [firstNameField, lastNameField].filter(Boolean).forEach(field => {
        if (field.dataset.avatarPreviewBound === '1') return;

        field.addEventListener('input', () => {
            const selectedAvatar = String(input?.value || '').trim();
            if (!selectedAvatar) {
                updateCurrentAvatarPreview('');
            }
        });

        field.dataset.avatarPreviewBound = '1';
    });

    initProfileAutoSave(root);
}

function queueSettingsAutoSave(delay = 260) {
    clearTimeout(settingsAutosaveTimer);
    settingsAutosaveTimer = setTimeout(() => {
        void flushSettingsAutoSave();
    }, delay);
}

async function flushSettingsAutoSave() {
    if (settingsAutosaveSaving) {
        settingsAutosaveRequested = true;
        return;
    }

    settingsAutosaveSaving = true;

    try {
        do {
            settingsAutosaveRequested = false;
            await saveSettings({ silent: true });
        } while (settingsAutosaveRequested);
    } finally {
        settingsAutosaveSaving = false;
    }
}

function initSettingsAutoSave(root = document) {
    const form = root.querySelector('#settingsForm');
    if (!form || form.dataset.autosaveBound === '1') return;

    form.dataset.autosaveBound = '1';

    form.querySelectorAll('input, textarea, select').forEach(el => {
        const type = String(el.type || '').toLowerCase();
        if (type === 'hidden' || type === 'button' || type === 'submit') return;

        el.addEventListener('blur', () => queueSettingsAutoSave());
        el.addEventListener('change', () => queueSettingsAutoSave());
    });
}

function initEmbeddedSettingsUi(root = document) {
    const providerRadios = root.querySelectorAll('input[name="settings_assistant_provider"]');
    const enableCheckbox = root.querySelector('#settingsAssistantEnabled');
    const block = root.querySelector('#assistantSettingsBlock');
    const card = root.querySelector('.settings-assistance-card');
    const form = root.querySelector('#settingsForm');
    const keyInput = root.querySelector('#settingsAssistantApiKey');
    const initialKeyMask = String(keyInput?.dataset?.apiKeyMask || '');
    let providerKeys = {};
    let initialProvider = '';

    function getSelectedProvider() {
        const checked = root.querySelector('input[name="settings_assistant_provider"]:checked');
        return String(checked?.value || 'ChatGPT');
    }

    function isMaskedApiKey(value) {
        return value && initialKeyMask && value === initialKeyMask;
    }

    function persistProviderKeys() {
        if (!form) return;
        form.dataset.providerKeys = JSON.stringify(providerKeys);
    }

    function updateProviderUI() {
        const note = root.querySelector('#settingsApiNote a');
        if (!note) return;

        const provider = getSelectedProvider();
        const links = {
            ChatGPT: 'https://platform.openai.com/api-keys',
            Gemini: 'https://aistudio.google.com/app/apikey',
            Claude: 'https://console.anthropic.com/settings/keys'
        };

        note.href = links[provider] || links.ChatGPT;

        note.textContent = 'here';

        const providerLabel = root.querySelector('#settingsAssistantProviderLabel');
        if (providerLabel) {
            providerLabel.textContent = 'Model ' + provider;
        }
    }

    function syncKeyFieldFromProvider() {
        if (!keyInput) return;
        const provider = getSelectedProvider();
        const nextValue = providerKeys[provider] || (
            provider === initialProvider && initialKeyMask ? initialKeyMask : ''
        );
        keyInput.value = nextValue;
    }

    function updateAssistVisibility() {
        if (!enableCheckbox || !block) return;
        block.classList.toggle('assistance-disabled', !enableCheckbox.checked);

        if (card) {
            card.classList.toggle('enabled', enableCheckbox.checked);
        }
    }

    let currentProvider = getSelectedProvider();
    initialProvider = currentProvider;

    if (keyInput && !providerKeys[currentProvider]) {
        const initialValue = String(keyInput.value || '').trim();
        if (initialValue !== '' && !isMaskedApiKey(initialValue)) {
            providerKeys[currentProvider] = initialValue;
            persistProviderKeys();
        }
    }

    providerRadios.forEach(radio => {
        if (radio.dataset.boundUi === '1') return;
        radio.addEventListener('change', () => {
            if (keyInput) {
                const currentValue = String(keyInput.value || '');
                if (!isMaskedApiKey(currentValue)) {
                    providerKeys[currentProvider] = currentValue;
                } else {
                    delete providerKeys[currentProvider];
                }
            }
            currentProvider = getSelectedProvider();
            persistProviderKeys();
            updateProviderUI();
            syncKeyFieldFromProvider();
        });
        radio.dataset.boundUi = '1';
    });

    if (keyInput && keyInput.dataset.boundUi !== '1') {
        keyInput.addEventListener('input', () => {
            const currentValue = String(keyInput.value || '');
            if (!isMaskedApiKey(currentValue)) {
                providerKeys[getSelectedProvider()] = currentValue;
            } else {
                delete providerKeys[getSelectedProvider()];
            }
            persistProviderKeys();
        });
        keyInput.dataset.boundUi = '1';
    }

    if (enableCheckbox && !enableCheckbox.dataset.boundUi) {
        enableCheckbox.addEventListener('change', updateAssistVisibility);
        enableCheckbox.dataset.boundUi = '1';
    }

    updateProviderUI();
    syncKeyFieldFromProvider();
    updateAssistVisibility();
    initSettingsAutoSave(root);
}

function openNewChatPopover() {
    const pop = document.getElementById('newChatPopover');
    const input = document.getElementById('newChatTitle');
    if (!pop) return;

    pop.classList.remove('hidden');
    setTimeout(() => input?.focus(), 0);
}

function closeNewChatPopover() {
    const pop = document.getElementById('newChatPopover');
    const input = document.getElementById('newChatTitle');
    if (!pop) return;

    pop.classList.add('hidden');
    if (input) input.value = '';
}

function toggleNewChatPopover() {
    const pop = document.getElementById('newChatPopover');
    if (!pop) return;

    if (pop.classList.contains('hidden')) {
        openNewChatPopover();
    } else {
        closeNewChatPopover();
    }
}

function handleNewChatTitleKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        submitInlineNewChat();
    } else if (e.key === 'Escape') {
        e.preventDefault();
        closeNewChatPopover();
    }
}

function submitInlineNewChatFromEvent(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    void submitInlineNewChat();
    return false;
}

async function submitInlineNewChat() {
    if (isGuestUser()) {
        renderSticky('Sign up to create your own chats.');
        return;
    }

    const input = document.getElementById('newChatTitle');
    const title = String(input?.value || '').trim();
    if (!title) return;

    const created = await createNewChat(title);
    if (!created?.chat?.id) return;

    closeNewChatPopover();

    setTimeout(() => {
        if (typeof openInviteModal === 'function') {
            openInviteModal(created.chat.id);
        }
    }, 220);
}

async function openSettingsTab(preloadOnly = false) {
    if (!isLoggedIn) {
        if (!preloadOnly) {
            setSidebarTab('settings');
        }
        return;
    }

    if (isGuestUser()) {
        if (!preloadOnly) {
            setSidebarTab('settings');
        }
        await renderSettingsInSidebar(preloadOnly);
        return;
    }

    if (preloadOnly) {
        await renderSettingsInSidebar(true);
        return;
    }

    setSidebarTab('settings');

    const dropdown = document.getElementById('sidebarUserDropdown');
    if (dropdown) dropdown.classList.remove('open');
}

async function openProfileTab(preloadOnly = false) {
    if (!isLoggedIn) {
        if (!preloadOnly) {
            setSidebarTab('profile');
        }
        return;
    }

    if (preloadOnly) {
        await renderProfileInSidebar(true);
        return;
    }

    setSidebarTab('profile');

    const dropdown = document.getElementById('sidebarUserDropdown');
    if (dropdown) dropdown.classList.remove('open');
}

async function saveSettings(options = {}) {
    const form = document.getElementById('settingsForm');
    const assistantProvider = form?.querySelector('input[name="settings_assistant_provider"]:checked')?.value || 'ChatGPT';
    const assistantApiKeyRaw = String(form?.querySelector('#settingsAssistantApiKey')?.value || '');
    const assistantApiKey = /^[\x2A\u2022\u00B7]+$/u.test(assistantApiKeyRaw.trim()) ? '' : assistantApiKeyRaw.trim();

    const silent = !!options.silent;
    const marketing = !!document.getElementById('settingsMarketing')?.checked;
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

    const res = await fetch('auth/save-settings.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
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
        if (data.ok) {
            notice.textContent = silent ? '' : 'Settings saved.';
        } else {
            handleSendNotJoinedError(data);
            notice.textContent = data.error || 'Could not save settings.';
        }
    }

    if (data.ok) {
        userAssistantSettings.enabled = assistantEnabled;
        userAssistantSettings.provider = assistantProvider;
        userAssistantSettings.hasKey = !!data.assistant_has_key;
        userAssistantSettings.betaActive = userAssistantSettings.betaActive;
        userAssistantSettings.keyMasked = data.assistant_key_masked || userAssistantSettings.keyMasked;
        refreshAssistantUi();

        const keyStatus = document.getElementById('settingsAssistantKeyStatus');
        if (keyStatus) {
            keyStatus.textContent = userAssistantSettings.hasKey
                ? ('Saved key: ' + (userAssistantSettings.keyMasked || 'already stored'))
                : 'No API key stored yet. Leave this empty for normal chat.';
        }
        
        setTsjilp('settings.quickLanguages', quick_languages);
        
    }

    return !!data.ok;
}

async function saveProfile(options = {}) {
    const silent = !!options.silent;
    const guest = isGuestUser();

    const firstName = document.getElementById('profileFirstName')?.value.trim() || '';
    const lastName = guest ? '' : (document.getElementById('profileLastName')?.value.trim() || '');
    const avatar = document.getElementById('profileAvatar')?.value.trim() || '';
    const avatarEnabledField = document.getElementById('profileAvatarEnabled');
    const avatarEnabled = avatarEnabledField
        ? (avatarEnabledField.type === 'checkbox'
            ? !!avatarEnabledField.checked
            : String(avatarEnabledField.value || '') === '1')
        : true;
    const currentPassword = guest ? '' : (document.getElementById('profileCurrentPassword')?.value || '');
    const newPassword = guest ? '' : (document.getElementById('profileNewPassword')?.value || '');
    const newPassword2 = guest ? '' : (document.getElementById('profileNewPassword2')?.value || '');
    const visibilityToggle = document.getElementById('profileVisibilityToggle');
    const visibility = (!guest && visibilityToggle) ? (visibilityToggle.checked ? 'visible' : 'hidden') : null;
    const notice = document.getElementById('profileNotice');

    if (!firstName) {
        if (notice && !silent) notice.textContent = 'Please enter your name.';
        return false;
    }

    const res = await fetch('auth/save-profile.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            first_name: firstName,
            last_name: lastName,
            avatar,
            avatar_enabled: avatarEnabled,
            current_password: currentPassword,
            new_password: newPassword,
            new_password2: newPassword2,
            ...(visibility !== null ? { visibility } : {})
        })
    });

    const data = await res.json();

    if (notice) {
        if (data.ok) {
            notice.textContent = 'Profile saved.';

            handleSendNotJoinedError(data);
            if (silent) {
                clearTimeout(window.__profileSavedNoticeTimer || 0);
                window.__profileSavedNoticeTimer = setTimeout(() => {
                    const currentNotice = document.getElementById('profileNotice');
                    if (currentNotice && currentNotice.textContent === 'Profile saved.') {
                        currentNotice.textContent = '';
                    }
                }, 1400);
            }
        } else {
            notice.textContent = data.error || 'Could not save profile.';
        }
    }

    if (!data.ok) {
        return false;
    }

    applyProfileToSidebarUi({
        avatar: data.avatar || avatar,
        initials: data.initials || '',
        name: data.name || '',
        avatarEnabled: Object.prototype.hasOwnProperty.call(data || {}, 'avatar_enabled')
            ? !!data.avatar_enabled
            : avatarEnabled
    });

    if (data.password_changed) {
        const currentField = document.getElementById('profileCurrentPassword');
        const nextField = document.getElementById('profileNewPassword');
        const next2Field = document.getElementById('profileNewPassword2');
        if (currentField) currentField.value = '';
        if (nextField) nextField.value = '';
        if (next2Field) next2Field.value = '';
    }

    return true;
}

async function renderSettingsInSidebar(preloadOnly = false) {
    const host = document.getElementById('sidebarSettingsEmbed');
    if (!host) {
        console.warn('sidebarSettingsEmbed not found');
        return;
    }

    if (!isLoggedIn) {
        host.innerHTML = `
            <div class="sidebar-simple-panel">
                <div class="sidebar-simple-card">
                    <div class="sidebar-simple-title">Settings</div>
                    <div class="sidebar-simple-text">Log in or create a free account to manage the intelligence layer.</div>
                </div>
            </div>
        `;
        return;
    }

    if (isGuestUser()) {
        host.innerHTML = `
            <div class="sidebar-simple-panel">
                <div class="sidebar-simple-card">
                    <div class="sidebar-simple-title">Settings</div>
                    <div class="sidebar-simple-text">Sign up, it's free and enables a lot of features.</div>
                </div>
            </div>
        `;
        return;
    }

    if (settingsSidebarLoaded) {
        initEmbeddedSettingsUi(host);
        initSettingsAutoSave(host);
        quickLang();
        return;
    }

    if (!preloadOnly) {
        host.innerHTML = `<div class="sidebar-contact-empty">Loading...</div>`;
    }

    try {
        const urlBase = 'auth/settings-modal.php';
        const settingsUrl = urlBase + (urlBase.includes('?') ? '&' : '?') + 'embed=1';

        const res = await fetch(settingsUrl, {
            credentials: 'same-origin'
        });

        if (!res.ok) {
            throw new Error('Settings request failed');
        }

        host.innerHTML = await res.text();

        const meta = host.querySelector('#settingsMeta');
        if (meta) {
            applySettingsMeta({
                assistant_enabled: meta.dataset.assistantEnabled === '1',
                assistant_provider: meta.dataset.assistantProvider || 'ChatGPT',
                assistant_has_key: meta.dataset.assistantHasKey === '1',
                assistant_key_masked: meta.dataset.assistantKeyMasked || '',
                beta_active: meta.dataset.betaActive === '1'
            });
            meta.remove();
        }

        settingsSidebarLoaded = true;
        initEmbeddedSettingsUi(host);
        initSettingsAutoSave(host);
        quickLang();

    } catch (e) {
        host.innerHTML = `<div class="sidebar-contact-empty">Loading failed</div>`;
        console.error('renderSettingsInSidebar failed', e);
    }
}

async function renderProfileInSidebar(preloadOnly = false) {
    const host = document.getElementById('sidebarProfileEmbed');
    if (!host) {
        console.warn('sidebarProfileEmbed not found');
        return;
    }

    if (!isLoggedIn) {
        host.innerHTML = `
            <div class="sidebar-simple-panel">
                <div class="sidebar-simple-card">
                    <div class="sidebar-simple-title">Your profile</div>
                    <div class="sidebar-simple-text">Log in to manage your profile.</div>
                </div>
            </div>
        `;
        return;
    }

    if (profileSidebarLoaded) {
        initEmbeddedProfileUi(host);
        initProfileAutoSave(host);

        if (isGuestUser()) {
            host.querySelectorAll('#profileUsername, #profileLastName, #profileCurrentPassword, #profileNewPassword, #profileNewPassword2')
                .forEach(el => {
                    const section = el.closest('.settings-section');
                    if (section) section.classList.add('hidden');
                });

            const firstNameField = host.querySelector('#profileFirstName');
            if (firstNameField) {
                firstNameField.placeholder = 'Display name';
            }
        }
        return;
    }

    if (!preloadOnly) {
        host.innerHTML = `<div class="sidebar-contact-empty">Loading profile...</div>`;
    }

    try {
        const urlBase = 'auth/profile-modal.php';
        const profileUrl = urlBase + (urlBase.includes('?') ? '&' : '?') + 'embed=1';

        const res = await fetch(profileUrl, {
            credentials: 'same-origin'
        });

        if (!res.ok) {
            throw new Error('Profile request failed');
        }

        host.innerHTML = await res.text();

        const meta = host.querySelector('#profileMeta');
        const avatarFromField = host.querySelector('#profileAvatar')?.value || '';
        if (meta) {
            applyProfileToSidebarUi({
                avatar: String(avatarFromField || meta.dataset.avatar || '').trim(),
                initials: String(meta.dataset.initials || '').trim(),
                name: String(meta.dataset.name || '').trim(),
                avatarEnabled: String(meta.dataset.avatarEnabled || '1') !== '0'
            });
            meta.remove();
        }

        profileSidebarLoaded = true;
        initEmbeddedProfileUi(host);
        initProfileAutoSave(host);

        if (isGuestUser()) {
            host.querySelectorAll('#profileUsername, #profileLastName, #profileCurrentPassword, #profileNewPassword, #profileNewPassword2')
                .forEach(el => {
                    const section = el.closest('.settings-section');
                    if (section) section.classList.add('hidden');
                });

            const firstNameField = host.querySelector('#profileFirstName');
            if (firstNameField) {
                firstNameField.placeholder = 'Display name';
            }
        }
    } catch (e) {
        host.innerHTML = `<div class="sidebar-contact-empty">Could not load profile.</div>`;
        console.error('renderProfileInSidebar failed', e);
    }
}

window.quickLang = function(action, value) {
    const input = document.getElementById('settingsQuickLanguagesInput');
    const selected = document.getElementById('settingsQuickSelected');
    const dropdown = document.getElementById('settingsQuickDropdown');
    const multi = document.getElementById('settingsQuickMulti');
    const reading = document.getElementById('settingsLanguage');

    const labels = assistantTools.languages;

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
        queueSettingsAutoSave();
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
            if (multi) multi.classList.add('open');
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
    const scope =
        btn?.closest('#settingsModal, #settingsRoot, #sidebarSettingsEmbed') ||
        document.getElementById('settingsModal') ||
        document.getElementById('settingsRoot') ||
        document.getElementById('sidebarSettingsEmbed');

    if (!scope) return;

    scope.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    scope.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));

    if (btn) {
        btn.classList.add('active');
    } else {
        const activeTab = scope.querySelector('.settings-tab[data-tab="' + tabName + '"]');
        if (activeTab) activeTab.classList.add('active');
    }

    const panel = scope.querySelector('.settings-tab-panel[data-tab="' + tabName + '"]');
    if (panel) {
        panel.classList.add('active');
    }
};

function applyGuestSidebarRestrictions() {
    const guest = isGuestUser();

    const globalCompass = document.getElementById('globalCompass');
    if (globalCompass) {
        globalCompass.classList.toggle('hidden', guest);
    }

    const archivedSection = document.getElementById('archivedSection');
    if (archivedSection) {
        archivedSection.classList.toggle('hidden', guest);
    }

    const newChatTopBtn = document.getElementById('newChatTopBtn');
    if (newChatTopBtn) {
        newChatTopBtn.classList.toggle('hidden', guest);
        newChatTopBtn.disabled = guest;
    }

    const chatHeaderSettingsBtn = document.getElementById('chatHeaderSettingsBtn');
    if (chatHeaderSettingsBtn) {
        chatHeaderSettingsBtn.classList.toggle('hidden', guest);
        chatHeaderSettingsBtn.disabled = guest;
    }

}


async function setSidebarTab(tab = 'chats') {
    sidebarTab = String(tab || 'chats');
    applyGuestSidebarRestrictions();

    document.querySelectorAll('.sidebar-panel').forEach(panel => {
        panel.classList.toggle('active', panel.dataset.tab === sidebarTab);
    });

    document.querySelectorAll('.sidebar-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === sidebarTab);
    });

    const mobileNewChatTopBtn = document.getElementById('mobileNewChatTopBtn');
    if (mobileNewChatTopBtn) {
        mobileNewChatTopBtn.classList.toggle('hidden', sidebarTab !== 'chats');
    }

    if (sidebarTab === 'settings') {
        renderSettingsInSidebar();
    } else if (sidebarTab === 'profile') {
        renderProfileInSidebar();
    } else if (sidebarTab === 'contacts') {
        await loadSidebarContacts(true);
    }
}

document.addEventListener('click', function (e) {
    const multi = document.getElementById('settingsQuickMulti');
    if (!multi || !multi.classList.contains('open')) return;

    if (multi.contains(e.target)) return;

    multi.classList.remove('open');
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
        syncSidebarToggleButtonState();
    }
});

document.addEventListener('click', function(e) {
    if (e.target.closest('#refreshMemoryBtn')) {
        loadSidebarMemory();
    }
});

document.addEventListener('click', function (e) {
    if (e.target.closest('[data-action="toggle-contact-accordion"]')) return;
    if (e.target.closest('.contact-accordion')) return;
    if (e.target.closest('.sidebar-contact-item')) return;
    if (!activeContactAccordionId) return;

    closeContactAccordion();
    renderSidebarContacts();
});

document.addEventListener('click', function (e) {
    const target = e.target;

    // assistant menu
    if (
        assistantMenu &&
        !assistantMenu.classList.contains('hidden') &&
        !assistantMenu.contains(target) &&
        !assistantToggleBtn?.contains(target)
    ) {
        toggleAssistantMenu(false);
    }

    // message menus
    if (!target.closest('.message-menu-btn') && !target.closest('.message-menu')) {
        closeAllMessageMenus();
    }

    // chat context card
    const chatContextCard = document.getElementById('chatContextCard');
    const clickedChatContextToggle = target.closest('.js-chat-context-toggle');

    if (
        chatContextCard &&
        !chatContextCard.classList.contains('hidden') &&
        !chatContextCard.contains(target) &&
        !clickedChatContextToggle
    ) {
        chatContextCard.classList.add('hidden');
    }

    // chat header menu
    const headerMenu = document.getElementById('chatHeaderMenu');
    if (headerMenu && !target.closest('.chat-header-wa')) {
        headerMenu.classList.add('hidden');
    }
});

async function changePassword() {
    await saveProfile({ silent: false });
}
async function sendPasswordResetLink() {
    const email = (authState.email || '').trim();

    if (!email) {
        showInlineNotice('Please enter your email first.');
        return;
    }

    const res = await fetch('auth/password-reset-start.php', {
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

    const participants = Array.isArray(chat?.participant_ids) ? chat.participant_ids : [];
    const snapshots = chat?.participant_snapshots && typeof chat.participant_snapshots === 'object' ? chat.participant_snapshots : {};
    const messages = Array.isArray(chat?.messages) ? chat.messages : [];

    const summary =
        (chat?.summary?.text || '').trim() ||
        buildFallbackSummary(messages);

    summaryEl.textContent = summary || '—';
    participantsEl.textContent = participants.length
        ? participants.map(id => String((snapshots[id] || {}).display_name || (snapshots[id] || {}).name || (snapshots[id] || {}).email || 'User')).join(', ')
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

    if (!options?.isManualReply) {
        state.items.push({
            kind: 'thinking',
            targetMessageId
        });
        renderTemporaryFlow();
    }

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
                composerTargetMessageId = String(targetMessageId || '').trim();
        
                const input = document.getElementById('input');
                if (input) {
                    input.value = answer;
                    input.disabled = false;
                    autoGrowTextarea.call(input);
                }
        
                hideComposerThinking();
                focusInput();
                return data;
            }
        
            state.items.push({
                kind: 'assistant_notice',
                content: 'Message not clear.',
                targetMessageId
            });
        
            renderTemporaryFlow();
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

    try {
        const suggestion = await requestComposerSuggestion(rawMessage);
        const cleanedSuggestion = String(suggestion || '').trim();

        return {
            ok: true,
            reply_type: isMeaningfulComposerSuggestion(rawMessage, cleanedSuggestion) ? 'draft' : 'passive',
            reply: cleanedSuggestion
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

function getAssistantText(data, fallback = '') {
    const raw = data?.reply ?? data?.text ?? data?.message ?? data?.content ?? fallback;

    if (typeof raw !== 'string') {
        return String(raw || fallback).trim();
    }

    const text = raw.trim();
    if (!text) return fallback;

    try {
        const parsed = JSON.parse(text);
        return String(
            parsed?.content ??
            parsed?.reply ??
            parsed?.text ??
            fallback
        ).trim();
    } catch (e) {
        // Try regex extraction for malformed JSON-like strings (single quotes, wrong separators)
        const m = text.match(/["']?content["']?\s*[=:]\s*["']([^"']+)["']/s);
        if (m) return m[1].trim();
        return text;
    }
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

    const row = composer.querySelector('.composer-toolbar-row') || composer;
    const input = document.getElementById('input');

    if (input && input.dataset.placeholderBeforeThinking === undefined) {
        input.dataset.placeholderBeforeThinking = input.placeholder || '';
        input.placeholder = 'Thinking...';
    }

    composer.classList.add('composer-reply-thinking');
    document.getElementById('assistantComposerBtn')?.classList.add('is-writing');

    if (!row.querySelector('.thinking-dots')) {
        const dots = document.createElement('div');
        dots.className = 'thinking-dots';
        dots.innerHTML = `
            <span class="thinking-dot"></span>
            <span class="thinking-dot"></span>
            <span class="thinking-dot"></span>
        `;
        row.appendChild(dots);
    }
}

function hideComposerThinking() {
    const composer = document.querySelector('#composerWrap .composer-toolbar');
    if (!composer) return;

    const input = document.getElementById('input');
    if (input && input.dataset.placeholderBeforeThinking !== undefined) {
        input.placeholder = input.dataset.placeholderBeforeThinking;
        delete input.dataset.placeholderBeforeThinking;
    }

    composer.classList.remove('composer-reply-thinking');
    document.getElementById('assistantComposerBtn')?.classList.remove('is-writing');

    const row = composer.querySelector('.composer-toolbar-row') || composer;
    const dots = row.querySelector('.thinking-dots');
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

async function loadSidebarMemory() {
    if (isGuestUser()) return;
    if (!currentChatId) return;

    try {
        const data = await api('get_chat_memory', {
            chat_id: currentChatId
        });

        renderSidebarMemory(data);
        updateMemoryTimestamp();

        const latest = data?.summary_blocks?.slice(-1)?.[0] || null;
        const topics = Array.isArray(latest?.topics) ? latest.topics.filter(Boolean) : [];
        const currentMeta = chatCompassMetaCache[String(currentChatId || '')] || {};
        chatCompassMetaCache[String(currentChatId || '')] = {
            ...currentMeta,
            loaded: true,
            summary: topics.slice(0, 3).join(' · '),
            summaryTopics: topics.slice(0, 3)
        };

        chatDetailsModalCache[String(currentChatId || '')] = {
            ...(chatDetailsModalCache[String(currentChatId || '')] || {}),
            chat: currentChatMeta || getChatDetailsSourceChat(currentChatId) || null,
            memoryData: data
        };

        currentCompassState.openIssuesOffset = 0;
        currentCompassState.openIssuesHasMore = false;
        await loadCurrentOpenIssues(0, false);

        const allList = document.getElementById('allIssuesList');
        if (allList) {
            allList.innerHTML = '<div class="global-action global-action-muted">Click All to load open issues</div>';
        }
        allCompassState.loaded = false;
        allCompassState.loading = false;
    } catch (e) {
        console.error('Could not load sidebar memory', e);
    }
}

function updateMemoryTimestamp() {
    const el = document.getElementById('memoryLastUpdated');
    if (!el) return;
    const now = new Date().toLocaleTimeString();
    el.textContent = now;
}

function renderSidebarMemory(data) {
    const summaryList = document.getElementById('summaryList');
    const memoryList = document.getElementById('memoryList');

    if (!data) return;

    if (summaryList) {
        summaryList.innerHTML = '';

        const latest = data.summary_blocks?.slice(-1)[0];
        const topics = Array.isArray(latest?.topics) ? latest.topics.slice(0, 3) : [];

        if (topics.length) {
            topics.forEach(topic => {
                const el = document.createElement('div');
                el.className = 'global-action global-action-compact';
                el.textContent = topic;
                summaryList.appendChild(el);
            });
        } else {
            summaryList.innerHTML = '<div class="global-action global-action-muted">No summary yet</div>';
        }
    }

    if (memoryList) {
        memoryList.innerHTML = '';

        const stable = data.stable_memory || {};
        const items = [];

        ['priority', 'facts', 'people'].forEach(key => {
            (stable[key] || []).forEach(item => {
                const text = typeof item === 'string' ? item : item?.text;
                if (text) items.push(text);
            });
        });

        const uniqueItems = [...new Set(items)].slice(0, 3);

        if (uniqueItems.length) {
            uniqueItems.forEach(text => {
                const el = document.createElement('div');
                el.className = 'global-action global-action-compact global-action-soft';
                el.textContent = text;
                memoryList.appendChild(el);
            });
        } else {
            memoryList.innerHTML = '<div class="global-action global-action-muted">No memory yet</div>';
        }
    }
}
function applyMemoryView(view) {
    if (isGuestUser()) return;
    const currentCompass = document.getElementById('currentCompass');
    const allCompass = document.getElementById('allCompass');

    document.querySelectorAll('.memory-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });

    currentCompass?.classList.toggle('hidden', view !== 'current');
    allCompass?.classList.toggle('hidden', view !== 'all');

    setTsjilp('ui.memoryView', view);

    if (view === 'all' && !allCompassState.loaded && !allCompassState.loading) {
        loadAllOpenIssues();
    }
}

async function openCompassItem(chatId, messageId = '') {
    if (!chatId) return;

    if (String(currentChatId || '') !== String(chatId)) {
        await loadChat(chatId);
    }

    applyMemoryView('current');

    if (messageId) {
        scrollChatToBottom(String(messageId));
    }
}

async function loadCurrentOpenIssues(offset = 0, append = false) {
    if (isGuestUser()) return;
    const list = document.getElementById('globalActionList');
    const moreWrap = document.getElementById('globalActionMoreWrap');

    if (!currentChatId) return;

    try {
        const data = await api('get_chat_open_issues', {
            chat_id: currentChatId,
            offset,
            limit: 5
        });

        const items = Array.isArray(data?.items) ? data.items : [];
        const hasMore = !!data?.has_more;
        const nextOffset = Number.isFinite(Number(data?.next_offset))
            ? Number(data.next_offset)
            : (offset + items.length);

        if (!append) {
            currentChatOpenIssues = {};
            if (list) {
                list.innerHTML = '';
            }
        }

        items.forEach(item => {
            const messageId = String(item?.message_id || '').trim();
            if (!messageId) return;
            currentChatOpenIssues[messageId] = {
                text: String(item?.text || '').trim(),
                type: Number(item?.type || 1)
            };
        });

        if (list && !items.length && !append) {
            list.innerHTML = '<div class="global-action global-action-muted">Nothing pending</div>';
        } else if (list) {
            const grouped = {};
            items.forEach(item => {
                const type = Number(item?.type || 1);
                if (!grouped[type]) grouped[type] = [];
                grouped[type].push(item);
            });

            const titles = {
                1: 'Reminders',
                2: 'Unanswered questions',
                3: 'Assistant actions',
                4: 'Important',
                5: 'Follow-up'
            };

            Object.keys(grouped).sort().forEach(type => {
                const header = document.createElement('div');
                header.className = 'open-issue-group-title';
                header.textContent = titles[type] || 'Open issues';
                list.appendChild(header);

                grouped[type].forEach(item => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'open-issue-row compass-click';

                    const dot = document.createElement('span');
                    dot.className = 'open-issue-dot open-issue-type-' + type;

                    const copy = document.createElement('span');
                    copy.className = 'open-issue-copy';

                    const meta = document.createElement('span');
                    meta.className = 'open-issue-meta';
                    meta.textContent = getOpenIssueTypeLabel(type) + ' · ' + formatOpenIssueDate(item.time);

                    const text = document.createElement('span');
                    text.className = 'open-issue-text';
                    text.textContent = getOpenIssueDisplayText(item);

                    copy.appendChild(meta);
                    copy.appendChild(text);

                    row.appendChild(dot);
                    row.appendChild(copy);

                    row.addEventListener('click', async function () {
                        await openCompassItem(item.chat_id || currentChatId, item.message_id || '');
                        closeSidebarOnMobile();
                    });

                    list.appendChild(row);
                });
            });
        }

        currentCompassState.openIssuesOffset = nextOffset;
        currentCompassState.openIssuesHasMore = hasMore;

        if (moreWrap) {
            if (hasMore) {
                moreWrap.classList.remove('hidden');
                moreWrap.innerHTML = `
                    <button type="button" class="global-action global-action-compact" onclick="loadCurrentOpenIssues(${nextOffset}, true)">
                        Load more
                    </button>
                `;
            } else {
                moreWrap.classList.add('hidden');
                moreWrap.innerHTML = '';
            }
        }

        if (!append && !items.length) {
            chatCompassMetaCache[String(currentChatId || '')] = {
                ...(chatCompassMetaCache[String(currentChatId || '')] || {}),
                loaded: true,
                issueCount: 0
            };
            updateChatRowCompassStatus(currentChatId);
            document.querySelectorAll('.message-wrap.has-open-issue').forEach(wrap => {
                setWrapOpenIssueState(wrap, false);
            });
            return;
        }

        chatCompassMetaCache[String(currentChatId || '')] = {
            ...(chatCompassMetaCache[String(currentChatId || '')] || {}),
            loaded: true,
            issueCount: Number(data?.total ?? data?.count ?? (append ? Object.keys(currentChatOpenIssues).length : items.length))
        };
        updateChatRowCompassStatus(currentChatId);

        document.querySelectorAll('.message-wrap[data-message-id]').forEach(wrap => {
            const messageId = String(wrap.dataset.messageId || '').trim();
            setWrapOpenIssueState(wrap, !!currentChatOpenIssues[messageId]);
        });
    } catch (e) {
        console.error('Could not load current open issues', e);
        if (!append && list) {
            list.innerHTML = '<div class="global-action global-action-muted">Could not load open issues</div>';
        }

        if (moreWrap) {
            moreWrap.classList.add('hidden');
            moreWrap.innerHTML = '';
        }
    }
}

async function loadAllOpenIssues() {
    if (isGuestUser()) return;
    const list = document.getElementById('allIssuesList');
    if (!list) return;

    allCompassState.loading = true;
    list.innerHTML = '<div class="global-action global-action-muted">Loading…</div>';

    try {
        const data = await api('get_all_open_issues', {
            limit_per_chat: 2,
            max_chats: 20
        });

        const chats = Array.isArray(data?.chats) ? data.chats : [];

        list.innerHTML = '';

        if (!chats.length) {
            list.innerHTML = '<div class="global-action global-action-muted">No open issues across chats</div>';
            allCompassState.loaded = true;
            return;
        }

        chats.forEach(chat => {
            const title = document.createElement('div');
            title.className = 'compass-chat-title';
            title.textContent = chat.title || 'Chat';
            list.appendChild(title);
            
            (chat.items || []).forEach(item => {
                const btn = document.createElement('button');
                btn.type = 'button';
            
                const itemType = Number(item.type || 1);
                btn.className = 'global-action global-action-compact compass-click open-issue-item open-issue-item-type-' + itemType;
            
                const meta = document.createElement('div');
                meta.className = 'open-issue-meta';
                meta.textContent = getOpenIssueTypeLabel(itemType) + ' · ' + formatOpenIssueDate(item.time);
            
                const text = document.createElement('div');
                text.className = 'open-issue-text';
                text.textContent = getOpenIssueDisplayText(item);
            
                btn.appendChild(meta);
                btn.appendChild(text);
            
                btn.addEventListener('click', async function () {
                    await openCompassItem(chat.chat_id, item.message_id || '');
                    closeSidebarOnMobile();
                });
            
                list.appendChild(btn);
            });
        });

        allCompassState.loaded = true;
    } catch (e) {
        console.error('Could not load all open issues', e);
        list.innerHTML = '<div class="global-action global-action-muted">Could not load open issues</div>';
    } finally {
        allCompassState.loading = false;
    }
}
function getChatPreviewLine(chat) {
    const messages = Array.isArray(chat?.messages) ? chat.messages : [];

    const visibleMessages = messages.filter(msg => {
        const role = String(msg?.role || '');
        if (role !== 'user' && role !== 'other') return false;

        const content = String(msg?.content || msg?.text || '').trim();
        if (!content) return false;

        return true;
    });

    if (!visibleMessages.length) {
        return 'No messages yet';
    }

    const last = visibleMessages[visibleMessages.length - 1];
    const content = String(last.content || last.text || '').replace(/\s+/g, ' ').trim();

    return content.length > 52 ? content.slice(0, 52) + '…' : content;
}