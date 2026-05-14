<?php
// Single-file admin panel — login + employee/department CRUD.

declare(strict_types=1);

require __DIR__ . '/db.php';
loadEnv();
startSession();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── POST actions ────────────────────────────────────────────────────────────

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        $expectedEmail = $_ENV['ADMIN_EMAIL'] ?? '';
        $expectedPass  = $_ENV['ADMIN_PASSWORD'] ?? '';
        $suppliedEmail = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $suppliedPass  = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        // Compare both factors with hash_equals; AND the results so neither
        // check short-circuits (keeps the comparison timing-independent).
        $emailOk = $expectedEmail !== '' && hash_equals(strtolower($expectedEmail), strtolower($suppliedEmail));
        $passOk  = $expectedPass !== ''  && hash_equals($expectedPass, $suppliedPass);
        if ($emailOk && $passOk) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            redirect('admin.php');
        }
        setFlash('err', 'Wrong email or password.');
        redirect('admin.php');
    }

    // All other POSTs require admin session + CSRF
    requireAdmin();
    checkCsrf();

    switch ($action) {
        case 'logout':
            $_SESSION = [];
            session_destroy();
            redirect('admin.php');

        case 'save_employee':
            $id = saveEmployee($_POST);
            setFlash('ok', !empty($_POST['id']) ? 'Employee updated.' : 'Employee created.');
            redirect('admin.php?edit=' . $id);

        case 'delete_employee':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteEmployee($id);
                setFlash('ok', 'Employee deleted.');
            }
            redirect('admin.php');

        case 'save_department':
            $id = saveDepartment($_POST);
            setFlash('ok', !empty($_POST['id']) ? 'Department updated.' : 'Department created.');
            redirect('admin.php#departments');

        case 'delete_department':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteDepartment($id);
                setFlash('ok', 'Department deleted (its members were detached, not deleted).');
            }
            redirect('admin.php#departments');
    }
    redirect('admin.php');
}

// ── Login view (unauth) ─────────────────────────────────────────────────────

if (!isAdmin()) {
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admin · code.store org chart</title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/admin.css">
    </head>
    <body>
    <div class="login-wrap">
        <h1>Admin sign-in</h1>
        <?php if ($flash): ?>
            <div class="flash <?= escapeHtml($flash['type']) ?>"><?= escapeHtml($flash['message']) ?></div>
        <?php endif; ?>
        <form method="post" class="card">
            <input type="hidden" name="action" value="login">
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" autocomplete="username" autofocus required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <div class="actions">
                <button type="submit">Sign in</button>
                <a class="btn secondary" href="index.php">Back to chart</a>
            </div>
        </form>
    </div>
    </body>
    </html><?php
    exit;
}

// ── Auth'd: pick a view ──────────────────────────────────────────────────────

$view = 'list';
$editEmployee = null;
$editDepartment = null;

if (isset($_GET['edit'])) {
    $editEmployee = getEmployee((int)$_GET['edit']);
    if (!$editEmployee) { setFlash('err', 'Employee not found.'); redirect('admin.php'); }
    $view = 'employee_form';
} elseif (isset($_GET['new'])) {
    $editEmployee = ['id' => '', 'parent_id' => '', 'first_name' => '', 'last_name' => '', 'department_name' => '', 'linkedin_url' => '', 'description' => '', 'avatar_path' => ''];
    $view = 'employee_form';
} elseif (isset($_GET['dept_edit'])) {
    $editDepartment = getDepartment((int)$_GET['dept_edit']);
    if (!$editDepartment) { setFlash('err', 'Department not found.'); redirect('admin.php'); }
    $view = 'department_form';
} elseif (isset($_GET['dept_new'])) {
    $editDepartment = ['id' => '', 'parent_id' => '', 'name' => '', 'sort_order' => 0];
    $view = 'department_form';
}

$csrf = csrfToken();
$departments = getDepartments();
$parentOptions = getParentOptions();
$employees = getEmployees();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin · code.store org chart</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

<header class="admin-header">
    <h1>code.store · Admin</h1>
    <div class="spacer"></div>
    <a href="index.php">View chart</a>
    <form method="post" style="display:inline;margin:0">
        <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="linklike">Sign out</button>
    </form>
