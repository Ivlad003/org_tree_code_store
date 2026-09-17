# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A self-hosted org-chart app for code.store: a small PHP + SQLite application that
renders an interactive organizational chart and provides an admin panel for editing
it. The chart data lives in a local SQLite database (seeded once from a CSV) and is
rendered client-side from a `window.ORG_DATA` payload emitted by the server.

## Architecture

- **Backend**: PHP 8.x with PDO/SQLite. No framework — plain entry-point scripts.
- **Database**: `data/db.sqlite` (departments + employees). Schema migrations run on
  connect in `src/db.php`. Seeded from `data/org_struct_code_store.csv` via `seed.php`.
- **Frontend**: vanilla JS (`public/assets/js/app.js`) renders the tree from
  `window.ORG_DATA`; styles in `public/assets/css/`.
- **Two-gate auth** (see `docs/google_oauth_auth_plan.md`):
  - *Viewer gate* — the chart is sensitive and deployed to the public internet, so
    viewing requires Google OAuth restricted to the code.store Workspace (`hd` claim).
    Controlled by the `VIEWER_AUTH` env flag (`open` for local dev, `google` for prod).
  - *Admin gate* — editing requires email + password (both from `.env`), an MVP
    shared credential. Admin implies viewer.
- **Avatars**: stored under `uploads/avatars/{id}.webp`, served only through the
  viewer-gated `public/avatar.php` passthrough (never as raw static files).

## Layout

```
public/        web document root — entry points + assets, the only web-served dir
  index.php        chart (viewer-gated landing page when not signed in)
  admin.php        admin panel: login + employee/department CRUD
  auth_google.php  Google OAuth (OIDC) viewer flow
  avatar.php       viewer-gated avatar passthrough
  upload.php       admin avatar upload handler
  assets/{css,js}
src/
  db.php           DB access, auth helpers, avatar pipeline — included, never served
data/            db.sqlite + source CSV   (sibling of public/, unreachable over HTTP)
uploads/avatars/ avatar files             (sibling of public/, served via avatar.php)
seed.php         one-time CSV → SQLite importer (CLI only)
router.php       dev-server router (maps clean URLs / and /admin)
docs/            design/roadmap docs; docs/archive/ holds historical artifacts
.env             secrets + flags (gitignored); see .env.example
```

Sensitive files (`src/`, `data/`, `uploads/`, `.env`) are **outside** the `public/`
document root, so they're unreachable over HTTP regardless of web-server config.

## Development

```bash
php seed.php                                  # one-time: import the CSV into SQLite
php -S 127.0.0.1:8000 -t public router.php    # run the dev server
```

Open http://localhost:8000/. Keep `VIEWER_AUTH=open` in local `.env` so the chart
renders without the Google sign-in round-trip. To exercise the viewer gate locally,
set `VIEWER_AUTH=google` and fill the OAuth credentials per `docs/google_oauth_setup.md`.

## Production

Point the web server's document root at `public/`. Deployment target is nginx; an
Apache fallback `public/.htaccess` is included. See the nginx/static-file-lockdown
section in `docs/google_oauth_auth_plan.md`.
