<?php
// Avatar passthrough — serves employee photos only to authenticated viewers.
// Direct access to uploads/ is denied at the web-server level (see the nginx
// config in docs/google_oauth_auth_plan.md and the router.php block for local
// dev), so this is the only route to avatar files.

declare(strict_types=1);

require __DIR__ . '/../src/db.php';
requireViewer();

$id   = (int)($_GET['id'] ?? 0);                       // int cast kills path traversal
$file = projectRoot() . "/uploads/avatars/{$id}.webp";
if ($id < 1 || !is_file($file)) {
    http_response_code(404);
    exit('Not found');
}

// A replaced photo keeps the same URL, so without a validator the browser served
// the old image for the full max-age — the admin saw the new one (their preview
// cache-busts) while everyone else saw the old.
$etag = '"' . filemtime($file) . '-' . filesize($file) . '"';
header('ETag: ' . $etag);
header('Cache-Control: private, max-age=300, must-revalidate');
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: image/webp');
header('Content-Length: ' . filesize($file));
readfile($file);
