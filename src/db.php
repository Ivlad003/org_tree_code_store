<?php
// Shared DB access + helpers. Included by the entry points in public/ and by seed.php.
// Lives in src/ (outside the public/ web root), so it's never reachable over HTTP.

declare(strict_types=1);

const EMPLOYEE_ID_OFFSET = 10000;

function projectRoot(): string {
    // db.php lives in src/; the repo root (holding data/, uploads/, .env) is its parent.
    return dirname(__DIR__);
}

function loadEnv(?string $path = null): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $path ??= projectRoot() . '/.env';
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dbFile = projectRoot() . '/data/db.sqlite';
    $isNew = !file_exists($dbFile);

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    // Without a busy timeout a second concurrent writer fails outright instead of
    // waiting. WAL lets the 60 chart readers read while an admin writes.
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS departments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER,
            name TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER,
            first_name TEXT NOT NULL,
            last_name TEXT,
            department_name TEXT,
            avatar_path TEXT,
            linkedin_url TEXT,
            description TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Seed AUTOINCREMENT for employees so the first inserted row gets id 10000.
    // sqlite_sequence has no uniqueness constraint, so always normalize: collapse any
    // existing rows for 'employees' down to one with seq = max(observed, offset-1).
    // Read first and only write when something is actually wrong: this runs on every
    // db() call, so an unconditional DELETE+INSERT made every chart view a writer —
    // contending for the write lock, and fataling outright on a read-only DB file.
    $floor = EMPLOYEE_ID_OFFSET - 1;
    $rows = $pdo->query("SELECT COUNT(*) AS n, COALESCE(MAX(seq), 0) AS hi FROM sqlite_sequence WHERE name = 'employees'")->fetch();
    $count = (int)$rows['n'];
    $hi    = (int)$rows['hi'];

    if ($count === 0) {
        $pdo->prepare("INSERT INTO sqlite_sequence (name, seq) VALUES ('employees', ?)")->execute([$floor]);
    } elseif ($count > 1 || $hi < $floor) {
        // >1 row is possible — sqlite_sequence has no unique constraint, and
        // AUTOINCREMENT then reads only the first, which can hand out stale ids.
        $target = max($hi, $floor);
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'employees'");
        $pdo->prepare("INSERT INTO sqlite_sequence (name, seq) VALUES ('employees', ?)")->execute([$target]);
    }
}

// ── Tree query: merge departments + employees into the flat shape app.js expects ─

function getTree(): array {
    $pdo = db();
    $rows = [];

    $depts = $pdo->query("SELECT id, parent_id, name, sort_order FROM departments ORDER BY sort_order, id")->fetchAll();
    foreach ($depts as $d) {
        $rows[] = [
            'id' => (string)$d['id'],
            'parentId' => $d['parent_id'] !== null ? (string)$d['parent_id'] : '',
            // The client used to guess "is this a department?" from a hardcoded list
            // of names, so every new department rendered as a person. Say it outright.
            'type' => 'dept',
            'first_name' => $d['name'],
            'last_name' => '',
            'department_name' => $d['name'],
            'img_url' => '',
            'linkedin_url' => '',
            'description' => '',
        ];
    }

    $emps = $pdo->query("SELECT id, parent_id, first_name, last_name, department_name, avatar_path, linkedin_url, description, sort_order FROM employees ORDER BY sort_order, id")->fetchAll();
    foreach ($emps as $e) {
        $rows[] = [
            'id' => (string)$e['id'],
            'parentId' => $e['parent_id'] !== null ? (string)$e['parent_id'] : '',
            'type' => 'person',
            'first_name' => $e['first_name'] ?? '',
            'last_name' => $e['last_name'] ?? '',
            'department_name' => $e['department_name'] ?? '',
            // Avatars are served through avatar.php (viewer-gated), never the raw path.
            // Check the file exists: a DB row whose file is gone (restored backup,
            // lost volume) otherwise emits an <img> that 404s on every page load.
            'img_url' => avatarUrl($e),
            'linkedin_url' => $e['linkedin_url'] ?? '',
            'description' => $e['description'] ?? '',
        ];
    }
    return $rows;
}

// Avatar URL for a row, or '' when the file is missing on disk.
function avatarUrl(array $employee): string {
    if (empty($employee['avatar_path'])) return '';
    $disk = projectRoot() . '/' . ltrim((string)$employee['avatar_path'], '/');
    return is_file($disk) ? 'avatar.php?id=' . (int)$employee['id'] : '';
}

