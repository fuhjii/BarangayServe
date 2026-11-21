<?php
/**
 * resident/services.php: the barangay services a resident can request (SRS FR-03).
 * The list comes from the `services` table, so staff can add services without code changes.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_resident();

$services = get_active_services();

page_start('Request a service', ['area' => 'resident', 'active' => 'services']);
echo page_header('Request a service', '', '', [
    ['Dashboard', 'resident/dashboard.php'],
    ['Request a service', null],
]);
?>
<section class="panel panel-flush" aria-label="Available services">
  <?php if ($services): ?>
    <ul class="service-list">
      <?php foreach ($services as $service): ?>
        <li class="service-item">
          <div>
            <h2><?= e($service['name']) ?></h2>
            <?php if ($service['description'] !== ''): ?><p><?= e($service['description']) ?></p><?php endif; ?>
            <?php if (!empty($service['pickup_requirements'])): ?>
              <p class="service-bring"><i class="bi bi-bag-check" aria-hidden="true"></i>Bring when you pick it up: <?= e($service['pickup_requirements']) ?></p>
            <?php endif; ?>
          </div>
          <a class="btn btn-primary" href="<?= e(url('resident/request_new.php') . '?service=' . (int) $service['id']) ?>">
            Start<span class="visually-hidden">: <?= e($service['name']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <?= empty_state('', 'No services open yet', 'Please check back later.') ?>
  <?php endif; ?>
</section>
<?php
page_end();
