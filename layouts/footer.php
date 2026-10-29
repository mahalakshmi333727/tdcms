<?php declare(strict_types=1); ?>
</div>
</main>
<!-- BEGIN shared authenticated bottom navigation -->
<?php if (is_logged_in()): ?>
<?php
$currentPage = $_GET['page'] ?? (is_admin() ? 'admin_dashboard' : 'dashboard');
$bottomNavItems = is_admin()
    ? [
        ['page' => 'admin_dashboard', 'label' => 'Dashboard', 'icon' => 'speedometer2', 'active' => $currentPage === 'admin_dashboard'],
        ['page' => 'admin_users', 'label' => 'Users', 'icon' => 'people', 'active' => in_array($currentPage, ['admin_users', 'admin_user_form'], true)],
        ['page' => 'admin_assignments', 'label' => 'Assignments', 'icon' => 'card-text', 'active' => in_array($currentPage, ['admin_assignments', 'admin_bulk_assign'], true)],
        ['page' => 'admin_notifications', 'label' => 'Notifications', 'icon' => 'bell', 'active' => $currentPage === 'admin_notifications'],
        ['page' => 'admin_login_logs', 'label' => 'Login Logs', 'icon' => 'box-arrow-in-right', 'active' => $currentPage === 'admin_login_logs'],
    ]
    : [
        ['page' => 'dashboard', 'label' => 'My Tasks', 'icon' => 'house', 'active' => in_array($currentPage, ['dashboard', 'user_excel_assignments', 'user_excel_assignment_view'], true)],
        ['page' => 'history', 'label' => 'Completed History', 'icon' => 'check2-circle', 'active' => $currentPage === 'history'],
    ];
?>
<nav class="app-bottom-nav" aria-label="Primary navigation">
  <div class="app-bottom-nav-inner">
    <?php foreach ($bottomNavItems as $item): ?>
      <a class="bottom-nav-link<?= $item['active'] ? ' active' : '' ?>"
         href="<?= e(APP_URL) ?>/index.php?page=<?= e($item['page']) ?>"
         <?= $item['active'] ? 'aria-current="page"' : '' ?>>
        <i class="bi bi-<?= e($item['icon']) ?>" aria-hidden="true"></i>
        <span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
<!-- END shared authenticated bottom navigation -->
<footer class="app-footer text-center text-muted small py-3">
  &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
</footer>
<?php if (is_logged_in()): ?>
<div class="app-bottom-nav-spacer" aria-hidden="true"></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e(APP_URL) ?>/assets/js/app.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
