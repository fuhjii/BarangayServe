<?php
/**
 * auth.php: user accounts, log in / log out, and "who may open this page?".
 *
 * IMPORTANT IDEA: the role is always read from the DATABASE (current_user()),
 * never trusted from the browser. Hiding a button is not security; every
 * protected page calls require_resident() or require_staff() on the server.
 */

// A real bcrypt hash of a throw-away password. Used so that logging in with an
// unknown email takes the same time as a wrong password (hides which emails exist).
const DUMMY_PASSWORD_HASH = '$2y$10$olItUa8HPSAsKTJcZOEHd.YQHqZpeJXyGnlLQ8lUkJY5bm4T8I9Dy';

// ---------------------------------------------------------------------------
// Finding users
// ---------------------------------------------------------------------------

function find_user_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, full_name, email, contact_number, address, role, is_active, created_at
           FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Includes password_hash: only use this for logging in. */
function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare(
        'SELECT id, full_name, email, contact_number, address, password_hash, role, is_active
           FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function email_is_taken(string $email, int $exceptUserId = 0): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
    $stmt->execute([$email, $exceptUserId]);
    return (int) $stmt->fetchColumn() > 0;
}

function normalize_email(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

// ---------------------------------------------------------------------------
// The user who is logged in right now
// ---------------------------------------------------------------------------

/**
 * Returns the logged-in user (a fresh copy from the database) or null.
 * Also enforces the idle time-out and switches off accounts that were deactivated.
 */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }

    $limit = max(1, (int) config('session.idle_timeout_minutes', 30)) * 60;
    if (time() - (int) ($_SESSION['last_activity'] ?? 0) > $limit) {
        logout_user('You were logged out because the page was idle for a while. Please log in again.');
        return null;
    }

    $found = find_user_by_id($id);
    if ($found === null || (int) $found['is_active'] !== 1) {
        logout_user('Your account is not available. Please contact the barangay office.');
        return null;
    }

    $_SESSION['last_activity'] = time();
    $user = $found;
    return $user;
}

function dashboard_path(string $role): string
{
    return $role === 'staff' ? 'staff/dashboard.php' : 'resident/dashboard.php';
}

/** Start a logged-in session. The session id is replaced to prevent session fixation. */
function login_session_start(array $user): void
{
    $intended = $_SESSION['intended'] ?? null;
    session_regenerate_id(true);
    $_SESSION = [
        'user_id'       => (int) $user['id'],
        'last_activity' => time(),
    ];
    if (is_string($intended)) {
        $_SESSION['intended'] = $intended;
    }
}

/** Forget the user completely. A new, empty session id is issued so the old one is useless. */
function logout_user(string $message = ''): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    if ($message !== '') {
        flash('info', $message);
    }
}

// ---------------------------------------------------------------------------
// Page guards: call one of these at the top of every protected page
// ---------------------------------------------------------------------------

/** The page address (relative to the project) the visitor asked for, e.g. resident/requests.php?page=2 */
function current_request_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (BASE_URL !== '' && strncmp($uri, BASE_URL . '/', strlen(BASE_URL) + 1) === 0) {
        $uri = substr($uri, strlen(BASE_URL));
    }
    return ltrim($uri, '/');
}

/** Accept only plain paths of this project (blocks open-redirect tricks such as //evil.com). */
function safe_internal_path(?string $path): ?string
{
    if ($path === null || $path === '' || strlen($path) > 300) {
        return null;
    }
    if (!preg_match('#^[A-Za-z0-9_\-./?=&%]+$#', $path) || strpos($path, '..') !== false || strpos($path, '//') !== false) {
        return null;
    }
    $file = basename((string) strtok($path, '?'));
    if (substr($file, -4) !== '.php' || in_array($file, ['login.php', 'register.php', 'logout.php'], true)) {
        return null;
    }
    return $path;
}

function require_login(string $area = 'resident'): array
{
    send_no_cache_headers();
    $user = current_user();
    if ($user === null) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['intended'] = current_request_path();
        }
        if (empty($_SESSION['flash'])) {
            flash('warning', 'Please log in to continue.');
        }
        redirect($area === 'staff' ? 'staff/login.php' : 'login.php');
    }
    return $user;
}

function require_resident(): array
{
    $user = require_login('resident');
    if ($user['role'] !== 'resident') {
        deny_access($user, 'This page is only for resident accounts.');
    }
    return $user;
}

function require_staff(): array
{
    $user = require_login('staff');
    if ($user['role'] !== 'staff') {
        deny_access($user, 'This page is only for authorized barangay staff.');
    }
    return $user;
}