// ── Validation ───────────────────────────────────────────────────────────────
// The chart is a strict tree: d3-org-chart throws "multiple roots" / "no root" and
// renders NOTHING if the data has two roots or a cycle. Both are one click away in
// the admin UI ("— none (root) —" is the default parent option), so the rules are
// enforced here, where every caller routes through, rather than per form.

class ValidationError extends RuntimeException {}

// Node ids are unique across both tables (employees start at EMPLOYEE_ID_OFFSET),
// so a single id → parent_id map describes the whole tree.
function parentMap(): array {
    $pdo = db();
    $map = [];
    foreach ($pdo->query("SELECT id, parent_id FROM departments")->fetchAll() as $r) {
        $map[(int)$r['id']] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
    }
    foreach ($pdo->query("SELECT id, parent_id FROM employees")->fetchAll() as $r) {
        $map[(int)$r['id']] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
    }
    return $map;
}

function currentRootId(): ?int {
    foreach (parentMap() as $id => $parent) {
        if ($parent === null) return $id;
    }
    return null;
}

// Validates the parent a save is about to write. $selfId is null when creating.
// Returns the parent id to persist (never an invalid one) or throws.
function validateParent(?int $parentId, ?int $selfId): ?int {
    $map  = parentMap();
    $root = currentRootId();

    if ($parentId === null) {
        // Only the existing root may stay parentless; anything else would be a
        // second root and would blank the chart for everyone.
        if ($root === null || ($selfId !== null && $selfId === $root)) return null;
        throw new ValidationError('The chart already has a root node. Pick a parent — only one node can sit at the top.');
    }
    if (!array_key_exists($parentId, $map)) {
        throw new ValidationError('That parent does not exist (it may have just been deleted).');
    }
    if ($selfId !== null) {
        if ($parentId === $selfId) {
            throw new ValidationError('A node cannot be its own parent.');
        }
        // Walk up from the chosen parent: meeting $selfId means we would close a loop.
        $seen = [];
        for ($cur = $parentId; $cur !== null; $cur = $map[$cur] ?? null) {
            if ($cur === $selfId) {
                throw new ValidationError('That parent sits below this node — it would create a loop.');
            }
            if (isset($seen[$cur])) break;   // pre-existing loop; don't spin
            $seen[$cur] = true;
        }
    }
    return $parentId;
}

// Only http(s) links are storable. escapeHtml() does not neutralise a
// javascript: URL — it survives into href= intact — and the client-side
// isValidUrl() check only guards the chart, not the admin list.
function sanitizeUrl(string $url): ?string {
    $url = trim($url);
    if ($url === '') return null;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'http' && $scheme !== 'https') {
        throw new ValidationError('The LinkedIn URL must start with http:// or https://');
    }
    return $url;
}

