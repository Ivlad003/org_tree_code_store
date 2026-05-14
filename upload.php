<?php
// Avatar upload handler. POST only; requires admin session + CSRF token.

declare(strict_types=1);

require __DIR__ . '/db.php';

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

$file = $_FILES['avatar'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    flash('err', 'Upload failed (PHP error ' . (int)$file['error'] . ').');
    header('Location: ' . $redirect);
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    flash('err', 'File too large (max 5 MB).');
    header('Location: ' . $redirect);
    exit;
}

$info = @getimagesize($file['tmp_name']);
if (!$info) {
    flash('err', 'Not a valid image.');
    header('Location: ' . $redirect);
    exit;
}

$img = match ($info[2]) {
    IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
    IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
    IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
    default => null,
};
if (!$img) {
    flash('err', 'Unsupported image type (jpg, png, webp only).');
    header('Location: ' . $redirect);
    exit;
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
    flash('err', 'Could not write avatar to disk.');
    header('Location: ' . $redirect);
    exit;
}
imagedestroy($img);

// If the old path differed (legacy non-webp), drop the stale file
if (!empty($employee['avatar_path']) && $employee['avatar_path'] !== 'uploads/avatars/' . $id . '.webp') {
    $old = projectRoot() . '/' . ltrim((string)$employee['avatar_path'], '/');
    if (is_file($old)) @unlink($old);
}

setEmployeeAvatar($id, 'uploads/avatars/' . $id . '.webp');
flash('ok', 'Avatar updated.');
header('Location: ' . $redirect);