</header>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?= escapeHtml($flash['type']) ?>"><?= escapeHtml($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($view === 'employee_form'): ?>
        <?php
            $isNew = empty($editEmployee['id']);
            $hasAvatar = !empty($editEmployee['avatar_path']);
        ?>
        <div class="card">
            <h2><?= $isNew ? 'New employee' : 'Edit employee #' . escapeHtml((string)$editEmployee['id']) ?></h2>

            <?php if (!$isNew): ?>
                <div class="avatar-block">
                    <?php if ($hasAvatar): ?>
                        <img class="avatar-preview" src="<?= escapeHtml($editEmployee['avatar_path']) ?>?v=<?= time() ?>" alt="">
                    <?php else: ?>
                        <div class="avatar-preview thumb placeholder">no photo</div>
                    <?php endif; ?>
                    <form action="upload.php" method="post" enctype="multipart/form-data" class="meta" style="flex:1">
                        <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
                        <input type="hidden" name="employee_id" value="<?= escapeHtml((string)$editEmployee['id']) ?>">
                        <div class="field">
                            <label>Replace photo (jpg / png / webp, max 5 MB)</label>
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <div class="actions">
                            <button type="submit">Upload photo</button>
                            <?php if ($hasAvatar): ?>
                                <label style="display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);font-size:13px;">
                                    <input type="checkbox" name="remove_avatar" value="1"> Remove existing photo
                                </label>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
                <input type="hidden" name="action" value="save_employee">
                <input type="hidden" name="id" value="<?= escapeHtml((string)$editEmployee['id']) ?>">

                <div class="form-row">
                    <div class="field">
                        <label for="first_name">First name *</label>
                        <input type="text" id="first_name" name="first_name" required value="<?= escapeHtml($editEmployee['first_name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="last_name">Last name</label>
                        <input type="text" id="last_name" name="last_name" value="<?= escapeHtml($editEmployee['last_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="department_name">Department / role (shown on card)</label>
                        <input type="text" id="department_name" name="department_name" list="dept-suggestions" value="<?= escapeHtml($editEmployee['department_name'] ?? '') ?>">
                        <datalist id="dept-suggestions">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= escapeHtml($d['name']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="field">
                        <label for="parent_id">Parent node (tree placement)</label>
                        <select id="parent_id" name="parent_id">
                            <option value="">— none (root) —</option>
                            <?php foreach ($parentOptions as $opt): ?>
                                <?php if (!$isNew && (int)$opt['id'] === (int)$editEmployee['id']) continue; ?>
                                <option value="<?= escapeHtml((string)$opt['id']) ?>" <?= ((int)($editEmployee['parent_id'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>>
                                    <?= escapeHtml($opt['label']) ?> (#<?= (int)$opt['id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label for="linkedin_url">LinkedIn URL</label>
                    <input type="url" id="linkedin_url" name="linkedin_url" placeholder="https://www.linkedin.com/in/…" value="<?= escapeHtml($editEmployee['linkedin_url'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="description">Description / bio</label>
                    <textarea id="description" name="description"><?= escapeHtml($editEmployee['description'] ?? '') ?></textarea>
                </div>

                <div class="actions">
                    <button type="submit"><?= $isNew ? 'Create employee' : 'Save changes' ?></button>
                    <a class="btn secondary" href="admin.php">Cancel</a>
                    <?php if (!$isNew): ?>
                        <form method="post" style="margin:0 0 0 auto" onsubmit="return confirm('Delete this employee? This cannot be undone.');">
                            <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
                            <input type="hidden" name="action" value="delete_employee">
                            <input type="hidden" name="id" value="<?= escapeHtml((string)$editEmployee['id']) ?>">
                            <button type="submit" class="danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    <?php elseif ($view === 'department_form'): ?>
        <?php $isNew = empty($editDepartment['id']); ?>
        <div class="card">
            <h2><?= $isNew ? 'New department' : 'Edit department #' . escapeHtml((string)$editDepartment['id']) ?></h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
                <input type="hidden" name="action" value="save_department">
                <input type="hidden" name="id" value="<?= escapeHtml((string)$editDepartment['id']) ?>">

                <div class="form-row">
                    <div class="field">
                        <label for="dname">Name *</label>
                        <input type="text" id="dname" name="name" required value="<?= escapeHtml($editDepartment['name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="dparent">Parent node</label>
                        <select id="dparent" name="parent_id">
                            <option value="">— none (root) —</option>
                            <?php foreach ($parentOptions as $opt): ?>
                                <?php if (!$isNew && (int)$opt['id'] === (int)$editDepartment['id']) continue; ?>
                                <option value="<?= escapeHtml((string)$opt['id']) ?>" <?= ((int)($editDepartment['parent_id'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>>
                                    <?= escapeHtml($opt['label']) ?> (#<?= (int)$opt['id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="max-width:140px">
                        <label for="dsort">Sort order</label>
                        <input type="number" id="dsort" name="sort_order" value="<?= (int)($editDepartment['sort_order'] ?? 0) ?>">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit"><?= $isNew ? 'Create department' : 'Save changes' ?></button>
                    <a class="btn secondary" href="admin.php">Cancel</a>
                    <?php if (!$isNew): ?>
                        <form method="post" style="margin:0 0 0 auto" onsubmit="return confirm('Delete this department? Members will be detached, not deleted.');">
                            <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
                            <input type="hidden" name="action" value="delete_department">
                            <input type="hidden" name="id" value="<?= escapeHtml((string)$editDepartment['id']) ?>">
                            <button type="submit" class="danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    <?php else: /* list view */ ?>

        <div class="card">
            <h2>Employees <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0;">(<?= count($employees) ?>)</span></h2>

            <div class="filters">
                <input type="text" id="search" placeholder="Search by name or department…" autocomplete="off">
                <select id="dept-filter">
                    <option value="">All departments</option>
                    <?php
                        $deptNames = [];
                        foreach ($employees as $e) {
                            $name = trim((string)($e['department_name'] ?? ''));
                            if ($name !== '') $deptNames[$name] = true;
                        }
                        $deptNames = array_keys($deptNames);
                        sort($deptNames);
                        foreach ($deptNames as $n): ?>
                        <option value="<?= escapeHtml($n) ?>"><?= escapeHtml($n) ?></option>
                    <?php endforeach; ?>
                </select>
                <a class="btn" href="admin.php?new=1">+ Add employee</a>
            </div>

            <table class="list" id="emp-table">
                <thead>
                    <tr>
                        <th style="width:48px"></th>
                        <th>Name</th>
                        <th>Department / role</th>
                        <th>LinkedIn</th>
                        <th style="width:180px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $e):
                    $full = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
                    $dept = (string)($e['department_name'] ?? '');
                ?>
                    <tr data-name="<?= escapeHtml(strtolower($full)) ?>" data-dept="<?= escapeHtml($dept) ?>">
                        <td>
                            <?php if (!empty($e['avatar_path'])): ?>
                                <img class="thumb" src="<?= escapeHtml($e['avatar_path']) ?>" alt="">
                            <?php else: ?>
                                <span class="thumb placeholder">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= escapeHtml($full ?: '(no name)') ?></td>
                        <td><?= escapeHtml($dept) ?></td>
                        <td>
                            <?php if (!empty($e['linkedin_url'])): ?>
                                <a href="<?= escapeHtml($e['linkedin_url']) ?>" target="_blank" rel="noopener">link</a>
                            <?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <a href="admin.php?edit=<?= (int)$e['id'] ?>">edit</a>
                            <form method="post" onsubmit="return confirm('Delete <?= escapeHtml($full) ?>?');">
                                <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                                <button type="submit" class="danger-link">delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card" id="departments">
            <h2>Departments <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0;">(<?= count($departments) ?>)</span></h2>
            <div class="filters">
                <a class="btn" href="admin.php?dept_new=1">+ Add department</a>
            </div>
            <table class="list">
                <thead>
                    <tr><th>Name</th><th>Parent</th><th style="width:120px">Sort</th><th style="width:180px">Actions</th></tr>
                </thead>
                <tbody>
                <?php
                    $deptById = [];
                    foreach ($departments as $d) $deptById[(int)$d['id']] = $d;
                    foreach ($departments as $d):
                        $parentLabel = '';
                        if (!empty($d['parent_id']) && isset($deptById[(int)$d['parent_id']])) {
                            $parentLabel = $deptById[(int)$d['parent_id']]['name'];
                        } elseif (!empty($d['parent_id'])) {
                            $emp = getEmployee((int)$d['parent_id']);
                            if ($emp) $parentLabel = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
                        }
                ?>
                    <tr>
                        <td><?= escapeHtml($d['name']) ?></td>
                        <td><?= escapeHtml($parentLabel) ?></td>
                        <td><?= (int)$d['sort_order'] ?></td>
                        <td class="row-actions">
                            <a href="admin.php?dept_edit=<?= (int)$d['id'] ?>">edit</a>
                            <form method="post" onsubmit="return confirm('Delete department <?= escapeHtml($d['name']) ?>? Members will be detached.');">
                                <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
                                <input type="hidden" name="action" value="delete_department">
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="danger-link">delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
            // Client-side filter for the employee table
            const search = document.getElementById('search');
            const dept   = document.getElementById('dept-filter');
            const rows   = document.querySelectorAll('#emp-table tbody tr');
            function apply() {
                const q = (search.value || '').trim().toLowerCase();
                const d = dept.value;
                rows.forEach(r => {
                    const matchQ = !q || r.dataset.name.includes(q) || r.dataset.dept.toLowerCase().includes(q);
                    const matchD = !d || r.dataset.dept === d;
                    r.style.display = (matchQ && matchD) ? '' : 'none';
                });
            }
            search.addEventListener('input', apply);
            dept.addEventListener('change', apply);
        </script>

    <?php endif; ?>

</div>
</body>
</html>
