<?php
require_once 'config.php';
$appConfig = load_app_config();
$isGuestUserSession = ! empty($_SESSION['user_id']) && (($appConfig['user']['member_kind'] ?? '') === 'invited_member');
$guestCreditsLeft = empty($_SESSION['user_id']) ? getGuestCreditsLeft() : GUEST_TRIAL_LIMIT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
<title>Tsjilp | Conversation intelligence</title>
<meta name="description" content="Better conversations, not more messages.">
<link rel="icon" href="/assets/images/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16.png">
<link rel="apple-touch-icon" href="/assets/images/favicon-180.png">
<meta name="theme-color" content="#0f2a44">
<meta name="application-name" content="Tsjilp">
<meta name="apple-mobile-web-app-title" content="Tsjilp">
<meta property="og:title" content="Tsjilp — Conversation intelligence">
<meta property="og:description" content="Better conversations, not more messages.">
<meta property="og:type" content="website">
<meta property="og:url" content="https://www.tsjilp.me/">
<meta property="og:image" content="https://www.tsjilp.me/assets/images/tsjilp-logo.png">
<link rel="stylesheet" type="text/css" href="/assets/style.css?11" onload="document.documentElement.classList.add('css-loaded')">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0f172a">
<style>
:root { -
	-reading-zoom: 1;
}

.content {
	zoom: var(- -reading-zoom);
}

html:not(.css-loaded) .app-shell, body:not(.loaded) .app-shell {
	opacity: 0;
	visibility: hidden;
}

.app-shell {
	transition: opacity .15s ease, visibility 0s linear .15s;
}

html.css-loaded body.loaded .app-shell {
	opacity: 1;
	visibility: visible;
}

