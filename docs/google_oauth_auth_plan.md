# Authentication plan: two-gate model — investigation & plan

Status: **proposal / not yet implemented**
Scope change: this widens the original `claude_code_admin_prompt.md` scope, which
explicitly excluded Google OAuth, user roles, *and* assumed the chart was public read-only.

---

## The two gates

The site will be served on the public internet with **no VPN**, but the org data is
sensitive. That splits auth into two independent gates:

| Gate | Protects | Who | Mechanism (MVP) | Mechanism (later) |
|---|---|---|---|---|
| **Admin gate** | Editing the chart — `admin.php`, `upload.php`, all CRUD POSTs | Whoever holds the password | Shared `ADMIN_PASSWORD` (unchanged) | Per-user, or keep shared + audit |
| **Viewer gate** | *Viewing* the chart — `index.php`, the injected `ORG_DATA`, avatars, the CSV | Anyone with a `@code.store` Google identity | **Google OAuth + `hd` claim** | Same + optional allowlist |

Key relationships:
- The two gates share one PHP session, different keys: `$_SESSION['admin']` (bool) and
  `$_SESSION['viewer_email']` (string).
- **Admin implies viewer** — `requireViewer()` passes if `viewer_email` is set *or* `admin` is true.
- Viewer does **not** imply admin.

The admin gate is intentionally left as the existing shared password for MVP — it's a small
trusted group and rotating a password is an acceptable revocation story. All the new OAuth
work is about the **viewer gate**.

---

## Current auth surface (what we're changing)

- **`index.php` has no gate at all** — it's fully public today, and injects the entire org
  tree as JSON into the page source (`index.php:117`). This is the biggest change.
- Admin gate: single shared password in `.env` → `ADMIN_PASSWORD`, checked with `hash_equals`
  in `admin.php:30`. Session state is one boolean: `$_SESSION['admin'] = true`.
- All admin POSTs already gated by `requireAdmin()` (`db.php:272`) and `checkCsrf()` (`db.php:287`).
- **Static-file data leaks that bypass any PHP gate:**
  - `assets/csv/org_struct_code_store.csv` — the full dataset as a flat file, and the
    `app.js` fallback source (`app.js:3`, `app.js:98`).
  - `uploads/avatars/*.webp` — photos numbered sequentially from `10000`, enumerable, served
    directly by Apache.

---

## Recommended approach: Google OAuth + `hd` claim for the viewer gate

Restrict viewer sign-in to the `code.store` Google Workspace tenant via the `hd`
(hosted-domain) claim in the OIDC ID token. `hd` is strictly stronger than an email-suffix
check — you cannot spoof `hd`, but you *can* present an unverified email ending in `@code.store`.

### What changes in code

1. **`.env`** — three new vars: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `OAUTH_REDIRECT_URI`.
2. **New `auth_google.php`** (~120 lines):
   - `?action=oauth_start` → redirect to Google with a state nonce stored in session
   - `?action=oauth_callback` → verify state, exchange code, verify ID token, assert
     `hd === 'code.store'`, set `$_SESSION['viewer_email']`
3. **`index.php`** — call `requireViewer()` at the top. If not authed, render a minimal
   "Sign in with Google to view the code.store team" landing page **instead of** the chart,
   and **do not** emit the `ORG_DATA` JSON.
4. **`db.php`** — add `isViewer()` / `requireViewer()` alongside the existing admin helpers.
5. **Lock down the static-file leaks** (mandatory — gating `index.php` is pointless otherwise).
   New `avatar.php` passthrough + nginx `deny` rules — see the **Static-file lockdown** section
   below for the full breakdown and config.
6. **`admin.php` login view** — optionally add a "Sign in with Google" button too, but the
   admin gate keeps the password for MVP.
7. Optional: `users` allowlist table + `audit_log` table.

No Composer needed — the OIDC dance is ~3 HTTP calls. ID token verification can use Google's
`tokeninfo` endpoint (1 extra call, simpler) **or** local JWKS verification (no round-trip,
~40 more lines).

---

## Static-file lockdown (CSV + avatars)

