<?php
declare(strict_types=1);

$businessModel = new Business($pdo);
$businessId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$business = $businessId === false ? null : $businessModel->find((int) $businessId);
$pageTitle = $business === null ? 'Create Business' : 'Edit Business';
$users = $businessModel->activeUsers();
$currentBusinessUsers = $business === null ? [] : $businessModel->users((int) $businessId);
$selectedUserId = $currentBusinessUsers === [] ? 0 : (int) $currentBusinessUsers[0]['id'];
$assignmentsReturnParams = ['page' => 'admin_assignments'];
foreach (['search', 'date_from', 'date_to'] as $key) {
    if (is_string($_GET[$key] ?? null) && $_GET[$key] !== '') {
        $assignmentsReturnParams[$key] = mb_substr($_GET[$key], 0, 100);
    }
}
foreach (['user_id', 'p'] as $key) {
    $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value !== false) {
        $assignmentsReturnParams[$key] = $value;
    }
}
$assignmentsReturnUrl = APP_URL . '/index.php?' . http_build_query($assignmentsReturnParams);
require __DIR__ . '/../../layouts/header.php';
?>
<main class="container py-4">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $business === null ? 'Create Business' : 'Edit Business' ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e($assignmentsReturnUrl) ?>">Back to Assignments</a>
  </div>

  <?php foreach (get_flashes() as $type => $messages): ?>
    <?php foreach ($messages as $message): ?>
      <div class="alert alert-<?= e($type === 'error' ? 'danger' : $type) ?>" role="alert"><?= e($message) ?></div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if ($businessId !== false && $business === null): ?>
    <div class="alert alert-warning">The requested business could not be found.</div>
  <?php else: ?>
    <form id="business-form" method="post" action="<?= e(APP_URL) ?>/index.php?action=save_business" class="card">
      <div class="card-body">
        <?= csrf_field() ?>
        <?php if ($business !== null): ?><input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>"><?php endif; ?>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="business-name">Business Name</label>
            <input class="form-control" id="business-name" name="name" maxlength="150" required value="<?= e($business['name'] ?? '') ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="business-reviews-count">Reviews Count</label>
            <output class="form-control bg-body-secondary" id="business-reviews-count" aria-live="polite"><?= (int) ($business['imported_reviews_count'] ?? 0) ?></output>
            <div class="form-text">Automatically counted from successfully imported reviews.</div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="business-reference">Reference Link</label>
            <input class="form-control" id="business-reference" name="reference_link" type="url" maxlength="500" placeholder="https://example.com" value="<?= e($business['reference_link'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label" for="business-users">Assigned User</label>
            <?php if ($users === []): ?>
              <div class="alert alert-warning mb-0">No active employee accounts are available. Create or enable an employee account before saving this business.</div>
            <?php elseif ($business === null): ?>
              <select class="form-select" id="business-users" name="user_id" required>
                <option value="">Select an eligible user</option>
                <?php foreach ($users as $user): ?>
                  <option value="<?= (int) $user['id'] ?>"><?= e($user['full_name'] . ' (' . $user['username'] . ')') ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <select class="form-select" id="business-users" name="user_id" required>
                <option value="">Select an eligible user</option>
                <?php foreach ($users as $user): ?>
                  <option value="<?= (int) $user['id'] ?>" <?= $selectedUserId === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['full_name'] . ' (' . $user['username'] . ')') ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($business !== null): ?><div class="form-text">Selecting another employee replaces the current assignee. Pending business tasks move to the selected employee; completed history remains unchanged.</div><?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="daily-limit">Daily Content Assignment Limit</label>
            <input class="form-control" id="daily-limit" name="daily_assignment_limit" type="number" min="1" step="1" required value="<?= e((string) ($business['daily_assignment_limit'] ?? '20')) ?>">
          </div>
          <?php if ($business !== null): ?>
            <div class="col-12">
              <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#business-assign-options">
                <i class="bi bi-send"></i> Assign
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit" <?= $business === null && $users === [] ? 'disabled' : '' ?>><?= $business === null ? 'Create Business' : 'Save Changes' ?></button>
        <a class="btn btn-outline-secondary" href="<?= e($assignmentsReturnUrl) ?>">Cancel</a>
      </div>
    </form>
    <div class="modal fade" id="business-assign-options" tabindex="-1" aria-labelledby="business-assign-options-title" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title fs-5" id="business-assign-options-title">Assign Options</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12 col-sm-6">
                <?php if ($business === null): ?>
                  <button class="btn btn-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center gap-2 py-3" type="submit" form="business-form" name="after_save" value="bulk_assign" <?= $users === [] ? 'disabled' : '' ?>>
                    <i class="bi bi-layers fs-4" aria-hidden="true"></i>
                    <span>Bulk Assign</span>
                  </button>
                <?php else: ?>
                  <a class="btn btn-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center gap-2 py-3" href="<?= e(APP_URL) ?>/index.php?page=admin_bulk_assign&amp;business_id=<?= (int) $business['id'] ?>">
                    <i class="bi bi-layers fs-4" aria-hidden="true"></i>
                    <span>Bulk Assign</span>
                  </a>
                <?php endif; ?>
              </div>
              <div class="col-12 col-sm-6">
                <?php if ($business === null): ?>
                  <button class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center gap-2 py-3" type="submit" form="business-form" name="after_save" value="import_file" <?= $users === [] ? 'disabled' : '' ?>>
                    <i class="bi bi-file-earmark-spreadsheet fs-4" aria-hidden="true"></i>
                    <span>Import File</span>
                  </button>
                <?php else: ?>
                  <a class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center gap-2 py-3" href="<?= e(APP_URL) ?>/index.php?page=admin_bulk_assign&amp;business_id=<?= (int) $business['id'] ?>&amp;tab=import">
                    <i class="bi bi-file-earmark-spreadsheet fs-4" aria-hidden="true"></i>
                    <span>Import File</span>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
