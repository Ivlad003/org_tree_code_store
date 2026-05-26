<?php
// Shared DB access + helpers. Included by the entry points in public/ and by seed.php.
// Lives in src/ (outside the public/ web root), so it's never reachable over HTTP.

declare(strict_types=1);

const EMPLOYEE_ID_OFFSET = 10000;

function projectRoot(): string {
    // db.php lives in src/; the repo root (holding data/, uploads/, .env) is its parent.
    return dirname(__DIR__);
}

function loadEnv(string $path = null): void {
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
    $floor = EMPLOYEE_ID_OFFSET - 1;
    $current = (int)$pdo->query("SELECT COALESCE(MAX(seq), 0) FROM sqlite_sequence WHERE name = 'employees'")->fetchColumn();
    $target = max($current, $floor);
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'employees'");
    $stmt = $pdo->prepare("INSERT INTO sqlite_sequence (name, seq) VALUES ('employees', ?)");
    $stmt->execute([$target]);
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
            'first_name' => $e['first_name'] ?? '',
            'last_name' => $e['last_name'] ?? '',
            'department_name' => $e['department_name'] ?? '',
            // Avatars are served through avatar.php (viewer-gated), never the raw path.
            'img_url' => !empty($e['avatar_path']) ? 'avatar.php?id=' . (int)$e['id'] : '',
            'linkedin_url' => $e['linkedin_url'] ?? '',
            'description' => $e['description'] ?? '',
        ];
    }
    return $rows;
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
    $pdo = db();
    $parentId = $data['parent_id'] !== '' && $data['parent_id'] !== null ? (int)$data['parent_id'] : null;
    $sortOrder = (int)($data['sort_order'] ?? 0);

    if (!empty($data['id'])) {
        $stmt = $pdo->prepare("UPDATE departments SET parent_id = ?, name = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$parentId, $data['name'], $sortOrder, (int)$data['id']]);
        return (int)$data['id'];
    }
    $stmt = $pdo->prepare("INSERT INTO departments (parent_id, name, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$parentId, $data['name'], $sortOrder]);
    return (int)$pdo->lastInsertId();
}

function deleteDepartment(int $id): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Detach employees that pointed at this dept
        $pdo->prepare("UPDATE employees SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
        // Detach child departments
        $pdo->prepare("UPDATE departments SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
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
    $pdo = db();
    $parentId = $data['parent_id'] !== '' && $data['parent_id'] !== null ? (int)$data['parent_id'] : null;
    $payload = [
        $parentId,
        trim($data['first_name'] ?? ''),
        trim($data['last_name'] ?? '') ?: null,
        trim($data['department_name'] ?? '') ?: null,
        trim($data['linkedin_url'] ?? '') ?: null,
        trim($data['description'] ?? '') ?: null,
    ];

    if (!empty($data['id'])) {
        $stmt = $pdo->prepare("
            UPDATE employees
            SET parent_id = ?, first_name = ?, last_name = ?, department_name = ?,
                linkedin_url = ?, description = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $payload[] = (int)$data['id'];
        $stmt->execute($payload);
        return (int)$data['id'];
    }
    $stmt = $pdo->prepare("
        INSERT INTO employees (parent_id, first_name, last_name, department_name, linkedin_url, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute($payload);
    return (int)$pdo->lastInsertId();
}

function deleteEmployee(int $id): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Re-parent any employees that pointed at this one
        $pdo->prepare("UPDATE employees SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);

        // Drop avatar file from disk
        $stmt = $pdo->prepare("SELECT avatar_path FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        $avatar = $stmt->fetchColumn();
        if ($avatar) {
            $disk = projectRoot() . '/' . ltrim((string)$avatar, '/');
            if (is_file($disk)) @unlink($disk);
        }

        $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
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
    if ($err === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        return null; // nothing uploaded — not an error
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
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('CSRF token mismatch');
    }
}

function escapeHtml(?string $text): string {
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
