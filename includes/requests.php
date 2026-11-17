<?php
/**
 * requests.php: everything about service requests (the heart of BarangayServe).
 *
 *   1. The five statuses and the rules for moving between them
 *   2. Reference numbers   (BR-YYYY-NNNNN)
 *   3. Saving a new request          -> create_service_request()
 *   4. Staff updates (status/remark) -> staff_update_request()
 *   5. Reading requests, history, counts, and filtered lists
 *
 * RULES THAT KEEP THE DATA SAFE
 *   - request_history is APPEND-ONLY. add_history_entry() below is the ONLY place in
 *     the whole application that writes to that table, and it only ever INSERTs.
 *   - A resident reads a request only through find_request_for_resident(), whose SQL
 *     contains "AND user_id = ?". Changing the id in the address bar can never show
 *     someone else's request.
 *   - A request and its history row are always saved together in one transaction.
 */

// ===========================================================================
// 1. STATUSES  (SRS FR-06 and Appendix C)
// ===========================================================================

/** The five statuses, in workflow order: Submitted -> Under Review -> Processing -> Ready for Pickup -> Completed */
const REQUEST_STATUSES = ['Submitted', 'Under Review', 'Processing', 'Ready for Pickup', 'Completed'];

/**
 * How each status looks and what it means to the resident.
 * 'slug' is used in CSS class names, 'icon' is a Bootstrap Icons name.
 */
const STATUS_DETAILS = [
    'Submitted' => [
        'slug' => 'submitted',
        'icon' => 'bi-inbox',
        'resident_text' => 'We received your request. Staff will start checking it soon.',
    ],
    'Under Review' => [
        'slug' => 'under-review',
        'icon' => 'bi-search',
        'resident_text' => 'Staff are checking the information you submitted.',
    ],
    'Processing' => [
        'slug' => 'processing',
        'icon' => 'bi-gear',
        'resident_text' => 'Staff are preparing your document or service.',
    ],
    'Ready for Pickup' => [
        'slug' => 'ready-for-pickup',
        'icon' => 'bi-bag-check',
        'resident_text' => 'Your document or service is ready. You can pick it up now.',
    ],
    'Completed' => [
        'slug' => 'completed',
        'icon' => 'bi-check-circle',
        'resident_text' => 'This request is finished.',
    ],
];

function is_valid_status(string $status): bool
{
    return in_array($status, REQUEST_STATUSES, true);
}

/** Position of a status in the workflow: 0 for Submitted ... 4 for Completed (-1 if unknown). */
function status_position(string $status): int
{
    $index = array_search($status, REQUEST_STATUSES, true);
    return $index === false ? -1 : (int) $index;
}

function next_status(string $status): ?string
{
    $position = status_position($status);
    return ($position >= 0 && isset(REQUEST_STATUSES[$position + 1])) ? REQUEST_STATUSES[$position + 1] : null;
}

function previous_status(string $status): ?string
{
    $position = status_position($status);
    return $position > 0 ? REQUEST_STATUSES[$position - 1] : null;
}

/**
 * A ready-made remark staff can keep or edit when they move a request forward.
 * The wording follows the examples in SRS FR-08.
 */
function suggested_remark(string $status): string
{
    switch ($status) {
        case 'Under Review':
            return 'Request is being checked.';
        case 'Processing':
            return 'Request is being processed.';
        case 'Ready for Pickup':
            $text  = 'Document is ready for pickup at ' . (string) config('office_location', 'the barangay hall') . '.';
            $hours = trim((string) config('office_hours', ''));
            return $hours !== '' ? $text . ' Office hours: ' . $hours . '.' : $text;
        case 'Completed':
            return 'Request completed.';
    }
    return '';
}

/**
 * WHAT MAY STAFF DO WITH A REQUEST THAT CURRENTLY HAS $status?
 *
 *   advance : move forward ONE step (the normal flow). Remark is optional.
 *   remark  : add a remark and keep the status. Remark is required.
 *   correct : move back ONE step to fix a mistake. Remark is required, and the
 *             history marks the entry as a "correction" so it is easy to audit.
 *
 * Skipping steps is not allowed, so every request shows the same, consistent path.
 * Returns a list of ['action', 'target', 'remark_required', 'default_remark'].
 */
