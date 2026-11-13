<?php
/**
 * helpers.php: small everyday functions used by almost every page.
 */

/**
 * Escape text before printing it in HTML. This stops cross-site scripting (XSS).
 * Use e() on EVERY value that came from the database or from a user.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Web address of a page inside this project: url('login.php') gives /barangayserve/login.php */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Web address of a file in assets/. The file's modified time is added so browsers reload it after a change. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = APP_ROOT . '/assets/' . $path;
    return url('assets/' . $path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * Send the browser to another page of this project and stop.
 * $path is relative to the project, for example redirect('resident/dashboard.php').
 */
function redirect(string $path): void
{
    // Never redirect to another website, even if $path came from the user.
    if (strpos($path, '://') !== false || strpos($path, '//') === 0 || strpbrk($path, "\r\n\\") !== false) {
        $path = 'index.php';
    }
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Text value from the form (POST), with spaces trimmed. Returns '' if it is missing. */
function post_text(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? clean_text(trim($value)) : '';
}

/** Text value from the address bar (GET), with spaces trimmed. */
function get_text(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? clean_text(trim($value)) : '';
}

/** A positive whole number from the address bar (for ids and page numbers). 0 if invalid. */
function get_int(string $key): int
{
    $value = $_GET[$key] ?? '';
    return (is_string($value) && ctype_digit($value) && strlen($value) < 10) ? (int) $value : 0;
}

/** The text the user typed in a form field, so we can show it again after an error. */
function old(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? null;
    return is_string($value) ? $value : $default;
}

/** Remove invisible control characters (keeps new lines and tabs) and make line breaks uniform. */
function clean_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
}

/** Number of characters (not bytes), so names like "Dela Peña" are counted correctly. */
function text_length(string $text): int
{
    return mb_strlen($text, 'UTF-8');
}

/**
 * Remember a message for the NEXT page the user sees (it survives a redirect).
 * Types: success, danger, warning, info.
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Get (and forget) the waiting messages. */
function flash_take(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

/** "Oct 26, 2026, 6:30 PM" (or a dash when there is no date). */
function format_datetime(?string $sqlDate): string
{
    $time = $sqlDate ? strtotime($sqlDate) : false;
    return $time ? date('M j, Y, g:i A', $time) : '—';
}

/** "Oct 26, 2026" */
function format_date(?string $sqlDate): string
{
    $time = $sqlDate ? strtotime($sqlDate) : false;
    return $time ? date('M j, Y', $time) : '—';
}

/** "1 request" / "3 requests" */
function plural(int $count, string $singular, ?string $pluralForm = null): string
{
    return $count . ' ' . ($count === 1 ? $singular : ($pluralForm ?? $singular . 's'));
}

/** The visitor's network address. Only REMOTE_ADDR is trusted (other headers can be faked). */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Shorten long text for table cells: "Barangay clear..." */
function shorten(string $text, int $max = 60): string
{
    return text_length($text) > $max ? mb_substr($text, 0, $max - 1, 'UTF-8') . '…' : $text;
}
