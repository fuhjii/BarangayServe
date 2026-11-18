<?php
/**
 * staff/field_edit.php: add a requirement to a service (?service=) or edit one (?service=&id=).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff();

$serviceId = get_int('service');
$service   = $serviceId > 0 ? get_service($serviceId) : null;
if ($service === null) {
    abort_not_found('We could not find that service.', 'staff/services.php', 'Go to services');
}

$fieldId = get_int('id');
$field   = null;
if ($fieldId > 0) {
    $field = get_service_field($fieldId, $serviceId);
    if ($field === null) {
        abort_not_found('We could not find that requirement.', 'staff/service_fields.php?service=' . $serviceId, 'Back to requirements');
    }
}
$isNew  = ($field === null);
$errors = [];

if (is_post()) {
    csrf_require();
    [$errors, $data] = validate_field_form($_POST);
    if (!$errors) {
        save_service_field($serviceId, $isNew ? 0 : (int) $field['id'], $data);
        flash('success', 'The requirement was saved.');
        redirect('staff/service_fields.php?service=' . $serviceId);
    }
}

$posted = is_post();
$val = function (string $key, string $fallback) use ($posted, $field) {
    if ($posted) {
        return old($key);
    }
    return $field !== null && $field[$key] !== null ? (string) $field[$key] : $fallback;
};
$required = $posted ? !empty($_POST['is_required']) : ($isNew ? true : (int) $field['is_required'] === 1);

page_start($isNew ? 'Add a requirement' : 'Edit requirement', ['area' => 'staff', 'active' => 'services']);
echo page_header($isNew ? 'Add a requirement' : 'Edit requirement', 'For the service: ' . $service['name'], '', [
    ['Dashboard', 'staff/dashboard.php'], ['Services', 'staff/services.php'],
    ['Requirements', 'staff/service_fields.php?service=' . $serviceId], [$isNew ? 'Add' : 'Edit', null],
]);
?>
<section class="panel" aria-labelledby="form-title">
  <h2 id="form-title" class="visually-hidden">Requirement form</h2>
  <?= error_summary($errors) ?>
  <form method="post" action="<?= e(url('staff/field_edit.php') . '?service=' . $serviceId . ($isNew ? '' : '&id=' . (int) $field['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <?= form_input('label', 'Requirement', $errors, [
        'value' => $val('label', ''), 'required' => true, 'maxlength' => 100,
        'help' => 'The text residents see, for example "Civil status".',
    ]) ?>
    <?= form_select('field_type', 'Kind of answer', $errors, FIELD_TYPES, [
        'value' => $val('field_type', 'text'), 'required' => true,
    ]) ?>
    <?= form_textarea('options', 'Choices', $errors, [
        'value' => $val('options', ''), 'rows' => 4, 'maxlength' => 2000,
        'help' => 'Only for "Choose one from a list": write one choice per line.',
    ]) ?>
    <div class="row g-3">
      <div class="col-sm-6">
        <?= form_input('min_value', 'Smallest number', $errors, ['type' => 'number', 'value' => $val('min_value', ''), 'inputmode' => 'numeric', 'help' => 'Only for "Number". Leave empty for 0.']) ?>
      </div>
      <div class="col-sm-6">
        <?= form_input('max_value', 'Largest number', $errors, ['type' => 'number', 'value' => $val('max_value', ''), 'inputmode' => 'numeric', 'help' => 'Only for "Number". Leave empty for no limit.']) ?>
      </div>
    </div>
    <?= form_input('help_text', 'Hint for residents', $errors, ['value' => $val('help_text', ''), 'maxlength' => 200, 'help' => 'Optional short text shown under the requirement.']) ?>
    <?= form_input('sort_order', 'Display order', $errors, [
        'type' => 'number', 'value' => $val('sort_order', '10'), 'required' => true, 'min' => '0', 'max' => '9999', 'inputmode' => 'numeric',
        'help' => 'Smaller numbers are shown first.',
    ]) ?>
    <?= form_checkbox('is_required', 'Residents must fill this in', $required) ?>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary btn-block-mobile">Save requirement</button>
      <a class="btn btn-outline-secondary btn-block-mobile" href="<?= e(url('staff/service_fields.php') . '?service=' . $serviceId) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php
page_end();
