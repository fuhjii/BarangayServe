<?php
/**
 * staff/requests.php: the list of ALL requests with search and filters (SRS FR-09).
 * Filters: reference number or resident name (q), service, status, submitted from / to.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff();

$filters = staff_filters_from_query();
$perPage = max(5, (int) config('per_page', 10));
$result  = list_staff_requests($filters, max(1, get_int('page')), $perPage);
$rows    = $result['rows'];
$total   = $result['total'];
$page    = $result['page'];

// The filters to keep when going to another page of results.
$keep = array_filter([
    'q'       => $filters['q'],
    'service' => $filters['service_id'] ?: '',
    'status'  => $filters['status'],
    'from'    => $filters['from'],
    'to'      => $filters['to'],
    'sort'    => $filters['sort'] === 'oldest' ? 'oldest' : '',
], function ($v) { return $v !== '' && $v !== null; });

$services = get_services_for_staff();

page_start('Requests', ['area' => 'staff', 'active' => 'requests']);
echo page_header('Requests', '', '', [
    ['Dashboard', 'staff/dashboard.php'], ['Requests', null],
]);
?>
<section class="panel panel-flush" aria-label="Request list">
  <form class="filter-bar" method="get" action="<?= e(url('staff/requests.php')) ?>">
    <div class="filter-search">
      <label class="form-label" for="f-q">Reference number or resident name</label>
      <input class="form-control" type="search" id="f-q" name="q" maxlength="100" value="<?= e($filters['q']) ?>" placeholder="BR-<?= e(date('Y')) ?>-00001 or Juan Dela Cruz">
    </div>
    <div>
      <label class="form-label" for="f-service">Service</label>
      <select class="form-select" id="f-service" name="service">
        <option value="">All services</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= (int) $s['id'] ?>"<?= (int) $s['id'] === (int) $filters['service_id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label" for="f-status">Status</label>
      <select class="form-select" id="f-status" name="status">
        <option value="">All statuses</option>
        <?php foreach (ALL_STATUSES as $s): ?>
          <option value="<?= e($s) ?>"<?= $s === $filters['status'] ? ' selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label" for="f-from">Submitted from</label>
      <input class="form-control" type="date" id="f-from" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div>
      <label class="form-label" for="f-to">Submitted until</label>
      <input class="form-control" type="date" id="f-to" name="to" value="<?= e($filters['to']) ?>">
    </div>
    <div class="filter-actions">
      <button type="submit" class="btn btn-primary">Search requests</button>
      <?php if ($keep): ?><a class="btn btn-outline-secondary" href="<?= e(url('staff/requests.php')) ?>">Clear</a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows): ?>
    <?= staff_requests_table($rows) ?>
    <div class="table-footer">
      <span><?= e(result_range_text($page, $perPage, $total)) ?>
        <?php if ($filters['sort'] === 'oldest'): ?>(oldest first)<?php else: ?>(newest first)<?php endif; ?>
        <a class="ms-2" href="<?= e(url('staff/requests.php') . '?' . http_build_query(array_merge($keep, ['sort' => $filters['sort'] === 'oldest' ? '' : 'oldest']))) ?>">
          Show <?= $filters['sort'] === 'oldest' ? 'newest' : 'oldest' ?> first</a></span>
      <?= pagination_links($page, $perPage, $total, 'staff/requests.php', $keep) ?>
    </div>
  <?php elseif ($keep): ?>
    <?= empty_state('', 'No requests match your search', 'Try a different reference number or name, or remove some filters.', 'staff/requests.php', 'Clear the search') ?>
  <?php else: ?>
    <?= empty_state('', 'No requests yet', 'Requests appear here as soon as a resident submits one.') ?>
  <?php endif; ?>
</section>
<?php
page_end();
