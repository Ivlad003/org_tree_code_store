<?php
// Router for `php -S 127.0.0.1:8000 router.php`. Apache/nginx use .htaccess instead.

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Block sensitive paths regardless of how the file is served.
// /uploads is viewer-gated — avatars are served only through avatar.php.
foreach (['/.env', '/.git', '/data', '/db.php', '/uploads', '/assets/csv'] as $blocked) {
    if ($uri === $blocked || str_starts_with($uri, $blocked . '/')) {
        http_response_code(404);
        exit;
    }
}

// Map / and /admin to .php
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}
if ($uri === '/admin') {
    require __DIR__ . '/admin.php';
    return true;
}

// Let php -S serve everything else (static files + .php)
return false;
