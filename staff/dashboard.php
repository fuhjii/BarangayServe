<?php
/**
 * staff/dashboard.php: request totals per status (SRS FR-10).
 * Every number is counted from the database each time the page loads.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff();

$counts  = count_requests_by_status(null);
$waiting = list_staff_requests(['status' => 'Submitted', 'sort' => 'oldest'], 1, 5)['rows'];
$latest  = list_staff_requests(['sort' => 'newest'], 1, 8)['rows'];

page_start('Staff dashboard', ['area' => 'staff', 'active' => 'dashboard']);
echo page_header('Staff dashboard', 'Requests from residents, counted by their current status.',
    '<a class="btn btn-primary" href="' . e(url('staff/requests.php')) . '">All requests</a>');
?>
<section class="panel panel-flush staff-queue" aria-labelledby="waiting-title">
  <div class="panel-head panel-title-row mb-0">
    <h2 id="waiting-title" class="panel-title">Waiting to be reviewed</h2>
    <?php if ($counts['Submitted'] > 5): ?>
      <a href="<?= e(url('staff/requests.php') . '?status=Submitted&sort=oldest') ?>">See all <?= (int) $counts['Submitted'] ?></a>
    <?php endif; ?>
  </div>
  <?php if ($waiting): ?>
    <?= staff_requests_table($waiting) ?>
  <?php else: ?>
    <?= empty_state('', 'Nothing is waiting', 'Every submitted request has already been picked up for review.') ?>
  <?php endif; ?>
</section>

<section class="panel staff-status-summary" aria-labelledby="summary-title">
  <div class="panel-title-row">
    <div>
      <h2 id="summary-title" class="panel-title">Request status</h2>
      <p class="section-intro"><strong><?= (int) $counts['total'] ?></strong> total <?= $counts['total'] === 1 ? 'request' : 'requests' ?></p>
    </div>
  </div>
  <?= status_pipeline($counts, 'staff/requests.php') ?>
</section>
<section class="panel panel-flush" aria-labelledby="latest-title">
  <div class="panel-head panel-title-row mb-0">
    <h2 id="latest-title" class="panel-title">Latest requests</h2>
    <?php if ($latest): ?><a href="<?= e(url('staff/requests.php')) ?>">See the full list</a><?php endif; ?>
  </div>
  <?php if ($latest): ?>
    <?= staff_requests_table($latest) ?>
  <?php else: ?>
    <?= empty_state('', 'No requests yet', 'Requests appear here as soon as a resident submits one.') ?>
  <?php endif; ?>
</section>
<?php
page_end();
