<?php
declare(strict_types=1);
$pageTitle = 'My Tasks';

$assignmentModel = new Assignment($pdo);
$pending = $assignmentModel->pendingForUser((int) current_user_id());

require __DIR__ . '/../../layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="mb-0">Welcome, <?= e($_SESSION['user_name'] ?? '') ?> 👋</h4>
    <p class="text-muted mb-0">You have <span id="pendingCountBadge" class="fw-semibold"><?= count($pending) ?></span> pending item(s) to review.</p>
  </div>
</div>

<?php if (empty($pending)): ?>
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
    <?php foreach ($pending as $item): ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card assignment-card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="card-title mb-0"><?= e($item['title'] ?: 'Assigned Text') ?></h6>
              <span class="badge badge-pending text-white">Pending</span>
            </div>
            <?php if (!empty($item['category'])): ?>
              <span class="badge text-bg-secondary mb-2 align-self-start"><i class="bi bi-tag"></i> <?= e($item['category']) ?></span>
            <?php endif; ?>
            <div class="assignment-text mb-2 flex-grow-1"><?= e($item['text_content']) ?></div>
            <?php if (!empty($item['tags'])): ?>
              <div class="mb-2">
                <?php foreach (array_filter(array_map('trim', explode(',', $item['tags']))) as $tag): ?>
                  <span class="badge text-bg-light border me-1"><?= e($tag) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <p class="text-muted small mb-3"><i class="bi bi-clock"></i> Assigned <?= e(format_datetime($item['assigned_at'])) ?></p>

            <?php $hasLink = !empty($item['reference_link']); ?>

            <button type="button" class="btn btn-outline-secondary btn-copy-text mb-2" data-id="<?= (int) $item['id'] ?>">
              <i class="bi bi-clipboard"></i> Copy Text
            </button>

            <?php if ($hasLink): ?>
              <a href="<?= e($item['reference_link']) ?>" target="_blank" rel="noopener noreferrer"
                 class="btn btn-outline-primary btn-open-reference mb-2" data-id="<?= (int) $item['id'] ?>">
                <i class="bi bi-box-arrow-up-right"></i> Open Reference Link
              </a>
              <p class="text-muted small mb-2 unlock-hint" data-id="<?= (int) $item['id'] ?>">
                <i class="bi bi-info-circle"></i> Open the reference link above to unlock Assign.
              </p>
            <?php else: ?>
              <p class="text-muted small mb-2 unlock-hint" data-id="<?= (int) $item['id'] ?>">
                <i class="bi bi-info-circle"></i> Copy the text above to unlock Assign.
              </p>
            <?php endif; ?>

            <button type="button" class="btn btn-primary btn-assign-complete mt-auto"
                    data-id="<?= (int) $item['id'] ?>"
                    data-has-link="<?= $hasLink ? '1' : '0' ?>"
                    disabled>
              <i class="bi bi-check2-circle"></i> Assign
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
