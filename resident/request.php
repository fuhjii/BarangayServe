<?php
/**
 * resident/request.php: details of ONE request, with its progress (FR-07, FR-08).
 *
 * OWNERSHIP: find_request_for_resident() contains "AND user_id = <me>". If the id in the
 * address bar belongs to someone else, the result is exactly the same "not found" page as
 * for an id that does not exist, so nothing about other residents' requests is revealed.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_resident();

$request = find_request_for_resident(get_int('id'), (int) $user['id']);
if ($request === null) {
    abort_not_found('We could not find that request in your account.', 'resident/requests.php', 'Go to my requests');
}

if (is_post()) {
    csrf_require();
    if (post_text('action') === 'cancel') {
        $result = resident_cancel_request((int) $request['id'], (int) $user['id'], post_text('expected_status'), post_text('reason'));
        flash($result['ok'] ? 'success' : 'warning', $result['ok'] ? $result['message'] : $result['error']);
    }
    redirect('resident/request.php?id=' . (int) $request['id']);
}

$history = get_request_history((int) $request['id']);
$answers = decode_request_answers($request['request_data']);
$status  = $request['status'];

// The page shows the latest updates; the full list is one click away.
$historyLimit = 3;
$historyShown = array_slice($history, 0, $historyLimit);

page_start($request['reference_number'], ['area' => 'resident', 'active' => 'requests']);
echo page_header('Request details', '', '', [
    ['Dashboard', 'resident/dashboard.php'],
    ['My requests', 'resident/requests.php'],
    [$request['reference_number'], null],
]);
?>
<?= reference_stub($request, true) ?>

<?php if ($status === 'Ready for Pickup'): ?>
  <div class="pickup-notice mt-3" role="status">
    <div>
      <strong>Ready for pickup</strong>
      <p>Go to <?= e((string) config('office_location', 'the barangay hall')) ?> and show
         <span class="fw-bold"><?= e($request['reference_number']) ?></span>.
         <?php $hours = trim((string) config('office_hours', '')); echo $hours !== '' ? e($hours) . '.' : ''; ?></p>
      <?php $bringList = trim((string) ((get_service((int) $request['service_id']) ?? [])['pickup_requirements'] ?? '')); ?>
      <?php if ($bringList !== ''): ?><p><strong>Please bring:</strong> <?= e(rtrim($bringList, '.')) ?>.</p><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (is_closed_status($status)): ?>
  <section class="closed-notice closed-<?= e(STATUS_DETAILS[$status]['slug']) ?> mt-3" role="status" aria-label="<?= e($status) ?>">
    <strong><?= $status === 'Rejected' ? 'This request was rejected' : 'You cancelled this request' ?></strong>
    <p><?= $status === 'Cancelled' ? 'Nothing more is needed. You can send a new request any time.' : e(STATUS_DETAILS[$status]['resident_text']) ?></p>
    <?php if ($status === 'Rejected' && !empty($history[0]['remark'])): ?>
      <p class="closed-reason"><?= nl2br(e($history[0]['remark'])) ?></p>
    <?php endif; ?>
  </section>
<?php else: ?>
<section class="panel" aria-labelledby="progress-title">
  <h2 id="progress-title" class="panel-title">Progress</h2>
  <?= status_tracker($status) ?>
  <p class="progress-note"><?= e(STATUS_DETAILS[$status]['resident_text'] ?? '') ?></p>
</section>
<?php endif; ?>

<div class="request-layout request-layout-stack mt-3">
  <section class="panel" aria-labelledby="info-title">
    <h2 id="info-title" class="panel-title">Details</h2>
    <dl class="detail-list">
      <div class="detail-row"><dt>Service</dt><dd><?= e($request['service_type']) ?></dd></div>
      <div class="detail-row"><dt>Purpose</dt><dd><?= nl2br(e($request['purpose'])) ?></dd></div>
      <div class="detail-row"><dt>Full name</dt><dd><?= e($request['applicant_name']) ?></dd></div>
      <div class="detail-row"><dt>Contact number</dt><dd><?= e($request['applicant_contact']) ?></dd></div>
      <div class="detail-row"><dt>Home address</dt><dd><?= e($request['applicant_address']) ?></dd></div>
    </dl>
    <?php if ($answers): ?>
      <h3 class="form-section-title">More about your request</h3>
      <?= answers_list($answers) ?>
    <?php endif; ?>
  </section>

  <section class="panel" aria-labelledby="history-title">
    <div class="panel-title-row">
      <h2 id="history-title" class="panel-title">Updates</h2>
      <?php if (count($history) > $historyLimit): ?>
        <a class="text-link" href="<?= e(url('resident/history.php') . '?id=' . (int) $request['id']) ?>">See all <?= count($history) ?></a>
      <?php endif; ?>
    </div>
    <?= history_table($historyShown, false) ?>
  </section>
</div>

<?php if (resident_can_cancel($status)): ?>
  <details class="cancel-box mt-3">
    <summary><span class="cancel-trigger"><i class="bi bi-x-circle" aria-hidden="true"></i><span class="cancel-title">Cancel this request</span><i class="bi bi-chevron-down cancel-chevron" aria-hidden="true"></i></span><span class="cancel-hint">Possible until staff start processing.</span></summary>
    <form method="post" action="<?= e(url('resident/request.php') . '?id=' . (int) $request['id']) ?>"
          data-confirm="Cancel this request? This cannot be undone." data-confirm-button="Yes, cancel request">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" name="expected_status" value="<?= e($status) ?>">
      <?= form_textarea('reason', 'Reason', [], ['rows' => 3, 'maxlength' => 500]) ?>
      <button type="submit" class="btn btn-cancel">Cancel request</button>
    </form>
  </details>
<?php endif; ?>
<?php
page_end();
