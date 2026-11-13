<?php
/**
 * components.php: small reusable pieces of user interface.
 *
 * Each function RETURNS a piece of HTML (a string). Pages print it with:   <?= status_badge($status) ?>
 * Every value that came from a user or the database goes through e() (HTML escaping)
 * so that nobody can inject scripts into the page (cross-site scripting).
 */

/** Build ' name="value"' text from an array. null/false are skipped; true prints just the name. */
function html_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $name => $value) {
        if ($value === null || $value === false) {
            continue;
        }
        $out .= ' ' . $name . ($value === true ? '' : '="' . e($value) . '"');
    }
    return $out;
}

/** Adds a confirmation question to a form or button (handled by assets/js/app.js). */
function confirm_attr(string $message, string $buttonLabel = 'Yes, continue'): string
{
    return ' data-confirm="' . e($message) . '" data-confirm-button="' . e($buttonLabel) . '"';
}

// ---------------------------------------------------------------------------
// Page header with optional breadcrumb
// ---------------------------------------------------------------------------

/**
 * $crumbs: [['Dashboard', 'resident/dashboard.php'], ['My requests', null]]  (last one has no link)
 * $actionsHtml: trusted HTML for buttons on the right (built by our own code).
 */
function page_header(string $title, string $subtitle = '', string $actionsHtml = '', array $crumbs = []): string
{
    $html = '<div class="page-header">';
    if ($crumbs) {
        $html .= '<nav aria-label="Breadcrumb"><ol class="breadcrumb">';
        foreach ($crumbs as $crumb) {
            if (!empty($crumb[1])) {
                $html .= '<li class="breadcrumb-item"><a href="' . e(url($crumb[1])) . '">' . e($crumb[0]) . '</a></li>';
            } else {
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . e($crumb[0]) . '</li>';
            }
        }
        $html .= '</ol></nav>';
    }
    $html .= '<div class="page-header-row"><div class="page-header-text">';
    $html .= '<h1>' . e($title) . '</h1>';
    if ($subtitle !== '') {
        $html .= '<p class="page-subtitle">' . e($subtitle) . '</p>';
    }
    $html .= '</div>';
    if ($actionsHtml !== '') {
        $html .= '<div class="page-actions">' . $actionsHtml . '</div>';
    }
    return $html . '</div></div>';
}

// ---------------------------------------------------------------------------
// Status badge, progress tracker, and the reference "stub"
// ---------------------------------------------------------------------------

/** A coloured label with an icon AND the status text (colour is never the only clue). */
function status_badge(string $status): string
{
    $d = STATUS_DETAILS[$status] ?? ['slug' => 'unknown'];
    return '<span class="status-badge status-' . e($d['slug']) . '"><span>' . e($status) . '</span></span>';
}

/**
 * The five-step progress tracker (Submitted -> ... -> Completed).
 * Steps before the current one are "done", the current one is highlighted.
 */
function status_tracker(string $status): string
{
    $current = status_position($status);
    $last    = count(REQUEST_STATUSES) - 1;

    $html = '<ol class="tracker" aria-label="Request progress">';
    foreach (REQUEST_STATUSES as $i => $name) {
        $isCurrent = ($i === $current);
        $isDone    = ($i < $current) || ($isCurrent && $i === $last);   // "Completed" counts as done
        $class     = 'tracker-step' . ($isDone ? ' is-done' : '') . ($isCurrent ? ' is-current' : '');
        $note      = $isDone && !$isCurrent ? 'done' : ($isCurrent ? ($isDone ? 'done, this is the final step' : 'current step') : 'not reached yet');

        $html .= '<li class="' . $class . '"' . ($isCurrent ? ' aria-current="step"' : '') . '>';
        $html .= '<span class="tracker-dot" aria-hidden="true">' . ($i + 1) . '</span>';
        $html .= '<span class="tracker-label">' . e($name) . '<span class="visually-hidden"> (' . e($note) . ')</span></span>';
        $html .= '</li>';
    }
    return $html . '</ol>';
}

/**
 * The reference "stub": the reference number is the one thing residents keep and bring
 * to the barangay hall, so it is shown big, like the claim stub of a ticket.
 * $request needs: reference_number, service_type, status, submitted_at.
 */
