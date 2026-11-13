<?php
/**
 * login.php: log in with email and password (SRS FR-02).
 * Residents and staff may both use it; each is sent to their own dashboard.
 */
require_once __DIR__ . '/includes/bootstrap.php';
redirect_if_logged_in();

$errors = [];

if (is_post()) {
    csrf_require();

    $email    = post_text('email');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $result = attempt_login($email, $password);
    if ($result['user'] !== null) {
        login_session_start($result['user']);          // new session id (stops session fixation)
        redirect_after_login($result['user']);         // the right dashboard
    }
    // The message never says whether the email exists.
    $errors['_form'] = $result['error'];
}

page_start('Log in', ['area' => 'public']);
?>
<div class="auth-layout">
  <section class="panel" aria-labelledby="login-title">
    <h1 id="login-title" class="h2 mb-3">Log in</h1>

    <?= error_summary($errors) ?>

    <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <?= form_input('email', 'Email address', [], [
          'type' => 'email', 'value' => old('email'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'username',
      ]) ?>
      <?= form_input('password', 'Password', [], [
          'type' => 'password', 'required' => true, 'maxlength' => 200, 'autocomplete' => 'current-password', 'toggle' => true,
      ]) ?>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-block-mobile">Log in</button>
      </div>
    </form>
    <p class="auth-switch">New here? <a href="<?= e(url('register.php')) ?>">Create a resident account</a></p>
  </section>

  <aside class="auth-aside">
    <h2>Barangay staff?</h2>
    <p>Authorized staff use the <a href="<?= e(url('staff/login.php')) ?>">staff log in page</a>.
       Staff accounts are created by the barangay office. They cannot be created on this website.</p>
  </aside>
</div>
<?php
page_end();
