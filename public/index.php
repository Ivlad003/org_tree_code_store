<?php
require __DIR__ . '/../src/db.php';
startSession();

$viewerFlash = $_SESSION['viewer_flash'] ?? null;
unset($_SESSION['viewer_flash']);

// ── Viewer gate ──────────────────────────────────────────────────────────────
// When VIEWER_AUTH=open this is a no-op (public chart). When =google, an
// unauthenticated visitor gets the sign-in page instead of the chart, and the
// org data is never emitted into the page.
if (!isViewer()) {
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>code.store — Team</title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
        <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/admin.css">
    </head>
    <body>
    <div class="login-wrap">
        <h1>code.store · Team</h1>
        <?php if ($viewerFlash): ?>
            <div class="flash err"><?= escapeHtml($viewerFlash) ?></div>
        <?php endif; ?>
        <div class="card" style="text-align:center">
            <p style="color:var(--text-muted);margin:0 0 18px">
                This org chart is private. Sign in with your
                <strong>@code.store</strong> Google account to view it.
            </p>
            <a class="btn" href="auth_google.php?action=start">Sign in with Google</a>
            <div style="margin-top:14px">
                <a class="btn secondary" href="admin.php">Admin sign-in</a>
            </div>
        </div>
    </div>
    </body>
    </html><?php
    exit;
}

$tree = getTree();
$loggedIn = isAdmin();
$viewer = viewerEmail();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>code.store — Team</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<div id="app">

    <!-- ── Header ───────────────────────────────────────────────── -->
    <header id="header">
        <a id="header-logo" href="#">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                <rect width="22" height="22" rx="6" fill="var(--accent)"/>
                <path d="M6 8l-3 3 3 3M16 8l3 3-3 3M13 6l-4 10" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            code.store
        </a>

        <div class="header-divider"></div>

        <div id="search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input id="search" type="text" placeholder="Search people…" autocomplete="off" spellcheck="false">
            <button id="search-clear" title="Clear search" aria-label="Clear search">
                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="header-divider"></div>

        <div class="dept-dropdown" id="dept-dropdown">
            <button class="dept-trigger" id="dept-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span class="dot" id="dept-trigger-dot" style="background:var(--text-muted)"></span>
                <span id="dept-trigger-label">All</span>
                <svg class="dept-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </button>
            <div class="dept-menu" id="dept-menu" role="listbox">
                <button class="dept-option active" data-dept="all" data-label="All" role="option">
                    <span class="dot" style="background:var(--text-muted)"></span>All
                </button>
            </div>
        </div>

        <div id="header-spacer"></div>

        <span id="people-count"></span>

        <div class="header-divider"></div>

        <div id="zoom-controls">
            <button class="zoom-btn" id="btn-zoom-in"   title="Zoom in">+</button>
            <button class="zoom-btn" id="btn-zoom-out"  title="Zoom out">−</button>
            <button class="zoom-btn" id="btn-fit"       title="Fit to screen" style="width:auto;padding:0 8px;font-size:11px;letter-spacing:.03em">FIT</button>
            <button class="zoom-btn" id="btn-tree"      title="Expand all"     style="width:auto;padding:0 8px;font-size:11px;letter-spacing:.03em">EXPAND</button>
            <button class="zoom-btn" id="btn-compact"   title="Toggle compact/spread layout" style="width:auto;padding:0 8px;font-size:11px;letter-spacing:.03em">COMPACT</button>
            <button class="zoom-btn" id="btn-theme"     title="Toggle light/dark theme"      style="width:auto;padding:0 8px;font-size:13px">☀️</button>
            <a class="zoom-btn" href="admin.php" title="<?= $loggedIn ? 'Admin panel' : 'Admin sign-in' ?>" style="width:auto;padding:0 8px;font-size:11px;letter-spacing:.03em;text-decoration:none;display:inline-flex;align-items:center;">
                <?= $loggedIn ? 'ADMIN' : 'SIGN IN' ?>
            </a>
            <?php if ($viewer && !$loggedIn): ?>
                <form method="post" action="auth_google.php?action=signout" style="display:inline;margin:0">
                    <input type="hidden" name="csrf" value="<?= escapeHtml(csrfToken()) ?>">
                    <button type="submit" class="zoom-btn" title="Sign out (<?= escapeHtml($viewer) ?>)" style="width:auto;padding:0 8px;font-size:11px;letter-spacing:.03em;">
                        SIGN OUT
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <!-- ── Chart ────────────────────────────────────────────────── -->
    <div id="chart-container">
        <div class="state-msg" id="loading-msg">
            <div class="spinner"></div>
            <span>Loading team…</span>
        </div>
        <div id="no-results">
            <strong>No matches</strong>
            <span>Try a different name or department</span>
        </div>
    </div>

    <!-- ── Modal ────────────────────────────────────────────────── -->
    <div id="modal-backdrop" class="hidden">
        <div class="modal-box">
            <div class="modal-header">
                <img id="modal-photo" src="" alt="" style="display:none">
                <div id="modal-avatar"></div>
                <div class="modal-meta">
                    <div id="modal-name"></div>
                    <div id="modal-department"><span id="modal-dept-text"></span></div>
                </div>
                <button class="modal-close" id="modal-close">×</button>
            </div>
            <div class="modal-body">
                <p id="modal-description"></p>
                <a id="modal-linkedin" href="" target="_blank" rel="noopener noreferrer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                    View on LinkedIn
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // JSON_HEX_TAG is load-bearing: without it a name containing a literal
    // closing script tag ends this block early — stored XSS for every viewer,
    // and the chart never renders. (Hence no such tag in this comment either.)
    // JSON_INVALID_UTF8_SUBSTITUTE is the other half: one Latin-1 byte in a name
    // (Excel export, restored backup) makes json_encode return false, which emits
    // `= ;` — a syntax error that blanks the chart for everyone.
    window.ORG_DATA = <?= json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]' ?>;
</script>
<!-- Pinned to exact versions with SRI. @3 floated: a new 3.x would ship silently,
     and with SRI a floating tag would break the chart the day it moved. -->
<script src="https://d3js.org/d3.v7.min.js"
        integrity="sha384-CjloA8y00+1SDAUkjs099PVfnY2KmDC2BZnws9kh8D/lX1s46w6EPhpXdqMfjK6i" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-org-chart@3.1.1/build/d3-org-chart.js"
        integrity="sha384-Spi6Wpw0KD3XtOrFTKxq2+d+5V/HXiEvFnUkF7sFwHEdi2G+Ux0HLUlPzxsn2rZ/" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-flextree@2.1.2/build/d3-flextree.js"
        integrity="sha384-6pTgblH+kfP7e8kLkJxI96n+G6MCr28XHUtlXyr3cSjSyT/co6eOBwwCPAX8pBb5" crossorigin="anonymous"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
