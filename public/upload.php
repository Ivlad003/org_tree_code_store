<?php
// Avatar upload handler. POST only; requires admin session + CSRF token.

declare(strict_types=1);

require __DIR__ . '/../src/db.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only');
}
checkCsrf();

$id = (int)($_POST['employee_id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Missing employee_id');
}
$employee = getEmployee($id);
if (!$employee) {
    http_response_code(404);
    exit('Employee not found');
}

$redirect = 'admin.php?edit=' . $id;

function flash(string $type, string $message): void {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Handle "remove" checkbox
if (!empty($_POST['remove_avatar'])) {
    if (!empty($employee['avatar_path'])) {
        $old = projectRoot() . '/' . ltrim((string)$employee['avatar_path'], '/');
        if (is_file($old)) @unlink($old);
    }
    setEmployeeAvatar($id, null);
    flash('ok', 'Avatar removed.');
    header('Location: ' . $redirect);
    exit;
}

// Handle file upload (skip silently if nothing was uploaded)
if (empty($_FILES['avatar']['tmp_name'])) {
    header('Location: ' . $redirect);
    exit;
}

$error = storeAvatarUpload($id, $_FILES['avatar']);
if ($error !== null) {
    flash('err', $error);
    header('Location: ' . $redirect);
    exit;
}

// If the old path differed (legacy non-webp), drop the stale file
if (!empty($employee['avatar_path']) && $employee['avatar_path'] !== 'uploads/avatars/' . $id . '.webp') {
    $old = projectRoot() . '/' . ltrim((string)$employee['avatar_path'], '/');
    if (is_file($old)) @unlink($old);
}

flash('ok', 'Avatar updated.');
header('Location: ' . $redirect);