function staff_action_choices(string $status): array
{
    $choices = [];

    $next = next_status($status);
    if ($next !== null) {
        $choices[] = ['action' => 'advance', 'target' => $next, 'remark_required' => false, 'default_remark' => suggested_remark($next)];
    }

    $choices[] = ['action' => 'remark', 'target' => $status, 'remark_required' => true, 'default_remark' => ''];

    $previous = previous_status($status);
    if ($previous !== null) {
        $choices[] = ['action' => 'correct', 'target' => $previous, 'remark_required' => true, 'default_remark' => ''];
    }
    return $choices;
}

// ===========================================================================
// 2. REFERENCE NUMBERS  (SRS FR-05)   BR-YYYY-NNNNN, for example BR-2026-00125
// ===========================================================================

function format_reference_number(int $year, int $sequence): string
{
    return sprintf('BR-%04d-%05d', $year, $sequence);
}

/**
 * Take the next sequence number for a year.
 *
 * HOW IT STAYS UNIQUE: the table reference_counters has one row per year. The
 * INSERT ... ON DUPLICATE KEY UPDATE below locks that row until the surrounding
 * transaction ends. If two residents submit at the same moment, the second one waits
 * for the first one to finish, then receives the next number. The UNIQUE key on
 * service_requests.reference_number is a second safety net. We do NOT count the
 * existing requests, which would give the same number to two people.
 *
 * Call this ONLY inside run_transaction().
 */
function next_reference_sequence(PDO $pdo, int $year): int
{
    $pdo->prepare(
        'INSERT INTO reference_counters (ref_year, last_number) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE last_number = last_number + 1'
    )->execute([$year]);

    $stmt = $pdo->prepare('SELECT last_number FROM reference_counters WHERE ref_year = ? FOR UPDATE');
    $stmt->execute([$year]);
    return (int) $stmt->fetchColumn();
}

// ===========================================================================
// 3. HISTORY  (SRS FR-08)  APPEND-ONLY
// ===========================================================================

/**
 * Add one line to a request's history. This is the ONLY function that writes to
 * request_history, and it only INSERTs: old lines are never changed or deleted.
 *
 * $type: submitted | status_change | correction | remark
 */