// Runs $fn holding the write lock from the first statement. Validation that reads
// the tree and a write that depends on it must sit inside the SAME transaction:
// otherwise two admins both validate against the pre-move tree and both commit,
// producing a cycle or a dangling parent — either of which blanks the chart.
function inWriteTransaction(callable $fn) {
    $pdo = db();
    if ($pdo->inTransaction()) return $fn($pdo);   // already inside one; don't nest
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function requireNonEmpty(string $value, string $label): string {
    $value = trim($value);
    if ($value === '') throw new ValidationError($label . ' is required.');
    return $value;
}

// ── Department CRUD ──────────────────────────────────────────────────────────

function getDepartments(): array {
    return db()->query("SELECT id, parent_id, name, sort_order FROM departments ORDER BY name")->fetchAll();
}

function getDepartment(int $id): ?array {
    $stmt = db()->prepare("SELECT id, parent_id, name, sort_order FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function saveDepartment(array $data): int {
    $selfId    = !empty($data['id']) ? (int)$data['id'] : null;
    $rawParent = ($data['parent_id'] ?? '') !== '' && $data['parent_id'] !== null ? (int)$data['parent_id'] : null;
    $name      = requireNonEmpty((string)($data['name'] ?? ''), 'Department name');
    $sortOrder = (int)($data['sort_order'] ?? 0);

    return inWriteTransaction(function (PDO $pdo) use ($selfId, $rawParent, $name, $sortOrder) {
        $parentId = validateParent($rawParent, $selfId);
        if ($selfId !== null) {
            $stmt = $pdo->prepare("UPDATE departments SET parent_id = ?, name = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$parentId, $name, $sortOrder, $selfId]);
            return $selfId;
        }
        $stmt = $pdo->prepare("INSERT INTO departments (parent_id, name, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$parentId, $name, $sortOrder]);
        return (int)$pdo->lastInsertId();
    });
}

function deleteDepartment(int $id): void {
    // Re-parenting children to NULL turned every one of them into a root and blanked
    // the chart. Promote them to this node's parent instead, so the tree stays whole.
    // detachTarget() must run INSIDE the transaction: read outside it, another admin
    // can delete the grandparent in the gap and the children point at a missing row.
    inWriteTransaction(function (PDO $pdo) use ($id) {
        $grandparent = detachTarget($id, 'departments');
        $pdo->prepare("UPDATE employees SET parent_id = ? WHERE parent_id = ?")->execute([$grandparent, $id]);
        $pdo->prepare("UPDATE departments SET parent_id = ? WHERE parent_id = ?")->execute([$grandparent, $id]);
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
    });
}

// Where a deleted node's children should go: its own parent. Deleting the root is
// refused while it still has children — there would be no single node left on top.
function detachTarget(int $id, string $table): ?int {
    $stmt = db()->prepare("SELECT parent_id FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false) throw new ValidationError('That record no longer exists.');

    $parent = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
    if ($parent === null) {
        $kids = (int)db()->query("
            SELECT (SELECT COUNT(*) FROM departments WHERE parent_id = {$id})
                 + (SELECT COUNT(*) FROM employees   WHERE parent_id = {$id})
        ")->fetchColumn();
        if ($kids > 0) {
            throw new ValidationError('This is the top node of the chart. Move or delete its children first.');
        }
        // Childless root: allowed only when it is the last node left, so deleting it
        // clears an already-empty chart rather than wiping a populated one.
        $others = (int)db()->query("
            SELECT (SELECT COUNT(*) FROM departments) + (SELECT COUNT(*) FROM employees)
        ")->fetchColumn();
        if ($others > 1) {
            throw new ValidationError('This is the top node of the chart. Give the chart another top node first.');
        }
    }
    return $parent;
}

// ── Employee CRUD ────────────────────────────────────────────────────────────

function getEmployees(): array {
    return db()->query("
        SELECT id, parent_id, first_name, last_name, department_name, avatar_path, linkedin_url, description
        FROM employees
        ORDER BY last_name, first_name
    ")->fetchAll();
}

function getEmployee(int $id): ?array {
    $stmt = db()->prepare("
        SELECT id, parent_id, first_name, last_name, department_name, avatar_path, linkedin_url, description
        FROM employees WHERE id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function saveEmployee(array $data): int {
    $selfId    = !empty($data['id']) ? (int)$data['id'] : null;
    $rawParent = ($data['parent_id'] ?? '') !== '' && $data['parent_id'] !== null ? (int)$data['parent_id'] : null;
    $firstName = requireNonEmpty((string)($data['first_name'] ?? ''), 'First name');
    // Every field gets a string cast: a crafted POST can send last_name[]=x, and
    // trim() on an array is a fatal TypeError.
    $fields = [
        $firstName,
        trim((string)($data['last_name'] ?? '')) ?: null,
        trim((string)($data['department_name'] ?? '')) ?: null,
        sanitizeUrl((string)($data['linkedin_url'] ?? '')),
        trim((string)($data['description'] ?? '')) ?: null,
    ];

    return inWriteTransaction(function (PDO $pdo) use ($selfId, $rawParent, $fields) {
        $payload = array_merge([validateParent($rawParent, $selfId)], $fields);
        if ($selfId !== null) {
            $stmt = $pdo->prepare("
                UPDATE employees
                SET parent_id = ?, first_name = ?, last_name = ?, department_name = ?,
                    linkedin_url = ?, description = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $payload[] = $selfId;
            $stmt->execute($payload);
            return $selfId;
        }
        $stmt = $pdo->prepare("
            INSERT INTO employees (parent_id, first_name, last_name, department_name, linkedin_url, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($payload);
        return (int)$pdo->lastInsertId();
    });
}

function deleteEmployee(int $id): void {
    $avatar = inWriteTransaction(function (PDO $pdo) use ($id) {
        $grandparent = detachTarget($id, 'employees');
        // Promote reports to this employee's own manager, not to root (see deleteDepartment).
        $pdo->prepare("UPDATE employees SET parent_id = ? WHERE parent_id = ?")->execute([$grandparent, $id]);
        $pdo->prepare("UPDATE departments SET parent_id = ? WHERE parent_id = ?")->execute([$grandparent, $id]);

        $stmt = $pdo->prepare("SELECT avatar_path FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        $path = $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
        return $path;
    });

    // unlink() cannot be rolled back. Deleting the file inside the transaction meant
    // a later failure restored the row with its photo already gone for good.
    if ($avatar) {
        $disk = projectRoot() . '/' . ltrim((string)$avatar, '/');
        if (is_file($disk)) @unlink($disk);
    }
}

function setEmployeeAvatar(int $id, ?string $path): void {
    $stmt = db()->prepare("UPDATE employees SET avatar_path = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$path, $id]);
}

// Process a single uploaded image ($_FILES entry) into uploads/avatars/{id}.webp,
// scaling down to 400px and recording the path. Returns null on success, or a
// human-readable error string. An empty upload (no file chosen) is a no-op → null.
// Shared by upload.php (edit form) and admin.php save_employee (create form).
function storeAvatarUpload(int $id, array $file): ?string {
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) {
        return null; // nothing chosen — not an error
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return 'That photo is larger than the server allows ('
             . ini_get('upload_max_filesize') . ' max).';
    }
    if ($err !== UPLOAD_ERR_OK) {
        return 'Upload failed (PHP error ' . (int)$err . ').';
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return 'File too large (max 5 MB).';
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        return 'Not a valid image.';
    }
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        default => null,
    };
    if (!$img) {
        return 'Unsupported image type (jpg, png, webp only).';
    }

    $w = imagesx($img); $h = imagesy($img);
    $max = 400;
    if ($w > $max || $h > $max) {
        $scale = min($max / $w, $max / $h);
        $resized = imagescale($img, (int)round($w * $scale), (int)round($h * $scale));
        if ($resized) { imagedestroy($img); $img = $resized; }
    }

    $avatarDir = projectRoot() . '/uploads/avatars';
    if (!is_dir($avatarDir)) @mkdir($avatarDir, 0775, true);
    $outFs = $avatarDir . '/' . $id . '.webp';
    if (!imagewebp($img, $outFs, 85)) {
        imagedestroy($img);
        return 'Could not write avatar to disk.';
    }
    imagedestroy($img);

    setEmployeeAvatar($id, 'uploads/avatars/' . $id . '.webp');
    return null;
}

// ── Tree node options (for parent_id <select>) ───────────────────────────────

function getParentOptions(): array {
    $pdo = db();
    $out = [];
    foreach ($pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll() as $d) {
        $out[] = ['id' => (int)$d['id'], 'label' => '[Dept] ' . $d['name']];
    }
    foreach ($pdo->query("SELECT id, first_name, last_name FROM employees ORDER BY last_name, first_name")->fetchAll() as $e) {
        $name = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
        $out[] = ['id' => (int)$e['id'], 'label' => $name ?: ('Employee #' . $e['id'])];
    }
    return $out;
}

// ── Auth + security helpers ──────────────────────────────────────────────────

function startSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isAdmin(): bool {
    startSession();
    return !empty($_SESSION['admin']);
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: admin.php');
        exit;
    }
}

// ── Viewer gate (Google OAuth, restricted to the code.store Workspace) ───────
// See docs/google_oauth_auth_plan.md. The admin gate (above) is independent;
// an admin always counts as a viewer, but not vice versa.

// Option A from the plan: cap the viewer session so a fired employee's stale
// session can't outlive this window. Tighten by lowering, or move to Option B.
const VIEWER_SESSION_MAX_AGE = 8 * 3600; // 8 hours

function viewerAuthMode(): string {
    loadEnv();
    return strtolower(trim($_ENV['VIEWER_AUTH'] ?? 'google')) === 'open' ? 'open' : 'google';
}

function isViewer(): bool {
    if (viewerAuthMode() === 'open') return true;  // gate disabled — public chart
    if (isAdmin()) return true;                    // admin implies viewer
    startSession();
    if (empty($_SESSION['viewer_email'])) return false;
    $loginAt = (int)($_SESSION['viewer_login_at'] ?? 0);
    if ($loginAt <= 0 || (time() - $loginAt) > VIEWER_SESSION_MAX_AGE) {
        unset($_SESSION['viewer_email'], $_SESSION['viewer_name'], $_SESSION['viewer_login_at']);
        return false;
    }
    return true;
}

function requireViewer(): void {
    if (!isViewer()) {
        http_response_code(403);
        exit('Forbidden — sign in to view this resource.');
    }
}

function viewerEmail(): ?string {
    startSession();
    $email = $_SESSION['viewer_email'] ?? null;
    return is_string($email) && $email !== '' ? $email : null;
}

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function checkCsrf(): void {
    startSession();
    $expected = $_SESSION['csrf'] ?? '';
    $token    = $_POST['csrf'] ?? '';
    // hash_equals('', '') is true, so a session that has never minted a token
    // accepted a POST carrying no token at all. Require both sides to be present.
    if (!is_string($expected) || $expected === '' || !is_string($token) || $token === ''
        || !hash_equals($expected, $token)) {
        http_response_code(403);
        exit('CSRF token mismatch');
    }
}

function escapeHtml(?string $text): string {
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
