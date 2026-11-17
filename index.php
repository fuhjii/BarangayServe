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
  <h1 id="home-title">Barangay requests, tracked from your phone.</h1>
  <p class="lead">Send a request, get a reference number, and see each step as staff process it.</p>
  <div class="home-actions">
    <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>">Create an account</a>
    <a class="btn btn-outline-primary btn-lg" href="<?= e(url('login.php')) ?>">Log in</a>
  </div>
</section>

<section class="home-steps" aria-labelledby="how-title">
  <h2 id="how-title" class="visually-hidden">How a request moves</h2>
  <ol class="how-list">
    <?php foreach (REQUEST_STATUSES as $i => $status): $d = STATUS_DETAILS[$status]; ?>
      <li class="how-<?= e($d['slug']) ?>">
        <span class="how-title"><?= e($status) ?></span>
        <p><?= e($d['resident_text']) ?></p>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

<p class="home-note">
  BarangayServe records and tracks requests. Barangay staff review each one and hand over documents at the barangay hall.
  <a href="<?= e(url('staff/login.php')) ?>">Staff log in</a>
</p>
<?php
page_end();