function add_history_entry(PDO $pdo, int $requestId, string $status, string $type, ?string $remark, int $userId, string $when): void
{
    $pdo->prepare(
        'INSERT INTO request_history (request_id, status, entry_type, remark, changed_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$requestId, $status, $type, ($remark === null || $remark === '') ? null : $remark, $userId, $when]);
}

/** The full history of one request, newest first (the latest update is on top). */
function get_request_history(int $requestId): array
{
    $stmt = db()->prepare(
        'SELECT h.id, h.status, h.entry_type, h.remark, h.created_at,
                u.full_name AS changed_by_name, u.role AS changed_by_role
           FROM request_history h
           JOIN users u ON u.id = h.changed_by
          WHERE h.request_id = ?
          ORDER BY h.created_at DESC, h.id DESC'
    );
    $stmt->execute([$requestId]);
    return $stmt->fetchAll();
}

// ===========================================================================
// 4. SAVING A NEW REQUEST  (SRS FR-04)
// ===========================================================================

/**
 * Save a new request, give it a reference number, and write the first history line,
 * all in ONE transaction (all saved, or nothing saved).
 *
 *   $applicant : ['name' => ..., 'address' => ..., 'contact' => ...]
 *   $answers   : the already-validated answers to the service's questions
 *   $token     : the one-time form token (see csrf.php). It is stored in a UNIQUE column,
 *                so pressing Refresh, double-clicking Submit, or using the Back button
 *                can never create a second request.
 *
 * Returns ['id' => int, 'reference_number' => string, 'duplicate' => bool].
 * 'duplicate' is true when this same form had already been saved earlier.
 */
function create_service_request(int $userId, array $service, array $applicant, string $purpose, array $answers, string $token): array
{
    $existing = find_request_by_token($token, $userId);
    if ($existing !== null) {
        return ['id' => (int) $existing['id'], 'reference_number' => $existing['reference_number'], 'duplicate' => true];
    }

    $json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('The answers could not be converted to JSON.');
    }

    try {
        return run_transaction(function (PDO $pdo) use ($userId, $service, $applicant, $purpose, $json, $token) {
            $now  = now_sql();
            $year = (int) date('Y');                                // the year of submission
            $reference = format_reference_number($year, next_reference_sequence($pdo, $year));

            $pdo->prepare(
                'INSERT INTO service_requests
                    (reference_number, user_id, service_id, service_type, applicant_name, applicant_address,
                     applicant_contact, purpose, request_data, status, submission_token, submitted_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $reference, $userId, (int) $service['id'], $service['name'],
                $applicant['name'], $applicant['address'], $applicant['contact'],
                $purpose, $json, 'Submitted', $token, $now, $now,
            ]);
            $requestId = (int) $pdo->lastInsertId();

            // The first history line: status "Submitted", written by the resident.
            add_history_entry($pdo, $requestId, 'Submitted', 'submitted', 'Request received.', $userId, $now);

            return ['id' => $requestId, 'reference_number' => $reference, 'duplicate' => false];
        });
    } catch (PDOException $e) {
        // The same form reached the server twice at the same moment: the other copy won.
        if (duplicate_key_is($e, 'uq_requests_token')) {
            $existing = find_request_by_token($token, $userId);
            if ($existing !== null) {
                return ['id' => (int) $existing['id'], 'reference_number' => $existing['reference_number'], 'duplicate' => true];
            }
        }
        throw $e;
    }
}

/** Has this form token already been saved by this resident? */
function find_request_by_token(string $token, int $userId): ?array
{
    $stmt = db()->prepare('SELECT id, reference_number FROM service_requests WHERE submission_token = ? AND user_id = ?');
    $stmt->execute([$token, $userId]);
    return $stmt->fetch() ?: null;
}

// ===========================================================================
// 5. STAFF UPDATES  (SRS FR-06 and FR-09)
// ===========================================================================

/**
 * Staff change the status and/or add a remark. The request row and the new history
 * line are saved together in ONE transaction.
 *
 *   $action         : 'advance' | 'remark' | 'correct'   (see staff_action_choices)
 *   $expectedStatus : the status the staff member SAW on the page. If someone else
 *                     changed the request in the meantime, the update is refused instead
 *                     of silently skipping a step.
 *
 * Returns ['ok' => bool, 'error' => ?string, 'field' => ?string, 'status' => ?string, 'message' => ?string]
 */
