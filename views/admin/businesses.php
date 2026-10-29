<?php
declare(strict_types=1);

$pageTitle = 'Assignments';
$businessModel = new Business($pdo);
$filterError = null;
$search = isset($_GET['search']) && is_string($_GET['search'])
    ? mb_substr(trim($_GET['search']), 0, 100)
    : '';
$rawUserId = $_GET['user_id'] ?? '';
$userId = is_string($rawUserId) || is_int($rawUserId)
    ? filter_var($rawUserId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$userId = $userId === false ? 0 : (int) $userId;
$parseFilterDate = static function (mixed $value, string $label, ?string &$error): string {
    if (!is_string($value) || $value === '') {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        $date === false
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value
    ) {
        $error = $label . ' must be a valid date.';
        return '';
    }
    return $value;
};
$dateFrom = $parseFilterDate($_GET['date_from'] ?? '', 'Start date', $filterError);
$dateTo = $parseFilterDate($_GET['date_to'] ?? '', 'End date', $filterError);
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $filterError = 'Start date must be on or before end date.';
    $dateFrom = '';
    $dateTo = '';
}
$filters = [
    'search' => $search,
    'user_id' => $userId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];
$rawPage = $_GET['p'] ?? 1;
$requestedPage = (is_string($rawPage) || is_int($rawPage))
    ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$result = $businessModel->list($requestedPage === false ? 1 : (int) $requestedPage, 10, $filters);
