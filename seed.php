<?php
// One-time seed: import assets/csv/org_struct_code_store.csv into SQLite.
// Run from CLI:  php seed.php
// Safe to re-run only if you first delete data/db.sqlite — schema migrations
// run on connect, but this script INSERTs without dedup.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('seed.php must be run from the command line');
}

require __DIR__ . '/db.php';

// CSV lives under data/ (web-denied), not the public webroot.
const CSV_PATH = __DIR__ . '/data/org_struct_code_store.csv';
const AVATAR_DIR = __DIR__ . '/uploads/avatars';
const GROUP_NAMES = ['pms', 'pm', 'qa', 'developers', 'developer', 'hr', 'growth', 'bdr', 'founders', 'code.store'];

function parseCsv(string $path): array {
    $rows = [];
    $fh = fopen($path, 'r');
    if (!$fh) {
        fwrite(STDERR, "Cannot open CSV: $path\n");
        exit(1);
    }
    $headers = fgetcsv($fh);
    if (!$headers) { fclose($fh); return []; }
    $headers = array_map('trim', $headers);
    while (($cells = fgetcsv($fh)) !== false) {
        $row = [];
        foreach ($headers as $i => $h) {
            $row[$h] = isset($cells[$i]) ? trim((string)$cells[$i]) : '';
        }
        if (($row['id'] ?? '') === '') continue;
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function isGroupRow(array $r): bool {
    $name = strtolower(trim($r['first_name'] ?? ''));
    return $name !== '' && in_array($name, GROUP_NAMES, true) && ($r['last_name'] ?? '') === '';
}

// Fetch URL → temp file. Returns path or null on failure.
function downloadImage(string $url): ?string {
    $tmp = tempnam(sys_get_temp_dir(), 'avatar_');
    $fh = fopen($tmp, 'wb');
    if (!$fh) { @unlink($tmp); return null; }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'org_tree_code_store/seed',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fh);

    if (!$ok || $code < 200 || $code >= 300 || filesize($tmp) < 100) {
        @unlink($tmp);
        return null;
    }
    return $tmp;
}

// Convert a downloaded image file to WebP at uploads/avatars/{id}.webp.
function processAvatar(int $employeeId, string $srcPath): ?string {
    $info = @getimagesize($srcPath);
    if (!$info) return null;

    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
        IMAGETYPE_PNG => @imagecreatefrompng($srcPath),
        IMAGETYPE_WEBP => @imagecreatefromwebp($srcPath),
        IMAGETYPE_GIF => @imagecreatefromgif($srcPath),
        default => null,
    };
    if (!$img) return null;

    $w = imagesx($img); $h = imagesy($img);
    $max = 400;
    if ($w > $max || $h > $max) {
        $scale = min($max / $w, $max / $h);
        $resized = imagescale($img, (int)round($w * $scale), (int)round($h * $scale));
        if ($resized) { imagedestroy($img); $img = $resized; }
    }

    if (!is_dir(AVATAR_DIR)) @mkdir(AVATAR_DIR, 0775, true);
    $outFs = AVATAR_DIR . '/' . $employeeId . '.webp';
    $ok = imagewebp($img, $outFs, 85);
    imagedestroy($img);
    return $ok ? 'uploads/avatars/' . $employeeId . '.webp' : null;
}

// ── Main ─────────────────────────────────────────────────────────────────────

echo "Seeding from " . CSV_PATH . "\n";
if (!file_exists(CSV_PATH)) {
    fwrite(STDERR, "CSV not found.\n");
    exit(1);
}

$pdo = db();

// Refuse to seed into a non-empty database
$existing = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn()
          + (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
if ($existing > 0) {
    fwrite(STDERR, "Database already has $existing rows. Delete data/db.sqlite to re-seed.\n");
    exit(1);
}

$rows = parseCsv(CSV_PATH);
echo "Parsed " . count($rows) . " rows.\n";

// Pass 1 — classify
$departments = [];
$employees = [];
foreach ($rows as $r) {
    // Fix comma-split names ("Zenyk, Haiduk" in first_name)
    if (!empty($r['first_name']) && str_contains($r['first_name'], ',')) {
        $parts = array_map('trim', explode(',', $r['first_name'], 2));
        $r['first_name'] = $parts[0];
        if (empty($r['last_name']) && !empty($parts[1])) $r['last_name'] = $parts[1];
    }
    if (isGroupRow($r)) {
        $departments[] = $r;
    } else {
        $employees[] = $r;
    }
}
echo "Classified: " . count($departments) . " departments, " . count($employees) . " employees.\n";

// Pass 2 — insert departments preserving their original CSV IDs
$pdo->beginTransaction();
$insDept = $pdo->prepare("INSERT INTO departments (id, parent_id, name) VALUES (?, ?, ?)");
foreach ($departments as $d) {
    $parent = $d['parentId'] !== '' ? (int)$d['parentId'] : null;
    $insDept->execute([(int)$d['id'], $parent, $d['first_name']]);
}

// Bump sqlite_sequence so future auto-inserts don't collide with seeded dept IDs
$maxDept = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM departments")->fetchColumn();
$pdo->exec("INSERT OR IGNORE INTO sqlite_sequence (name, seq) VALUES ('departments', $maxDept)");
$pdo->exec("UPDATE sqlite_sequence SET seq = MAX(seq, $maxDept) WHERE name = 'departments'");

// Pass 3 — insert employees auto-generated IDs (start at EMPLOYEE_ID_OFFSET), build oldId → newId map
$insEmp = $pdo->prepare("
    INSERT INTO employees (first_name, last_name, department_name, linkedin_url, description)
    VALUES (?, ?, ?, ?, ?)
");
$idMap = []; // oldCsvId(int) => newEmployeeId(int)
foreach ($employees as $e) {
    $insEmp->execute([
        $e['first_name'] ?? '',
        ($e['last_name'] ?? '') ?: null,
        ($e['department_name'] ?? '') ?: null,
        ($e['linkedin_url'] ?? '') ?: null,
        ($e['description'] ?? '') ?: null,
    ]);
    $idMap[(int)$e['id']] = (int)$pdo->lastInsertId();
}

// Pass 4 — remap parent_ids on employees
$updParent = $pdo->prepare("UPDATE employees SET parent_id = ? WHERE id = ?");
foreach ($employees as $e) {
    $oldId = (int)$e['id'];
    $newId = $idMap[$oldId];
    $oldParent = $e['parentId'] !== '' ? (int)$e['parentId'] : null;
    if ($oldParent === null) continue;
    // Parent is either a department (kept original id) or another employee (needs remap)
    $newParent = $idMap[$oldParent] ?? $oldParent;
    $updParent->execute([$newParent, $newId]);
}

$pdo->commit();
echo "Inserted " . count($departments) . " departments and " . count($employees) . " employees.\n";

// Pass 5 — download img_url avatars, convert to WebP, update avatar_path
echo "\nDownloading avatars…\n";
$ok = 0; $fail = 0;
foreach ($employees as $e) {
    $url = trim($e['img_url'] ?? '');
    if ($url === '') continue;
    $newId = $idMap[(int)$e['id']];

    $name = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
    echo "  [$newId] $name — ";

    $tmp = downloadImage($url);
    if (!$tmp) { echo "download failed\n"; $fail++; continue; }

    $relPath = processAvatar($newId, $tmp);
    @unlink($tmp);
    if (!$relPath) { echo "convert failed\n"; $fail++; continue; }

    setEmployeeAvatar($newId, $relPath);
    echo "OK\n";
    $ok++;
}

echo "\nDone. Avatars: $ok ok, $fail failed.\n";
