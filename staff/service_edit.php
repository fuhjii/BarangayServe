<?php
/**
 * staff/service_edit.php: add a new service (no id) or edit one (?id=).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff();

$id      = get_int('id');
$service = null;
if ($id > 0) {
    $service = get_service($id);
    if ($service === null) {
        abort_not_found('We could not find that service.', 'staff/services.php', 'Go to services');
    }
}
$isNew  = ($service === null);
$errors = [];

if (is_post()) {
    csrf_require();
    [$errors, $data] = validate_service_form($_POST, $isNew ? 0 : (int) $service['id']);
    if (!$errors) {
        try {
            $savedId = save_service($isNew ? 0 : (int) $service['id'], $data);
        } catch (PDOException $e) {
            if (!is_duplicate_key_error($e)) {
                throw $e;
            }
            $errors['name'] = 'Another service already uses this name.';
        }
        if (!$errors) {
            if ($isNew) {
                flash('success', 'The service was added. Now add the requirements residents must provide for it (optional).');
                redirect('staff/service_fields.php?service=' . $savedId);
            }
            flash('success', 'The service was saved.');
            redirect('staff/services.php');
        }
    }
}

$posted = is_post();
$val = function (string $key, string $fallback) use ($posted, $service) {
    return $posted ? old($key) : ($service !== null ? (string) $service[$key] : $fallback);
};
$isActive = $posted ? !empty($_POST['is_active']) : ($isNew ? true : (int) $service['is_active'] === 1);

page_start($isNew ? 'Add a service' : 'Edit service', ['area' => 'staff', 'active' => 'services']);
echo page_header($isNew ? 'Add a service' : 'Edit service', '', '', [
    ['Dashboard', 'staff/dashboard.php'], ['Services', 'staff/services.php'], [$isNew ? 'Add a service' : $service['name'], null],
]);
?>
<section class="panel" aria-labelledby="form-title">
  <h2 id="form-title" class="visually-hidden">Service form</h2>
  <?= error_summary($errors) ?>
  <form method="post" action="<?= e(url('staff/service_edit.php') . ($isNew ? '' : '?id=' . (int) $service['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <?= form_input('name', 'Service name', $errors, ['value' => $val('name', ''), 'required' => true, 'maxlength' => 100]) ?>
    <?= form_textarea('description', 'Description', $errors, [
        'value' => $val('description', ''), 'rows' => 3, 'maxlength' => 255,
        'help' => 'One or two sentences that tell residents what this service is for.',
    ]) ?>
    <?= form_input('sort_order', 'Display order', $errors, [
        'type' => 'number', 'value' => $val('sort_order', '100'), 'required' => true, 'min' => '0', 'max' => '9999', 'inputmode' => 'numeric',
        'help' => 'Smaller numbers are shown first.',
    ]) ?>
    <?= form_checkbox('is_active', 'Open for requests', $isActive, 'Turn this off to hide the service from residents. Old requests are not affected.') ?>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary btn-block-mobile">Save service</button>
      <a class="btn btn-outline-secondary btn-block-mobile" href="<?= e(url('staff/services.php')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php
page_end();