html.css-loaded body.loaded {
	opacity: 1;
	visibility: visible;
}
</style>
<script>
window.APP_CONFIG = <?php echo json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.GUEST_TRIAL_USES_LEFT = <?= (int)$guestCreditsLeft ?>;
window.currentUserId = "<?= $_SESSION['user_id'] ?? '' ?>";
const isLoggedIn = <?= empty($_SESSION['user_id']) ? 'false' : 'true' ?>;
const app_base_url = '<?= $app_base_url ?>';
</script>
<script src="/assets/tsjilp.ui.min.js?11" defer></script>
</head>
<body <?= empty($_SESSION['user_id']) ? ' class="logged-out"' : '' ?>>
    <div id="splashScreen" class="splash-screen">
        <div class="splash-content">
            <div class="splash-logo">
                <img src="/assets/images/favicon-48.png" alt="Tsjilp" width="48" height="48">
            </div>
            <div class="splash-text" id="splashText">Loading your chats</div>
            <div class="splash-progress">
                <div class="splash-progress-bar" id="splashProgressBar"></div>
            </div>
            <div class="splash-footer">Private conversations</div>
            <div class="splash-error" id="splashError" style="display: none;">
                <button id="splashRetryBtn" class="splash-retry-btn">Retry</button>
            </div>
        </div>
    </div>
    <div class="app-shell">
        <div id="appView" class="">
            <section id="chatPage">
                <div class="app two-pane-layout">
                    <aside id="sidebar" class="sidebar">
                        <div class="sidebar-top">
                            <div class="sidebar-top-left">
                                <button type="button" class="sidebar-brand-info-btn" aria-label="What is Tsjilp" title="What is Tsjilp" onclick="openTsjilpInfoModal()">i</button>
                                <div class="brand-stack">
                                    <div class="brand" id="brandTitle">Tsjilp.me</div>
                                </div>
                            </div>
                            <div class="sidebar-top-right">
                                <?php if (!$isGuestUserSession): ?>
                                <button type="button" id="newChatTopBtn" class="icon-btn" aria-label="New chat" onclick="toggleNewChatPopover()">+</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sidebar-shell">
                            <div class="sidebar-scroll sidebar-panels">
                                <div class="sidebar-panel active" data-tab="chats">
                                    <?php if (empty($_SESSION['user_id'])): ?>
                                        <div class="guest-sidebar">
                                        <ul>
                                            <li>Write like a pro</li>
                                            <li>Understand better</li>
                                            <li>Control your tone</li>
                                            <li>Translate better</li>
                                            <li>Summarize long chats</li>
                                            <li>Own your data</li>
                                            <li>Works with any LLM</li>
                                            <li>Write less, do more</li>
                                        </ul>
                                    </div>
                                    <?php else: ?>
                                    <div class="conversation-list-wrap">
                                        <div class="sidebar-section-search">
                                            <div id="newChatPopover" class="new-chat-inline hidden">
                                                <form class="new-chat-inline-row" onsubmit="return submitInlineNewChatFromEvent(event)" autocomplete="off">
                                                    <input id="newChatTitle" name="new_chat_title" style="margin-bottom: 20px;" class="sidebar-inline-search" type="text" placeholder="New chat title"
                                                        autocomplete="new-password" autocorrect="off" autocapitalize="sentences" spellcheck="false" inputmode="text" enterkeyhint="done"
                                                        onkeydown="handleNewChatTitleKeydown(event)">
                                                    <button type="submit" class="new-chat-submit-btn" aria-label="Create chat" title="Create chat">
                                                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                                            <path d="M5 12h14m0 0-6-6m6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="sidebar-search-wrap">
                                                <input id="sidebarChatSearch" class="sidebar-inline-search" type="search" placeholder="Search chats..." oninput="updateChatSearchFromInput()"
                                                    autocomplete="off">
                                                <button type="button" id="sidebarChatSearchClear" class="sidebar-search-clear hidden" aria-label="Clear chat search" onclick="clearChatSearch()">×</button>
                                            </div>
                                            <div id="chatSearchResults" class="hidden"></div>
                                        </div>
                                        <div id="chatList"></div>
                                    </div>
                                    <div class="archived-block" id="archivedSection">
                                        <div class="sidebar-head">
                                            <div class="sidebar-label archived-label">Archived</div>
                                        </div>
                                        <div id="archivedChatList" style="padding: 0 0 100px;"></div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="sidebar-panel" data-tab="contacts">
                                    <div class="sidebar-section-search">
                                        <div class="sidebar-search-wrap">
                                            <input id="sidebarContactsSearch" class="sidebar-inline-search" type="search" placeholder="Search contacts..." oninput="updateContactsSearchFromInput()"
                                                autocomplete="off">
                                            <button type="button" id="sidebarContactsSearchClear" class="sidebar-search-clear hidden" aria-label="Clear contacts search" onclick="clearContactsSearch()">×</button>
                                        </div>
                                        <div class="contacts-header-row">
                                            <label class="contacts-show-all" for="contactsShowAllToggle"> <input id="contactsShowAllToggle" type="checkbox"
                                                onchange="toggleShowAllContacts(this.checked)"> <span>Show all</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div id="sidebarContactsList" class="sidebar-contacts-list">
                                        <div class="sidebar-contact-empty">Loading contacts...</div>
                                    </div>
                                </div>
                                <div class="sidebar-panel" data-tab="settings">
                                    <div id="sidebarSettingsEmbed" class="sidebar-settings-host">
                                        <?php if (empty($_SESSION['user_id'])): ?>
                                            <div class="sidebar-simple-panel">
                                            <div class="sidebar-simple-card">
                                                <div class="sidebar-simple-title">Settings</div>
                                                <div class="sidebar-simple-text">Log in to manage assistant and conversation settings.</div>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                            <div class="sidebar-contact-empty">Loading settings...</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="sidebar-panel" data-tab="profile">
                                    <?php if (empty($_SESSION['user_id'])): ?>
                                        <div class="sidebar-simple-panel">
                                        <div class="sidebar-simple-card">
                                            <div class="sidebar-simple-title">Your profile</div>
                                            <div class="sidebar-simple-text">Log in to view or edit your profile.</div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <div id="sidebarProfileEmbed" class="sidebar-profile-host">
                                        <div class="sidebar-contact-empty">Loading profile...</div>
                                            <?php if ($isGuestUserSession): ?>
                                            <button class="primary-btn" type="button" onclick="goToSignupFromGuest()">Sign up</button>
                                        Sign up to unlock full profile options and account security.
                                        <button class="secondary-btn" type="button" onclick="logoutUser()">Log out</button>
                                            <?php else: ?>
                                            <button class="primary-btn" type="button" onclick="logoutUser()">Log out</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                                <?php if (empty($_SESSION['user_id'])): ?>
                                    <div class="sidebar-auth-cta-actions">
                                <button class="secondary-btn" type="button" onclick="openAuthModal('login')">Log in</button>
                                <button class="primary-btn" type="button" onclick="openAuthModal('signup')">Sign up</button>
                            </div>
                                <?php endif; ?>

                                <button type="button" id="mobileNewChatTopBtn" class="mobile-new-chat-fab hidden" aria-label="New chat" title="New chat" onclick="toggleNewChatPopover()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                    stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 5v14M5 12h14"></path>
                                    </svg>
                            </button>

                            <div class="sidebar-tabbar" role="tablist" aria-label="Sidebar sections">
                                <button type="button" class="sidebar-tab-btn active" data-tab="chats" onclick="setSidebarTab('chats')">
                                    <span class="sidebar-tab-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" focusable="false">
                                            <path
                                                d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"></path>
                                        </svg>
                                    </span> <span class="sidebar-tab-label">Chats</span>
                                </button>
                                <button type="button" class="sidebar-tab-btn" data-tab="contacts" onclick="setSidebarTab('contacts')">
                                    <span class="sidebar-tab-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" focusable="false">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="8.5" cy="7" r="4"></circle>
                                            <path d="M20 8v6"></path>
                                            <path d="M23 11h-6"></path>
                                        </svg>
                                    </span> <span class="sidebar-tab-label">Contacts</span>
                                </button>
                                <button type="button" class="sidebar-tab-btn" data-tab="settings" onclick="openSettingsTab()">
                                    <span class="sidebar-tab-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" focusable="false">
                                            <circle cx="12" cy="12" r="3"></circle>
                                            <path
                                                d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1v.17a2 2 0 1 1-4 0V21a1.65 1.65 0 0 0-.33-1 1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H2.83a2 2 0 1 1 0-4H3a1.65 1.65 0 0 0 1-.33 1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1V2.83a2 2 0 1 1 4 0V3a1.65 1.65 0 0 0 .33 1 1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.24.31.37.69.37 1.08v.17a1.65 1.65 0 0 0 .6 1 1.65 1.65 0 0 0 1 .33h.17a2 2 0 1 1 0 4h-.17a1.65 1.65 0 0 0-1 .33c-.31.24-.53.58-.6 1z"></path>
                                        </svg>
                                    </span> <span class="sidebar-tab-label">Settings</span>
                                </button>
                                <button type="button" class="sidebar-tab-btn" data-tab="profile" onclick="openProfileTab()">
                                    <span class="sidebar-tab-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" focusable="false">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </span> <span class="sidebar-tab-label">Profile</span>
                                </button>
                            </div>
                        </div>
                    </aside>
                    <main class="main chat-pane">
                        <div class="chat-top">
                            <button id="leftmenuFloating" class="icon-btn" type="button" aria-label="Open sidebar" aria-expanded="true" title="Open sidebar">
                                <span class="sidebar-arrow"> <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M9 5l7 7-7 7" />
                                </svg>
                                </span>
                            </button>
                            <button class="chat-header-main" type="button" onclick="toggleChatHeaderMenu(event)">
                                <div class="chat-header-avatars" id="chatHeaderAvatars"></div>
                                <div class="chat-header-copy">
                                    <div class="chat-header-title" id="headerTitle"></div>
                                    <div class="chat-header-sub" id="headerSubline"></div>
                                </div>
                            </button>
                            <div class="chat-header-right">
                            <?php if (defined('APP_DEBUG') && APP_DEBUG): ?><button onclick="openDebugMemory()" class="header-btn">Memory</button><?php endif; ?>
                            <button id="focusModeBtn">⛶</button>
                                <button id="contrastToggleBtn" class="theme-toggle" aria-label="Toggle theme">
                                    <svg id="themeToggleIcon" class="theme-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"></svg>
                                </button>
                                <button id="readingModeBtn" class="reading-mode-btn" type="button">Aa</button>
                                <button type="button" class="chat-header-menu-btn" aria-label="Chat menu" onclick="toggleChatHeaderMenu(event)">⋮</button>
                            </div>
                            <div class="chat-header-menu hidden" id="chatHeaderMenu">
                                <button type="button" id="chatHeaderInviteMenuBtn" onclick="openInviteModal(currentChatId)">
                                    <span class="chat-header-menu-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M12 5v14"></path>
                                            <path d="M5 12h14"></path>
                                            <path d="M5 7.5h8"></path>
                                        </svg>
                                    </span> <span class="chat-header-menu-label">People</span>
                                </button>
                                <button type="button" class="js-chat-context-toggle" onclick="toggleChatContext()">
                                    <span class="chat-header-menu-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M12 6v6l4 2"></path>
                                            <circle cx="12" cy="12" r="8"></circle>
                                        </svg>
                                    </span> <span class="chat-header-menu-label">Catch up</span>
                                </button>
                                <button type="button" id="chatHeaderSettingsBtn" onclick="openSettingsTab()">
                                    <span class="chat-header-menu-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="3"></circle>
                                            <path
                                                d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 1-2 0 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 1 0-2 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 1 2 0 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.24.32.46.66.6 1a1.7 1.7 0 0 1 0 2c-.14.34-.36.68-.6 1z"></path>
                                        </svg>
                                    </span> <span class="chat-header-menu-label">Settings</span>
                                </button>
                                <?php if ($isGuestUserSession): ?>
                                <button type="button" id="chatHeaderSignupBtn" onclick="goToSignupFromGuest()">
                                    <span class="chat-header-menu-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M12 5v14"></path>
                                            <path d="M5 12h14"></path>
                                        </svg>
                                    </span> <span class="chat-header-menu-label">Sign up</span>
                                </button>
                                <?php endif; ?>
                                <a href="/docs/" class="chat-header-menu-link" target="_blank" rel="noopener"> <span class="chat-header-menu-icon" aria-hidden="true"> <svg viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <path d="M12 16v-4"></path>
                                            <path d="M12 8h.01"></path>
                                        </svg>
                                </span> <span class="chat-header-menu-label">How it works</span>
                                </a> <a href="/docs/support-us/" class="chat-header-menu-link" target="_blank" rel="noopener"> <span class="chat-header-menu-icon" aria-hidden="true"> <svg
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                        </svg>
                                </span> <span class="chat-header-menu-label">Donate to Tsjilp</span>
                                </a>
                                <button type="button" id="chatHeaderLogoutBtn" onclick="logoutUser()">
                                    <span class="chat-header-menu-icon" aria-hidden="true"> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                            <path d="M16 17l5-5-5-5"></path>
                                            <path d="M21 12H9"></path>
                                        </svg>
                                    </span> <span class="chat-header-menu-label">Log out</span>
                                </button>
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
                                    <div id="chatIntro" style="display: flex; justify-content: center; text-align: center; pointer-events: none; margin: 0;">
                                        <div>
                                            <img src="/assets/images/favicon-48.png" alt="Tsjilp" style="width: 48px; height: 48px; margin-bottom: 16px;">
                                            <div style="font-size: var(- -fs-2xl); line-height: 1.15; font-weight: 500; margin-bottom: 10px;">Conversation intelligence</div>
                                            <div style="font-size: var(- -fs-base); color: #6b7280; margin-bottom: 26px;">Better conversations, not more messages.</div>
                                            <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; pointer-events: auto;">
                                                <button type="button" class="primary-btn" onclick="openAuthModal('signup')">Sign up</button>
                                                <button type="button" class="secondary-btn" onclick="openAuthModal('login')">Log in</button>
                                            </div>
                                            <div style="margin: 30px 0; font-size: var(- -fs-base); color: #6b7280; pointer-events: auto;">
                                                Free to use. No ads. <a href="/docs/" style="color: #6b7280;">How it works</a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (empty($_SESSION['user_id'])): ?>
                                        <div class="legal-footer" role="contentinfo">
                                        <a href="/docs/privacy.html">Privacy</a> <span class="legal-footer-sep">·</span> <a href="/docs/terms.html">Terms</a> <span class="legal-footer-sep">·</span> <a
                                            href="/docs/cookies.html">Cookies</a> <span class="legal-footer-sep">·</span> <a href="/docs/disclaimer.html">Disclaimer</a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <button id="scrollToBottom" class="scroll-bottom-btn" onclick="scrollChatToBottom(true)" style="display: none">
                            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                                <path d="M12 5v14m0 0 6-6m-6 6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </button>
                        <div id="feedbackModalMount"></div>
                        <div id="messageHoverActionsGlobal" class="message-hover-actions-global" aria-hidden="true" style="display: none">
                            <button type="button" class="message-hover-action reply-action" data-hover-action="reply" aria-label="Reply" title="Reply">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-reply-fill" viewBox="0 0 16 16">
                                    <path d="M5.921 11.9 1.353 8.62a.72.72 0 0 1 0-1.238L5.921 4.1A.716.716 0 0 1 7 4.719V6c1.5 0 6 0 7 8-2.5-4.5-7-4-7-4v1.281c0 .56-.606.898-1.079.62z" />
                                </svg>
                            </button>
                            <button type="button" class="message-hover-action help-action" data-hover-action="understand" aria-label="Understand" title="Understand">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-question-square" viewBox="0 0 16 16">
                                    <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z" />
                                    <path
                                        d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286m1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94" />
                                </svg>
                            </button>
                            <button type="button" class="message-hover-action edit-action" data-hover-action="edit" aria-label="Edit" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                                    <path
                                        d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                                    <path fill-rule="evenodd"
                                        d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z" />
                                </svg>
                            </button>
                            <button type="button" class="message-hover-action delete-action" data-hover-action="delete" aria-label="Delete" title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-square" viewBox="0 0 16 16">
                                    <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z" />
                                    <path
                                        d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708" />
                                </svg>
                            </button>
                            <button type="button" class="message-hover-action issue message-flag-btn" data-hover-action="issue" aria-label="Needs attention" title="Needs attention">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pin-angle" viewBox="0 0 16 16">
                                    <path
                                        d="M9.828.722a.5.5 0 0 1 .354.146l4.95 4.95a.5.5 0 0 1 0 .707c-.48.48-1.072.588-1.503.588-.177 0-.335-.018-.46-.039l-3.134 3.134a6 6 0 0 1 .16 1.013c.046.702-.032 1.687-.72 2.375a.5.5 0 0 1-.707 0l-2.829-2.828-3.182 3.182c-.195.195-1.219.902-1.414.707s.512-1.22.707-1.414l3.182-3.182-2.828-2.829a.5.5 0 0 1 0-.707c.688-.688 1.673-.767 2.375-.72a6 6 0 0 1 1.013.16l3.134-3.133a3 3 0 0 1-.04-.461c0-.43.108-1.022.589-1.503a.5.5 0 0 1 .353-.146m.122 2.112v-.002zm0-.002v.002a.5.5 0 0 1-.122.51L6.293 6.878a.5.5 0 0 1-.511.12H5.78l-.014-.004a5 5 0 0 0-.288-.076 5 5 0 0 0-.765-.116c-.422-.028-.836.008-1.175.15l5.51 5.509c.141-.34.177-.753.149-1.175a5 5 0 0 0-.192-1.054l-.004-.013v-.001a.5.5 0 0 1 .12-.512l3.536-3.535a.5.5 0 0 1 .532-.115l.096.022c.087.017.208.034.344.034q.172.002.343-.04L9.927 2.028q-.042.172-.04.343a1.8 1.8 0 0 0 .062.46z" />
                                </svg>
                            </button>
                        </div>
                        <div id="feedbackBar" class="feedback-bar hidden">
                            <span class="feedback-bar-label">Do you like Tsjilp so far?</span>
                            <button type="button" class="feedback-bar-btn" onclick="handleFeedbackRating('up')" title="Yes">👍</button>
                            <button type="button" class="feedback-bar-btn" onclick="handleFeedbackRating('down')" title="No">👎</button>
                            <button type="button" class="feedback-bar-dismiss" onclick="dismissFeedbackBar()" aria-label="Dismiss">×</button>
                        </div>
                        <div class="composer-wrap" id="composerWrap">
                            <div class="composer-inner">
                                <div class="recipient-row-scroll">
                                    <div class="recipient-row">
                                        <div class="recipient-label">To:</div>
                                        <div class="recipient-pills" id="recipientPills"></div>
                                        <div class="composer-tools">
                                            <div id="composerTransformWrap" class="composer-transform-wrap hidden">
                                                <div class="composer-action-stack" id="composerActionStack">
                                                    <button type="button" id="composerActionTrigger" class="composer-action-btn composer-action-trigger" aria-label="Ask assistant"
                                                        title="Ask assistant">✨</button>
                                                    <div class="composer-action-menu" id="composerActionMenu">
                                                        <button type="button" class="composer-action-btn" data-action="polish">✎ Polish</button>
                                                        <button type="button" class="composer-action-btn" data-action="summarize">✎ Summarize</button>
                                                        <button type="button" class="composer-action-btn" data-action="translate">✎ Translate</button>
                                                        <button type="button" class="composer-action-btn" data-action="Warm">✎ Warm</button>                                                        
                                                        <button type="button" class="composer-action-btn" data-action="humor">✎ Humor</button>
                                                        <button type="button" class="composer-action-btn" data-action="cold">✎ Cold</button>
                                                        <form id="assistantComposerBubble" class="assistant-bubble">
                                                            <input id="assistantComposerInput" type="text" placeholder="Other..." autocomplete="off">
                                                            <button type="submit" class="hidden" aria-hidden="true" tabindex="-1"></button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="composer-private-toggle">
                                            <label class="settings-toggle"> <input type="checkbox" id="assistantPrivateModeToggle"> <span class="settings-toggle-slider"></span>
                                            </label> <span class="composer-private-label">Ask AI</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="composer-toolbar">
                                    <div id="composerReplyPreview" class="composer-reply-preview hidden">
                                        <div class="composer-reply-body">
                                            <div class="composer-reply-title" id="composerReplyTitle">Replying to</div>
                                            <div class="composer-reply-text" id="composerReplyText"></div>
                                        </div>
                                        <button type="button" id="composerReplyClose" class="composer-reply-close" aria-label="Dismiss" title="Dismiss">×</button>
                                    </div>
                                    <div class="composer-toolbar-row">
                                        <div class="composer-side-tools">
                                            <button class="plus-btn" id="assistantToggleBtn" type="button" aria-label="Assistant options">
                                                <svg width="22" height="22" viewBox="0 0 24 24">
                                                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                                            </svg>
                                            </button>
                                            <div class="assistant-compose-write-wrap">
                                                <button class="assistant-btn" id="assistantComposerBtn" type="button" aria-label="Auto reply">
                                                    <span class="assistant-feather"><img src="/assets/images/birdy-24.png" class="assistant-bird-icon" alt="" aria-hidden="true" width="22" height="22"></span>
                                                </button>
                                                <span id="composerWriteStatus" class="composer-write-status hidden">Writing…</span>
                                            </div>
                                        </div>
                                        <div class="assistant-menu hidden" id="assistantMenu">
                                            <div class="assistant-menu-section">
                                                <div class="assistant-menu-title">Assistant</div>
                                                <label class="assistant-row"> <input type="checkbox" id="assistEnabled" checked> <span>Enable assistance</span>
                                                </label>
                                                <div class="assistant-menu-subtitle hidden" id="assistantGuestNotice"></div>
                                                <div class="assistant-menu-subtitle" style="margin: 0 22px; font-size: xx-small; color: orchid;" id="assistantMenuProviderLabel">Model</div>
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
                                                <label class="assistant-row"> <input type="checkbox" id="optDraftReplies" checked> <span>Reply Agent</span>
                                                </label>
                                                <div class="assistant-row assistant-improve-send-row" id="optImproveAndSendWrap">
                                                    <label class="assistant-improve-send-check"> <input type="checkbox" id="optCheckBeforeSend" checked> <span>Polish Message</span>
                                                    </label> <label class="assistant-improve-send-sub option-nested-checkbox"> <input type="checkbox" id="optImproveAndSend"> <span>Auto Send</span>
                                                    </label>
                                                </div>
                                                <label class="assistant-row"> <input type="checkbox" id="optTranslate"> <span>Translator</span>
                                                </label>
                                            </div>
                                        </div>
                                        <textarea class="chat-input" id="input" placeholder="Write a message…" rows="1"></textarea>
                                        <button class="action-btn" id="actionBtn" type="button" aria-label="Send">
                                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                            <path d="M5 12h14m0 0-6-6m6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        </button>
                                    </div>
                                    <!-- /.composer-toolbar-row -->
                                </div>
                                <!-- /.composer-toolbar -->
                                <div class="assistant-status" id="assistantStatus">Try Tsjilp assistant free · 10 messages left</div>
                            </div>
                    
                    </main>
                </div>
            </section>
        </div>
    </div>
    <div id="inviteModalMount"></div>
    <div id="guestJoinModalMount"></div>
    <div id="guestLogoutModalMount"></div>
    <div id="tsjilpInfoModalMount"></div>
    <div id="aiModalMount"></div>
    <div id="debugModalMount"><?php include 'src/debug-modal.php'; ?></div>
    <div id="cookieConsentBanner" class="cookie-consent-banner hidden" role="region" aria-label="Cookie consent">
        <p class="cookie-consent-text">We use cookies to improve your experience and for security. You can accept all cookies or reject non-essential cookies.</p>
        <div class="cookie-consent-actions">
            <button type="button" class="cookie-consent-btn cookie-consent-reject" onclick="setCookieConsent('rejected')">Reject</button>
            <button type="button" class="cookie-consent-btn cookie-consent-accept" onclick="setCookieConsent('accepted')">Accept</button>
        </div>
    </div>
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