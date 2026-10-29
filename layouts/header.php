<?php
/** Shared page header. Expects $pageTitle to be set by the including view. */
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$adminUnreadNotifications = is_admin() ? (new AdminNotification($pdo))->unreadCount() : 0;
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
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<?php if (is_logged_in()): ?>
<nav class="navbar navbar-expand-lg app-navbar">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="<?= e(APP_URL) ?>/index.php?page=<?= is_admin() ? 'admin_dashboard' : 'dashboard' ?>">
      <i class="bi bi-shield-lock-fill me-1"></i><?= e(APP_NAME) ?>
    </a>
    <div class="app-navbar-controls">
      <button id="themeToggle" class="btn btn-sm btn-outline-secondary app-navbar-theme-toggle" type="button" title="Toggle dark mode" aria-label="Toggle dark mode">
        <i class="bi bi-moon-stars"></i>
      </button>
      <button class="navbar-toggler app-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (is_admin()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_users"><i class="bi bi-people"></i> Users</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_assignments"><i class="bi bi-card-text"></i> Assignments</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_notifications"><i class="bi bi-bell"></i> Notifications<?php if ($adminUnreadNotifications > 0): ?> <span class="badge text-bg-danger"><?= $adminUnreadNotifications ?></span><?php endif; ?></a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=admin_login_logs"><i class="bi bi-box-arrow-in-right"></i> Login Logs</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=dashboard"><i class="bi bi-house"></i> My Tasks</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/index.php?page=history"><i class="bi bi-check2-circle"></i> Completed History</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= e($_SESSION['user_name'] ?? $_SESSION['username'] ?? '') ?>
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
<div class="app-navbar-spacer" aria-hidden="true"></div>
<?php endif; ?>
<main class="app-main <?= is_logged_in() ? '' : 'guest-main' ?>">
<div class="app-page-container">
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
