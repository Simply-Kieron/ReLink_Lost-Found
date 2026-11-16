<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/item_uploads.php';

$staff = require_role('staff');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $decision = $_POST['decision'] ?? '';

    if ($itemId === false || $itemId === null || !is_string($decision) || !in_array($decision, ['approved', 'rejected'], true)) {
        http_response_code(400);
        exit('Choose a valid lost report and review decision.');
    }

    try {
        $update = $pdo->prepare(
            "UPDATE items
            SET review_status = :review_status, reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :id AND type = 'lost' AND review_status <> :current_review_status"
        );
        $update->execute([
            'review_status' => $decision,
            'reviewed_by' => $staff['id'],
            'id' => $itemId,
            'current_review_status' => $decision,
        ]);

        if ($update->rowCount() !== 1) {
            $find = $pdo->prepare("SELECT review_status FROM items WHERE id = :id AND type = 'lost'");
            $find->execute(['id' => $itemId]);
            $currentStatus = $find->fetchColumn();
            if ($currentStatus === false) {
                http_response_code(404);
                exit('Lost item report not found.');
            }
            set_flash('error', 'This lost report has already changed. Refresh the page and try again.');
            redirect('manage-lost.php');
        }

        set_flash(
            'success',
            $decision === 'approved'
                ? 'The lost report is approved and visible to students.'
                : 'The lost report was rejected and hidden from public listings.'
        );
        redirect('manage-lost.php');
    } catch (PDOException $exception) {
        error_log('Lost item review failed: ' . $exception->getMessage());
        http_response_code(500);
        $error = 'The lost report could not be updated. Please try again later.';
    }
}

try {
    $statement = $pdo->prepare(
        "SELECT items.id, items.title, items.description, items.location, items.date_event,
            items.status, items.review_status, items.created_at, items.image_path, items.image_alt,
            categories.name AS category_name, reporters.name AS reporter_name
        FROM items
        INNER JOIN categories ON categories.id = items.category_id
        INNER JOIN users AS reporters ON reporters.id = items.reported_by
        WHERE items.type = 'lost'
        ORDER BY CASE WHEN items.review_status = 'rejected' THEN 1 ELSE 0 END,
            items.created_at DESC, items.id DESC"
    );
    $statement->execute();
    $reports = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('Lost item management query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Lost reports are temporarily unavailable.');
}

$pageTitle = 'Manage Lost Reports';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell staff-item-management" aria-labelledby="manage-lost-title">
    <div class="surface-card staff-management-surface">
        <div class="section-header" style="margin-bottom: 1rem;">
            <div>
                <p class="eyebrow">Staff tools</p>
                <h1 id="manage-lost-title">Manage lost reports</h1>
                <p>Reject reports that should not be public, or restore a rejected report. Rejected reports are retained for review.</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <p class="error-box" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($reports === []): ?>
            <p class="empty-state">There are no lost item reports yet.</p>
        <?php else: ?>
            <ul class="claim-list browse-grid feed-item-list staff-management-list">
                <?php foreach ($reports as $report): ?>
                    <?php
                    $isRejected = $report['review_status'] === 'rejected';
                    $reviewLabel = $isRejected
                        ? 'Rejected · hidden'
                        : ($report['review_status'] === 'pending' ? 'Pending review' : 'Approved · public');
                    ?>
                    <li>
                        <article class="claim-card browse-card feed-card" style="height: 100%;">
                            <div class="relink-item-media">
                            <?php if ($report['image_path'] !== null): ?>
                                <img class="relink-item-image" src="../<?= e($report['image_path']) ?>" alt="<?= e(item_image_alt($report['image_alt'], 'Lost item: ' . $report['title'])) ?>">
                            <?php else: ?>
                                <div class="media-placeholder">No image provided</div>
                            <?php endif; ?>
                            </div>
                            <div class="relink-item-copy">
                            <div class="claim-card-head">
                                <h2 style="margin: 0;"><?= e($report['title']) ?></h2>
                                <span class="status-pill <?= $isRejected ? 'status-rejected' : ($report['review_status'] === 'pending' ? 'status-pending' : 'status-approved') ?>"><?= e($reviewLabel) ?></span>
                            </div>
                            <p class="field-note" style="margin: 0;">Reported by <?= e($report['reporter_name']) ?> · <?= e(ucfirst($report['status'])) ?></p>
                            <p class="field-note" style="margin: 0;"><?= e($report['category_name']) ?> · Lost <?= e($report['date_event']) ?></p>
                            <p class="field-note" style="margin: 0;"><strong style="color: var(--palette-text);">Last seen at:</strong> <?= e($report['location']) ?></p>
                            </div>
                            <p class="browse-card-copy relink-item-description"><span class="relink-description-label">Description</span><span class="relink-description-text"><?= e($report['description']) ?></span></p>
                            <div class="staff-card-controls">

                            <p class="field-note">Submitted <?= e($report['created_at']) ?></p>
                            <form method="post" action="manage-lost.php" class="action-row">
                                <?= csrf_field() ?>
                                <input type="hidden" name="item_id" value="<?= e($report['id']) ?>">
                                <?php if ($isRejected): ?>
                                    <button class="primary-button" type="submit" name="decision" value="approved">Restore and publish</button>
                                <?php else: ?>
                                    <button class="secondary-button" type="submit" name="decision" value="rejected">Reject and hide</button>
                                <?php endif; ?>
                            </form>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
