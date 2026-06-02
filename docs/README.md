# Tsjilp Starter Package

This is a minimal PHP starter skeleton for the Tsjilp / Eidolon project.

## What it does

- `/` shows the app shell in guest mode
- guest mode can chat with the assistant only
- `/@harry` is a protected route
- if not logged in, `/@harry` shows the login screen
- if logged in, `/@harry` shows the protected conversation route
- login is temporary session-based only, just to prove state and routing

## Structure

- `public/index.php` = front controller
- `app/bootstrap.php` = app bootstrapping
- `app/auth.php` = login state helpers
- `app/router.php` = route detection
- `app/state.php` = temporary session-backed conversation state
- `app/views/` = layout and page fragments

## Notes

This package is not a final architecture. It is a clean base for replacing the static mockup with a real shell:

- fixed top / sidebar / body / bottom layout
- route-based rendering
- logged-in vs guest behavior
- assistant-only guest conversation at `/`
- protected user space at `/@username`

## Nginx note

Because you are using Nginx, configure it to route unknown paths to `public/index.php`.
A typical rule is a `try_files` fallback to `/index.php?$query_string` in the `public` root.
