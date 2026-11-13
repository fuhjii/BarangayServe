<?php
/**
 * bootstrap.php
 *
 * Every page starts with:   require_once __DIR__ . '/includes/bootstrap.php';
 *
 * What it does, in order:
 *   1. loads config/config.php
 *   2. sets the time zone and the error-handling rules
 *   3. works out BASE_URL (the web path of this project, e.g. /barangayserve)
 *   4. sends security headers
 *   5. starts a safe PHP session
 *   6. loads the helper files in includes/
 */

if (defined('BS_BOOTSTRAPPED')) {
    return;
}
define('BS_BOOTSTRAPPED', true);
define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

// ---------------------------------------------------------------------------
// 1. Configuration
// ---------------------------------------------------------------------------
$bsConfigFile = APP_ROOT . '/config/config.php';
if (!is_file($bsConfigFile)) {
    // First-run help for students who have not created config/config.php yet.
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>BarangayServe setup</title>'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<style>body{font-family:system-ui,sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#1e2a3b}'
       . 'code{background:#eef2f8;padding:.1rem .35rem;border-radius:4px}</style></head><body>'
       . '<h1>BarangayServe is not set up yet</h1>'
       . '<p>The file <code>config/config.php</code> is missing.</p>'
       . '<ol><li>Copy <code>config/config.example.php</code> and name the copy <code>config/config.php</code>.</li>'
       . '<li>Open it and enter your database user and password.</li>'
       . '<li>Reload this page.</li></ol>'
       . '<p>The full steps are in <code>README.md</code>.</p></body></html>';
    exit;
}
$GLOBALS['bs_config'] = require $bsConfigFile;

/**
 * Read a setting from config/config.php using dots for nesting.
 * Example: config('db.host')  or  config('per_page', 10)
 */
function config(string $key, $default = null)
{
    $value = $GLOBALS['bs_config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

// ---------------------------------------------------------------------------
// 2. Time zone and error handling
// ---------------------------------------------------------------------------
$bsTimezone = (string) config('timezone', 'Asia/Manila');
if (!in_array($bsTimezone, timezone_identifiers_list(), true)) {
    $bsTimezone = 'Asia/Manila';
}
date_default_timezone_set($bsTimezone);

define('BS_DEBUG', (bool) config('debug', false));
error_reporting(E_ALL);
ini_set('display_errors', BS_DEBUG ? '1' : '0');   // never show raw errors to residents
ini_set('log_errors', '1');
$bsLogDir = APP_ROOT . '/storage/logs';
if (is_dir($bsLogDir) && is_writable($bsLogDir)) {
    ini_set('error_log', $bsLogDir . '/php-error.log');
}

// Any error nobody caught ends up here: log the details, show a friendly page.
set_exception_handler(function (Throwable $e) {
    error_log('[BarangayServe] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {                      // tools/*.php: plain text, not a web page
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    show_error_page(
        500,
        'Something went wrong',
        'The system could not complete your request. Please try again in a moment. '
        . 'If the problem continues, please tell the barangay office.',
        BS_DEBUG ? $e : null
    );
    exit;
});

// ---------------------------------------------------------------------------
// 3. BASE_URL: the web path where this project lives ('' or '/barangayserve')
// ---------------------------------------------------------------------------
function detect_base_url(): string
{
    $configured = trim((string) config('base_url', ''));
    if ($configured !== '') {
        return rtrim('/' . trim($configured, '/'), '/');
    }

    $appRoot = realpath(APP_ROOT);
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    if ($appRoot && $docRoot && strncasecmp($appRoot, $docRoot, strlen($docRoot)) === 0) {
        $rest = substr($appRoot, strlen($docRoot));
        if ($rest === '' || $rest[0] === '/' || $rest[0] === '\\') {
            return rtrim(str_replace('\\', '/', $rest), '/');
        }
    }

    // Fallback (for Apache "Alias" setups): compare the script's file path with its URL path.
    $scriptFile = str_replace('\\', '/', (string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $scriptUrl  = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $root       = str_replace('\\', '/', (string) $appRoot);
    if ($scriptFile !== '' && $root !== '' && strncasecmp($scriptFile, $root, strlen($root)) === 0) {
        $inside = substr($scriptFile, strlen($root));            // e.g. /resident/dashboard.php
        if ($inside !== '' && substr($scriptUrl, -strlen($inside)) === $inside) {
            return rtrim(substr($scriptUrl, 0, -strlen($inside)), '/');
        }
    }
    return '';
}
define('BASE_URL', detect_base_url());

// ---------------------------------------------------------------------------
// 4. Security headers (they apply to every page)
// ---------------------------------------------------------------------------
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
}

function send_security_headers(): void
{
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (config('content_security_policy', true)) {
        // Only this site's own files may run or load. The pages use no inline scripts or styles.
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
            . "script-src 'self'; style-src 'self'; font-src 'self'; connect-src 'self'; "
            . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    }
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/** Tell browsers not to keep a copy of private pages (so Back after logout shows nothing private). */
function send_no_cache_headers(): void
{
    if (!headers_sent() && PHP_SAPI !== 'cli') {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

// ---------------------------------------------------------------------------
// 5. Session
// ---------------------------------------------------------------------------
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }
    $secure = config('session.cookie_secure', 'auto');
    $secure = ($secure === 'auto') ? is_https() : (bool) $secure;

    ini_set('session.use_strict_mode', '1');     // refuse session IDs the server did not create
    ini_set('session.use_only_cookies', '1');    // never accept the session ID from the URL
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) max(3600, ((int) config('session.idle_timeout_minutes', 30)) * 120));

    session_name((string) config('session.name', 'barangayserve_sid'));
    session_set_cookie_params([
        'lifetime' => 0,                          // ends when the browser closes
        'path'     => (BASE_URL === '' ? '/' : BASE_URL . '/'),   // keeps this app's cookie apart from other local apps
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,                       // JavaScript cannot read the cookie
        'samesite' => 'Lax',                      // not sent on cross-site POST requests
    ]);
    session_start();
}

send_security_headers();
start_secure_session();

// ---------------------------------------------------------------------------
// 6. The rest of the application code
// ---------------------------------------------------------------------------
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/requests.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/components.php';