/**
 * For pages that only make sense when logged OUT (home, log in, register):
 * a logged-in user is sent straight to their own dashboard.
 */
function redirect_if_logged_in(): void
{
    $user = current_user();
    if ($user !== null) {
        redirect(dashboard_path($user['role']));
    }
}

/** Pages that change data accept POST only. Anything else is sent back with a short message. */
function require_post(string $backPath): void
{
    if (!is_post()) {
        redirect($backPath);
    }
}

function deny_access(array $user, string $message): void
{
    http_response_code(403);
    show_error_page(403, 'Access denied', $message, null, dashboard_path($user['role']), 'Go to my dashboard');
    exit;
}

/** After logging in, go back to the page the user wanted (if allowed for their role), else the dashboard. */
function redirect_after_login(array $user): void
{
    $intended = safe_internal_path($_SESSION['intended'] ?? null);
    unset($_SESSION['intended']);
    $area = $user['role'] === 'staff' ? 'staff/' : 'resident/';
    if ($intended !== null && (strncmp($intended, $area, strlen($area)) === 0 || strncmp($intended, 'account.php', 11) === 0)) {
        redirect($intended);
    }
    redirect(dashboard_path($user['role']));
}

// ---------------------------------------------------------------------------
// Logging in (with protection against password guessing)
// ---------------------------------------------------------------------------

function login_is_locked(string $email): bool
{
    $max   = max(1, (int) config('login.max_failed_attempts', 5));
    $since = date('Y-m-d H:i:s', time() - max(1, (int) config('login.lock_minutes', 15)) * 60);
    $stmt  = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND attempted_at >= ?');
    $stmt->execute([substr($email, 0, 150), client_ip(), $since]);
    return (int) $stmt->fetchColumn() >= $max;
}

function record_failed_login(string $email): void
{
    $pdo = db();
    $pdo->prepare('INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, ?)')
        ->execute([substr($email, 0, 150), client_ip(), now_sql()]);
    // Housekeeping: forget attempts older than one day.
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
        ->execute([date('Y-m-d H:i:s', time() - 86400)]);
}

/**
 * Check an email and password. Returns ['user' => array|null, 'error' => string|null].
 * The error text never says whether the email exists.
 */
function attempt_login(string $email, string $password): array
{
    $email = normalize_email($email);
    if ($email === '' || $password === '') {
        return ['user' => null, 'error' => 'Please enter your email address and password.'];
    }
    if (login_is_locked($email)) {
        $minutes = max(1, (int) config('login.lock_minutes', 15));
        return ['user' => null, 'error' => "Too many failed attempts. Please wait $minutes minutes and try again."];
    }

    $user = strlen($password) <= 200 ? find_user_by_email($email) : null;
    $hash = $user['password_hash'] ?? DUMMY_PASSWORD_HASH;
    $passwordOk = strlen($password) <= 200 && password_verify($password, $hash);

    if ($user === null || !$passwordOk) {
        record_failed_login($email);
        return ['user' => null, 'error' => 'The email address or password is incorrect.'];
    }
    if ((int) $user['is_active'] !== 1) {
        return ['user' => null, 'error' => 'This account is not active. Please contact the barangay office.'];
    }

    db()->prepare('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?')->execute([$email, client_ip()]);
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    unset($user['password_hash']);
    return ['user' => $user, 'error' => null];
}

// ---------------------------------------------------------------------------
// Creating and changing accounts
// ---------------------------------------------------------------------------

/**
 * Insert a user. $role is chosen by OUR code only:
 * the public registration page always passes 'resident'.
 */
function create_user(string $fullName, string $email, string $contact, string $address, string $password, string $role): int
{
    if (!in_array($role, ['resident', 'staff'], true)) {
        throw new InvalidArgumentException('Invalid role.');
    }
    $now = now_sql();
    db()->prepare(
        'INSERT INTO users (full_name, email, contact_number, address, password_hash, role, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$fullName, $email, $contact, $address, password_hash($password, PASSWORD_DEFAULT), $role, $now, $now]);
    return (int) db()->lastInsertId();
}

function update_profile(int $userId, string $fullName, string $contact, string $address): void
{
    db()->prepare('UPDATE users SET full_name = ?, contact_number = ?, address = ?, updated_at = ? WHERE id = ?')
        ->execute([$fullName, $contact, $address, now_sql(), $userId]);
}

function current_password_is_correct(int $userId, string $password): bool
{
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    return is_string($hash) && strlen($password) <= 200 && password_verify($password, $hash);
}

function change_password(int $userId, string $newPassword): void
{
    db()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), now_sql(), $userId]);
}
