<?php
/**
 * staff/history.php: the complete history of one request, including who made each change.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff();

$requestId = get_int('id');
$request   = $requestId > 0 ? find_request_for_staff($requestId) : null;
if ($request === null) {
    abort_not_found('We could not find that request.', 'staff/requests.php', 'Go to the request list');
}
$history = get_request_history($requestId);

page_start('History of ' . $request['reference_number'], ['area' => 'staff', 'active' => 'requests']);
echo page_header('Request history', 'Every status change and remark, oldest first. History lines are never changed or deleted.',
    '<a class="btn btn-outline-primary" href="' . e(url('staff/request.php') . '?id=' . $requestId) . '">Back to the request</a>',
    [['Dashboard', 'staff/dashboard.php'], ['Requests', 'staff/requests.php'],
     [$request['reference_number'], 'staff/request.php?id=' . $requestId], ['History', null]]);
?>
<?= reference_stub($request) ?>
<section class="panel panel-flush" aria-labelledby="history-title">
  <div class="panel-head"><h2 id="history-title" class="panel-title mb-0">History</h2></div>
  <div class="p-3"><?= history_table($history, true) ?></div>
</section>
<?php
page_end();
