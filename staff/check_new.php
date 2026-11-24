<?php
/**
 * staff/check_new.php: a tiny answer for the staff pages that reload themselves.
 *
 * It returns ONE number: {"version": 123}, the id of the newest request_history entry.
 * That number changes whenever a request is submitted, changed, or cancelled. The staff
 * dashboard and request list (assets/js/app.js, section 8) ask for it every few seconds and
 * reload only when it is different.
 *
 * This page does NOT call current_user() on purpose: that function counts every call as
 * "activity", and an open page asking every few seconds would then never reach the idle
 * time-out. Here the session is only READ. When the staff member has been idle for too long
 * (or is not staff), the answer is 401 and the page stops asking.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
send_no_cache_headers();
header('Content-Type: application/json; charset=UTF-8');

$id    = $_SESSION['user_id'] ?? null;
$limit = max(1, (int) config('session.idle_timeout_minutes', 30)) * 60;
$idle  = time() - (int) ($_SESSION['last_activity'] ?? 0);
session_write_close();   // only reading: do not hold the session lock while the database answers

$allowed = false;
if (is_int($id) && $idle <= $limit) {
    $found   = find_user_by_id($id);
    $allowed = $found !== null && (int) $found['is_active'] === 1 && $found['role'] === 'staff';
}

if (!$allowed) {
    http_response_code(401);
    echo '{"version":null}';
    exit;
}

echo json_encode(['version' => latest_history_id()]);