**The problem.** When a request hits `index.php`, the web server runs PHP and PHP runs
`requireViewer()`. When a request hits `assets/csv/org_struct_code_store.csv` or
`uploads/avatars/10001.webp`, nginx just streams the file off disk — **PHP never executes**,
so the viewer gate is not in the request path. The avatars are also trivially enumerable
(`10000.webp`, `10001.webp`, … sequential from the employee ID offset). Gating `index.php`
while these stay world-readable protects nothing.

The two files need *different* treatment: the CSV is static (deny unconditionally), the
avatars are conditional (serve to logged-in viewers, hide from everyone else).

### CSV — remove it from the equation

The CSV exists only as the `app.js` offline fallback (`app.js:3`). In PHP-backend mode
`index.php` injects `window.ORG_DATA` directly, so the CSV is dead weight.

1. Move `assets/csv/org_struct_code_store.csv` into `data/` (already denied) — or delete it;
   `seed.php` can re-fetch from the Google Sheet.
2. Drop the CSV-fallback branch from `app.js` (`app.js:96-101`).
3. Belt-and-suspenders: an nginx `deny` on `/assets/csv/` in case a file ever lingers.

A plain nginx rule is sufficient here because the decision is unconditional — "nobody, ever"
is exactly what a `location` block does well.

### Avatars — needs a per-request auth decision

Avatars *must* be served, but only to authenticated `@code.store` viewers. That decision is
tied to the PHP session. **Plain nginx rules cannot do this** — nginx has no view of the PHP
session (a cookie pointing at a session file); a `location` block can only do unconditional
allow/deny. nginx has to *ask PHP*.

| Option | How it works | Trade-off |
|---|---|---|
| **A. PHP passthrough** *(MVP)* | `avatar.php?id=10001` → `requireViewer()` → `readfile()`. `uploads/` denied at nginx level. | Simple, portable, no special nginx config. PHP streams ~10 KB webp files — fine at this scale. |
| **B. `auth_request` + `X-Accel-Redirect`** *(later)* | nginx intercepts `/uploads/avatars/…`, subrequests a PHP auth endpoint; on 200, nginx serves the file itself via `sendfile`. | Best performance, but nginx-specific config, more moving parts. Worth it only at scale. |
| **C. Pure nginx rule** | — | ❌ Not possible — nginx can't see the session. |

`avatar.php` for option A (≈25 lines):

```php
<?php
require __DIR__ . '/db.php';
requireViewer();
$id = (int)($_GET['id'] ?? 0);                 // int cast kills path traversal
$file = projectRoot() . "/uploads/avatars/$id.webp";
if ($id < 1 || !is_file($file)) { http_response_code(404); exit; }
header('Content-Type: image/webp');
header('Cache-Control: private, max-age=300');
readfile($file);
```

`getTree()` then emits `img_url` as `avatar.php?id=10001` instead of the raw path. The nginx
`deny` on `/uploads/` does **not** break `readfile()` — that's filesystem access, not HTTP.

### nginx config (replaces the current `.htaccess`)

```nginx
# --- sensitive files / dirs: unconditional deny ---
location ~ /\.(?!well-known)        { deny all; }   # .env, .git, dotfiles
location = /db.php                  { deny all; }
location ~ ^/(seed|router)\.php$    { deny all; }   # CLI/internal only
location ^~ /data/                  { deny all; }
location ^~ /uploads/               { deny all; }   # avatars only via avatar.php
location ^~ /assets/csv/            { deny all; }   # belt-and-suspenders

# --- option B only (later): internal location for X-Accel-Redirect ---
# location /protected-avatars/ {
#     internal;
#     alias /var/www/org-tree-code-store.test/uploads/avatars/;
# }

index index.php index.html;
```

`upload.php` stays publicly POST-able (it's the admin upload endpoint and runs its own
session + CSRF + referrer checks).

---

## Open questions

### 1. Is `code.store` a Google Workspace domain? — **RESOLVED: yes**

Confirmed 2026-05-13 via DNS.

```bash
$ dig +short MX code.store
1 aspmx.l.google.com.
5 alt1.aspmx.l.google.com.
5 alt2.aspmx.l.google.com.
10 alt3.aspmx.l.google.com.
10 alt4.aspmx.l.google.com.

$ dig +short TXT code.store | grep -i google
"v=spf1 include:_spf.google.com include:7091183.spf05.hubspotemail.net -all"
"google-site-verification=7cXKNrXKNwW7A_eGvNSWAAF8tnU05_zSfLW3i1FyGpM"
```

