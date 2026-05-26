# Task: Build minimal org chart admin panel (PHP + SQLite)

## Goal
A minimal self-hosted admin panel for managing the code.store org chart.
No Docker, no OAuth, no roles — just a simple password-protected CRUD interface
with photo upload. Built alongside the existing `index.html` + `app.js` frontend.

---

## Stack
- PHP 8.2 (built-in server for local dev, Apache/nginx for deploy)
- SQLite via PDO (single file, zero config)
- GD library for image processing (built into PHP)
- Plain HTML + minimal CSS (no frameworks)
- Existing frontend: `index.html` + `app.js` (d3-org-chart)

---

## File structure

```
project/
├── index.php              ← replaces index.html — injects ORG_DATA from SQLite
├── admin.php              ← full CRUD admin panel (single file)
├── db.php                 ← SQLite connection + helper functions
├── upload.php             ← photo upload handler (called via form POST)
├── app.js                 ← unchanged from current version, reads window.ORG_DATA
├── assets/
│   ├── css/
│   │   ├── style.css      ← org chart styles (from current index.html <style>)
│   │   └── admin.css      ← admin panel styles
│   └── js/
│       └── app.js         ← symlink or copy of app.js
├── uploads/
│   └── avatars/           ← employee photos (WebP)
├── data/
│   └── db.sqlite          ← database file
└── .env                   ← ADMIN_PASSWORD=secret
```

---

## Database schema (SQLite)

```sql
CREATE TABLE departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER,
    name TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER,           -- references employees.id OR departments.id
    first_name TEXT NOT NULL,
    last_name TEXT,
    department_name TEXT,
    role TEXT,                   -- job title / description shown in card
    avatar_path TEXT,            -- /uploads/avatars/123.webp
    linkedin_url TEXT,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

The tree is flat — both departments and employees share the same hierarchy via
`parent_id`. This matches the existing d3-org-chart data model (id + parentId).

---

## db.php

- Connect to `data/db.sqlite` via PDO
- Run schema migrations on first connect (CREATE TABLE IF NOT EXISTS)
- Helper functions:
  - `getTree()` — returns all rows as array for JSON encoding
  - `getEmployee($id)`
  - `saveEmployee($data)` — INSERT or UPDATE
  - `deleteEmployee($id)`
  - `getDepartments()` — flat list for `<select>` in forms

---

## index.php

Replaces `index.html`. Identical output but:
1. Checks if user is authenticated (session cookie set by admin.php login)
   - If not authenticated: show the org chart anyway (read-only, no auth needed for viewing)
2. Calls `getTree()` and injects data:

```php
<?php require 'db.php'; $tree = getTree(); ?>
<script>
    window.ORG_DATA = <?= json_encode($tree) ?>;
</script>
```

3. Loads `assets/css/style.css` and `assets/js/app.js`
4. The `app.js` change: replace `loadData()` fetch with:

```js
async function loadData() {
    if (window.ORG_DATA) return window.ORG_DATA;
    // fallback: fetch from Google Sheets (keep original URL as backup)
    ...
}
```

---

## admin.php

Single-file admin panel. Sections:

### Authentication
- Read `ADMIN_PASSWORD` from `.env`
- Simple login form (POST) → set `$_SESSION['admin'] = true`
- Logout link
- All admin actions check session, redirect to login if not set

### Employee list (default view)
- Table: avatar thumbnail | full name | department | role | edit | delete
- Search input (filters table client-side via JS)
- "Add employee" button → shows add form
- Department filter dropdown

### Add / Edit form
Fields:
- First name (required)
- Last name
- Department (select from departments table + "add new" option)
- Parent node (select — shows all employees and departments for tree placement)
- Role / title
- LinkedIn URL
- Description
- Photo upload (file input, accepts jpg/png/webp, max 5MB)
  - Shows current photo thumbnail if editing
  - Checkbox "Remove photo" when editing

### Delete
- POST action with confirmation (`onclick="return confirm(...)"`)
- Also deletes avatar file from disk

### Department management
- Simple list below employee list
- Add / rename / delete departments
- Note: deleting a department does NOT delete its employees (set their dept to null)

---

## upload.php

Called via form POST from admin.php. Not accessible directly (check referrer + session).

Steps:
1. Validate session
2. Validate file: type must be image/jpeg, image/png, or image/webp; size < 5MB
3. Load image with GD (`imagecreatefromjpeg` / `imagecreatefrompng` / `imagecreatefromwebp`)
4. Resize to max 400×400px keeping aspect ratio (`imagescale`)
5. Convert and save as WebP: `imagewebp($img, "uploads/avatars/{$id}.webp", 85)`
6. Update `avatar_path` in DB
7. Delete old avatar file if exists
8. Redirect back to edit form

---

## app.js changes

Minimal. Only change `loadData()`:

```js
async function loadData() {
    // Use server-injected data if available (PHP backend mode)
    if (typeof window.ORG_DATA !== 'undefined' && window.ORG_DATA) {
        return processRows(window.ORG_DATA);
    }
    // Fallback: fetch from Google Sheets CSV (development / static mode)
    try {
        const response = await fetch(DATA_URL);
        ...
    }
}
```

Extract row processing logic into `processRows(rows)` so both paths use it.

---

## .env format

```
ADMIN_PASSWORD=your_password_here
```

Parse with a simple function (no library needed):
```php
function loadEnv($path = '.env') {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}
```

---

## Security requirements
- All DB queries use PDO prepared statements (no string interpolation in SQL)
- `escapeHtml()` on all output in admin panel
- File upload: validate MIME type via `getimagesize()`, not just extension
- Admin routes check session on every request
- `.env` and `data/` must not be web-accessible — add to `.htaccess` or nginx config:

```
# .htaccess
<FilesMatch "^\.env$">
    Deny from all
</FilesMatch>
<FilesMatch "^db\.php$">
    Deny from all
</FilesMatch>
```

---

## What NOT to build
- No Docker (run with `php -S localhost:8000`)
- No Google OAuth
- No user roles
- No REST API
- No JS framework in admin (plain HTML forms)
- No pagination (company is small, simple list is fine)

---

## Local development

```bash
php -S localhost:8000
# open http://localhost:8000        → org chart
# open http://localhost:8000/admin  → admin panel
```

---

## Seed data

After schema creation, import existing data from the Google Sheets CSV
(`DATA_URL` assets/csv/org_struct_code_store.csv in app.js) into SQLite as a one-time migration script `seed.php`:

```
php seed.php
```

`seed.php` should:
1. Fetch the CSV from `DATA_URL`
2. Parse it (reuse the JS parseCSV logic, rewritten in PHP)
3. Insert all rows into `employees` table
4. Output count of imported rows

claude --resume 6b4665ea-1d21-48f4-851a-4efb6679f032