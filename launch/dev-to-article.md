# Why I Built a Chat App Where the AI Is a Secretary, Not a Chatbot

*Or: what happens when you design AI assistance around human communication instead of replacing it.*

---

Text is a terrible medium for human communication.

In real life, we rely on tone, facial expressions, gestures, and immediate feedback to stay understood. Text removes all of those signals. The result is familiar to everyone who has ever sent a message that was read the wrong way:

- Short replies that seem cold
- Sarcasm that lands wrong
- Ambiguous phrasing that causes friction
- Long threads where the original point gets buried
- Decisions that were made but never tracked
- Open questions that never got answered

Modern messaging tools have done nothing to solve this. They move messages faster. They add more features. They make it easier to send more.

I wanted to do the opposite: help people communicate more clearly with fewer misunderstandings, less repetition, and less noise.

## The assistant as secretary

The most useful way to think about the Tsjilp assistant is as a quiet, competent secretary for your communication.

A good secretary doesn't make decisions for you. They prepare what you need, keep track of what matters, remind you of open items, and help you communicate more professionally. But they stay in the background. They don't speak on your behalf unless you ask them to.

Another useful metaphor is the e-bike. When you ride an e-bike, you still pedal. You still control the direction. The motor only assists when the terrain gets difficult. You remain the one riding.

Tsjilp works like that. You still communicate. The assistant only helps where the conversation gets difficult — confusing messages, charged emotions, complicated replies.

## Chat first, assistant second

This is the core principle behind every decision in Tsjilp: the conversation is always the primary interface. The assistant is secondary to it.

When you open Tsjilp, you see a conversation. The assistant is one layer deeper. The default view is always the chat itself.

This sounds obvious, but it's the opposite of how most AI tools work. Most AI tools put the AI front and center. The human becomes a reviewer of AI output rather than the one driving the conversation.

I wanted Tsjilp to feel like a chat app where the assistant helps quietly — not an AI tool where humans participate as afterthoughts.

## Privacy is not a feature. It's the architecture.

Everything the assistant does is private to you by default. Nothing AI-generated is sent unless you send it yourself.

Draft replies? Private. Explanations of confusing messages? Private. Summaries, memory, attention tracking? All private. The other person in the chat never sees any of it. They only see the messages you actually send.

This isn't a toggle or a setting. It's how the system is built. The assistant works for you, not for the chat. It's a private layer on top of a normal conversation.

## What the assistant actually does

- **Draft replies** — Tap the feather button, get a suggestion written in your voice
- **Explain messages** — When a message is confusing or emotionally charged, the assistant explains what it likely means in plain language
- **Translate naturally** — Not word-for-word, but the way a person would actually say it
- **Polish before send** — Checks your draft for grammar, tone, and clarity. You compare and choose which version to send
- **Catch up** — Return to a long chat and get a short private overview of what happened
- **Needs attention** — Tracks messages that still need a reply or follow-up
- **Memory** — Stores useful context per chat so suggestions improve over time

Nothing is sent automatically. Every message is reviewed and sent by you.

## Why I open-sourced it

I built this because I believe the problem is real. Text communication is broken in ways that faster messaging doesn't fix. We need tools that help people understand each other better, not tools that help them send more faster.

But one person can't solve this alone. If the idea resonates — that AI should assist communication, not replace it — I want others to build on it, improve it, and adapt it.

Tsjilp is AGPL-3.0. Use it, modify it, self-host it. If you run a modified version publicly, share your source. That's the deal. It ensures the project stays open for everyone.

## Try it

- **Live:** [tsjilp.me](https://www.tsjilp.me/)
- **Source:** [github.com/harrykuperus/tsjilp](https://github.com/harrykuperus/tsjilp)
- **How it works:** [tsjilp.me/docs](https://www.tsjilp.me/docs/)

New users get free AI credits to try the assistant. Bring your own OpenAI API key for unlimited use.

---

*Better conversations, not more messages.*