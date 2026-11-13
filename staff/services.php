<?php
/**
 * staff/services.php: staff manage the list of services residents can request (SRS 7.7).
 * Only staff can open this page; residents cannot add or change services.
 * Services are switched on or off, never deleted, so old requests keep their service.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff();

if (is_post()) {
    csrf_require();
    $serviceId = (int) (ctype_digit(post_text('service_id')) ? post_text('service_id') : 0);
    $service   = $serviceId > 0 ? get_service($serviceId) : null;
    if ($service !== null && in_array(post_text('active'), ['0', '1'], true)) {
        $makeActive = post_text('active') === '1';
        set_service_active($serviceId, $makeActive);
        flash('success', '"' . $service['name'] . '" is now ' . ($makeActive ? 'open for requests.' : 'hidden from residents.'));
    }
    redirect('staff/services.php');
}

$services = get_services_for_staff();

page_start('Services', ['area' => 'staff', 'active' => 'services']);
echo page_header('Services', 'The services residents can request, and the questions each one asks.',
    '<a class="btn btn-primary" href="' . e(url('staff/service_edit.php')) . '">Add a service</a>',
    [['Dashboard', 'staff/dashboard.php'], ['Services', null]]);
?>
<section class="panel panel-flush" aria-label="Services">
  <?php if ($services): ?>
    <ul class="service-list">
      <?php foreach ($services as $s): $active = (int) $s['is_active'] === 1; ?>
        <li class="service-item<?= $active ? '' : ' is-inactive' ?>">
          <div>
            <h2><?= e($s['name']) ?>
              <?php if (!$active): ?><span class="entry-tag">Hidden from residents</span><?php endif; ?></h2>
            <?php if ($s['description'] !== ''): ?><p><?= e($s['description']) ?></p><?php endif; ?>
            <p class="service-meta"><?= e(plural((int) $s['field_count'], 'extra question')) ?> &middot; <?= e(plural((int) $s['request_count'], 'request')) ?> so far &middot; display order <?= (int) $s['sort_order'] ?></p>
          </div>
          <div class="row-actions">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('staff/service_fields.php') . '?service=' . (int) $s['id']) ?>">Questions<span class="visually-hidden"> of <?= e($s['name']) ?></span></a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('staff/service_edit.php') . '?id=' . (int) $s['id']) ?>">Edit<span class="visually-hidden"> <?= e($s['name']) ?></span></a>
            <form method="post" action="<?= e(url('staff/services.php')) ?>"
                  <?= confirm_attr($active ? 'Hide "' . $s['name'] . '" from residents? Existing requests are not affected.' : 'Open "' . $s['name'] . '" for requests again?', $active ? 'Yes, hide it' : 'Yes, open it') ?>>
              <?= csrf_field() ?>
              <input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">
              <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
              <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $active ? 'Hide' : 'Open' ?><span class="visually-hidden"> <?= e($s['name']) ?></span></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <?= empty_state('', 'No services yet', 'Add the first service residents can request.', 'staff/service_edit.php', 'Add a service') ?>
  <?php endif; ?>
</section>
<?php
page_end();