function reference_stub(array $request, bool $copyButton = false): string
{
    $html  = '<section class="stub" aria-label="Request summary">';
    $html .= '<div class="stub-main">';
    $html .= '<p class="stub-label">Reference number</p>';
    $html .= '<p class="stub-number"><span id="reference-number">' . e($request['reference_number']) . '</span>';
    if ($copyButton) {
        $html .= ' <button type="button" class="btn btn-sm btn-outline-secondary stub-copy" data-copy-target="#reference-number"'
            . ' hidden><span>Copy reference</span></button>';
    }
    $html .= '</p>';
    $html .= '<p class="stub-service">' . e($request['service_type']) . '</p>';
    $html .= '</div>';
    $html .= '<div class="stub-side">';
    $html .= '<p class="stub-label">Current status</p>';
    $html .= '<p class="stub-status">' . status_badge($request['status']) . '</p>';
    $html .= '<p class="stub-date">Submitted ' . e(format_datetime($request['submitted_at'])) . '</p>';
    $html .= '</div></section>';
    return $html;
}

/** A row of five numbers, one per status. Each links to the request list for that status. */
function status_pipeline(array $counts, string $listPath): string
{
    $html = '<ol class="pipeline">';
    foreach (REQUEST_STATUSES as $status) {
        $d    = STATUS_DETAILS[$status];
        $n    = (int) ($counts[$status] ?? 0);
        $href = url($listPath) . '?' . http_build_query(['status' => $status]);
        $html .= '<li class="pipeline-item pipeline-' . e($d['slug']) . '">';
        $html .= '<a href="' . e($href) . '" aria-label="' . e($status . ': ' . plural($n, 'request') . '. Show them.') . '">';
        $html .= '<span class="pipeline-count" aria-hidden="true">' . $n . '</span>';
        $html .= '<span class="pipeline-label" aria-hidden="true">' . e($status) . '</span>';
        $html .= '</a></li>';
    }
    return $html . '</ol>';
}

// ---------------------------------------------------------------------------
// History table (SRS FR-08)
// ---------------------------------------------------------------------------

/**
 * Chronological history, oldest first. Columns: date and time, status, remark.
 * Staff also see who made each change (the "audit" column). Residents do not.
 */
