<?php
/**
 * csrf.php: protection against forged form submissions, and against double submits.
 *
 * 1) CSRF token. Every form that changes data contains a hidden secret that only
 *    our own pages know. A malicious website cannot guess it, so it cannot make
 *    a logged-in user's browser submit our forms.
 *      In the form:      <?= csrf_field() ?>
 *      In the handler:   csrf_require();
 *
 * 2) One-time form token. The request form gets a random token when it is shown.
 *    The token is saved with the request (UNIQUE column), so pressing refresh,
 *    double-clicking Submit, or going Back and submitting again cannot create a
 *    second request.
 */

/** The secret for this browser session (created on first use). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** The hidden input to place inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Stop with a friendly error page when the CSRF token is missing or wrong. */
function csrf_require(): void
{
    if (!csrf_is_valid()) {
        http_response_code(403);
        show_error_page(
            403,
            'This form has expired',
            'For your safety the form could not be accepted. Please go back, reload the page, and try again.'
        );
        exit;
    }
}

/** Make a new one-time token for the request form (32 hex characters). */
function form_token_issue(): string
{
    $tokens = $_SESSION['form_tokens'] ?? [];
    if (!is_array($tokens)) {
        $tokens = [];
    }
    // Forget tokens older than 2 hours and keep at most 20 (several tabs may be open).
    foreach ($tokens as $old => $createdAt) {
        if ((int) $createdAt < time() - 7200) {
            unset($tokens[$old]);
        }
    }
    while (count($tokens) >= 20) {
        array_shift($tokens);
    }
    $token = bin2hex(random_bytes(16));
    $tokens[$token] = time();
    $_SESSION['form_tokens'] = $tokens;
    return $token;
}

/** Was this token really handed out by our form in this session, and not used yet? */
function form_token_is_issued(string $token): bool
{
    return isset($_SESSION['form_tokens'][$token]);
}

/** Mark the token as used. */
function form_token_consume(string $token): void
{
    unset($_SESSION['form_tokens'][$token]);
}
