<?php
/**
 * logout.php: end the session (POST only, with a CSRF token, so another website
 * cannot log a user out by embedding a link).
 */
require_once __DIR__ . '/includes/bootstrap.php';
send_no_cache_headers();

if (!is_post()) {
    redirect('index.php');
}
csrf_require();

$wasStaff = (current_user()['role'] ?? '') === 'staff';
logout_user();                                   // empties the session and issues a new session id
flash('success', 'You have been logged out.');
redirect($wasStaff ? 'staff/login.php' : 'login.php');
