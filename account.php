<?php
/**
 * account.php: view and update your own profile, and change your password.
 * Used by residents and staff. A user can only ever change their OWN account
 * (the id comes from the session, never from the form).
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login('resident');
$area = $user['role'] === 'staff' ? 'staff' : 'resident';

$profileErrors  = [];
$passwordErrors = [];

if (is_post()) {
    csrf_require();
    $form = post_text('form');

    if ($form === 'profile') {
        $fullName = post_text('full_name');
        $contact  = post_text('contact_number');
        $address  = post_text('address');
        foreach ([
            'full_name'      => validate_full_name($fullName),
            'contact_number' => validate_contact_number($contact),
            'address'        => validate_address($address),
        ] as $field => $problem) {
            if ($problem !== null) {
                $profileErrors[$field] = $problem;
            }
        }
        if (!$profileErrors) {
            update_profile((int) $user['id'], $fullName, $contact, $address);
            flash('success', 'Your account details were saved. Requests you already submitted keep the details you gave at that time.');
            redirect('account.php');
        }
    } elseif ($form === 'password') {
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new     = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm = is_string($_POST['new_password_confirm'] ?? null) ? $_POST['new_password_confirm'] : '';

        if ($current === '' || !current_password_is_correct((int) $user['id'], $current)) {
            $passwordErrors['current_password'] = 'Your current password is not correct.';
        }
        $problem = validate_password($new);
        if ($problem !== null) {
            $passwordErrors['new_password'] = $problem;
        } elseif ($new !== $confirm) {
            $passwordErrors['new_password_confirm'] = 'The two new passwords are not the same.';
        }
        if (!$passwordErrors) {
            change_password((int) $user['id'], $new);
            session_regenerate_id(true);               // a password change also renews the session id
            flash('success', 'Your password was changed.');
            redirect('account.php');
        }
    } else {
        redirect('account.php');
    }
}

$showProfilePost  = is_post() && post_text('form') === 'profile';
$showPasswordPost = is_post() && post_text('form') === 'password';

page_start('My account', ['area' => $area, 'active' => 'account']);
echo page_header('My account', '', '', [
    [$area === 'staff' ? 'Dashboard' : 'Dashboard', dashboard_path($user['role'])],
    ['My account', null],
]);
?>
<div class="row g-4">
  <div class="col-lg-6">
    <section class="panel h-100" aria-labelledby="profile-title">
      <h2 id="profile-title" class="panel-title">Your details</h2>
      <?= error_summary($profileErrors) ?>
      <form method="post" action="<?= e(url('account.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="profile">
        <div class="readonly-box">
          <strong>Email address</strong><br><?= e($user['email']) ?><br>
          <span class="text-body-secondary">Your email address is your login and cannot be changed here.</span>
        </div>
        <?= form_input('full_name', 'Full name', $profileErrors, [
            'value' => $showProfilePost ? old('full_name') : $user['full_name'], 'required' => true, 'maxlength' => 100, 'autocomplete' => 'name',
        ]) ?>
        <?= form_input('contact_number', 'Contact number', $profileErrors, [
            'type' => 'tel', 'value' => $showProfilePost ? old('contact_number') : $user['contact_number'],
            'required' => true, 'maxlength' => 11, 'autocomplete' => 'tel', 'inputmode' => 'numeric', 'pattern' => '[0-9]{11}',
            'placeholder' => '09171234567', 'help' => '11 digits, numbers only.',
        ]) ?>
        <?= form_input('address', 'Home address', $profileErrors, [
            'value' => $showProfilePost ? old('address') : $user['address'], 'required' => true, 'maxlength' => 255, 'autocomplete' => 'street-address',
        ]) ?>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-block-mobile">Save details</button>
        </div>
      </form>
    </section>
  </div>

  <div class="col-lg-6">
    <section class="panel h-100" aria-labelledby="password-title">
      <h2 id="password-title" class="panel-title">Change password</h2>
      <?= error_summary($passwordErrors) ?>
      <form method="post" action="<?= e(url('account.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="password">
        <?= form_input('current_password', 'Current password', $passwordErrors, [
            'type' => 'password', 'required' => true, 'maxlength' => 200, 'autocomplete' => 'current-password', 'toggle' => true,
        ]) ?>
        <?= form_input('new_password', 'New password', $passwordErrors, [
            'type' => 'password', 'required' => true, 'maxlength' => 72, 'autocomplete' => 'new-password',
            'help' => 'At least 8 characters, with at least one letter and one number.',
        ]) ?>
        <?= form_input('new_password_confirm', 'Type the new password again', $passwordErrors, [
            'type' => 'password', 'required' => true, 'maxlength' => 72, 'autocomplete' => 'new-password',
        ]) ?>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-block-mobile">Change password</button>
        </div>
      </form>
    </section>
  </div>
</div>
<?php
page_end();
