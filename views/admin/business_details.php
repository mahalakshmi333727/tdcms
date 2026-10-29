<?php
declare(strict_types=1);

$businessId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$businessModel = new Business($pdo);
$business = $businessId === false ? null : $businessModel->find((int) $businessId);
$pageTitle = 'Business Details';
require __DIR__ . '/../../layouts/header.php';
?>
<main class="container py-4">
  <?php foreach (get_flashes() as $type => $messages): ?>
    <?php foreach ($messages as $message): ?>
      <div class="alert alert-<?= e($type === 'error' ? 'danger' : $type) ?>" role="alert"><?= e($message) ?></div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if ($business === null): ?>
    <div class="alert alert-warning">The requested business could not be found.</div>
    <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/index.php?page=admin_assignments">Back to Assignments</a>
  <?php else: ?>
    <?php
    $members = $businessModel->users((int) $business['id']);
    $activeMembers = array_values(array_filter(
        $members,
        static fn (array $member): bool => $member['status'] === 'active'
    ));
    $availableUsers = $businessModel->activeUsers();
    $contentRows = $businessModel->content((int) $business['id']);
    $importRows = $businessModel->imports((int) $business['id']);
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h1 class="h4 mb-1"><?= e($business['name']) ?></h1>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>/index.php?page=admin_assignments">Businesses</a>
        <a class="btn btn-primary" href="<?= e(APP_URL) ?>/index.php?page=admin_business_form&amp;id=<?= (int) $business['id'] ?>">Edit Business</a>
      </div>
    </div>

    <div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
      <?php foreach ([
          'Reviews Count' => (int) $business['imported_reviews_count'],
          'Total Content' => (int) $business['total_content_count'],
          'Used Content' => (int) $business['used_content_count'],
          'Active Content' => (int) $business['active_content_count'],
          'Daily Limit' => (int) $business['daily_assignment_limit'],
          "Today's Assignments" => (int) $business['today_assigned_count'],
          'Remaining Today' => (int) $business['remaining_daily_capacity'],
      ] as $label => $value): ?>
        <div class="col"><div class="card h-100"><div class="card-body">
          <div class="small text-body-secondary"><?= e($label) ?></div>
          <div class="fs-4 fw-semibold"><?= $value ?></div>
        </div></div></div>
      <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-lg-5">
        <section class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Assigned User</h2>
            <span class="badge text-bg-secondary"><?= count($members) ?></span>
          </div>
          <div class="card-body">
            <?php if ($members === []): ?><p class="text-body-secondary mb-0">No users are currently assigned.</p><?php else: ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($members as $member): ?>
                  <li class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><?= e($member['full_name']) ?> <span class="text-body-secondary">(<?= e($member['username']) ?><?= $member['status'] === 'active' ? '' : ', disabled' ?>)</span></span>
                    <form method="post" action="<?= e(APP_URL) ?>/index.php?action=remove_business_user">
                      <?= csrf_field() ?>
                      <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
                      <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
          <?php if ($availableUsers !== []): ?>
            <div class="card-footer">
              <form method="post" action="<?= e(APP_URL) ?>/index.php?action=add_business_user" class="d-flex flex-wrap gap-2 js-submit-loading">
                <?= csrf_field() ?>
                <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
                <label class="visually-hidden" for="add-business-user"><?= $members !== [] ? 'Replace assigned user' : 'Assign user' ?></label>
                <select class="form-select form-select-sm flex-grow-1" id="add-business-user" name="user_id" required>
                  <option value=""><?= $members !== [] ? 'Select a user to replace current user' : 'Assign an active user' ?></option>
                  <?php foreach ($availableUsers as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e($user['full_name'] . ' (' . $user['username'] . ')') ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit"><?= $members !== [] ? 'Replace' : 'Assign' ?></button>
              </form>
            </div>
          <?php endif; ?>
        </section>
      </div>
      <div class="col-12 col-lg-7">
        <section class="card h-100">
          <div class="card-header"><h2 class="h6 mb-0">Business Information</h2></div>
          <div class="card-body">
            <p class="mb-2"><strong>Reference Link:</strong>
              <?php if ($business['reference_link']): ?><a href="<?= e($business['reference_link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($business['reference_link']) ?></a><?php else: ?>Not set<?php endif; ?>
            </p>
            <p class="mb-2"><strong>Content source:</strong> <?= (int) $business['total_content_count'] > 0 ? 'Imported content' : 'No content imported' ?></p>
            <p class="mb-0"><strong>Daily usage:</strong> <?= (int) $business['today_assigned_count'] ?> of <?= (int) $business['daily_assignment_limit'] ?> assigned today (Asia/Kolkata)</p>
          </div>
        </section>
      </div>
    </div>

    <section class="card mb-4">
      <div class="card-header"><h2 class="h6 mb-0">Import More Excel Content</h2></div>
      <div class="card-body">
        <form method="post" action="<?= e(APP_URL) ?>/index.php?action=import_business_excel" enctype="multipart/form-data" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
          <div class="col-12 col-md-9">
            <label class="form-label" for="excel-import">Excel file (.xlsx)</label>
            <input class="form-control" id="excel-import" name="excel_file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            <div class="form-text">There is no application-level record-count limit. Invalid and duplicate rows are reported in import history; server upload settings and available resources still apply.</div>
          </div>
          <div class="col-12 col-md-3"><button class="btn btn-primary w-100" type="submit">Import Excel</button></div>
        </form>
      </div>
    </section>

    <section class="card mb-4">
      <div class="card-header"><h2 class="h6 mb-0">Content and Assignments</h2></div>
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead><tr><th>Title / Content</th><th>Status</th><th>Assigned User</th><th>Assignment</th><th>Action</th></tr></thead>
          <tbody>
          <?php if ($contentRows === []): ?>
            <tr><td colspan="5" class="text-center text-body-secondary py-4">No content has been imported.</td></tr>
          <?php else: foreach ($contentRows as $item): ?>
            <?php
            $preview = function_exists('mb_strimwidth')
                ? mb_strimwidth($item['text_content'], 0, 180, '…')
                : substr($item['text_content'], 0, 180);
            if ($item['assignment_id'] === null) {
                $status = 'Available';
                $statusClass = 'text-bg-secondary';
            } elseif ($item['assignment_status'] === 'completed') {
                $status = 'Used';
                $statusClass = 'text-bg-success';
            } elseif ($item['assignment_archived_at'] !== null) {
                $status = 'Archived';
                $statusClass = 'text-bg-secondary';
            } else {
                $status = 'Active';
                $statusClass = 'text-bg-primary';
            }
            ?>
            <tr>
              <td><strong><?= e($item['title']) ?></strong><div class="small text-body-secondary"><?= e($preview) ?></div></td>
              <td><span class="badge <?= $statusClass ?>"><?= e($status) ?></span></td>
              <td><?= e($item['assigned_user'] ?? '—') ?></td>
              <td>
                <?php if ($item['assignment_id'] !== null): ?>
                  <a href="<?= e(APP_URL) ?>/index.php?page=admin_individual_assignments&amp;search=<?= rawurlencode((string) $item['title']) ?>">#<?= (int) $item['assignment_id'] ?></a>
                  <?php if ($item['completed_at']): ?><div class="small text-body-secondary">Completed <?= e($item['completed_at']) ?></div><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ($item['assignment_id'] === null && $activeMembers !== []): ?>
                  <form method="post" action="<?= e(APP_URL) ?>/index.php?action=assign_business_content" class="d-flex flex-wrap gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
                    <input type="hidden" name="content_id" value="<?= (int) $item['id'] ?>">
                    <label class="visually-hidden" for="content-user-<?= (int) $item['id'] ?>">Assign to user</label>
                    <select class="form-select form-select-sm" id="content-user-<?= (int) $item['id'] ?>" name="user_id" required>
                      <?php foreach ($activeMembers as $member): ?><option value="<?= (int) $member['id'] ?>"><?= e($member['full_name']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" type="submit" <?= (int) $business['remaining_daily_capacity'] < 1 ? 'disabled' : '' ?>>Assign</button>
                  </form>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2 class="h6 mb-0">Import History</h2></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Imported</th><th>File</th><th>Imported</th><th>Rejected</th><th>Result</th></tr></thead>
          <tbody>
          <?php if ($importRows === []): ?>
            <tr><td colspan="5" class="text-center text-body-secondary py-4">No imports yet.</td></tr>
          <?php else: foreach ($importRows as $importRow): ?>
            <?php $failureSummary = $importRow['failure_summary'] ? json_decode($importRow['failure_summary'], true) : []; ?>
            <tr>
              <td><?= e($importRow['created_at']) ?><div class="small text-body-secondary"><?= e($importRow['imported_by_name']) ?></div></td>
              <td><?= e($importRow['original_filename']) ?></td>
              <td><?= (int) $importRow['imported_count'] ?></td>
              <td><?= (int) $importRow['rejected_count'] ?></td>
              <td>
                <?php if (is_array($failureSummary) && $failureSummary !== []): ?>
                  <details><summary>Show rejected records</summary><ul class="small mt-2 mb-0"><?php foreach ($failureSummary as $failure): ?><li><?= e((string) $failure) ?></li><?php endforeach; ?></ul></details>
                <?php else: ?>No rejected records<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
