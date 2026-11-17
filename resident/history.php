<?php
/**
 * resident/history.php: the full chronological history of one request (FR-08).
 * Same ownership rule as request.php.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_resident();

$request = find_request_for_resident(get_int('id'), (int) $user['id']);
if ($request === null) {
    abort_not_found('We could not find that request in your account.', 'resident/requests.php', 'Go to my requests');
}
$history = get_request_history((int) $request['id']);

page_start('History of ' . $request['reference_number'], ['area' => 'resident', 'active' => 'requests']);
echo page_header('History', '', '', [['Dashboard', 'resident/dashboard.php'], ['My requests', 'resident/requests.php'],
     [$request['reference_number'], 'resident/request.php?id=' . (int) $request['id']], ['History', null]]);
?>
<?= reference_stub($request) ?>
<section class="panel panel-flush" aria-labelledby="history-title">
  <div class="panel-head"><h2 id="history-title" class="panel-title mb-0">History</h2></div>
  <div class="p-3"><?= history_table($history, false) ?></div>
</section>
<?php
page_end();