$filterUsers = $businessModel->filterUsers();
$paginationBase = [
    'page' => 'admin_assignments',
    'search' => $search,
    'user_id' => $userId > 0 ? $userId : '',
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];
$pageItems = pagination_page_items($result['page'], $result['pages']);
require __DIR__ . '/../../layouts/header.php';
?>
<main class="container py-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">Assignments</h1>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= e(APP_URL) ?>/index.php?page=admin_business_form">Create Assignment</a>
    </div>
  </div>

  <?php foreach (get_flashes() as $type => $messages): ?>
    <?php foreach ($messages as $message): ?>
      <div class="alert alert-<?= e($type === 'error' ? 'danger' : $type) ?>" role="alert"><?= e($message) ?></div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if ($filterError !== null): ?>
    <div class="alert alert-warning" role="alert"><?= e($filterError) ?></div>
  <?php endif; ?>

  <form method="get" action="<?= e(APP_URL) ?>/index.php" class="card p-3 mb-4">
    <input type="hidden" name="page" value="admin_assignments">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label" for="business-search">Search</label>
        <input class="form-control" id="business-search" name="search" type="search" maxlength="100" placeholder="Business or assigned user" value="<?= e($search) ?>">
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label" for="business-user-filter">User</label>
        <select class="form-select" id="business-user-filter" name="user_id">
          <option value="">All assigned users</option>
          <?php foreach ($filterUsers as $filterUser): ?>
            <option value="<?= (int) $filterUser['id'] ?>" <?= $userId === (int) $filterUser['id'] ? 'selected' : '' ?>><?= e($filterUser['full_name'] . ' (' . $filterUser['username'] . ')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-xl-2">
        <label class="form-label" for="business-date-from">Start Date</label>
        <input class="form-control" id="business-date-from" name="date_from" type="date" value="<?= e($dateFrom) ?>" aria-describedby="business-date-help">
      </div>
      <div class="col-6 col-xl-2">
        <label class="form-label" for="business-date-to">End Date</label>
        <input class="form-control" id="business-date-to" name="date_to" type="date" value="<?= e($dateTo) ?>" aria-describedby="business-date-help">
      </div>
      <div class="col-12 col-xl-2 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1" type="submit">Apply Filter</button>
        <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/index.php?page=admin_assignments">Clear</a>
      </div>
      <div class="col-12 form-text" id="business-date-help">Dates filter by business creation date.</div>
    </div>
  </form>

  <?php if ($result['rows'] === []): ?>
    <div class="card"><div class="card-body text-center py-5">
      <p class="mb-0"><?= $search !== '' || $userId > 0 || $dateFrom !== '' || $dateTo !== '' ? 'No businesses match these filters.' : 'No businesses have been added yet.' ?></p>
    </div></div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
      <?php foreach ($result['rows'] as $business): ?>
        <?php $availableUsers = $businessModel->availableUsersForBusiness((int) $business['id']); ?>
        <?php $hasAssignedUser = !empty($business['assigned_user_id']); ?>
        <div class="col">
          <article class="card h-100">
            <div class="card-body d-flex flex-column">
              <h2 class="h5 card-title"><a class="text-decoration-none" href="<?= e(APP_URL) ?>/index.php?page=admin_business_details&amp;id=<?= (int) $business['id'] ?>"><?= e($business['name']) ?></a></h2>
              <div class="row row-cols-3 g-2 small text-center mb-3">
                <div class="col"><a class="d-block text-reset text-decoration-none border rounded p-2 h-100" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query(array_replace($paginationBase, ['page' => 'admin_business_content', 'p' => $result['page'], 'business_id' => (int) $business['id'], 'content_status' => 'all']))) ?>"><div class="text-body-secondary">Reviews Count</div><strong><?= (int) $business['imported_reviews_count'] ?></strong></a></div>
                <div class="col"><a class="d-block text-reset text-decoration-none border rounded p-2 h-100" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query(array_replace($paginationBase, ['page' => 'admin_business_content', 'p' => $result['page'], 'business_id' => (int) $business['id'], 'content_status' => 'used']))) ?>"><div class="text-body-secondary">Used Content Count</div><strong><?= (int) $business['used_content_count'] ?></strong></a></div>
                <div class="col"><a class="d-block text-reset text-decoration-none border rounded p-2 h-100" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query(array_replace($paginationBase, ['page' => 'admin_business_content', 'p' => $result['page'], 'business_id' => (int) $business['id'], 'content_status' => 'active']))) ?>"><div class="text-body-secondary">Active Content Count</div><strong><?= (int) $business['active_content_count'] ?></strong></a></div>
              </div>
              <div class="small mb-3">
                <strong>Assigned User:</strong>
                <?= e($business['assigned_users'] ?: 'None') ?>
              </div>
              <div class="small mb-3"><strong>Daily Content Limit:</strong> <?= (int) $business['daily_assignment_limit'] ?></div>
              <div class="small mb-3 text-break">
                <strong>Reference Link:</strong>
                <?php if (!empty($business['reference_link'])): ?>
                  <a href="<?= e($business['reference_link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($business['reference_link']) ?></a>
                <?php else: ?>Not set<?php endif; ?>
              </div>
              <form method="post" action="<?= e(APP_URL) ?>/index.php?action=add_business_user" class="mb-3 js-submit-loading">
                <?= csrf_field() ?>
                <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
                <input type="hidden" name="return_to" value="admin_assignments">
                <label class="form-label small" for="add-business-user-<?= (int) $business['id'] ?>"><?= $hasAssignedUser ? 'Replace Assigned User' : 'Assign User' ?></label>
                <div class="d-flex gap-2">
                  <select class="form-select form-select-sm" id="add-business-user-<?= (int) $business['id'] ?>" name="user_id" <?= $availableUsers === [] ? 'disabled' : 'required' ?>>
                    <option value=""><?= $availableUsers === [] ? 'No eligible users' : 'Select eligible user' ?></option>
                    <?php foreach ($availableUsers as $user): ?>
                      <option value="<?= (int) $user['id'] ?>"><?= e($user['full_name'] . ' (' . $user['username'] . ')') ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-outline-primary" type="submit" <?= $availableUsers === [] ? 'disabled' : '' ?>><?= $hasAssignedUser ? 'Replace' : 'Assign' ?></button>
                </div>
              </form>
              <div class="small mb-2">
                <strong>Last Import:</strong>
                <?php if ($business['last_import_filename'] !== null): ?>
                  <?= e($business['last_import_filename']) ?>
                  (<?= (int) $business['last_imported_count'] ?> imported, <?= (int) $business['last_rejected_count'] ?> rejected)
                <?php else: ?>No import yet<?php endif; ?>
              </div>
              <div class="d-flex flex-wrap gap-2 mt-auto">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query(array_replace($paginationBase, ['page' => 'admin_business_form', 'id' => (int) $business['id'], 'p' => $result['page']])) ) ?>">
                  <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                </a>
                <form method="post" action="<?= e(APP_URL) ?>/index.php?action=delete_business" onsubmit="return window.confirm('Delete this business? Businesses with content or import history cannot be deleted.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
                  <input type="hidden" name="search" value="<?= e($search) ?>">
                  <input type="hidden" name="user_id" value="<?= $userId > 0 ? $userId : '' ?>">
                  <input type="hidden" name="date_from" value="<?= e($dateFrom) ?>">
                  <input type="hidden" name="date_to" value="<?= e($dateTo) ?>">
                  <input type="hidden" name="p" value="<?= (int) $result['page'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                </form>
                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#assign-options-<?= (int) $business['id'] ?>">
                  <i class="bi bi-send"></i> Assign
                </button>
              </div>
              <div class="modal fade" id="assign-options-<?= (int) $business['id'] ?>" tabindex="-1" aria-labelledby="assign-options-title-<?= (int) $business['id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h2 class="modal-title fs-5" id="assign-options-title-<?= (int) $business['id'] ?>">Assign Options</h2>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="row g-3">
                        <div class="col-12 col-sm-6">
                          <a class="btn btn-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center gap-2 py-3" href="<?= e(APP_URL) ?>/index.php?page=admin_bulk_assign&amp;business_id=<?= (int) $business['id'] ?>">
                            <i class="bi bi-layers fs-4" aria-hidden="true"></i>
                            <span>Bulk Assign</span>
                          </a>
                        </div>
                        <div class="col-12 col-sm-6">
                          <a class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center gap-2 py-3" href="<?= e(APP_URL) ?>/index.php?page=admin_bulk_assign&amp;business_id=<?= (int) $business['id'] ?>&amp;tab=import">
                            <i class="bi bi-file-earmark-spreadsheet fs-4" aria-hidden="true"></i>
                            <span>Import File</span>
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
      <nav class="mt-4" aria-label="Business assignments pagination">
        <ul class="pagination justify-content-center flex-wrap">
          <li class="page-item <?= $result['page'] === 1 ? 'disabled' : '' ?>">
            <?php if ($result['page'] === 1): ?>
              <span class="page-link" aria-label="Previous page">&larr;</span>
            <?php else: ?>
              <a class="page-link" aria-label="Previous page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationBase + ['p' => $result['page'] - 1])) ?>">&larr;</a>
            <?php endif; ?>
          </li>
          <?php foreach ($pageItems as $pageItem): ?>
            <?php if ($pageItem === '…'): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php else: ?>
              <li class="page-item <?= $pageItem === $result['page'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationBase + ['p' => $pageItem])) ?>" <?= $pageItem === $result['page'] ? 'aria-current="page"' : '' ?>><?= $pageItem ?></a>
              </li>
            <?php endif; ?>
          <?php endforeach; ?>
          <li class="page-item <?= $result['page'] === $result['pages'] ? 'disabled' : '' ?>">
            <?php if ($result['page'] === $result['pages']): ?>
              <span class="page-link" aria-label="Next page">&rarr;</span>
            <?php else: ?>
              <a class="page-link" aria-label="Next page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationBase + ['p' => $result['page'] + 1])) ?>">&rarr;</a>
            <?php endif; ?>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