function staff_update_request(int $requestId, int $staffId, string $action, string $expectedStatus, string $remark): array
{
    $fail = function (string $error, ?string $field = null): array {
        return ['ok' => false, 'error' => $error, 'field' => $field, 'status' => null, 'message' => null];
    };

    if (!in_array($action, ['advance', 'remark', 'correct'], true)) {
        return $fail('Please choose what you want to do with this request.', 'action');
    }

    // Check the remark BEFORE touching the database.
    $remark = trim($remark);
    if ($remark === '' && $action === 'remark') {
        return $fail('Please write the remark you want to add.', 'remark');
    }
    if ($remark === '' && $action === 'correct') {
        return $fail('Please explain the correction in the remark. The resident will see it in the history.', 'remark');
    }
    $problem = validate_remark($remark, false);
    if ($problem !== null) {
        return $fail($problem, 'remark');
    }

    return run_transaction(function (PDO $pdo) use ($requestId, $staffId, $action, $expectedStatus, $remark, $fail) {
        // Lock this request's row so two staff members cannot update it at the same moment.
        $stmt = $pdo->prepare('SELECT reference_number, status FROM service_requests WHERE id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $row = $stmt->fetch();
        if (!$row) {
            return $fail('This request no longer exists.');
        }
        $current = $row['status'];

        if ($current !== $expectedStatus) {
            return $fail('This request was just updated by someone else and is now "' . $current . '". '
                . 'The page below shows the latest information. Please check it and try again.', 'stale');
        }

        if ($action === 'advance') {
            $target = next_status($current);
            $type   = 'status_change';
            if ($target === null) {
                return $fail('This request is already at the last step (' . $current . ').');
            }
        } elseif ($action === 'correct') {
            $target = previous_status($current);
            $type   = 'correction';
            if ($target === null) {
                return $fail('This request is at the first step and cannot go back.');
            }
        } else {
            $target = $current;
            $type   = 'remark';
        }

        $now = now_sql();
        $pdo->prepare('UPDATE service_requests SET status = ?, updated_at = ? WHERE id = ?')
            ->execute([$target, $now, $requestId]);
        add_history_entry($pdo, $requestId, $target, $type, $remark, $staffId, $now);

        if ($action === 'advance') {
            $message = $row['reference_number'] . ' is now "' . $target . '".';
        } elseif ($action === 'correct') {
            $message = $row['reference_number'] . ' was moved back to "' . $target . '" (correction saved in the history).';
        } else {
            $message = 'Your remark was added to ' . $row['reference_number'] . '.';
        }
        return ['ok' => true, 'error' => null, 'field' => null, 'status' => $target, 'message' => $message];
    });
}

// ===========================================================================
// 6. READING REQUESTS
// ===========================================================================

/** The service questions' answers are saved as JSON text. Turn them back into an array. */
function decode_request_answers(?string $json): array
{
    $data = json_decode((string) $json, true);
    return is_array($data) ? $data : [];
}

/**
 * One request that belongs to this resident, or null.
 * The "AND user_id = ?" is what stops residents from opening each other's requests.
 */
function find_request_for_resident(int $requestId, int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT id, reference_number, user_id, service_id, service_type, applicant_name, applicant_address,
                applicant_contact, purpose, request_data, status, submitted_at, updated_at
           FROM service_requests
          WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$requestId, $userId]);
    return $stmt->fetch() ?: null;
}

/** One request for staff, together with the resident's account details. */
function find_request_for_staff(int $requestId): ?array
{
    $stmt = db()->prepare(
        'SELECT r.id, r.reference_number, r.user_id, r.service_id, r.service_type, r.applicant_name,
                r.applicant_address, r.applicant_contact, r.purpose, r.request_data, r.status,
                r.submitted_at, r.updated_at,
                u.full_name AS account_name, u.email AS account_email,
                u.contact_number AS account_contact, u.address AS account_address
           FROM service_requests r
           JOIN users u ON u.id = r.user_id
          WHERE r.id = ?'
    );
    $stmt->execute([$requestId]);
    return $stmt->fetch() ?: null;
}

/**
 * How many requests are in each status: ['Submitted' => 3, ..., 'Completed' => 9, 'total' => 21].
 * Pass a user id for one resident's own numbers, or null for ALL requests (staff dashboard).
 * The numbers are counted from the database every time (nothing is hard-coded).
 */
function count_requests_by_status(?int $userId = null): array
{
    if ($userId === null) {
        $rows = db()->query('SELECT status, COUNT(*) AS total FROM service_requests GROUP BY status')->fetchAll();
    } else {
        $stmt = db()->prepare('SELECT status, COUNT(*) AS total FROM service_requests WHERE user_id = ? GROUP BY status');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    }

    $counts = array_fill_keys(REQUEST_STATUSES, 0);
    foreach ($rows as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }
    $counts['total'] = array_sum($counts);
    return $counts;
}

/** Keep a page number inside the real range (1 .. last page). */
function clamp_page(int $page, int $total, int $perPage): int
{
    $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
    return max(1, min($page, $lastPage));
}

