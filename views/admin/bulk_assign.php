<?php
declare(strict_types=1);
$pageTitle = 'Bulk Assign';

$userModel = new User($pdo);
$allUsers = $userModel->allActiveUsers();
$businessModel = new Business($pdo);
$businesses = $businessModel->choices();
$requestedBusinessId = filter_var(
    $_GET['business_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$showImportTab = isset($_GET['tab']) && is_string($_GET['tab']) && $_GET['tab'] === 'import';
$selectedBusinessId = 0;
$selectedBusinessName = '';
$currentAssigneeId = 0;
$assignmentUsers = $allUsers;
if ($requestedBusinessId !== false) {
    foreach ($businesses as $businessChoice) {
        if ((int) $businessChoice['id'] === (int) $requestedBusinessId) {
            $selectedBusinessId = (int) $requestedBusinessId;
            $selectedBusinessName = $businessChoice['name'];
            break;
        }
    }
    if ($selectedBusinessId > 0) {
        foreach ($businessModel->users($selectedBusinessId) as $assignedUser) {
            if ($assignedUser['status'] === 'active') {
                $currentAssigneeId = (int) $assignedUser['id'];
                break;
            }
        }
    }
    $assignmentUsers = $selectedBusinessId > 0
        ? array_values(array_filter(
            $allUsers,
            static fn (array $user): bool => (int) $user['id'] === $currentAssigneeId
        ))
        : $allUsers;
}
$manualAssignmentRequestId = random_token();
$importAssignmentRequestId = random_token();

require __DIR__ . '/../../layouts/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-11 col-lg-9">
    <div class="card p-4">
      <h5 class="mb-3"><i class="bi bi-layers"></i> Bulk Assign Text</h5>

      <ul class="nav nav-tabs" id="bulkAssignTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $showImportTab ? '' : 'active' ?>" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-pane" type="button" role="tab" aria-selected="<?= $showImportTab ? 'false' : 'true' ?>">
            <i class="bi bi-pencil-square"></i> Manual Entry
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $showImportTab ? 'active' : '' ?>" id="import-tab" data-bs-toggle="tab" data-bs-target="#import-pane" type="button" role="tab" aria-selected="<?= $showImportTab ? 'true' : 'false' ?>">
            <i class="bi bi-upload"></i> Import File (JSON / Excel / CSV)
          </button>
        </li>
      </ul>

      <div class="tab-content border border-top-0 rounded-bottom p-3">
        <!-- ============================================================ -->
        <!-- MANUAL ENTRY: one task, assigned to ONE selected user         -->
        <!-- ============================================================ -->
        <div class="tab-pane fade <?= $showImportTab ? '' : 'show active' ?>" id="manual-pane" role="tabpanel">
          <form method="post" action="<?= e(APP_URL) ?>/index.php?action=bulk_assign" class="js-submit-loading bulk-assign-form">
            <?= csrf_field() ?>
            <input type="hidden" name="assignment_request_id" value="<?= e($manualAssignmentRequestId) ?>">

            <div class="mb-3">
              <label class="form-label">Assign To</label>
                <select name="user_id" class="form-select" required>
                  <option value=""><?= $assignmentUsers === [] ? 'No active user assigned to this business' : '-- Select User --' ?></option>
                <?php foreach ($assignmentUsers as $u): ?>
                  <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === $currentAssigneeId ? 'selected' : '' ?>><?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Select one user to assign this text to.</div>
            </div>

            <?php if ($businesses !== []): ?>
              <div class="mb-3">
                <label class="form-label">Business<?= $selectedBusinessId === 0 ? ' (optional)' : '' ?></label>
                <?php if ($selectedBusinessId > 0): ?>
                  <input class="form-control" type="text" value="<?= e($selectedBusinessName) ?>" aria-label="Business name">
                  <input type="hidden" name="business_id" value="<?= $selectedBusinessId ?>">
                  <div class="form-text">The selected user must belong to this business.</div>
                <?php else: ?>
                  <select name="business_id" class="form-select" data-manual-business-select>
                    <option value="">Standalone assignment</option>
                    <?php foreach ($businesses as $business): ?>
                      <option value="<?= (int) $business['id'] ?>"><?= e($business['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Choose a business to link this task to its content records and daily limit. The selected user must belong to that business.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label">Title <span class="text-muted small">(required for business assignments)</span></label>
                <input type="text" class="form-control" name="title" maxlength="150" <?= $selectedBusinessId > 0 ? 'required' : '' ?>>
                <div class="form-text">Required when linking this task to a business; maximum 150 characters.</div>
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
                <label class="form-label">Tags <span class="text-muted small">(optional, comma-separated)</span></label>
                <input type="text" class="form-control" name="tags" maxlength="500" placeholder="welcome, general">
              </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-3" <?= $assignmentUsers === [] ? 'disabled' : '' ?>><i class="bi bi-send"></i> Assign Task</button>
          </form>
        </div>

        <div class="tab-pane fade <?= $showImportTab ? 'show active' : '' ?>" id="import-pane" role="tabpanel">
          <form method="post" action="<?= e(APP_URL) ?>/index.php?action=bulk_assign" enctype="multipart/form-data" class="js-submit-loading bulk-assign-form">
            <?= csrf_field() ?>
            <input type="hidden" name="assignment_request_id" value="<?= e($importAssignmentRequestId) ?>">
            <div class="mb-3">
              <label class="form-label">Assign To</label>
              <select name="user_id" class="form-select" required>
                <option value=""><?= $assignmentUsers === [] ? 'No active user assigned to this business' : '-- Select One User --' ?></option>
                <?php foreach ($assignmentUsers as $u): ?>
                  <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === $currentAssigneeId ? 'selected' : '' ?>><?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Every imported task will be assigned to this one user.</div>
            </div>
            <?php if ($businesses !== []): ?>
              <div class="mb-3">
                <label class="form-label">Business<?= $selectedBusinessId === 0 ? ' (optional)' : '' ?></label>
                <?php if ($selectedBusinessId > 0): ?>
                  <input class="form-control" type="text" value="<?= e($selectedBusinessName) ?>" aria-label="Business name">
                  <input type="hidden" name="business_id" value="<?= $selectedBusinessId ?>">
                  <div class="form-text">The selected user must belong to this business.</div>
                <?php else: ?>
                  <select name="business_id" class="form-select">
                    <option value="">Standalone assignments</option>
                    <?php foreach ($businesses as $business): ?>
                      <option value="<?= (int) $business['id'] ?>"><?= e($business['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Choose a business to link imported tasks to its content records and daily limit. The selected user must belong to that business.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="mb-3">
              <label class="form-label">Common Reference Link / Paste Destination <span class="text-muted small">(optional)</span></label>
              <input type="url" class="form-control" name="common_reference_link" maxlength="500" placeholder="https://example.com/review-page">
              <div class="form-text">This link will be applied to every task imported in this operation.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Task Files</label>
              <input type="file" class="form-control" id="taskImportFiles" name="import_files[]" accept=".json,.xlsx,.xls,.csv" multiple required>
              <div id="taskImportFileCount" class="form-text">No files selected. Select up to 100 files.</div>
              <div class="form-text">Each row becomes a text task. Required columns: <code>title</code> and <code>content</code>. Maximum 500 rows and 3 MB per file.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100" <?= $assignmentUsers === [] ? 'disabled' : '' ?>><i class="bi bi-upload"></i> Import &amp; Assign</button>
          </form>
        </div>

      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>