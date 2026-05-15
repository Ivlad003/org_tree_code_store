# Google Cloud setup for the viewer gate

Operational walkthrough for creating the OAuth 2.0 client that powers
`auth_google.php`. Pairs with the design in `google_oauth_auth_plan.md`.

**Estimated time:** ~10 minutes.

---

## 0. Prerequisites

- Sign in to https://console.cloud.google.com with a `@code.store` Google account
  that has permission to create projects inside the **code.store** organization.
  (If you're a Workspace super-admin you're fine. If not, ask one to grant you
  Project Creator on the org.)
- The org selector in the top bar should show **code.store**, not "No organization".
  This is what later lets you set the consent screen to *Internal*.

---

## 1. Create (or select) a project

Console → top bar **project picker** → **New Project**.

| Field | Value |
|---|---|
| Name | `code-store-org-chart` (or similar) |
| Organization | **code.store** ← must be this, not "No organization" |
| Location | code.store |

Wait for the project to provision, then select it.

---

## 2. Configure the OAuth consent screen

Console → **APIs & Services → OAuth consent screen**.

| Field | Value | Why |
|---|---|---|
| User Type | **Internal** | Restricts sign-in to the code.store Workspace at Google's side — strictly stronger than our `hd` check, and skips the app-verification process required for External + sensitive scopes. |
| App name | `code.store Team Chart` | Shown on the consent screen. |
| User support email | a `@code.store` address | Required field. |
| App logo | optional | Skip for the spike. |
| App domain → Application home page | `https://your-domain` (prod) | Optional but tidier. |
| Developer contact email | a `@code.store` address | Required. |

**Scopes** screen → click **Add or Remove Scopes** → tick:

- `.../auth/userinfo.email`
- `.../auth/userinfo.profile`
- `openid`

These are the three non-sensitive scopes our code requests. Save and continue.

**Test users**: not needed for Internal apps (everyone in the Workspace is implicitly allowed).

Click **Back to Dashboard**. For Internal apps the publishing state stays
"In production" automatically — no verification needed.

---

## 3. Create the OAuth 2.0 Client ID

Console → **APIs & Services → Credentials** → **+ Create Credentials → OAuth client ID**.

| Field | Value |
|---|---|
| Application type | **Web application** |
| Name | `code.store org chart — web` |
| Authorized JavaScript origins | `https://your-domain` *and* `http://localhost:8000` |
| Authorized redirect URIs | `https://your-domain/auth_google.php` *and* `http://localhost:8000/auth_google.php` |

The redirect URI must **equal `OAUTH_REDIRECT_URI` in `.env` byte-for-byte** —
no trailing slash, no query string. Register both prod and localhost so the same
client works for local dev. Google explicitly allows `http://localhost:*` here.

Click **Create**. A modal shows the **Client ID** and **Client secret** — copy both
immediately (the secret is shown only once; you can rotate it later if needed).

---

## 4. Fill `.env` and flip the flag

```bash
# .env (local dev)
VIEWER_AUTH=google
GOOGLE_CLIENT_ID=<paste here>
GOOGLE_CLIENT_SECRET=<paste here>
OAUTH_REDIRECT_URI=http://localhost:8000/auth_google.php
```

For production, set `OAUTH_REDIRECT_URI=https://your-domain/auth_google.php`
in the deployed `.env`. Same client ID works for both as long as both URIs
are registered in step 3.

---

## 5. Test the flow

```bash
php -S 127.0.0.1:8000 router.php
```

Open http://localhost:8000/ — you should see the **Sign in with Google**
landing page (not the chart). Click it. The expected sequence:

1. Browser → `auth_google.php?action=start` → redirect to `accounts.google.com`
2. Google's account chooser appears (restricted to code.store accounts because
   the consent screen is Internal)
3. After consent → redirect back to `auth_google.php` with `?code=…&state=…`
4. Server-side: code exchange, `hd` check, session set
5. Redirect to `/index.php` — the chart now renders, and the header shows a
   **SIGN OUT** link with your email in the title attribute

If you sign in with a non-code.store Google account (e.g. a personal Gmail),
Google itself blocks the attempt at step 2 because the app is Internal. Belt
and suspenders: even if Google somehow returned a token, our `hd === code.store`
check would reject it at step 4.

---

## 6. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `Error 400: redirect_uri_mismatch` | The URI Google receives doesn't exactly match a registered Authorized redirect URI. | Check for trailing slash, http vs https, port, query string. Re-copy from `.env` into the Cloud Console. |
| Stuck on Google's consent screen, "App isn't verified" | Consent screen was set to **External**, not Internal. | Switch to Internal (User Type → Internal). Requires the project to live inside the code.store org. |
| Flash: *"Sign-in is restricted to verified @code.store accounts."* | Token came back without `hd` or with `email_verified=false`. | Almost always means you signed in with a personal Gmail. Try again with `@code.store`. |
| Flash: *"Token exchange with Google failed."* | Bad `GOOGLE_CLIENT_SECRET`, or the server can't reach `oauth2.googleapis.com`. | Re-paste the secret. Check `curl https://oauth2.googleapis.com/token` reachability. |
| Flash: *"Sign-in state mismatch — please try again."* | Session cookie lost between `start` and callback (third-party-cookie blocking, cookie-jar reset). | Make sure prod is on HTTPS; consider setting `session.cookie_secure=1` and `samesite=Lax` (already the default in `startSession()`). |
| Signed in but chart never appears | `getTree()` returns empty, or the JS crashed. | Open devtools → console; check that `window.ORG_DATA` is non-empty in the page source. |

---

## 7. Rotating / revoking

- **Rotate secret**: Credentials → click the client → **Reset Secret** → paste
  new secret into `.env`. Old secret stops working immediately.
- **Revoke a session**: at the user level, suspend the Google account in
  Workspace admin. New sign-ins fail immediately; existing sessions expire
  within `VIEWER_SESSION_MAX_AGE` (8h, set in `db.php`).
- **Revoke the whole client**: Credentials → delete the OAuth client.
  All in-flight sessions stay valid until they expire (cap is 8h); no one can
  sign in anew. Useful as a kill-switch.
