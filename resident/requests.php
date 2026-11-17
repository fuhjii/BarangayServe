<?php
/**
 * resident/requests.php: "My requests". Lists ONLY the logged-in resident's requests (FR-07).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_resident();
$uid  = (int) $user['id'];

$status  = get_text('status');
$status  = is_valid_status($status) ? $status : '';
$perPage = max(5, (int) config('per_page', 10));
$result  = list_resident_requests($uid, $status, max(1, get_int('page')), $perPage);
$rows    = $result['rows'];
$total   = $result['total'];
$page    = $result['page'];

$allCount = $status === '' ? $total : count_requests_by_status($uid)['total'];

page_start('My requests', ['area' => 'resident', 'active' => 'requests']);
echo page_header('My requests', '',
    '<a class="btn btn-primary" href="' . e(url('resident/services.php')) . '">Request a service</a>',
    [['Dashboard', 'resident/dashboard.php'], ['My requests', null]]);
?>
<section class="panel panel-flush" aria-label="My requests">
  <?php if ($allCount > 0): ?>
    <form class="filter-bar filter-bar-narrow" method="get" action="<?= e(url('resident/requests.php')) ?>">
      <div>
        <label class="form-label" for="filter-status">Show</label>
        <select class="form-select" id="filter-status" name="status" data-auto-submit>
          <option value="">All statuses</option>
          <?php foreach (REQUEST_STATUSES as $s): ?>
            <option value="<?= e($s) ?>"<?= $s === $status ? ' selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($status !== ''): ?><a class="btn btn-outline-secondary" href="<?= e(url('resident/requests.php')) ?>">Clear</a><?php endif; ?>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($rows): ?>
    <?= resident_requests_table($rows) ?>
    <div class="table-footer">
      <span><?= e(result_range_text($page, $perPage, $total)) ?></span>
      <?= pagination_links($page, $perPage, $total, 'resident/requests.php', $status !== '' ? ['status' => $status] : []) ?>
    </div>
  <?php elseif ($allCount > 0): ?>
    <?= empty_state('', 'No matches', 'Nothing is "' . $status . '" right now.', 'resident/requests.php', 'Show all requests') ?>
  <?php else: ?>
    <?= empty_state('', 'Nothing here yet', 'Your requests will show up here.', 'resident/services.php', 'Request a service') ?>
  <?php endif; ?>
</section>
<?php
page_end();
