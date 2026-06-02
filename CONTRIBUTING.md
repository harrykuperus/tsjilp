# Contributing to Tsjilp

Thank you for your interest in contributing to Tsjilp. This document explains how to contribute effectively.

## Code of Conduct

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## How to contribute

### Bug reports

- Open an issue with the **bug** label
- Include steps to reproduce, expected behavior, and actual behavior
- Include your PHP version, browser, and any relevant server details
- Check existing issues first to avoid duplicates

### Feature requests

- Open an issue with the **feature** label
- Explain the problem it solves, not just the solution you want
- Keep it aligned with Tsjilp's philosophy: chat first, assistant second
- Features that add noise, complexity, or break the calm interface philosophy are unlikely to be accepted

### Pull requests

1. Fork the repository
2. Create a branch from `main`: `git checkout -b your-feature-name`
3. Make your changes
4. Test your changes locally
5. Submit a pull request with a clear description

### Coding guidelines

- **PHP:** Follow PSR-12
- **JavaScript:** Keep it clean and readable. No frameworks required for the MVP.
- **Security:** Never hardcode credentials, API keys, or secrets
- **Privacy:** Any AI assistant feature must remain private to the user by default. Nothing the assistant does should be visible to other chat participants unless the user explicitly sends it.
- **Philosophy:** When in doubt, ask: "Does this reduce communication friction?" If not, it probably doesn't belong.

### Philosophy alignment

Tsjilp is built on specific principles. Contributions that conflict with these are unlikely to be accepted:

- **Chat first, assistant second** — The conversation is the primary interface. The assistant appears only when invited.
- **Privacy by default** — AI suggestions, memory, and summaries are private to the user.
- **Calm interfaces** — No automatic interruptions, pop-ups, or unsolicited AI actions.
- **Simplicity over features** — Every feature must reduce communication friction.
- **User control** — Nothing is sent automatically. The user reviews and sends everything.

### Commit messages

- Use clear, descriptive commit messages
- Reference issue numbers when relevant: `Fix message scroll issue (#42)`

### Questions?

Open an issue with the **question** label. No question is too small.