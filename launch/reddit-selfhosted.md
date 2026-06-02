# r/selfhosted Post

**Title:** I open-sourced my self-hosted chat app with a private AI assistant layer (AGPL-3.0, PHP/JS, Docker)

**Body:**

Hey r/selfhosted,

I've been working on a project called [Tsjilp](https://www.tsjilp.me/) — a chat app that has a private AI assistant built in. I just open-sourced it under AGPL-3.0.

**What it is:**

A chat app where an AI assistant helps you communicate better, but stays completely invisible to other people in the conversation. You still write and send every message yourself. The assistant just helps when you need it.

**What the assistant does (only you can see any of this):**

- Draft replies in your voice
- Explain confusing or emotionally charged messages
- Translate naturally (not word-for-word)
- Polish your drafts before sending
- Summarize long conversations when you come back
- Track messages that still need a reply
- Remember context per chat over time

**Why self-hosted:**

- Your data stays on your server
- The AI works with your own OpenAI API key (or use built-in trial credits to try it)
- AGPL-3.0 — anyone running a modified version publicly must share source
- No telemetry, no cloud dependency beyond the LLM API call

**Tech stack:**

- PHP 8.1+ / vanilla JS
- SQLite (file-based, no database server needed)
- Docker compose for easy setup
- Currently uses AJAX polling (websockets planned)

**The philosophy:**

Chat first, assistant second. The assistant is like a quiet secretary — it prepares what you need, keeps track of what matters, and helps you express yourself clearly. But it never speaks for you, never interrupts, and nothing it generates enters the conversation unless you hit Send.

Repo: https://github.com/harrykuperus/tsjilp

Happy to answer questions or take feedback.