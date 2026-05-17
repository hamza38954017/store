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
    return callInfinityFreeAPI(API_BASE . '/api.php', $data);
}

function callInfinityFreeAPI(string $apiUrl, array $payload): array {
    $cookieJar = sys_get_temp_dir() . '/if_cookie_' . md5($apiUrl) . '.txt';

    // --- First attempt ---
    $response = httpPost($apiUrl, $payload, $cookieJar);

    // If we got JSON back, we're done
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;

    // --- Looks like the AES challenge page ---
    if (!str_contains($response, 'slowAES.decrypt')) {
        return ['error' => 'Unexpected non-JSON response'];
    }

    // Parse the three hex values: a (key), b (iv), c (ciphertext)
    if (!preg_match('/var a=toNumbers\("([0-9a-f]+)"\)/', $response, $mA) ||
        !preg_match('/var b=toNumbers\("([0-9a-f]+)"\)/', $response, $mB) ||
        !preg_match('/var c=toNumbers\("([0-9a-f]+)"\)/', $response, $mC)) {
        return ['error' => 'Could not parse AES challenge'];
    }

    // Decrypt using AES-128-CBC (equivalent to slowAES mode 2)
    $plaintext = openssl_decrypt(
        hex2bin($mC[1]), 'AES-128-CBC',
        hex2bin($mA[1]),
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        hex2bin($mB[1])
    );
    if ($plaintext === false) return ['error' => 'AES decryption failed'];

    // Write the __test cookie to the jar
    $host = parse_url($apiUrl, PHP_URL_HOST);
    file_put_contents($cookieJar,
        "# Netscape HTTP Cookie File\n" .
        "$host\tFALSE\t/\tFALSE\t2145916555\t__test\t" . bin2hex($plaintext) . "\n"
    );

    // --- Retry with cookie + ?i=1 ---
    $retryUrl = $apiUrl . (str_contains($apiUrl, '?') ? '&' : '?') . 'i=1';
    $decoded  = json_decode(httpPost($retryUrl, $payload, $cookieJar), true);
    return $decoded ?? ['error' => 'Still not JSON after challenge'];
}

function httpPost(string $url, array $payload, string $cookieJar): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    return $out ?: '';
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
