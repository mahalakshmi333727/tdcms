<?php
declare(strict_types=1);
$pageTitle = 'Bulk Assign';

$userModel = new User($pdo);
$allUsers = $userModel->allActiveUsers();
$manualAssignmentRequestId = random_token();
$importAssignmentRequestId = random_token();

require __DIR__ . '/../../layouts/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-11 col-lg-9">
    <div class="card p-4">
      <h5 class="mb-3"><i class="bi bi-layers"></i> Bulk Assign Text</h5>

      <!-- Shared user picker — used by both the Manual and Import forms below -->
      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label mb-0">Select Users</label>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="selectAllUsers">
            <label class="form-check-label small" for="selectAllUsers">Select All</label>
          </div>
        </div>
        <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;" id="userPickerBox">
          <?php foreach ($allUsers as $u): ?>
            <div class="form-check">
              <input class="form-check-input user-checkbox" type="checkbox" value="<?= (int) $u['id'] ?>" id="u<?= (int) $u['id'] ?>">
              <label class="form-check-label" for="u<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</label>
            </div>
          <?php endforeach; ?>
          <?php if (empty($allUsers)): ?>
            <p class="text-muted mb-0">No active users available.</p>
          <?php endif; ?>
        </div>
        <div class="form-text">The same user selection applies whichever tab below you submit.</div>
      </div>

      <ul class="nav nav-tabs" id="bulkAssignTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-pane" type="button" role="tab">
            <i class="bi bi-pencil-square"></i> Manual Entry
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="import-tab" data-bs-toggle="tab" data-bs-target="#import-pane" type="button" role="tab">
            <i class="bi bi-upload"></i> Import File (JSON / Excel / CSV)
          </button>
        </li>
      </ul>

      <div class="tab-content border border-top-0 rounded-bottom p-3">
        <!-- ============================================================ -->
        <!-- MANUAL ENTRY: one task, assigned to every selected user       -->
        <!-- ============================================================ -->
        <div class="tab-pane fade show active" id="manual-pane" role="tabpanel">
          <form method="post" action="<?= e(APP_URL) ?>/index.php?action=bulk_assign" class="js-submit-loading bulk-assign-form">
            <?= csrf_field() ?>
            <input type="hidden" name="assignment_request_id" value="<?= e($manualAssignmentRequestId) ?>">
            <div class="user-ids-mount"></div>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label">Title <span class="text-muted small">(optional)</span></label>
                <input type="text" class="form-control" name="title" maxlength="150">
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
            <button type="submit" class="btn btn-primary w-100 mt-3"><i class="bi bi-send"></i> Assign to Selected Users</button>
          </form>
        </div>

        <!-- ============================================================ -->
        <!-- IMPORT FILE: many tasks, each assigned to every selected user -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="import-pane" role="tabpanel">
          <form method="post" action="<?= e(APP_URL) ?>/index.php?action=bulk_assign" enctype="multipart/form-data" class="js-submit-loading bulk-assign-form">
            <?= csrf_field() ?>
            <input type="hidden" name="assignment_request_id" value="<?= e($importAssignmentRequestId) ?>">
            <div class="user-ids-mount"></div>

            <div class="mb-3">
              <label class="form-label">Task File</label>
              <input type="file" class="form-control" name="import_file" accept=".json,.xlsx,.csv" required>
              <div class="form-text">
                Each row/object becomes one task, assigned to every user selected above.
                Supported columns/keys: <code>title</code>, <code>category</code>, <code>content</code>
                <span class="text-muted">(required — the task text)</span>, <code>reference_link</code>, <code>tags</code>.
                Max 500 rows, 3&nbsp;MB.
              </div>
            </div>

            <div class="alert alert-light border small mb-3">
              <strong><i class="bi bi-file-earmark-code"></i> Sample JSON:</strong>
<pre class="mb-2 mt-1" style="white-space: pre-wrap;">[
  {
    "title": "Welcome Message",
    "category": "General",
    "content": "Welcome to our company.",
    "reference_link": "https://example.com",
    "tags": ["welcome", "general"]
  }
]</pre>
              <div class="d-flex flex-wrap gap-2">
                <a href="<?= e(APP_URL) ?>/assets/samples/sample_tasks.json" class="btn btn-sm btn-outline-secondary" download>
                  <i class="bi bi-download"></i> Download sample .json
                </a>
                <a href="<?= e(APP_URL) ?>/assets/samples/sample_tasks.xlsx" class="btn btn-sm btn-outline-secondary" download>
                  <i class="bi bi-download"></i> Download sample .xlsx
                </a>
                <a href="<?= e(APP_URL) ?>/assets/samples/sample_tasks.csv" class="btn btn-sm btn-outline-secondary" download>
                  <i class="bi bi-download"></i> Download sample .csv
                </a>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Import &amp; Assign to Selected Users</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
