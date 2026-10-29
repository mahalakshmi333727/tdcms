<?php
declare(strict_types=1);

$editId = (int) ($_GET['id'] ?? 0);
$userModel = new User($pdo);
$editUser = $editId > 0 ? $userModel->findById($editId) : null;
$pageTitle = $editUser ? 'Edit User' : 'Create User';

require __DIR__ . '/../../layouts/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="card p-4">
      <h5 class="mb-3"><i class="bi bi-person-plus"></i> <?= e($pageTitle) ?></h5>
      <form id="userForm" method="post" action="<?= e(APP_URL) ?>/index.php?action=<?= $editUser ? 'update_user' : 'create_user' ?>" class="js-submit-loading"
            data-has-admin-credential="<?= $editUser && $editUser['role'] === 'admin' && !empty($editUser['password_hash']) ? 'true' : 'false' ?>">
        <?= csrf_field() ?>
        <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>"><?php endif; ?>

        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-control" name="full_name" required maxlength="120" value="<?= e($editUser['full_name'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" name="username" required maxlength="60" pattern="[a-z0-9._-]{3,60}" value="<?= e($editUser['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" required maxlength="150" value="<?= e($editUser['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="user" <?= (($editUser['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>User</option>
            <option value="admin" <?= (($editUser['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>
        <div class="mb-3 d-none" id="adminPasswordGroup">
          <label class="form-label" for="adminPassword">Administrator Password</label>
          <input type="password" class="form-control" id="adminPassword" name="admin_password" minlength="<?= PASSWORD_MIN_LENGTH ?>" maxlength="255" autocomplete="new-password">
          <div class="form-text">Required for a new administrator. Leave blank to keep the current password.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2"></i> <?= $editUser ? 'Save Changes' : 'Create User' ?></button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
