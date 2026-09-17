<?php
// Avatar upload handler. POST only; requires admin session + CSRF token.

declare(strict_types=1);

require __DIR__ . '/../src/db.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only');
}
// An upload over post_max_size arrives with $_POST empty — including the CSRF
// token — so without this the admin gets "CSRF token mismatch" for a size problem.
$postMax = (int)ini_get('post_max_size') * 1024 * 1024;
if (empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    startSession();
    $_SESSION['flash'] = ['type' => 'err', 'message' => 'That file is too large to upload (server limit '
        . ini_get('post_max_size') . ').'];
    header('Location: admin.php');
    exit;
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

// Skip silently only when no file was chosen. A file that PHP itself rejected
// (over upload_max_filesize) also arrives with an empty tmp_name but carries an
// error code — reporting that as "nothing happened" is how a 3 MB photo failed
// with no message while the form advertised a 5 MB limit.
$uploadErr = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
if ($uploadErr === UPLOAD_ERR_NO_FILE) {
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
