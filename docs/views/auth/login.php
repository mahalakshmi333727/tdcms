<?php
declare(strict_types=1);
$pageTitle = 'Login';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="login-wrapper">
  <div class="card auth-card p-4 p-md-5 shadow-lg">
    <div class="text-center mb-4">
      <i class="bi bi-shield-lock-fill display-4 text-primary"></i>
      <h3 class="mt-2 mb-0"><?= e(APP_NAME) ?></h3>
      <p class="text-muted small">Secure Text Distribution &amp; Copy Management</p>
    </div>
    <form method="post" action="<?= e(APP_URL) ?>/index.php?action=login" class="js-submit-loading" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="username">Username</label>
        <input type="text" class="form-control form-control-lg" id="username" name="username" required maxlength="60" autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control form-control-lg" id="password" name="password" required maxlength="255">
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
      </button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
