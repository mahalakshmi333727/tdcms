<?php
declare(strict_types=1);
$pageTitle = 'My Tasks';

$assignmentModel = new Assignment($pdo);
$page = max(1, (int) ($_GET['p'] ?? 1));
$pending = $assignmentModel->pendingPageForUser((int) current_user_id(), $page);
$paginationParams = ['page' => 'dashboard'];
$paginationStart = 0;
$paginationEnd = 0;
if ($pending['pages'] > 7) {
    $paginationStart = max(2, min($pending['page'] - 1, $pending['pages'] - 2));
    $paginationEnd = min($pending['pages'] - 1, max($pending['page'] + 1, 3));
}

require __DIR__ . '/../../layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="mb-0">Welcome, <?= e($_SESSION['user_name'] ?? '') ?> 👋</h4>
    <p class="text-muted mb-0">You have <span id="pendingCountBadge" class="fw-semibold"><?= (int) $pending['total'] ?></span> pending item(s) to review.</p>
  </div>
</div>

<?php if (empty($pending['rows'])): ?>
  <div class="card text-center py-5" id="emptyState">
    <div class="card-body">
      <i class="bi bi-inbox display-3 text-muted"></i>
      <h5 class="mt-3">No pending items</h5>
      <p class="text-muted mb-0">You're all caught up. New assignments will appear here.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card text-center py-5 d-none" id="emptyState">
    <div class="card-body">
      <i class="bi bi-inbox display-3 text-muted"></i>
      <h5 class="mt-3">No pending items</h5>
      <p class="text-muted mb-0">You're all caught up. New assignments will appear here.</p>
    </div>
  </div>
  <div class="row g-3">
    <?php foreach ($pending['rows'] as $item): ?>
      <?php
      $textContent = (string) ($item['text_content'] ?? '');
      $hasTextContent = trim($textContent) !== '';
      ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card assignment-card h-100" data-prompt-pending="<?= !empty($item['completion_prompt_pending']) ? '1' : '0' ?>" data-has-content="<?= $hasTextContent ? '1' : '0' ?>">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="card-title mb-0"><?= e($item['business_name']) ?></h6>
              <span class="badge badge-pending text-white">Pending</span>
            </div>
            <span class="badge text-bg-secondary mb-2 align-self-start"><i class="bi bi-tag"></i> <?= e($item['task_type']) ?></span>
            <div class="assignment-text mb-2 flex-grow-1" data-id="<?= (int) $item['id'] ?>" tabindex="-1"><?= e($textContent) ?></div>
            <?php if (!empty($item['tags'])): ?>
              <div class="mb-2">
                <?php foreach (array_filter(array_map('trim', explode(',', $item['tags']))) as $tag): ?>
                  <span class="badge text-bg-light border me-1"><?= e($tag) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <p class="text-muted small mb-3"><i class="bi bi-clock"></i> Assigned <?= e(format_datetime($item['assigned_at'])) ?></p>

            <?php $hasLink = !empty($item['has_reference_link']); ?>
            <?php $textCopied = (bool) ($item['text_copied'] ?? false); ?>
            <?php $linkClicked = (bool) ($item['link_clicked'] ?? false); ?>

            <div class="mb-3 small">
              <div class="d-flex align-items-center gap-2 <?= $textCopied ? 'text-success' : 'text-muted' ?>">
                <span><?= $textCopied ? '✓' : '○' ?></span>
                <span>Text Copied</span>
              </div>
              <div class="d-flex align-items-center gap-2 <?= $linkClicked ? 'text-success' : 'text-muted' ?>">
                <span><?= $linkClicked ? '✓' : '○' ?></span>
                <span>Link Visited</span>
              </div>
              <?php if ($textCopied && $linkClicked): ?>
                <div class="d-flex align-items-center gap-2 text-success mt-1">
                  <span>✓</span>
                  <span>Task Completed</span>
                </div>
              <?php endif; ?>
            </div>

            <button type="button" class="btn btn-outline-secondary btn-copy-text mb-2" data-id="<?= (int) $item['id'] ?>" data-copied="<?= $textCopied ? '1' : '0' ?>" <?= !$hasTextContent ? 'disabled' : '' ?>>
              <i class="bi bi-clipboard"></i> Copy Text
            </button>

            <?php if ($hasLink): ?>
              <button type="button"
                 class="btn btn-outline-primary btn-open-reference mb-2" data-id="<?= (int) $item['id'] ?>" data-clicked="<?= $linkClicked ? '1' : '0' ?>" <?= (!$hasTextContent || !$textCopied) ? 'disabled' : '' ?>>
                <i class="bi bi-box-arrow-up-right"></i> Open Reference Link
              </button>
            <?php endif; ?>

            <?php if ($hasTextContent): ?>
              <div class="alert alert-light border small mb-2 text-muted status-hint" data-id="<?= (int) $item['id'] ?>">
                <?php if ($textCopied && (!$hasLink || $linkClicked)): ?>
                  The required steps are complete. Confirm when you have finished the task.
                <?php elseif ($textCopied || $linkClicked): ?>
                  One step completed. Finish the remaining action to close the task.
                <?php else: ?>
                  Copy the text and open the reference link to complete this task.
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($pending['pages'] > 1): ?>
    <nav class="mt-3" aria-label="Pending tasks pagination">
      <ul class="pagination justify-content-center">
        <?php if ($pending['page'] > 1): ?>
          <li class="page-item">
            <a class="page-link" aria-label="Previous page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $pending['page'] - 1])) ?>">&larr;</a>
          </li>
        <?php else: ?>
          <li class="page-item disabled"><span class="page-link" aria-label="Previous page">&larr;</span></li>
        <?php endif; ?>

        <?php if ($pending['pages'] <= 7): ?>
          <?php for ($i = 1; $i <= $pending['pages']; $i++): ?>
            <li class="page-item <?= $i === $pending['page'] ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $i])) ?>" <?= $i === $pending['page'] ? 'aria-current="page"' : '' ?>><?= $i ?></a>
            </li>
          <?php endfor; ?>
        <?php else: ?>
          <li class="page-item <?= $pending['page'] === 1 ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => 1])) ?>" <?= $pending['page'] === 1 ? 'aria-current="page"' : '' ?>>1</a>
          </li>
          <?php if ($paginationStart > 2): ?>
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <?php endif; ?>
          <?php for ($i = $paginationStart; $i <= $paginationEnd; $i++): ?>
            <li class="page-item <?= $i === $pending['page'] ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $i])) ?>" <?= $i === $pending['page'] ? 'aria-current="page"' : '' ?>><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <?php if ($paginationEnd < $pending['pages'] - 1): ?>
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <?php endif; ?>
          <li class="page-item <?= $pending['page'] === $pending['pages'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $pending['pages']])) ?>" <?= $pending['page'] === $pending['pages'] ? 'aria-current="page"' : '' ?>><?= $pending['pages'] ?></a>
          </li>
        <?php endif; ?>

        <?php if ($pending['page'] < $pending['pages']): ?>
          <li class="page-item">
            <a class="page-link" aria-label="Next page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $pending['page'] + 1])) ?>">&rarr;</a>
          </li>
        <?php else: ?>
          <li class="page-item disabled"><span class="page-link" aria-label="Next page">&rarr;</span></li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<div class="task-confirm-modal" id="taskConfirmModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="taskConfirmTitle">
  <div class="task-confirm-backdrop"></div>
  <div class="task-confirm-dialog" role="document">
    <p id="taskConfirmTitle" class="task-confirm-question">Is the task completed?</p>
    <div class="task-confirm-actions">
      <button type="button" class="btn btn-light task-confirm-cancel">No, Task Not Completed</button>
      <button type="button" class="btn btn-primary task-confirm-submit" id="confirmTaskCompletion">
        Yes, Task Completed
      </button>
    </div>
  </div>
</div>

<div class="task-confirm-modal task-confirm-modal-secondary" id="taskNoConfirmationModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="taskNoConfirmationTitle">
  <div class="task-confirm-backdrop"></div>
  <div class="task-confirm-dialog" role="document">
    <p id="taskNoConfirmationTitle" class="task-confirm-question">Are you sure?</p>
    <div class="task-confirm-actions">
      <button type="button" class="btn btn-light" id="cancelTaskNoConfirmation">No</button>
      <button type="button" class="btn btn-primary" id="confirmTaskNoConfirmation">Yes</button>
    </div>
  </div>
</div>

<div class="task-confirm-modal" id="taskSuccessModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="taskSuccessTitle">
  <div class="task-confirm-backdrop" data-success-close></div>
  <div class="task-confirm-dialog" role="document">
    <div class="task-confirm-icon"><i class="bi bi-check2-circle"></i></div>
    <h2 id="taskSuccessTitle">&#10003; Task Completed</h2>
    <p class="task-confirm-question">Your task has been successfully marked as completed.</p>
    <div class="task-confirm-actions">
      <button type="button" class="btn btn-primary task-confirm-submit" id="closeTaskSuccess">Done</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>