<?php
/**
 * resident/dashboard.php: a summary of the logged-in resident's OWN requests.
 * Every number is counted from the database with "WHERE user_id = <this resident>".
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_resident();
$uid  = (int) $user['id'];

$counts = count_requests_by_status($uid);
$recent = list_resident_requests($uid, '', 1, 5)['rows'];
$ready  = $counts['Ready for Pickup'] > 0 ? list_resident_requests($uid, 'Ready for Pickup', 1, 5)['rows'] : [];
$focus  = $ready[0] ?? ($recent[0] ?? null);
$focusTitle = $ready ? 'Ready to collect' : 'Latest request';

page_start('Dashboard', ['area' => 'resident', 'active' => 'dashboard']);
echo page_header(
    'Hello, ' . explode(' ', trim((string) $user['full_name']))[0],
    '',
    '<a class="btn btn-primary" href="' . e(url('resident/services.php')) . '">Request a service</a>'
);
?>

<?php if ($ready): ?>
  <div class="pickup-notice" role="status">
    <div>
      <?php if (count($ready) === 1): ?>
        <strong><?= e($ready[0]['reference_number']) ?> is ready for pickup</strong>
      <?php else: ?>
        <strong><?= count($ready) ?> requests are ready for pickup</strong>
      <?php endif; ?>
      <p>Bring your reference number to <?= e((string) config('office_location', 'the barangay hall')) ?><?php
          $hours = trim((string) config('office_hours', ''));
          echo $hours !== '' ? ' (' . e($hours) . ')' : '';
      ?>.</p>
      <?php if (count($ready) > 1): ?>
        <ul class="mb-0">
          <?php foreach ($ready as $row): ?>
            <li><a href="<?= e(url('resident/request.php') . '?id=' . (int) $row['id']) ?>"><strong><?= e($row['reference_number']) ?></strong></a><span class="pickup-service"><?= e($row['service_type']) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<section class="resident-focus" aria-labelledby="focus-title">
  <?php if ($focus): ?>
    <div class="focus-main">
      <h2 id="focus-title"><?= e($focusTitle) ?></h2>
      <p class="focus-reference"><?= e($focus['reference_number']) ?></p>
      <p class="focus-service"><?= e($focus['service_type']) ?></p>
      <p class="focus-date"><?= e(format_datetime($focus['submitted_at'])) ?></p>
    </div>
    <div class="focus-progress">
      <div class="focus-status-line"><?= status_badge($focus['status']) ?></div>
      <p><?= e(STATUS_DETAILS[$focus['status']]['resident_text'] ?? 'Barangay staff are working on your request.') ?></p>
      <?= status_tracker($focus['status']) ?>
      <a class="text-link" href="<?= e(url('resident/request.php') . '?id=' . (int) $focus['id']) ?>">View details</a>
    </div>
  <?php else: ?>
    <div class="focus-empty">
      <h2 id="focus-title">No requests yet</h2>
      <p>Pick a service to get started.</p>
      <a class="btn btn-primary" href="<?= e(url('resident/services.php')) ?>">Request a service</a>
    </div>
  <?php endif; ?>
</section>

<?php if ($counts['total'] > 0): ?>
<section class="status-overview" aria-labelledby="summary-title">
  <div class="section-heading">
    <h2 id="summary-title">By status</h2>
  </div>
  <?= status_pipeline($counts, 'resident/requests.php') ?>
</section>
<?php endif; ?>

<section class="request-list-section" aria-labelledby="recent-title">
  <div class="section-heading">
    <h2 id="recent-title">Recent</h2>
    <?php if ($counts['total'] > 0): ?>
      <a class="text-link" href="<?= e(url('resident/requests.php')) ?>">See all</a>
    <?php endif; ?>
  </div>
  <?php if ($recent): ?>
    <?= resident_requests_table($recent) ?>
  <?php else: ?>
    <?= empty_state('', 'Nothing here yet', 'Your requests will show up here.', 'resident/services.php', 'Request a service') ?>
  <?php endif; ?>
</section>
<?php
page_end();
