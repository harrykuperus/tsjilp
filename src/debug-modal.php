<?php if (defined('APP_DEBUG') && APP_DEBUG): ?>
<div id="debugMemoryModal" class="debug-memory-modal hidden">
    <div class="debug-memory-header">
        <strong>Memory Debug</strong>
        <button type="button" id="debugRefreshBtn">Refresh</button>
        <button type="button" id="debugCloseBtn">✕</button>
    </div>

    <div class="debug-memory-body">

        <div class="debug-block">
            <div class="debug-title">Stable Memory</div>
            <pre id="debugStableMemory">{}</pre>
        </div>

        <div class="debug-block">
            <div class="debug-title">Summary Blocks</div>
            <pre id="debugSummaryBlocks">[]</pre>
        </div>

        <div class="debug-block">
            <div class="debug-title">Prompt Context</div>
            <pre id="debugContextMessages">[]</pre>
        </div>

    </div>
</div>

<style>

.debug-memory-modal {
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    width: 420px;
    background: #fff;
    border-left: 1px solid #e5e5e5;
    z-index: 9999;
    display: flex;
    flex-direction: column;
}

.debug-memory-header {
    display: flex;
    gap: 8px;
    align-items: center;
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
}

.debug-memory-header strong {
    flex: 1;
}

.debug-memory-body {
    overflow-y: auto;
    padding: 10px;
}

.debug-block {
    margin-bottom: 12px;
}

.debug-title {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.debug-memory-modal pre {
    background: #f5f5f5;
    padding: 8px;
    border-radius: 6px;
    font: 11px/1.4 monospace;
    white-space: pre-wrap;
}

</style>

<script>

async function loadDebugMemory() {

    if (!currentChatId) return;

    try {

        const res = await fetch('api.php?action=get_chat_memory', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                chat_id: currentChatId
            })
        });

        const data = await res.json();

        document.getElementById('debugStableMemory').textContent =
            JSON.stringify(data.stable_memory || {}, null, 2);

        document.getElementById('debugSummaryBlocks').textContent =
            JSON.stringify(data.summary_blocks || [], null, 2);

        document.getElementById('debugContextMessages').textContent =
            JSON.stringify(data.context_messages || [], null, 2);

    } catch (e) {
        console.error(e);
    }
}

document.addEventListener('click', function(e) {

    if (e.target.id === 'debugRefreshBtn') {
        loadDebugMemory();
    }

    if (e.target.id === 'debugCloseBtn') {
        document.getElementById('debugMemoryModal').classList.add('hidden');
    }

});

window.openDebugMemory = function() {
    document.getElementById('debugMemoryModal').classList.remove('hidden');
    loadDebugMemory();
};

</script>

<?php endif; ?>