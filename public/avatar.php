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

header('Content-Type: image/webp');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=300');
readfile($file);
