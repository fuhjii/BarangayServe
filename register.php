<?php
/**
 * register.php: create a RESIDENT account (SRS FR-01).
 *
 * SECURITY NOTE: the role is never read from the form. This page always creates
 * a 'resident'. Staff accounts can only be created with tools/create_staff.php.
 */
require_once __DIR__ . '/includes/bootstrap.php';
redirect_if_logged_in();

$errors = [];

if (is_post()) {
    csrf_require();

    $fullName = post_text('full_name');
    $email    = normalize_email(post_text('email'));
    $contact  = post_text('contact_number');
    $address  = post_text('address');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    // 1. Validate every field on the server.
    foreach ([
        'full_name'      => validate_full_name($fullName),
        'email'          => validate_email($email),
        'contact_number' => validate_contact_number($contact),
        'address'        => validate_address($address),
        'password'       => validate_password($password),
    ] as $field => $problem) {
        if ($problem !== null) {
            $errors[$field] = $problem;
        }
    }
    if (!isset($errors['password']) && $password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords are not the same. Please type the password again.';
    }
    if (!isset($errors['email']) && email_is_taken($email)) {
        $errors['email'] = 'An account with this email address already exists. Please log in, or use another email address.';
    }

    // 2. Save the account. The role is fixed to 'resident' here.
    if (!$errors) {
        try {
            create_user($fullName, $email, $contact, $address, $password, 'resident');
            flash('success', 'Your account was created. You can now log in with your email address and password.');
            redirect('login.php');
        } catch (PDOException $e) {
            if (is_duplicate_key_error($e)) {
                // Someone registered the same email at the very same moment (the UNIQUE key caught it).
                $errors['email'] = 'An account with this email address already exists. Please log in, or use another email address.';
            } else {
                throw $e;
            }
        }
    }
}

page_start('Create an account', ['area' => 'public']);
?>
<div class="auth-layout auth-wide">
  <section class="panel" aria-labelledby="register-title">
    <h1 id="register-title" class="h2 mb-3">Create a resident account</h1>

    <?= error_summary($errors) ?>

    <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <?= form_input('full_name', 'Full name', $errors, [
          'value' => old('full_name'), 'required' => true, 'maxlength' => 100, 'autocomplete' => 'name',
          'help' => 'As it appears on your ID.',
      ]) ?>
      <?= form_input('email', 'Email address', $errors, [
          'type' => 'email', 'value' => old('email'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'email',
          'help' => 'Used to log in.',
      ]) ?>
      <?= form_input('contact_number', 'Contact number', $errors, [
          'type' => 'tel', 'value' => old('contact_number'), 'required' => true, 'maxlength' => 20,
          'autocomplete' => 'tel', 'inputmode' => 'tel', 'placeholder' => '0917 123 4567',
      ]) ?>
      <?= form_input('address', 'Home address', $errors, [
          'value' => old('address'), 'required' => true, 'maxlength' => 255, 'autocomplete' => 'street-address',
          'help' => 'House number, street, purok or subdivision.',
      ]) ?>
      <?= form_input('password', 'Password', $errors, [
          'type' => 'password', 'required' => true, 'maxlength' => 72, 'autocomplete' => 'new-password', 'toggle' => true,
          'help' => 'At least 8 characters, with a letter and a number.',
      ]) ?>
      <?= form_input('password_confirm', 'Type the password again', $errors, [
          'type' => 'password', 'required' => true, 'maxlength' => 72, 'autocomplete' => 'new-password',
      ]) ?>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-block-mobile">Create account</button>
      </div>
    </form>
    <p class="auth-switch">Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
  </section>

</div>
<?php
page_end();
