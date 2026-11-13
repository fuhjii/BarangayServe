<?php
/**
 * staff/login.php: log-in page for authorized barangay staff (SRS FR-02, 6.4).
 * A resident account cannot log in here. Staff accounts are NOT created on any public
 * page: the barangay office creates them with tools/create_staff.php.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_logged_in();

$errors = [];

if (is_post()) {
    csrf_require();

    $email    = post_text('email');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $result = attempt_login($email, $password);
    if ($result['user'] !== null && $result['user']['role'] !== 'staff') {
        // Correct password, but not a staff account: do NOT start a session.
        $errors['_form'] = 'This page is only for authorized barangay staff. Residents, please use the resident log in page.';
    } elseif ($result['user'] !== null) {
        login_session_start($result['user']);
        redirect_after_login($result['user']);
    } else {
        $errors['_form'] = $result['error'];
    }
}

page_start('Staff log in', ['area' => 'public']);
?>
<div class="auth-layout">
  <section class="panel" aria-labelledby="login-title">
    <h1 id="login-title" class="h2 mb-3">Barangay staff log in</h1>
    <?= error_summary($errors) ?>
    <form method="post" action="<?= e(url('staff/login.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <?= form_input('email', 'Staff email address', [], [
          'type' => 'email', 'value' => old('email'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'username',
      ]) ?>
      <?= form_input('password', 'Password', [], [
          'type' => 'password', 'required' => true, 'maxlength' => 200, 'autocomplete' => 'current-password', 'toggle' => true,
      ]) ?>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-block-mobile">Log in</button>
      </div>
    </form>
    <p class="auth-switch">Are you a resident? <a href="<?= e(url('login.php')) ?>">Go to the resident log in</a></p>
  </section>

  <aside class="auth-aside">
    <div class="staff-notice">
      <strong>Authorized personnel only.</strong>
      <p class="mb-0">Staff accounts are created by the barangay office. If you need an account or forgot your password, please ask the barangay administrator.</p>
    </div>
  </aside>
</div>
<?php
page_end();
