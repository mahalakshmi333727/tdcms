<?php
declare(strict_types=1);
$pageTitle = 'Assignments';

$page = max(1, (int) ($_GET['p'] ?? 1));
$filters = [
    'status'    => sanitize_input($_GET['status'] ?? ''),
    'user_id'   => (int) ($_GET['user_id'] ?? 0) ?: '',
    'search'    => sanitize_input($_GET['search'] ?? ''),
    'date_from' => sanitize_input($_GET['date_from'] ?? ''),
    'date_to'   => sanitize_input($_GET['date_to'] ?? ''),
];

$assignmentModel = new Assignment($pdo);
$userModel = new User($pdo);
$result = $assignmentModel->listAll($filters, $page);
$allUsers = $userModel->allActiveUsers();

require __DIR__ . '/../../layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="mb-0"><i class="bi bi-card-text"></i> Assignments</h4>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= e(APP_URL) ?>/index.php?page=admin_assign_form" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Assign Text</a>
    <a href="<?= e(APP_URL) ?>/index.php?page=admin_bulk_assign" class="btn btn-outline-primary"><i class="bi bi-layers"></i> Bulk Assign</a>
    <a href="<?= e(APP_URL) ?>/index.php?action=export_csv&status=<?= urlencode($filters['status']) ?>&user_id=<?= urlencode((string) $filters['user_id']) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
  </div>
</div>

