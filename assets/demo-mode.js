export async function start(ctx) {
    const {
        endpoint,
        token,
        setDemoMode,
        resetState,
        updateHeaderForChat,
        clearConversationUI,
        renderDay,
        renderGuestSidebar,
        renderAssistantStatic,
        renderSticky,
        renderMessage,
        renderThinking,
        wait,
        typeText,
        scrollChatToBottom,
        createAssistantTypingBubble,
        addTimelineItem,
        showCatchup,
        showJoinNote,
        showAssist,
        getPlaybackToken
    } = ctx;

    setDemoMode(true);
    resetState();

    clearConversationUI();
    updateHeaderForChat('Loading demo…');
    renderDay('Now');
    renderGuestSidebar('Loading demo…');

    const res = await fetch(endpoint, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);

    const payload = await res.json();

    let demo;
    if (Array.isArray(payload?.demos)) {
        if (!payload.demos.length) throw new Error('No demos found');
        demo = payload.demos[Math.floor(Math.random() * payload.demos.length)];
    } else if (Array.isArray(payload?.messages)) {
        demo = payload;
    } else if (Array.isArray(payload)) {
        demo = { messages: payload };
    } else {
        throw new Error('Invalid demo format');
    }

    if (token !== getPlaybackToken()) return;

    updateHeaderForChat(demo.title || 'Demo');
    renderGuestSidebar(demo.title || 'Demo');

    await playDemoChat(demo.messages || [], {
        token,
        renderAssistantStatic,
        renderSticky,
        renderMessage,
        renderThinking,
        wait,
        typeText,
        scrollChatToBottom,
        createAssistantTypingBubble,
        addTimelineItem,
        showCatchup,
        showJoinNote,
        showAssist,
        getPlaybackToken
    });
}

async function playDemoChat(messages, ctx) {
    for (const msg of messages) {
        if (ctx.token !== ctx.getPlaybackToken()) return;

        const delay = msg.delay ?? msg._demo?.delay ?? 500;
        if (delay > 0) {
            await ctx.wait(delay);
        }

        if (ctx.token !== ctx.getPlaybackToken()) return;

        await renderDemoMessage(msg, ctx);
    }
}

async function renderDemoMessage(msg, ctx) {
    const role = msg.role || 'assistant';
    const kind = msg.kind || '';

    if (kind === 'sticky') {
        ctx.renderSticky(msg.content || '');
        ctx.scrollChatToBottom(true);
        return;
    }

    if (kind === 'timeline') {
        ctx.addTimelineItem(msg);
        return;
    }

    if (kind === 'catchup') {
        ctx.showCatchup(msg);
        return;
    }

    if (kind === 'join') {
        ctx.showJoinNote(msg);
        return;
    }

    if (kind === 'assist') {
        ctx.showAssist(msg);
        return;
    }

    if (role === 'assistant') {
        await renderAssistantDemoMessage(msg, ctx);
        return;
    }

    ctx.renderMessage({
        role: role,
        content: msg.content || ''
    });
    ctx.scrollChatToBottom(true);
}

async function renderAssistantDemoMessage(msg, ctx) {
    const text = msg.content || '';
    const typingDelay = msg.typingDelay ?? msg._demo?.typingDelay ?? 400;
    const typingSpeed = msg.typingSpeed ?? msg._demo?.typingSpeed ?? 18;
    const thinkingText = msg.thinking ?? msg._demo?.thinking ?? '';

    if (thinkingText) {
        ctx.renderThinking(thinkingText);
        ctx.scrollChatToBottom(true);
        await ctx.wait(msg.thinkingDelay ?? msg._demo?.thinkingDelay ?? 900);

        if (ctx.token !== ctx.getPlaybackToken()) return;

        const thinkingEl = document.getElementById('thinking');
        if (thinkingEl) thinkingEl.remove();
    }

    if (typingDelay > 0) {
        await ctx.wait(typingDelay);
        if (ctx.token !== ctx.getPlaybackToken()) return;
    }

    const contentEl = ctx.createAssistantTypingBubble(true);

    if (!contentEl) {
        console.error('DemoMode: content element not found');
        return;
    }

    await ctx.typeText(contentEl, text, {
        speed: typingSpeed,
        onTick: () => ctx.scrollChatToBottom(true),
        shouldAbort: () => ctx.token !== ctx.getPlaybackToken()
    });

    ctx.scrollChatToBottom(true);
}