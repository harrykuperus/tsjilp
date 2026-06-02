# Hacker News Show HN

**Title:** Show HN: Tsjilp – Open-source chat where the AI is a secretary, not a chatbot

**Body:**

I built Tsjilp, a chat app with a private AI assistant layer, and just open-sourced it.

The problem: text communication strips away tone, expressions, and timing. Misunderstandings are common, context gets lost, and managing many conversations is mentally exhausting. Current tools move messages faster but do nothing to prevent miscommunication.

Tsjilp takes a different approach. The assistant acts like a quiet secretary — it helps you write more clearly, understand what others mean, keep track of context, and reduce the mental overhead of managing conversations. But it never takes over.

Key design decisions:

- **Chat first, assistant second.** The conversation is always the primary interface. The assistant appears only when you open it.
- **Nothing is sent automatically.** Every AI-generated suggestion is private to you. You review and send everything yourself.
- **Other participants never see AI activity.** They only see the messages you actually send. Your use of the assistant is invisible.
- **Self-hosted, AGPL-3.0.** Your data on your server. Anyone running a modified public instance must share source.

What the assistant does:

- Draft replies in your writing style
- Explain confusing or emotionally charged messages
- Translate naturally between languages
- Polish your drafts for grammar, tone, and clarity
- Summarize long conversations when you return
- Track messages that still need a reply
- Remember useful context per chat over time

Tech: PHP 8.1+, SQLite, vanilla JS. Uses AJAX polling now (websockets planned). Docker compose for setup. Bring your own OpenAI API key or use trial credits.

Think of an e-bike. You still pedal. You still steer. The motor only assists when the terrain gets difficult.

Site: https://www.tsjilp.me/
Repo: https://github.com/harrykuperus/tsjilp
How it works: https://www.tsjilp.me/docs/
Vision: https://www.tsjilp.me/docs/vision/