function history_table(array $entries, bool $forStaff = false): string
{
    if (!$entries) {
        return '<p class="text-body-secondary mb-0">There is no history yet.</p>';
    }

    $html  = '<div class="table-wrap"><table class="table table-stack history-table align-middle">';
    $html .= '<caption class="visually-hidden">History of status changes and remarks, oldest first</caption>';
    $html .= '<thead><tr><th scope="col">Date and time</th><th scope="col">Status</th><th scope="col">Remark</th>';
    $html .= $forStaff ? '<th scope="col">Updated by</th>' : '';
    $html .= '</tr></thead><tbody>';

    foreach ($entries as $entry) {
        $when = strtotime($entry['created_at']);
        $html .= '<tr>';
        $html .= '<td data-label="Date and time"><time datetime="' . e(date('c', $when)) . '">'
            . e(format_datetime($entry['created_at'])) . '</time></td>';

        $html .= '<td data-label="Status">' . status_badge($entry['status']);
        if ($entry['entry_type'] === 'correction') {
            $html .= ' <span class="entry-tag entry-tag-correction">Correction: moved back</span>';
        } elseif ($entry['entry_type'] === 'remark') {
            $html .= ' <span class="entry-tag">Remark added</span>';
        }
        $html .= '</td>';

        $html .= '<td data-label="Remark">' . ($entry['remark'] !== null && $entry['remark'] !== ''
            ? nl2br(e($entry['remark']))
            : '<span class="text-body-secondary">No remark</span>') . '</td>';

        if ($forStaff) {
            $who = $entry['changed_by_name'] . ($entry['changed_by_role'] === 'resident' ? ' (resident)' : ' (staff)');
            $html .= '<td data-label="Updated by">' . e($who) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** The answers to a service's questions (read back from the saved JSON) as a tidy list. */
function answers_list(array $answers): string
{
    if (!$answers) {
        return '<p class="text-body-secondary mb-0">This service did not need any extra information.</p>';
    }
    $html = '<dl class="detail-list">';
    foreach ($answers as $answer) {
        $value = (string) ($answer['value'] ?? '');
        if (($answer['type'] ?? '') === 'date' && $value !== '') {
            $value = format_date($value);
        }
        $html .= '<div class="detail-row"><dt>' . e($answer['label'] ?? '') . '</dt><dd>'
            . ($value === '' ? '<span class="text-body-secondary">Not provided</span>' : nl2br(e($value)))
            . '</dd></div>';
    }
    return $html . '</dl>';
}

// ---------------------------------------------------------------------------
// Empty state, pagination
// ---------------------------------------------------------------------------

function empty_state(string $icon, string $title, string $text, ?string $actionPath = null, ?string $actionLabel = null): string
{
    $html  = '<div class="empty-state">';
    $html .= '<h2 class="h5">' . e($title) . '</h2><p>' . e($text) . '</p>';
    if ($actionPath !== null && $actionLabel !== null) {
        $html .= '<a class="btn btn-primary" href="' . e(url($actionPath)) . '">' . e($actionLabel) . '</a>';
    }
    return $html . '</div>';
}

/** "Showing 11 to 20 of 34 requests" */
function result_range_text(int $page, int $perPage, int $total, string $noun = 'request'): string
{
    if ($total === 0) {
        return 'No ' . $noun . 's found';
    }
    $first = ($page - 1) * $perPage + 1;
    $last  = min($total, $page * $perPage);
    return 'Showing ' . $first . ' to ' . $last . ' of ' . plural($total, $noun);
}

/**
 * Page links under a table. $query holds the filters to keep, for example ['status' => 'Processing'].
 */
function pagination_links(int $page, int $perPage, int $total, string $path, array $query = []): string
{
    $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
    if ($lastPage <= 1) {
        return '';
    }
    unset($query['page']);

    $href = function (int $p) use ($path, $query): string {
        $q = $p > 1 ? array_merge($query, ['page' => $p]) : $query;
        return url($path) . ($q ? '?' . http_build_query($q) : '');
    };
    $item = function (string $label, ?int $p, bool $current = false, string $aria = '') use ($href): string {
        if ($p === null) {
            return '<li class="page-item disabled"><span class="page-link">' . $label . '</span></li>';
        }
        return '<li class="page-item' . ($current ? ' active' : '') . '"' . ($current ? ' aria-current="page"' : '') . '>'
            . '<a class="page-link" href="' . e($href($p)) . '"' . ($aria !== '' ? ' aria-label="' . e($aria) . '"' : '') . '>' . $label . '</a></li>';
    };

    // Show the first page, the last page, and two pages on each side of the current one.
    $shown = [];
    for ($p = 1; $p <= $lastPage; $p++) {
        if ($p === 1 || $p === $lastPage || abs($p - $page) <= 2) {
            $shown[] = $p;
        }
    }

    $html  = '<nav aria-label="Pages of results"><ul class="pagination flex-wrap mb-0">';
    $html .= $item('<i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Previous page</span>', $page > 1 ? $page - 1 : null);
    $previous = 0;
    foreach ($shown as $p) {
        if ($p - $previous > 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $html .= $item((string) $p, $p, $p === $page, $p === $page ? 'Page ' . $p . ', current page' : 'Go to page ' . $p);
        $previous = $p;
    }
    $html .= $item('<i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Next page</span>', $page < $lastPage ? $page + 1 : null);
    return $html . '</ul></nav>';
}

// ---------------------------------------------------------------------------
// Forms: labelled fields with help text and error messages
// ---------------------------------------------------------------------------

/**
 * The red box at the top of a form that lists every problem, with links to the fields.
 * Keys that do not start with "field" can be anything; "_form" is a general message.
 */
function error_summary(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $count = count($errors);
    $html  = '<div class="alert alert-danger error-summary" id="error-summary" role="alert" tabindex="-1">';
    $html .= '<h2 class="h6 alert-heading mb-2"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i> '
        . ($count === 1 ? 'Please fix this problem' : 'Please fix these ' . $count . ' problems') . '</h2><ul class="mb-0">';
    foreach ($errors as $key => $message) {
        $html .= '<li>' . ($key === '_form'
            ? e($message)
            : '<a href="#field-' . e($key) . '">' . e($message) . '</a>') . '</li>';
    }
    return $html . '</ul></div>';
}

/** The label of a field, with a red * when the field is required. */
function form_label(string $id, string $label, bool $required): string
{
    return '<label for="' . e($id) . '" class="form-label">' . e($label)
        . ($required ? ' <span class="req" aria-hidden="true">*</span>' : ' <span class="optional">(optional)</span>')
        . '</label>';
}

/** Help text and the error message under a field (and the ids the field points to with aria-describedby). */
function form_notes(string $name, ?string $help, ?string $error): array
{
    $html = '';
    $ids  = [];
    if ($help !== null && $help !== '') {
        $html .= '<div id="help-' . e($name) . '" class="form-text">' . e($help) . '</div>';
        $ids[] = 'help-' . $name;
    }
    if ($error !== null) {
        $html .= '<div id="error-' . e($name) . '" class="invalid-feedback d-block">'
            . '<i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i> ' . e($error) . '</div>';
        $ids[] = 'error-' . $name;
    }
    return [$html, $ids ? implode(' ', $ids) : null];
}

/**
 * A text-style input.
 * $opts: type, value, required, help, maxlength, minlength, autocomplete, inputmode,
 *        placeholder, pattern, min, max, readonly, toggle (password show/hide button)
 */
function form_input(string $name, string $label, array $errors, array $opts = []): string
{
    $id       = 'field-' . $name;
    $type     = (string) ($opts['type'] ?? 'text');
    $required = !empty($opts['required']);
    $error    = $errors[$name] ?? null;
    [$notes, $describedBy] = form_notes($name, $opts['help'] ?? null, $error);

    $input = '<input' . html_attrs([
        'type'             => $type,
        'class'            => 'form-control' . ($error !== null ? ' is-invalid' : ''),
        'id'               => $id,
        'name'             => $name,
        'value'            => $type === 'password' ? null : (string) ($opts['value'] ?? ''),   // never print passwords back
        'required'         => $required,
        'maxlength'        => $opts['maxlength'] ?? null,
        'minlength'        => $opts['minlength'] ?? null,
        'autocomplete'     => $opts['autocomplete'] ?? null,
        'inputmode'        => $opts['inputmode'] ?? null,
        'placeholder'      => $opts['placeholder'] ?? null,
        'pattern'          => $opts['pattern'] ?? null,
        'min'              => $opts['min'] ?? null,
        'max'              => $opts['max'] ?? null,
        'readonly'         => !empty($opts['readonly']),
        'aria-invalid'     => $error !== null ? 'true' : null,
        'aria-describedby' => $describedBy,
    ]) . '>';

    if (!empty($opts['toggle'])) {
        $input = '<div class="input-group">' . $input
            . '<button type="button" class="btn btn-outline-secondary" data-toggle-password="#' . e($id) . '" aria-label="Show password">'
            . '<i class="bi bi-eye" aria-hidden="true"></i></button></div>';
    }
    return '<div class="mb-3">' . form_label($id, $label, $required) . $input . $notes . '</div>';
}

/** A multi-line text box. $opts: value, required, help, rows, maxlength, placeholder */
function form_textarea(string $name, string $label, array $errors, array $opts = []): string
{
    $id       = 'field-' . $name;
    $required = !empty($opts['required']);
    $error    = $errors[$name] ?? null;
    [$notes, $describedBy] = form_notes($name, $opts['help'] ?? null, $error);

    $area = '<textarea' . html_attrs([
        'class'            => 'form-control' . ($error !== null ? ' is-invalid' : ''),
        'id'               => $id,
        'name'             => $name,
        'rows'             => (string) ($opts['rows'] ?? 3),
        'required'         => $required,
        'maxlength'        => $opts['maxlength'] ?? null,
        'placeholder'      => $opts['placeholder'] ?? null,
        'data-counter'     => isset($opts['maxlength']) ? 'true' : null,
        'aria-invalid'     => $error !== null ? 'true' : null,
        'aria-describedby' => $describedBy,
    ]) . '>' . e((string) ($opts['value'] ?? '')) . '</textarea>';

    return '<div class="mb-3">' . form_label($id, $label, $required) . $area . $notes . '</div>';
}

/**
 * A drop-down list. $choices is [value => text]. $opts: value (selected), required, help, placeholder.
 */
function form_select(string $name, string $label, array $errors, array $choices, array $opts = []): string
{
    $id       = 'field-' . $name;
    $required = !empty($opts['required']);
    $error    = $errors[$name] ?? null;
    $selected = (string) ($opts['value'] ?? '');
    [$notes, $describedBy] = form_notes($name, $opts['help'] ?? null, $error);

    $select = '<select' . html_attrs([
        'class'            => 'form-select' . ($error !== null ? ' is-invalid' : ''),
        'id'               => $id,
        'name'             => $name,
        'required'         => $required,
        'aria-invalid'     => $error !== null ? 'true' : null,
        'aria-describedby' => $describedBy,
    ]) . '>';
    if (isset($opts['placeholder'])) {
        $select .= '<option value="">' . e($opts['placeholder']) . '</option>';
    }
    foreach ($choices as $value => $text) {
        $select .= '<option value="' . e($value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . e($text) . '</option>';
    }
    $select .= '</select>';

    return '<div class="mb-3">' . form_label($id, $label, $required) . $select . $notes . '</div>';
}

// ---------------------------------------------------------------------------
// Request tables (used on several pages, so they live here once)
// ---------------------------------------------------------------------------

/** The resident's request table: reference, service, date, status, and a "View details" action. */
function resident_requests_table(array $rows): string
{
    $html  = '<div class="table-wrap"><table class="table table-stack align-middle">';
    $html .= '<caption class="visually-hidden">Your service requests, newest first</caption>';
    $html .= '<thead><tr><th scope="col">Reference number</th><th scope="col">Service</th>'
           . '<th scope="col">Date submitted</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Action</span></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $link = url('resident/request.php') . '?id=' . (int) $row['id'];
        $html .= '<tr>';
        $html .= '<td class="ref" data-label="Reference number"><a href="' . e($link) . '">' . e($row['reference_number']) . '</a></td>';
        $html .= '<td data-label="Service">' . e($row['service_type']) . '</td>';
        $html .= '<td data-label="Date submitted">' . e(format_datetime($row['submitted_at'])) . '</td>';
        $html .= '<td data-label="Status">' . status_badge($row['status']) . '</td>';
        $html .= '<td class="td-actions"><a class="btn btn-sm btn-outline-primary" href="' . e($link) . '">View details'
               . '<span class="visually-hidden"> of ' . e($row['reference_number']) . '</span></a></td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** The staff request table (SRS FR-09): reference, resident, service, date, status, and an action. */
function staff_requests_table(array $rows): string
{
    $html  = '<div class="table-wrap"><table class="table table-stack align-middle">';
    $html .= '<caption class="visually-hidden">Submitted service requests</caption>';
    $html .= '<thead><tr><th scope="col">Reference number</th><th scope="col">Resident</th><th scope="col">Service</th>'
           . '<th scope="col">Date submitted</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Action</span></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $link = url('staff/request.php') . '?id=' . (int) $row['id'];
        $html .= '<tr>';
        $html .= '<td class="ref" data-label="Reference number"><a href="' . e($link) . '">' . e($row['reference_number']) . '</a></td>';
        $html .= '<td data-label="Resident">' . e($row['applicant_name']) . '</td>';
        $html .= '<td data-label="Service">' . e($row['service_type']) . '</td>';
        $html .= '<td data-label="Date submitted">' . e(format_datetime($row['submitted_at'])) . '</td>';
        $html .= '<td data-label="Status">' . status_badge($row['status']) . '</td>';
        $html .= '<td class="td-actions"><a class="btn btn-sm btn-primary" href="' . e($link) . '">Open'
               . '<span class="visually-hidden"> ' . e($row['reference_number']) . '</span></a></td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** A single tick box. */
function form_checkbox(string $name, string $label, bool $checked, ?string $help = null): string
{
    $id = 'field-' . $name;
    $html  = '<div class="form-check mb-3">';
    $html .= '<input class="form-check-input" type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1"'
        . ($checked ? ' checked' : '') . ($help ? ' aria-describedby="help-' . e($name) . '"' : '') . '>';
    $html .= '<label class="form-check-label" for="' . e($id) . '">' . e($label) . '</label>';
    if ($help) {
        $html .= '<div id="help-' . e($name) . '" class="form-text">' . e($help) . '</div>';
    }
    return $html . '</div>';
}