- All MX priorities point to Google's `aspmx.l.google.com` family — the canonical Workspace
  MX fingerprint.
- SPF includes `_spf.google.com`.
- A `google-site-verification=…` TXT record exists, set when the domain is verified inside
  Google Workspace / Cloud admin.

**Implication:** we can rely on the `hd` claim and gate sign-in with `hd === 'code.store'`.
Email-suffix fallback (kept for reference, in case `hd` ever comes back empty):
`email_verified === true` **and** `str_ends_with(strtolower($email), '@code.store')`.

### 2. Allowlist for people outside `@code.store`?

Out of scope for now — for both gates, `code.store` is the only population. An allowlist
table is purely additive later and doesn't require redesigning anything.

### 3. Session lifetime / stale sessions after offboarding — **mostly settled**

See the next section. Stakeholder call: an ~8h stale **viewer** session for a fired employee
is *not critical*. So Option A with a generous lifetime is acceptable. Still open: pick the
exact cap (8h? 24h?).

---

## What happens when a `@code.store` user is fired?

OAuth does **not** auto-revoke. Two distinct moments:

**At next sign-in — safe.** Once Workspace admin suspends or deletes the Google account,
Google refuses to issue a valid ID token. The OAuth callback fails. No new session.

**During an existing session — stale until it expires.** The app sets `$_SESSION['viewer_email']`
at login and doesn't re-check with Google. A fired user with a live session cookie keeps
*view* access until it expires.

Per stakeholder: for the **viewer gate** this is low-criticality — the data is sensitive but
read-only, and an ex-employee already saw the chart while employed. So:

| Option | What it does | Cost |
|---|---|---|
| **A. Session lifetime cap** *(chosen)* | Cap the session (8–24h), force re-auth. Fired user locked out at next forced re-auth. | Trivial — `startSession()` cookie params + a stored `logged_in_at` check. |
| **B. Periodic re-verify** | Store ID token `exp`; when it lapses (~1h) bounce through silent re-auth. Google refuses fresh tokens for suspended accounts. | ~30 min — keep as a later upgrade. |
| **C. Real-time revocation check** | Hit `tokeninfo` on every request. | Overkill. |

For the **admin gate**, revocation = rotate `ADMIN_PASSWORD`. That's the MVP story; per-user
admin auth is a later step if attribution/audit becomes a requirement.

---

## Effort

- **MVP** — viewer-gate Google OAuth (`hd` gate, Option A session cap), gate `index.php` +
  sign-in landing page, lock down CSV + avatar leaks; admin gate untouched: **~4–6 hours**.
  (The admin side is now ~0 work; the added cost is the static-asset lockdown.)
- **Production** — local JWT verify against Google JWKS, `users` allowlist, audit log,
  "Signed in as …" UI, Option B re-verify, optional per-user admin auth: **~1–1.5 days**.

---

## Tradeoffs & implementation notes

- Original `claude_code_admin_prompt.md` says *"No Google OAuth"*, *"No user roles"*, and
  treats the chart as public read-only. This proposal intentionally widens all three.
- **The static-file leaks are the easy thing to forget.** Gating `index.php` while leaving
  `assets/csv/…` and `uploads/avatars/…` world-readable protects nothing. They must ship together.
- Google Cloud Console setup (OAuth client, redirect URIs) is manual. Local dev needs its own
  redirect URI (e.g. `http://localhost:8000/index.php?action=oauth_callback`) registered
  alongside production.
- `php -S localhost` is fine for OAuth callbacks as long as the redirect URI is registered.
- Avatar passthrough adds a small per-image PHP cost. For a small company this is negligible;
  add an `ETag` / `Cache-Control: private` if it ever matters.

---

## Suggested next steps

1. ~~Confirm `code.store` is Workspace.~~ **Done — confirmed 2026-05-13.**
2. ~~Decide avatar strategy.~~ **Done — PHP passthrough (`avatar.php`) for MVP, nginx deploy.**
3. Pick the viewer session lifetime cap (8h vs 24h).
4. Confirm the admin gate stays shared-password for MVP (assumed yes).
5. Port the current `.htaccess` rules to the nginx config (see Static-file lockdown section).
6. Spike the viewer-gate MVP behind a feature flag in `.env` (`VIEWER_AUTH=google|open`).
