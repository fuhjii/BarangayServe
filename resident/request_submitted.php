<?php
/**
 * resident/request_submitted.php: the confirmation page with the reference number (FR-04, FR-05).
 * Only the owner of the request can open it.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_resident();

$request = find_request_for_resident(get_int('id'), (int) $user['id']);
if ($request === null) {
    abort_not_found('We could not find that request in your account.', 'resident/requests.php', 'Go to my requests');
}

page_start('Request submitted', ['area' => 'resident', 'active' => 'requests']);
echo page_header('Your request was submitted', 'Please keep your reference number. You need it when you ask the barangay about this request.');
?>
<?= reference_stub($request, true) ?>

<section class="panel" aria-labelledby="next-title">
  <h2 id="next-title" class="panel-title">What happens next</h2>
  <ol class="next-steps">
    <li>Barangay staff will check your request. The status will change to <strong>Under Review</strong>.</li>
    <li>Staff prepare your document or service (<strong>Processing</strong>).</li>
    <li>When it is <strong>Ready for Pickup</strong>, go to <?= e((string) config('office_location', 'the barangay hall')) ?> and show your reference number.</li>
    <li>You can log in at any time to see the status and history of this request.</li>
  </ol>
  <div class="form-actions">
    <a class="btn btn-primary" href="<?= e(url('resident/request.php') . '?id=' . (int) $request['id']) ?>">View request details</a>
    <a class="btn btn-outline-primary" href="<?= e(url('resident/requests.php')) ?>">My requests</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('resident/services.php')) ?>">Request another service</a>
  </div>
</section>
<?php
page_end();
