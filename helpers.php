<?php
// helpers.php — included by all 4 Render PHP files instead of config.php
// Set these in Render Dashboard → Environment Variables:
//   API_BASE = https://yoursite.infinityfree.net    (no trailing slash)
//   API_KEY  = CHANGE_THIS_TO_A_LONG_RANDOM_SECRET_KEY

define('API_BASE', rtrim($_ENV['API_BASE'] ?? getenv('API_BASE') ?: '', '/'));
define('API_KEY',  $_ENV['API_KEY']  ?? getenv('API_KEY')  ?: '');

date_default_timezone_set('Asia/Kolkata');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// ── API call — POST JSON to InfinityFree api.php ──────────────────────────
function apiCall(string $action, array $data = []): array {
    $data['action']  = $action;
    $data['api_key'] = API_KEY;
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content'       => json_encode($data),
        'timeout'       => 20,
        'ignore_errors' => true,
    ]]);
    $resp = @file_get_contents(API_BASE . '/api.php', false, $ctx);
    
    // 🛑 ADD THESE THREE LINES FOR DEBUGGING
    if (isset($_GET['debug'])) {
        die("<pre>API URL: " . API_BASE . "/api.php\n\nRESPONSE:\n" . htmlspecialchars((string)$resp) . "</pre>");
    }

    if ($resp === false) return ['error' => 'API unreachable'];
    return json_decode($resp, true) ?? ['error' => 'Invalid API response'];
}


// ── Utility functions (no DB needed — computed on Render) ─────────────────
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function getClientIP(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        return filter_var(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0], FILTER_VALIDATE_IP) ?: '0.0.0.0';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getBrowserInfo(): string {
    return json_encode([
        'user_agent'  => $_SERVER['HTTP_USER_AGENT']      ?? '',
        'accept_lang' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        'referer'     => $_SERVER['HTTP_REFERER']         ?? '',
        'timestamp'   => date('Y-m-d H:i:s'),
        'ip'          => getClientIP(),
    ]);
}

function parseBrowserName(string $ua): string {
    if (stripos($ua, 'Edg/')    !== false) return 'Edge';
    if (stripos($ua, 'OPR/')    !== false) return 'Opera';
    if (stripos($ua, 'Chrome')  !== false) return 'Chrome';
    if (stripos($ua, 'Safari')  !== false) return 'Safari';
    if (stripos($ua, 'Firefox') !== false) return 'Firefox';
    if (stripos($ua, 'MSIE')    !== false || stripos($ua, 'Trident') !== false) return 'IE';
    return 'Other';
}

function parseOSName(string $ua): string {
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Android') !== false) return 'Android';
    if (stripos($ua, 'iPhone')  !== false || stripos($ua, 'iPad') !== false) return 'iOS';
    if (stripos($ua, 'Mac OS')  !== false) return 'macOS';
    if (stripos($ua, 'Linux')   !== false) return 'Linux';
    return 'Other';
}

function generateOrderId(): string {
    return 'PS' . date('ymd') . strtoupper(substr(uniqid(), -6));
}

function generateSlug(string $title): string {
    $s = strtolower(trim($title));
    $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
    $s = preg_replace('/[\s-]+/', '-', $s);
    return trim($s, '-');
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// HTTPS detection — works behind Render's reverse proxy
function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}
?>
