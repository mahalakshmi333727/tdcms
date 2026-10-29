<?php
declare(strict_types=1);
$pageTitle = 'Assign Text';

$userModel = new User($pdo);
$allUsers = $userModel->allActiveUsers();
$assignmentRequestId = random_token();

require __DIR__ . '/../../layouts/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-9 col-lg-7">
    <div class="card p-4">
      <h5 class="mb-3"><i class="bi bi-plus-circle"></i> Assign Text to a User</h5>
      <form method="post" action="<?= e(APP_URL) ?>/index.php?action=create_assignment" class="js-submit-loading">
        <?= csrf_field() ?>
        <input type="hidden" name="assignment_request_id" value="<?= e($assignmentRequestId) ?>">
        <div class="mb-3">
          <label class="form-label">Assign To</label>
          <select name="user_id" class="form-select" required>
            <option value="">Select a user…</option>
            <?php foreach ($allUsers as $u): ?>
              <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">Title <span class="text-muted small">(optional)</span></label>
            <input type="text" class="form-control" name="title" maxlength="150" placeholder="e.g. Standard Response Template">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Category <span class="text-muted small">(optional)</span></label>
            <input type="text" class="form-control" name="category" maxlength="100" placeholder="e.g. General, Support">
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label">Text Content</label>
          <textarea class="form-control" name="text_content" rows="6" required maxlength="20000"></textarea>
        </div>
        <div class="row g-3">
          <div class="col-12 col-md-8">
            <label class="form-label">Reference Link <span class="text-muted small">(optional)</span></label>
            <input type="url" class="form-control" name="reference_link" placeholder="https://example.com">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Tags <span class="text-muted small">(optional)</span></label>
            <input type="text" class="form-control" name="tags" maxlength="500" placeholder="welcome, general">
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-3"><i class="bi bi-send"></i> Assign</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
