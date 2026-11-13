<?php
/**
 * staff/request.php: one request in full, with the status / remark form (SRS FR-06, FR-08, FR-09).
 *
 * The form is handled on this same page (POST), then the browser is redirected back here
 * (POST-redirect-GET) so that Refresh cannot send the update twice.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff();

$requestId = get_int('id');
$request   = $requestId > 0 ? find_request_for_staff($requestId) : null;
if ($request === null) {
    abort_not_found('We could not find that request.', 'staff/requests.php', 'Go to the request list');
}

$errors = [];

if (is_post()) {
    csrf_require();

    $action   = post_text('action');
    $expected = post_text('expected_status');
    $remark   = post_text('remark');

    // "Move forward" with an empty remark: save the standard remark (the examples in SRS FR-08).
    if ($action === 'advance' && $remark === '' && next_status($expected) !== null) {
        $remark = suggested_remark((string) next_status($expected));
    }

    $result = staff_update_request($requestId, (int) $user['id'], $action, $expected, $remark);
    if ($result['ok']) {
        flash('success', $result['message']);
        redirect('staff/request.php?id=' . $requestId);
    }
    if ($result['field'] === 'stale') {
        flash('warning', $result['error']);
        redirect('staff/request.php?id=' . $requestId);
    }
    $errors[$result['field'] ?? '_form'] = $result['error'];
}

// Read again so the page always shows the newest status and history.
$request = find_request_for_staff($requestId);
$history = get_request_history($requestId);
$answers = decode_request_answers($request['request_data']);
$status  = $request['status'];
$choices = staff_action_choices($status);
$chosen  = is_post() ? post_text('action') : '';
if (!in_array($chosen, array_column($choices, 'action'), true)) {
    $chosen = $choices[0]['action'];
}

page_start($request['reference_number'], ['area' => 'staff', 'active' => 'requests']);
echo page_header('Request ' . $request['reference_number'], '', '', [
    ['Dashboard', 'staff/dashboard.php'], ['Requests', 'staff/requests.php'], [$request['reference_number'], null],
]);
?>
<?= reference_stub($request) ?>

<div class="request-layout staff-request-layout mt-3">
  <div>
    <section class="panel" aria-labelledby="info-title">
      <h2 id="info-title" class="panel-title">Information submitted by the resident</h2>
      <dl class="detail-list">
        <div class="detail-row"><dt>Service</dt><dd><?= e($request['service_type']) ?></dd></div>
        <div class="detail-row"><dt>Purpose</dt><dd><?= nl2br(e($request['purpose'])) ?></dd></div>
        <div class="detail-row"><dt>Full name on request</dt><dd><?= e($request['applicant_name']) ?></dd></div>
        <div class="detail-row"><dt>Contact number</dt><dd><?= e($request['applicant_contact']) ?></dd></div>
        <div class="detail-row"><dt>Home address</dt><dd><?= e($request['applicant_address']) ?></dd></div>
        <div class="detail-row"><dt>Date submitted</dt><dd><?= e(format_datetime($request['submitted_at'])) ?></dd></div>
      </dl>
      <?php if ($answers): ?>
        <h3 class="form-section-title">Service details</h3>
        <?= answers_list($answers) ?>
      <?php endif; ?>
      <h3 class="form-section-title">Resident account</h3>
      <dl class="detail-list">
        <div class="detail-row"><dt>Account name</dt><dd><?= e($request['account_name']) ?></dd></div>
        <div class="detail-row"><dt>Email address</dt><dd><?= e($request['account_email']) ?></dd></div>
      </dl>
    </section>

    <section class="panel" aria-labelledby="history-title">
      <div class="panel-title-row">
        <h2 id="history-title" class="panel-title">History</h2>
        <a href="<?= e(url('staff/history.php') . '?id=' . $requestId) ?>">Full history page</a>
      </div>
      <?= history_table($history, true) ?>
    </section>
  </div>

  <div class="request-side">
    <section class="panel" aria-labelledby="update-title">
      <h2 id="update-title" class="panel-title">Update this request</h2>
      <p>Current status: <?= status_badge($status) ?></p>

      <?= error_summary($errors) ?>

      <form method="post" action="<?= e(url('staff/request.php') . '?id=' . $requestId) ?>" novalidate
            data-confirm="Save this update? The resident will be able to see the new status and the remark." data-confirm-button="Yes, save update">
        <?= csrf_field() ?>
        <input type="hidden" name="expected_status" value="<?= e($status) ?>">

        <fieldset id="field-action" class="mb-3">
          <legend class="form-label">What do you want to do?</legend>
          <?php foreach ($choices as $choice): ?>
            <label class="action-choice">
              <input class="form-check-input" type="radio" name="action" value="<?= e($choice['action']) ?>"<?= $choice['action'] === $chosen ? ' checked' : '' ?>>
              <?php if ($choice['action'] === 'advance'): ?>
                <strong>Move forward to <?= status_badge($choice['target']) ?></strong>
                <small>The normal next step.</small>
              <?php elseif ($choice['action'] === 'remark'): ?>
                <strong>Add a remark only</strong>
                <small>The status stays "<?= e($status) ?>". A remark is required.</small>
              <?php else: ?>
                <strong>Correct a mistake: go back to <?= status_badge($choice['target']) ?></strong>
                <small>Use this only if the status was changed by mistake. A remark is required and the history marks it as a correction.</small>
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <?php $next = next_status($status); ?>
        <?= form_textarea('remark', 'Processing remark', $errors, [
            'value'     => is_post() ? old('remark') : '',
            'rows'      => 4,
            'maxlength' => 500,
            'help'      => 'The resident can read this remark.'
                . ($next !== null ? ' If you move forward and leave this empty, we save: "' . suggested_remark($next) . '"' : ''),
        ]) ?>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-block-mobile">Save update</button>
        </div>
      </form>

      <?php if ($next === null): ?>
        <p class="final-note mt-3 mb-0">This request is at the last step. You can still add a remark or correct a mistake.</p>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php
page_end();