<form method="get" action="<?= e(APP_URL) ?>/index.php" class="card p-3 mb-4">
  <input type="hidden" name="page" value="admin_assignments">
  <div class="row g-2">
    <div class="col-12 col-md-3">
      <input type="text" name="search" class="form-control" placeholder="Search text or user" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-6 col-md-2">
      <select name="status" class="form-select">
        <option value="">All Status</option>
        <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="user_id" class="form-select">
        <option value="">All Users</option>
        <?php foreach ($allUsers as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= ((string) $filters['user_id'] === (string) $u['id']) ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
    </div>
    <div class="col-6 col-md-2">
      <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
    </div>
    <div class="col-12 col-md-1">
      <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<div class="card table-responsive-card">
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr><th>User</th><th>Title</th><th>Category</th><th>Text Preview</th><th>Link</th><th>Status</th><th>Assigned</th><th>Completed</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($result['rows'] as $row): ?>
        <tr>
          <td><?= e($row['user_full_name']) ?></td>
          <td><?= e($row['title'] ?: '—') ?></td>
          <td><?= !empty($row['category']) ? '<span class="badge text-bg-secondary">' . e($row['category']) . '</span>' : '—' ?></td>
          <td><?= e(text_preview($row['text_content'], 50)) ?></td>
          <td><?= !empty($row['reference_link']) ? '<a href="' . e($row['reference_link']) . '" target="_blank" rel="noopener noreferrer"><i class="bi bi-link-45deg"></i></a>' : '—' ?></td>
          <td>
            <?php if ($row['status'] === 'pending'): ?>
              <span class="badge badge-pending text-white">Pending</span>
            <?php else: ?>
              <span class="badge badge-completed text-white">Completed</span>
            <?php endif; ?>
          </td>
          <td><?= e(format_datetime($row['assigned_at'])) ?></td>
          <td><?= e(format_datetime($row['completed_at'])) ?></td>
          <td>
            <div class="assignment-actions">
              <button type="button" class="btn btn-sm btn-outline-secondary btn-view-assignment" data-id="<?= (int) $row['id'] ?>">
                <i class="bi bi-eye"></i> View
              </button>
              <button type="button" class="btn btn-sm btn-outline-primary btn-edit-assignment" data-id="<?= (int) $row['id'] ?>">
                <i class="bi bi-pencil-square"></i> Edit
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger btn-delete-assignment" data-id="<?= (int) $row['id'] ?>">
                <i class="bi bi-trash"></i> Delete
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($result['rows'])): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No assignments found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($result['pages'] > 1): ?>
  <nav class="mt-3">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <li class="page-item <?= $i === $result['page'] ? 'active' : '' ?>">
          <a class="page-link" href="?page=admin_assignments&p=<?= $i ?>&status=<?= urlencode($filters['status']) ?>&user_id=<?= urlencode((string) $filters['user_id']) ?>&search=<?= urlencode($filters['search']) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<div class="modal fade" id="viewAssignmentModal" tabindex="-1" aria-labelledby="viewAssignmentTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewAssignmentTitle">Assignment Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Assignment ID</dt><dd class="col-sm-8" id="viewAssignmentId"></dd>
          <dt class="col-sm-4">Assigned Employee</dt><dd class="col-sm-8" id="viewAssignmentEmployee"></dd>
          <dt class="col-sm-4">Title</dt><dd class="col-sm-8" id="viewAssignmentName"></dd>
          <dt class="col-sm-4">Category</dt><dd class="col-sm-8" id="viewAssignmentCategory"></dd>
          <dt class="col-sm-4">Tags</dt><dd class="col-sm-8" id="viewAssignmentTags"></dd>
          <dt class="col-sm-4">Status</dt><dd class="col-sm-8" id="viewAssignmentStatus"></dd>
          <dt class="col-sm-4">Reference Link</dt>
          <dd class="col-sm-8"><a id="viewAssignmentLink" target="_blank" rel="noopener noreferrer"></a><span id="viewAssignmentNoLink">—</span></dd>
          <dt class="col-sm-4">Assigned By</dt><dd class="col-sm-8" id="viewAssignmentAssigner"></dd>
          <dt class="col-sm-4">Assigned At</dt><dd class="col-sm-8" id="viewAssignmentAssignedAt"></dd>
          <dt class="col-sm-4">Completed At</dt><dd class="col-sm-8" id="viewAssignmentCompletedAt"></dd>
          <dt class="col-sm-4">Completion IP</dt><dd class="col-sm-8" id="viewAssignmentCompletionIp"></dd>
          <dt class="col-sm-4">Task Progress</dt><dd class="col-sm-8" id="viewAssignmentProgress"></dd>
        </dl>
        <h6 class="mt-3">Description / Text</h6>
        <div class="assignment-detail-text" id="viewAssignmentText"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-labelledby="editAssignmentTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="editAssignmentForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editAssignmentTitle">Edit Assignment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="editAssignmentError" class="alert alert-danger d-none" role="alert"></div>
          <input type="hidden" name="assignment_id">
          <div class="mb-3">
            <label for="editAssignmentUser" class="form-label">Assigned Employee</label>
            <select class="form-select" id="editAssignmentUser" name="user_id" required>
              <?php foreach ($allUsers as $user): ?>
                <option value="<?= (int) $user['id'] ?>"><?= e($user['full_name']) ?> (<?= e($user['username']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="editAssignmentName" class="form-label">Title</label>
            <input type="text" class="form-control" id="editAssignmentName" name="title" maxlength="150">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="editAssignmentCategory" class="form-label">Category</label>
              <input type="text" class="form-control" id="editAssignmentCategory" name="category" maxlength="100">
            </div>
            <div class="col-md-6">
              <label for="editAssignmentTags" class="form-label">Tags</label>
              <input type="text" class="form-control" id="editAssignmentTags" name="tags" maxlength="500">
            </div>
          </div>
          <div class="my-3">
            <label for="editAssignmentText" class="form-label">Description / Text</label>
            <textarea class="form-control" id="editAssignmentText" name="text_content" rows="7" maxlength="20000" required></textarea>
          </div>
          <div>
            <label for="editAssignmentLink" class="form-label">Reference Link</label>
            <input type="url" class="form-control" id="editAssignmentLink" name="reference_link" maxlength="500" placeholder="https://example.com">
          </div>
          <p class="form-text mb-0 mt-3" id="editAssignmentStatusNote"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="saveAssignmentChanges"><i class="bi bi-check2"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
