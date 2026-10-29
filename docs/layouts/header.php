<?php
/** Shared page header. Expects $pageTitle to be set by the including view. */
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="app-url" content="<?= e(APP_URL) ?>">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css">
</head>
<body>
<?php if (is_logged_in()): ?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="<?= e(APP_URL) ?>/index.php?page=<?= is_admin() ? 'admin_dashboard' : 'dashboard' ?>">
      <i class="bi bi-shield-lock-fill me-1"></i><?= e(APP_NAME) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (is_admin()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_users"><i class="bi bi-people"></i> Users</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_assignments"><i class="bi bi-card-text"></i> Assignments</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_activity_logs"><i class="bi bi-clock-history"></i> Activity Logs</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_login_logs"><i class="bi bi-box-arrow-in-right"></i> Login Logs</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=dashboard"><i class="bi bi-house"></i> My Tasks</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=history"><i class="bi bi-check2-circle"></i> Completed History</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><button id="themeToggle" class="btn btn-sm btn-outline-secondary me-lg-2 mb-2 mb-lg-0" type="button" title="Toggle dark mode"><i class="bi bi-moon-stars"></i></button></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= e($_SESSION['user_name'] ?? '') ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <form method="post" action="<?= e(APP_URL) ?>/index.php?action=logout">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Logout</button>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="app-main <?= is_logged_in() ? '' : 'guest-main' ?>">
<div class="container-fluid py-4">
<?php
$flashes = get_flashes();
foreach ($flashes as $type => $messages):
    $alertClass = $type === 'error' ? 'danger' : ($type === 'success' ? 'success' : 'info');
    foreach ($messages as $msg):
?>
  <div class="alert alert-<?= e($alertClass) ?> alert-dismissible fade show" role="alert">
    <?= e($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; endforeach; ?>
