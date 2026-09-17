<?php
// Viewer-gate Google OAuth (OpenID Connect).
// Restricts sign-in to the code.store Google Workspace tenant via the id_token
// `hd` claim. See docs/google_oauth_auth_plan.md.
//
// Routes (one registered redirect URI, no query string — Google-friendly):
//   ?action=start            → redirect to Google's consent screen
//   (callback)               → Google redirects back with ?code&state
//   ?action=signout          → clear the viewer session
//
// The redirect URI registered in Google Cloud Console must equal OAUTH_REDIRECT_URI
// exactly, e.g. https://your-domain/auth_google.php

declare(strict_types=1);

require __DIR__ . '/../src/db.php';
loadEnv();
startSession();

const ALLOWED_HD = 'code.store';

function oauthRedirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function oauthFail(string $msg): void {
    $_SESSION['viewer_flash'] = $msg;
    oauthRedirect('index.php');
}

// Decode (without signature verification) a JWT payload. Acceptable here because
// the token was just fetched directly from Google's token endpoint over TLS
// (OIDC §3.1.3.7). Production should add JWKS signature verification.
function decodeJwtPayload(string $jwt): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    $b64 = strtr($parts[1], '-_', '+/');
    $b64 = str_pad($b64, (int)(ceil(strlen($b64) / 4) * 4), '=');
    $json = base64_decode($b64, true);
    if ($json === false) return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

$action = $_GET['action'] ?? '';

// ── Sign out ─────────────────────────────────────────────────────────────────
// POST + CSRF, matching the admin logout. As a GET this was forgeable: SameSite=Lax
// still sends the cookie on a top-level cross-site navigation, so any page could
// sign a viewer out by pointing them at ?action=signout.
if ($action === 'signout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('POST only');
    }
    checkCsrf();
    $_SESSION = [];
    session_destroy();
    oauthRedirect('index.php');
}

$clientId     = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
$redirectUri  = $_ENV['OAUTH_REDIRECT_URI'] ?? '';

if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
    oauthFail('Google sign-in is not configured yet.');
}

// ── Callback: Google redirects back here with ?code & ?state ─────────────────
if (isset($_GET['code']) || isset($_GET['error'])) {
    $state = $_GET['state'] ?? '';
    if (!is_string($state) || $state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
        oauthFail('Sign-in state mismatch — please try again.');
    }
    unset($_SESSION['oauth_state']);

    if (!empty($_GET['error'])) {
        oauthFail('Google sign-in was cancelled.');
    }
    $code = $_GET['code'] ?? '';
    if (!is_string($code) || $code === '') {
        oauthFail('No authorization code returned by Google.');
    }

    // Exchange the authorization code for tokens.
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $httpCode !== 200) {
        oauthFail('Token exchange with Google failed.');
    }

    $token   = json_decode((string)$body, true);
    $idToken = is_array($token) ? ($token['id_token'] ?? '') : '';
    if (!is_string($idToken) || $idToken === '') {
        oauthFail('Google did not return an ID token.');
    }

    $claims = decodeJwtPayload($idToken);
    if ($claims === null) {
        oauthFail('Could not read the Google ID token.');
    }

    // Validate the standard claims …
    $issOk = in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
    $audOk = ($claims['aud'] ?? '') === $clientId;
    $expOk = isset($claims['exp']) && (int)$claims['exp'] > time();
    if (!$issOk || !$audOk || !$expOk) {
        oauthFail('The Google ID token did not validate.');
    }

    // … then enforce the code.store Workspace restriction.
    $hdOk          = ($claims['hd'] ?? '') === ALLOWED_HD;
    $emailVerified = ($claims['email_verified'] ?? false) === true
                  || ($claims['email_verified'] ?? '') === 'true';
    $email         = strtolower(trim((string)($claims['email'] ?? '')));
    if (!$hdOk || !$emailVerified || $email === '') {
        oauthFail('Sign-in is restricted to verified @' . ALLOWED_HD . ' accounts.');
    }

    session_regenerate_id(true);
    $_SESSION['viewer_email']    = $email;
    $_SESSION['viewer_name']     = (string)($claims['name'] ?? $email);
    $_SESSION['viewer_login_at'] = time();
    oauthRedirect('index.php');
}

// ── Start: redirect to Google's consent screen ───────────────────────────────
if ($action === 'start') {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $params = http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'hd'            => ALLOWED_HD,     // UI hint only — re-checked server-side
        'prompt'        => 'select_account',
        'access_type'   => 'online',
    ]);
    oauthRedirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
}

http_response_code(404);
exit('Unknown action');
