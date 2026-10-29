<?php
declare(strict_types=1);

$businessId = filter_var($_GET['business_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$statusInput = $_GET['content_status'] ?? 'all';
$contentStatus = is_string($statusInput) && in_array($statusInput, ['all', 'active', 'used'], true)
    ? $statusInput
    : 'all';
$businessModel = new Business($pdo);
$business = $businessId === false ? null : $businessModel->find((int) $businessId);
$contentRows = $business === null ? [] : $businessModel->content((int) $businessId, $contentStatus === 'all' ? null : $contentStatus);
$pageTitle = 'Business Content';
$readFilter = static fn (string $key): string => is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
$search = mb_substr($readFilter('search'), 0, 100);
$userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$assignmentPage = filter_var($_GET['p'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$backParams = ['page' => 'admin_assignments'];
foreach (['search' => $search, 'date_from' => $readFilter('date_from'), 'date_to' => $readFilter('date_to')] as $key => $value) {
    if ($value !== '') {
        $backParams[$key] = $value;
    }
}
if ($userId !== false) {
    $backParams['user_id'] = $userId;
}
if ($assignmentPage !== false) {
    $backParams['p'] = $assignmentPage;
}
$contentParams = [
    'page' => 'admin_business_content',
    'business_id' => (int) ($businessId === false ? 0 : $businessId),
    'content_status' => $contentStatus,
    'search' => $search,
    'date_from' => $readFilter('date_from'),
    'date_to' => $readFilter('date_to'),
    'user_id' => $userId === false ? '' : $userId,
    'p' => $assignmentPage === false ? '' : $assignmentPage,
];
require __DIR__ . '/../../layouts/header.php';
?>
<main class="container-fluid py-3 px-2 px-md-4">
  <?php if ($business === null): ?>
    <div class="alert alert-warning" role="alert">The requested business could not be found.</div>
    <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/index.php?page=admin_assignments">Back to Assignments</a>
  <?php else: ?>
    <?php
    $contentCount = count($contentRows);
    $statusLabels = ['all' => 'All Content', 'active' => 'Active Content', 'used' => 'Used Content'];
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div>
        <h1 class="h3 mb-1"><?= e($business['name']) ?></h1>
        <div class="text-body-secondary">
          Category: <?= e($business['category'] !== '' ? $business['category'] : 'Uncategorized') ?>
          <span class="mx-2">·</span>
          <?= e($statusLabels[$contentStatus]) ?>: <?= $contentCount ?> record(s)
        </div>
      </div>
      <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($backParams)) ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Assignments
      </a>
    </div>

    <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
      <?php foreach ([
          'Reviews Count' => (int) $business['imported_reviews_count'],
          'Content Records' => (int) $business['total_content_count'],
          'Active Content' => (int) $business['active_content_count'],
          'Used Content' => (int) $business['used_content_count'],
      ] as $label => $value): ?>
        <div class="col"><div class="card h-100"><div class="card-body py-3">
          <div class="small text-body-secondary"><?= e($label) ?></div>
          <div class="fs-4 fw-semibold"><?= $value ?></div>
        </div></div></div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <span class="fw-semibold me-1">Content view:</span>
      <?php foreach ($statusLabels as $statusValue => $label): ?>
        <a class="btn btn-sm <?= $contentStatus === $statusValue ? 'btn-primary' : 'btn-outline-primary' ?>"
           href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query(array_replace($contentParams, ['content_status' => $statusValue]))) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>

    <form method="post" action="<?= e(APP_URL) ?>/index.php?action=manage_business_content" class="business-content-form" data-business-content-form>
      <?= csrf_field() ?>
      <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
      <input type="hidden" name="content_request_id" value="<?= e(random_token()) ?>">
      <input type="hidden" name="return_to" value="admin_business_content">
      <input type="hidden" name="content_status" value="<?= e($contentStatus) ?>">
      <input type="hidden" name="search" value="<?= e($search) ?>">
      <input type="hidden" name="user_id" value="<?= $userId === false ? '' : (int) $userId ?>">
      <input type="hidden" name="date_from" value="<?= e($readFilter('date_from')) ?>">
      <input type="hidden" name="date_to" value="<?= e($readFilter('date_to')) ?>">
      <input type="hidden" name="p" value="<?= $assignmentPage === false ? '' : (int) $assignmentPage ?>">
      <input type="hidden" name="content_id" value="">
      <input type="hidden" name="operation" value="">

      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label class="form-check mb-0 me-2">
          <input class="form-check-input business-content-select-all" type="checkbox" aria-label="Select all content">
          <span class="form-check-label">Select All</span>
        </label>
        <button class="btn btn-sm btn-outline-primary" type="button" data-bulk-edit>Bulk Edit</button>
        <button class="btn btn-sm btn-primary" type="submit" data-operation="bulk_save">Save Selected</button>
        <button class="btn btn-sm btn-outline-danger" type="submit" data-operation="bulk_delete">Bulk Delete</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-cancel-bulk>Cancel</button>
      </div>

      <div class="card">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 business-content-table">
            <thead class="table-light">
              <tr>
                <th scope="col" class="content-select-column">Select</th>
                <th scope="col" class="content-title-column">Title</th>
                <th scope="col" class="content-category-column">Category</th>
                <th scope="col" class="content-text-column">Full Content</th>
                <th scope="col" class="content-reference-column">Reference Link</th>
                <th scope="col" class="content-status-column">Status</th>
                <th scope="col" class="content-actions-column">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($contentRows === []): ?>
                <tr><td colspan="7" class="text-center text-body-secondary py-5">No content records match this view.</td></tr>
              <?php else: foreach ($contentRows as $item): ?>
                <?php
                $contentId = (int) $item['id'];
                if ($item['assignment_id'] === null) {
                    $statusLabel = 'Available';
                    $statusClass = 'text-bg-secondary';
                } elseif ($item['assignment_status'] === 'completed') {
                    $statusLabel = 'Used';
                    $statusClass = 'text-bg-success';
                } elseif ($item['assignment_archived_at'] !== null) {
                    $statusLabel = 'Archived';
                    $statusClass = 'text-bg-secondary';
                } else {
                    $statusLabel = 'Active';
                    $statusClass = 'text-bg-primary';
                }
                ?>
                <tr data-business-content-row>
                  <td><input class="form-check-input business-content-checkbox" type="checkbox" name="content_ids[]" value="<?= $contentId ?>" aria-label="Select <?= e($item['title']) ?>"></td>
                  <td>
                    <span data-content-display class="content-cell-text"><?= e($item['title']) ?></span>
                    <input class="form-control d-none" data-content-input data-original="<?= e($item['title']) ?>" name="title[<?= $contentId ?>]" value="<?= e($item['title']) ?>" maxlength="150" disabled>
                  </td>
                  <td><?= e($business['category'] !== '' ? $business['category'] : 'Uncategorized') ?></td>
                  <td>
                    <div data-content-display class="content-cell-text"><?= e($item['text_content']) ?></div>
                    <textarea class="form-control d-none" data-content-input data-original="<?= e($item['text_content']) ?>" name="text_content[<?= $contentId ?>]" rows="5" maxlength="65535" disabled><?= e($item['text_content']) ?></textarea>
                  </td>
                  <td>
                    <div data-content-display class="content-cell-text"><?php if (!empty($item['reference_link'])): ?><a href="<?= e($item['reference_link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($item['reference_link']) ?></a><?php else: ?>—<?php endif; ?></div>
                    <input class="form-control d-none" data-content-input data-original="<?= e($item['reference_link'] ?? '') ?>" name="reference_link[<?= $contentId ?>]" type="url" maxlength="500" value="<?= e($item['reference_link'] ?? '') ?>" disabled>
                  </td>
                  <td><span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                  <td class="text-nowrap">
                    <div class="d-flex flex-wrap gap-1">
                      <button class="btn btn-sm btn-outline-primary" type="button" data-edit-row aria-label="Edit content">Edit</button>
                      <button class="btn btn-sm btn-primary d-none" type="submit" data-operation="save_one" data-content-id="<?= $contentId ?>">Save</button>
                      <button class="btn btn-sm btn-outline-secondary d-none" type="button" data-cancel-row>Cancel</button>
                      <button class="btn btn-sm btn-outline-danger" type="submit" data-operation="delete_one" data-content-id="<?= $contentId ?>">Delete</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </form>

  <?php endif; ?>
</main>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
