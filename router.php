<?php
// Dev-only router for the PHP built-in server. Run from the repo root:
//   php -S 127.0.0.1:8000 -t public router.php
// Production uses nginx (or Apache) with `public/` as the document root.

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Clean URLs: map / and /admin to their PHP entry points.
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/public/index.php';
    return true;
}
if ($uri === '/admin') {
    require __DIR__ . '/public/admin.php';
    return true;
}

// Everything else is served from the document root (public/) as-is.
// Sensitive dirs (src/, data/, uploads/) are siblings of public/, so the
// built-in server cannot reach them — no blocklist needed.
return false;
