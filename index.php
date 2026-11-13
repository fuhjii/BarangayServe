<?php
/**
 * index.php: the public home page.
 * A logged-in visitor is sent straight to their own dashboard.
 */
require_once __DIR__ . '/includes/bootstrap.php';
redirect_if_logged_in();

page_start('', ['area' => 'public', 'active' => 'home']);
?>
<section class="home-hero" aria-labelledby="home-title">
  <h1 id="home-title">Request barangay services online. Follow every step.</h1>
  <p class="lead">
    Send your request for a barangay clearance or certificate from your phone or computer,
    get a reference number, and check its progress without going to the barangay hall just to ask.
  </p>
  <div class="home-actions">
    <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>">Create a resident account</a>
    <a class="btn btn-outline-primary btn-lg" href="<?= e(url('login.php')) ?>">Log in</a>
  </div>
</section>

<section class="mt-4" aria-labelledby="how-title">
  <h2 id="how-title" class="mb-3">The five steps of every request</h2>
  <ol class="how-list">
    <?php foreach (REQUEST_STATUSES as $i => $status): $d = STATUS_DETAILS[$status]; ?>
      <li class="how-<?= e($d['slug']) ?>">
        <span class="how-title"><?= ($i + 1) . '. ' . e($status) ?></span>
        <p><?= e($d['resident_text']) ?></p>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

<div class="home-split">
  <section class="panel" aria-labelledby="residents-title">
    <h2 id="residents-title" class="panel-title">For residents</h2>
    <ul>
      <li>Create an account and log in.</li>
      <li>Choose a service and fill in the request form.</li>
      <li>Keep your reference number, for example <strong>BR-<?= e(date('Y')) ?>-00125</strong>.</li>
      <li>Check the status and the history of your request at any time.</li>
      <li>See when your document is ready for pickup.</li>
    </ul>
  </section>
  <section class="panel" aria-labelledby="staff-title">
    <h2 id="staff-title" class="panel-title">For barangay staff</h2>
    <ul>
      <li>See every submitted request in one list.</li>
      <li>Search by reference number, name, service, status, or date.</li>
      <li>Review the information the resident submitted.</li>
      <li>Update the status and write remarks that residents can read.</li>
    </ul>
    <p class="mt-3 mb-0"><a href="<?= e(url('staff/login.php')) ?>">Barangay staff log in</a></p>
  </section>
</div>

<p class="mt-4 text-body-secondary">
  BarangayServe only records and tracks requests. Barangay staff still review each request, decide on it,
  prepare the document, and hand it over at the barangay hall.
</p>
<?php
page_end();
