<?php
/**
 * staff/service_fields.php: the requirements a service asks for (list, delete).
 * Name, address, contact number, and purpose are asked for every service and are not listed here.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff();

$serviceId = get_int('service');
$service   = $serviceId > 0 ? get_service($serviceId) : null;
if ($service === null) {
    abort_not_found('We could not find that service.', 'staff/services.php', 'Go to services');
}

if (is_post()) {
    csrf_require();
    $fieldId = ctype_digit(post_text('field_id')) ? (int) post_text('field_id') : 0;
    $field   = $fieldId > 0 ? get_service_field($fieldId, $serviceId) : null;
    if ($field !== null) {
        delete_service_field($serviceId, $fieldId);
        flash('success', 'The requirement "' . $field['label'] . '" was removed. Requests that already used it keep their answers.');
    }
    redirect('staff/service_fields.php?service=' . $serviceId);
}

$fields = get_service_fields($serviceId);

page_start('Requirements: ' . $service['name'], ['area' => 'staff', 'active' => 'services']);
echo page_header($service['name'], 'What residents provide for this service.',
    '<a class="btn btn-primary" href="' . e(url('staff/field_edit.php') . '?service=' . $serviceId) . '">Add a requirement</a>',
    [['Dashboard', 'staff/dashboard.php'], ['Services', 'staff/services.php'], ['Requirements', null]]);
?>
<section class="panel panel-flush" aria-label="Requirements of <?= e($service['name']) ?>">
  <?php if ($fields): ?>
    <?php foreach ($fields as $f): ?>
      <div class="field-row">
        <div>
          <div class="field-label"><?= e($f['label']) ?></div>
          <div class="field-meta">
            <?= e(FIELD_TYPES[$f['field_type']] ?? $f['field_type']) ?> &middot;
            <?= (int) $f['is_required'] === 1 ? 'required' : 'optional' ?> &middot; order <?= (int) $f['sort_order'] ?>
            <?php if ($f['field_type'] === 'select'): ?> &middot; choices: <?= e(shorten(implode(', ', field_options($f)), 90)) ?><?php endif; ?>
          </div>
        </div>
        <div class="row-actions">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('staff/field_edit.php') . '?service=' . $serviceId . '&id=' . (int) $f['id']) ?>">Edit<span class="visually-hidden"> <?= e($f['label']) ?></span></a>
          <form method="post" action="<?= e(url('staff/service_fields.php') . '?service=' . $serviceId) ?>"
                <?= confirm_attr('Remove the requirement "' . $f['label'] . '"? Requests that already used it keep their answers.', 'Yes, remove it') ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="field_id" value="<?= (int) $f['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Remove<span class="visually-hidden"> <?= e($f['label']) ?></span></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <?= empty_state('', 'No requirements yet', 'Residents only give their name, address, contact number, and purpose.', 'staff/field_edit.php?service=' . $serviceId, 'Add a requirement') ?>
  <?php endif; ?>
</section>
<p class="mt-3"><a href="<?= e(url('staff/services.php')) ?>">Back to services</a></p>
<?php
page_end();
