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
   <form method="post" action="<?= e(APP_URL) ?>/index.php?action=login" class="js-submit-loading" id="loginForm" autocomplete="on" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="client_public_ip" id="clientPublicIp" value="">
      <div id="loginError" class="alert alert-danger d-none" role="alert" aria-live="polite"></div>
      <?php if (!is_localhost_development_request()): ?>
      <p class="form-text d-none" id="loginStatus" role="status" aria-live="polite"></p>
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label" for="username">Username</label>
        <input type="text" class="form-control form-control-lg" id="username" name="username" required minlength="3" maxlength="60" pattern="[A-Za-z0-9._\-]{3,60}" autocomplete="username" autofocus value="<?= e($_POST['username'] ?? '') ?>" aria-describedby="loginError">
      </div>
      <div class="mb-3 d-none" id="passwordGroup">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control form-control-lg" id="password" name="password" maxlength="255" autocomplete="current-password" aria-describedby="loginError">
      </div>
      <?php if (!is_localhost_development_request()): ?>
      <div class="mb-3 d-none" id="verificationCodeGroup">
        <label class="form-label" for="verificationCode">Email verification code</label>
        <input type="text" class="form-control form-control-lg" id="verificationCode" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" aria-describedby="loginError loginStatus">
      </div>
      <?php endif; ?>
      <button type="button" class="btn btn-primary btn-lg w-100 mt-2" id="continueButton">
        <i class="bi bi-arrow-right me-1"></i> Continue
      </button>
      <button type="submit" class="btn btn-primary btn-lg w-100 mt-2 d-none" id="loginButton">
        <i class="bi bi-box-arrow-in-right me-1"></i> Login
      </button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
