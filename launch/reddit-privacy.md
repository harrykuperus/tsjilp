# r/privacy Post

**Title:** Open-sourced a chat app where the AI assistant is completely private to you — nobody else can see it

**Body:**

I built [Tsjilp](https://www.tsjilp.me/) and just open-sourced it under AGPL-3.0.

The core idea is simple: the AI assistant helps you communicate better, but everything it does is private to you. Other people in the chat never see AI activity, suggestions, or any indication that you're using it.

**How privacy works:**

- AI suggestions, explanations, and drafts are shown only to you
- Memory, summaries, and catch-up notes are scoped to your account
- Nothing the assistant generates reaches the chat unless you explicitly send it
- Other participants see only the messages you actually send — nothing more
- You bring your own OpenAI API key, so your data doesn't go through my servers for AI processing
- Self-hosted — your conversation data stays on your infrastructure

**What the assistant does:**

- Helps you draft replies in your writing style
- Explains messages that might be confusing or emotionally charged
- Translates between languages naturally
- Checks your messages for tone and clarity before you send
- Summarizes conversations you've been away from
- Tracks what still needs your attention

**Why I built it this way:**

Most AI tools inject themselves into the conversation visibly. The other person knows. Sometimes the AI sends things you didn't review. I wanted the opposite: an AI that works like a quiet secretary. It prepares what you need privately. You decide what goes out.

**License:** AGPL-3.0 — ensures anyone who runs it as a service must share their source. No freeloading.

Repo: https://github.com/harrykuperus/tsjilp