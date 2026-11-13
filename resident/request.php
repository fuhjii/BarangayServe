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

$history = get_request_history((int) $request['id']);
$answers = decode_request_answers($request['request_data']);
$status  = $request['status'];

// The latest remark staff wrote (shown next to the current status).
$latestRemark = null;
for ($i = count($history) - 1; $i >= 0; $i--) {
    if ($history[$i]['remark'] !== null && $history[$i]['remark'] !== '') {
        $latestRemark = $history[$i];
        break;
    }
}

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
      <p>Your document or service is ready. Please go to <?= e((string) config('office_location', 'the barangay hall')) ?>
         and show your reference number <span class="fw-bold"><?= e($request['reference_number']) ?></span>.
         <?php $hours = trim((string) config('office_hours', '')); echo $hours !== '' ? ' Office hours: ' . e($hours) . '.' : ''; ?></p>
    </div>
  </div>
<?php endif; ?>

<section class="panel" aria-labelledby="progress-title">
  <h2 id="progress-title" class="panel-title">Progress</h2>
  <?= status_tracker($status) ?>
  <p class="mt-3 mb-0"><strong><?= e($status) ?>:</strong> <?= e(STATUS_DETAILS[$status]['resident_text'] ?? '') ?></p>
  <?php if ($latestRemark !== null): ?>
    <p class="mt-2 mb-0"><strong>Latest remark from the barangay</strong> (<?= e(format_datetime($latestRemark['created_at'])) ?>):<br>
      <?= nl2br(e($latestRemark['remark'])) ?></p>
  <?php endif; ?>
</section>

<div class="request-layout request-layout-stack mt-3">
  <section class="panel" aria-labelledby="info-title">
    <h2 id="info-title" class="panel-title">What you submitted</h2>
    <dl class="detail-list">
      <div class="detail-row"><dt>Service</dt><dd><?= e($request['service_type']) ?></dd></div>
      <div class="detail-row"><dt>Purpose</dt><dd><?= nl2br(e($request['purpose'])) ?></dd></div>
      <div class="detail-row"><dt>Full name</dt><dd><?= e($request['applicant_name']) ?></dd></div>
      <div class="detail-row"><dt>Contact number</dt><dd><?= e($request['applicant_contact']) ?></dd></div>
      <div class="detail-row"><dt>Home address</dt><dd><?= e($request['applicant_address']) ?></dd></div>
    </dl>
    <?php if ($answers): ?>
      <h3 class="form-section-title">Service details</h3>
      <?= answers_list($answers) ?>
    <?php endif; ?>
  </section>

  <section class="panel" aria-labelledby="history-title">
    <div class="panel-title-row">
      <h2 id="history-title" class="panel-title">History</h2>
      <a href="<?= e(url('resident/history.php') . '?id=' . (int) $request['id']) ?>">Full history page</a>
    </div>
    <?= history_table($history, false) ?>
    <p class="mt-3 mb-0 text-body-secondary">Last updated <?= e(format_datetime($request['updated_at'])) ?>.</p>
  </section>
</div>
<?php
page_end();