/** Run a SELECT that ends with "LIMIT ? OFFSET ?" and return all rows. */
function fetch_page(string $sql, array $params, int $perPage, int $offset): array
{
    $stmt  = db()->prepare($sql);
    $index = 1;
    foreach ($params as $value) {
        $stmt->bindValue($index++, $value);
    }
    $stmt->bindValue($index++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($index, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * A resident's own requests (newest first), optionally only one status.
 * Returns ['rows' => [...], 'total' => n, 'page' => n].
 */
function list_resident_requests(int $userId, string $status, int $page, int $perPage): array
{
    $where  = 'WHERE r.user_id = ?';
    $params = [$userId];
    if ($status !== '' && is_valid_status($status)) {
        $where   .= ' AND r.status = ?';
        $params[] = $status;
    }

    $stmt = db()->prepare("SELECT COUNT(*) FROM service_requests r $where");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    $page  = clamp_page($page, $total, $perPage);

    $rows = fetch_page(
        "SELECT r.id, r.reference_number, r.service_type, r.status, r.submitted_at, r.updated_at
           FROM service_requests r $where
          ORDER BY r.submitted_at DESC, r.id DESC
          LIMIT ? OFFSET ?",
        $params, $perPage, ($page - 1) * $perPage
    );
    return ['rows' => $rows, 'total' => $total, 'page' => $page];
}

/** Read the staff search form from the address bar and clean every value. */
function staff_filters_from_query(): array
{
    $status = get_text('status');
    $from   = get_text('from');
    $to     = get_text('to');
    return [
        'q'          => mb_substr(get_text('q'), 0, 100),
        'service_id' => get_int('service'),
        'status'     => is_valid_status($status) ? $status : '',
        'from'       => ($from !== '' && is_valid_date($from)) ? $from : '',
        'to'         => ($to !== '' && is_valid_date($to)) ? $to : '',
        'sort'       => get_text('sort') === 'oldest' ? 'oldest' : 'newest',
    ];
}

/**
 * All requests for staff, with search and filters (SRS FR-09).
 * $filters: q (reference number or name), service_id, status, from, to (dates), sort.
 * Every value goes into the SQL through "?" placeholders, never into the SQL text.
 * Returns ['rows' => [...], 'total' => n, 'page' => n].
 */
function list_staff_requests(array $filters, int $page, int $perPage): array
{
    $where  = [];
    $params = [];

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        // Escape % _ and \ so they are searched as normal characters.
        $like     = '%' . addcslashes($q, '%_\\') . '%';
        $where[]  = '(r.reference_number LIKE ? OR r.applicant_name LIKE ? OR u.full_name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    if (!empty($filters['service_id'])) {
        $where[]  = 'r.service_id = ?';
        $params[] = (int) $filters['service_id'];
    }
    if (!empty($filters['status']) && is_valid_status($filters['status'])) {
        $where[]  = 'r.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['from'])) {
        $where[]  = 'r.submitted_at >= ?';
        $params[] = $filters['from'] . ' 00:00:00';
    }
    if (!empty($filters['to'])) {
        $where[]  = 'r.submitted_at < ?';
        $params[] = date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // ORDER BY cannot use "?", so only these two fixed texts are ever allowed.
    $orderSql = (($filters['sort'] ?? 'newest') === 'oldest')
        ? 'r.submitted_at ASC, r.id ASC'
        : 'r.submitted_at DESC, r.id DESC';

    $fromSql = 'FROM service_requests r JOIN users u ON u.id = r.user_id ' . $whereSql;

    $stmt = db()->prepare("SELECT COUNT(*) $fromSql");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    $page  = clamp_page($page, $total, $perPage);

    $rows = fetch_page(
        "SELECT r.id, r.reference_number, r.service_type, r.applicant_name, r.status, r.submitted_at,
                r.updated_at, u.full_name AS account_name
           $fromSql
          ORDER BY $orderSql
          LIMIT ? OFFSET ?",
        $params, $perPage, ($page - 1) * $perPage
    );
    return ['rows' => $rows, 'total' => $total, 'page' => $page];
}
