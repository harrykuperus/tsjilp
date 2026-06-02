<div class="new-chat-popover hidden" id="newChatPopover">
    <div class="invite-toggle-wrap">
        <span class="sidebar-sub">Invite users</span> 
        <label class="settings-toggle settings-toggle-sm"> 
        <input type="checkbox" id="inviteToggle"> 
        <span class="settings-toggle-slider"></span>
        </label>
    </div>
    <div class="new-chat-row">
        <input type="text" id="newChatTitle" placeholder="Chat title" class="sidebar-search mini-composer-input">
        <button class="new-chat-btn mini-composer-send" style="font-size:24px;" id="createChatBtn" type="button" aria-label="Create chat" disabled>+</button>
    </div>
</div>
<script>
(function () {
    const pop = document.getElementById('newChatPopover');
    const input = document.getElementById('newChatTitle');
    const invite = document.getElementById('inviteToggle');
    const btn = document.getElementById('createChatBtn');

    if (!pop || pop.dataset.bound === '1') return;
    pop.dataset.bound = '1';

    function resetNewChatPopover() {
        input.value = '';
        invite.checked = false;
        btn.disabled = true;
    }

    function updateCreateState() {
        btn.disabled = input.value.trim().length < 5;
    }

    window.closeNewChatPopover = function () {
        pop.classList.add('hidden');
        resetNewChatPopover();
    };

    window.openNewChatPopover = function () {
        pop.classList.remove('hidden');
        resetNewChatPopover();
        setTimeout(() => input.focus(), 0);
    };

    input.addEventListener('input', updateCreateState);

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && input.value.trim().length >= 5) {
            e.preventDefault();
            btn.click();
        }
    });

    btn.addEventListener('click', async function () {
        const title = input.value.trim();
        if (title.length < 5) return;

        const data = await createNewChat(title);
        if (!data?.chat_id) return;

        const shouldInvite = !!invite.checked;
        closeNewChatPopover();

        if (shouldInvite) {
            openInviteModal(data.chat_id);
        }
    });

    document.addEventListener('click', function (e) {
        if (pop.classList.contains('hidden')) return;
        if (pop.contains(e.target) || e.target.closest('.new-chat-btn')) return;
        closeNewChatPopover();
    });

    updateCreateState();
})();
</script>