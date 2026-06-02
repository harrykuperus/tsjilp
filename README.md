# Tsjilp

**Two-stream chat app. Better human conversations, not more messages. For lazy people and professionals.**

Tsjilp is a chat app with a private AI assistant layer. The assistant helps you write more clearly, understand messages, translate, summarize conversations, and track what needs your attention — all invisible to other participants.

[Website](https://www.tsjilp.me/) · [How it works](https://www.tsjilp.me/docs/) · [Vision](https://www.tsjilp.me/docs/vision/)

---

## Current status

This is an MVP. The core features work:

- Chat messaging with real-time AJAX polling
- AI assistant (draft replies, explain, translate, polish, summarize, catch-up)
- Privacy-first architecture (assistant output is never visible to others)
- Authentication (email, Google, Apple)
- Contacts and writing personality
- PWA support

Not yet implemented:

- WebSockets (currently uses AJAX polling)
- Database schema export
- Docker setup
- Automated tests

Contributions welcome on any of these — see [CONTRIBUTING.md](CONTRIBUTING.md).

---

## What Tsjilp is

A chat app where an AI assistant helps you communicate better — but stays out of the way until you need it.

- **Assisted replies** — Tap the feather button, get a draft written in your voice
- **Explain messages** — When a message is confusing or emotionally charged, the assistant explains what it likely means
- **Translation** — Natural translation that reads like a person wrote it, not word-for-word
- **Polish before send** — The assistant checks your draft for grammar, tone, and clarity before it goes out
- **Catch up** — Return to a long chat and get a short private overview of what happened
- **Needs attention** — Tracks messages that still need a reply or follow-up
- **Memory** — Stores useful context per chat so suggestions get more relevant over time
- **Writing personality** — Set your style, the assistant writes like you

Everything the assistant does is **private to you**. Nothing is sent automatically. You review and send everything yourself.

## What Tsjilp is not

- Not a chatbot platform — the assistant never takes over conversations
- Not a project management tool — no task boards, tickets, or milestones
- Not a CRM — no lead pipelines or deal tracking
- Not a team collaboration suite — no channels, workspaces, or org integrations

It is assisted communication. An AI secretary that prepares what you need, keeps track of what matters, and helps you express yourself more clearly — but stays in the background and never speaks for you.

## Quick start

### Requirements

- PHP 8.1+
- SQLite (file-based, no database server needed)
- A modern browser
- OpenAI API key (or use built-in trial credits)

### 1. Clone the repository

```bash
git clone https://github.com/harrykuperus/tsjilp.git
cd tsjilp
```

### 2. Configure environment

```bash
cp .env.example .env
```

Edit `.env` and add your OpenAI API key.

See [.env.example](.env.example) for all available configuration options.

> **New users get free credits** — Tsjilp includes a small amount of free AI credits so you can try the assistant immediately without configuring your own API key. Once you like it, bring your own key for unlimited use.

### 3. Set up the database

No database setup needed — Tsjilp uses SQLite and creates the database file automatically on first run.

### 4. Serve

Point your web server (Nginx, Apache) at the project root, or use PHP's built-in server for local testing:

```bash
php -S localhost:8000
```

### Docker (coming soon)

Docker setup is in progress. For now, run directly with PHP and SQLite.

## Project structure

```
├── index.php           # Main app entry point
├── api.php             # Backend API (chat, AI, messaging)
├── api/                # Chat API endpoints
├── assets/             # JS, CSS, images
├── auth/               # Authentication (login, signup, OAuth, contacts)
├── config/             # App configuration (system, assistants, UI)
├── config.php          # Config loader
├── config/secrets.php  # Secret loader (reads from .env)
├── src/                # Core PHP (prompt builder, debug)
├── docs/               # Documentation site
├── manifest.json       # PWA manifest
└── nginx-security.conf # Recommended nginx security rules
```

## How it works

The assistant reads your conversations and helps you communicate more clearly, respond more thoughtfully, and keep track of context. It appears only when you open it. Nothing it generates enters the chat automatically. Every message is reviewed and sent by you.

Think of an e-bike. You still pedal. You still control the direction. The motor only assists when the terrain gets difficult.

Read the full documentation at [tsjilp.me/docs](https://www.tsjilp.me/docs/).

## Philosophy

Tsjilp is built on a simple principle: **chat first, assistant second**.

The conversation is always the primary interface. The assistant is secondary to it. Tsjilp should feel like a chat app where the assistant helps quietly — not an AI tool where humans participate as afterthoughts.

Read the [Vision](https://www.tsjilp.me/docs/vision/) page for the full thinking behind Tsjilp.

## Privacy

- AI suggestions are only shown to you — never to other participants
- Memory, summaries, and catch-up are private to your account
- Nothing the assistant generates reaches the chat unless you tap Send
- Your use of the assistant is invisible to others
- Self-hosted — your data stays on your server

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Security

If you discover a security vulnerability, please report it responsibly. See [SECURITY.md](SECURITY.md) for details.

## License

Tsjilp is released under the [GNU Affero General Public License v3.0](LICENSE).

This means you are free to use, modify, and self-host Tsjilp. If you run a modified version publicly (including as a network service), you must make the source code available under the same license. This ensures Tsjilp stays open for everyone.

## Author

Built by [Harry Kuperus](https://github.com/harrykuperus) — [IMP Multimedia](https://www.imp-multimedia.com/)

## Contact

- **Questions and general contact:** [info@tsjilp.me](mailto:info@tsjilp.me)
- **Security vulnerabilities:** See [SECURITY.md](SECURITY.md)
- **Bug reports and feature requests:** [GitHub Issues](https://github.com/harrykuperus/tsjilp/issues)