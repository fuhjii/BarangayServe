<?php
/**
 * resident/request_new.php: the request form for ONE service (SRS FR-03 and FR-04).
 *
 *   GET  ?service=ID  show the form (a one-time form token is created)
 *   POST ?service=ID  validate on the server, save request + first history line in ONE
 *                     transaction, then redirect to the confirmation page.
 *
 * Refresh / double click / Back button cannot create a second request:
 *   - the browser is redirected after saving (POST-redirect-GET), and
 *   - the form token is saved in a UNIQUE column, so the same form can only be saved once.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_resident();
$uid  = (int) $user['id'];

$serviceId = get_int('service');
$service   = $serviceId > 0 ? get_service($serviceId) : null;
if ($service === null || (int) $service['is_active'] !== 1) {
    flash('warning', 'That service is not available. Please choose one from the list.');
    redirect('resident/services.php');
}
$fields = get_service_fields((int) $service['id']);

$errors = [];
$token  = '';

if (is_post()) {
    csrf_require();

    $token = post_text('form_token');
    if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
        flash('warning', 'The form expired. Please fill it in again.');
        redirect('resident/request_new.php?service=' . (int) $service['id']);
    }

    // Was this very form already saved? (double click, Back button, slow connection)
    $existing = find_request_by_token($token, $uid);
    if ($existing !== null) {
        flash('info', 'This request was already submitted, so no second request was created.');
        redirect('resident/request_submitted.php?id=' . (int) $existing['id']);
    }
    if (!form_token_is_issued($token)) {
        flash('warning', 'The form expired. Please fill it in again.');
        redirect('resident/request_new.php?service=' . (int) $service['id']);
    }

    $applicant = [
        'name'    => post_text('applicant_name'),
        'contact' => post_text('applicant_contact'),
        'address' => post_text('applicant_address'),
    ];
    $purpose = post_text('purpose');

    foreach ([
        'applicant_name'    => validate_full_name($applicant['name']),
        'applicant_contact' => validate_contact_number($applicant['contact']),
        'applicant_address' => validate_address($applicant['address']),
        'purpose'           => validate_purpose($purpose),
    ] as $field => $problem) {
        if ($problem !== null) {
            $errors[$field] = $problem;
        }
    }

    [$answerErrors, $answers] = validate_service_answers($fields, $_POST);
    foreach ($answerErrors as $key => $message) {
        $errors['f_' . $key] = $message;            // same ids as the form inputs (f_<key>)
    }

    if (!$errors) {
        $result = create_service_request($uid, $service, $applicant, $purpose, $answers, $token);
        form_token_consume($token);
        flash($result['duplicate'] ? 'info' : 'success',
            $result['duplicate']
                ? 'This request was already submitted, so no second request was created.'
                : 'Your request was submitted.');
        redirect('resident/request_submitted.php?id=' . (int) $result['id']);
    }
}

if ($token === '' || !form_token_is_issued($token)) {
    $token = form_token_issue();                    // a fresh one-time token for a new form
}
$posted = is_post();

/** Print one service-specific question as the right kind of input. */
function render_service_field(array $field, array $errors, bool $posted): string
{
    $name = 'f_' . $field['field_key'];
    $opts = [
        'value'    => $posted ? old($name) : '',
        'required' => (int) $field['is_required'] === 1,
        'help'     => $field['help_text'] ?? null,
    ];
    switch ($field['field_type']) {
        case 'textarea':
            return form_textarea($name, $field['label'], $errors, $opts + ['rows' => 3, 'maxlength' => 500]);
        case 'number':
            $opts['type']      = 'number';
            $opts['inputmode'] = 'numeric';
            $opts['min']       = $field['min_value'] !== null ? (string) $field['min_value'] : '0';
            $opts['max']       = $field['max_value'] !== null ? (string) $field['max_value'] : null;
            return form_input($name, $field['label'], $errors, $opts);
        case 'date':
            $opts['type'] = 'date';
            $opts['max']  = date('Y-m-d');
            return form_input($name, $field['label'], $errors, $opts);
        case 'select':
            $choices = [];
            foreach (field_options($field) as $option) {
                $choices[$option] = $option;
            }
            return form_select($name, $field['label'], $errors, $choices, $opts + ['placeholder' => 'Choose one']);
        default:
            return form_input($name, $field['label'], $errors, $opts + ['maxlength' => 150]);
    }
}

page_start($service['name'], ['area' => 'resident', 'active' => 'services']);
echo page_header($service['name'], $service['description'], '', [
    ['Dashboard', 'resident/dashboard.php'],
    ['Request a service', 'resident/services.php'],
    [$service['name'], null],
]);
?>
<section class="panel service-request-form" aria-labelledby="form-title">
  <h2 id="form-title" class="visually-hidden">Request form: <?= e($service['name']) ?></h2>
  <?php if (!empty($service['pickup_requirements'])): ?>
    <p class="bring-note"><i class="bi bi-bag-check" aria-hidden="true"></i><span><strong>What to bring:</strong> <?= e($service['pickup_requirements']) ?></span></p>
  <?php endif; ?>
  <p class="text-body-secondary">Fields marked <span class="text-danger" aria-hidden="true">*</span> are required. Please check your answers before you send them. You cannot change a request after you submit it.</p>

  <?= error_summary($errors) ?>

  <form method="post" action="<?= e(url('resident/request_new.php') . '?service=' . (int) $service['id']) ?>" novalidate
        data-confirm="Send this request to the barangay now? You will not be able to edit it afterwards." data-confirm-button="Yes, submit request">
    <?= csrf_field() ?>
    <input type="hidden" name="form_token" value="<?= e($token) ?>">

    <h3 class="form-section-title">Your information</h3>
    <p class="form-section-note">We filled this in from your account. Change it if the document needs different details.</p>
    <?= form_input('applicant_name', 'Full name', $errors, [
        'value' => $posted ? old('applicant_name') : $user['full_name'], 'required' => true, 'maxlength' => 100, 'autocomplete' => 'name',
    ]) ?>
    <?= form_input('applicant_contact', 'Contact number', $errors, [
        'type' => 'tel', 'value' => $posted ? old('applicant_contact') : $user['contact_number'],
        'required' => true, 'maxlength' => 11, 'autocomplete' => 'tel', 'inputmode' => 'numeric', 'pattern' => '[0-9]{11}',
        'placeholder' => '09171234567', 'help' => '11 digits, numbers only.',
    ]) ?>
    <?= form_input('applicant_address', 'Home address', $errors, [
        'value' => $posted ? old('applicant_address') : $user['address'], 'required' => true, 'maxlength' => 255, 'autocomplete' => 'street-address',
    ]) ?>

    <h3 class="form-section-title">About this request</h3>
    <?= form_textarea('purpose', 'Purpose of request', $errors, [
        'value' => $posted ? old('purpose') : '', 'required' => true, 'rows' => 2, 'maxlength' => 255,
        'help' => 'Why do you need this? For example: job application, school enrollment, scholarship.',
    ]) ?>

    <?php if ($fields): ?>
      <h3 class="form-section-title">Details for <?= e($service['name']) ?></h3>
      <?php foreach ($fields as $field): ?>
        <?= render_service_field($field, $errors, $posted) ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary btn-block-mobile">Submit request</button>
      <a class="btn btn-outline-secondary btn-block-mobile" href="<?= e(url('resident/services.php')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php
page_end();
