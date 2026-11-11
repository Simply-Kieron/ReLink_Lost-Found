<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$staff = require_role('staff');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    $status = $_POST['status'] ?? '';
    $decision = $_POST['decision'] ?? '';

    if ($itemId === false || $itemId === null || !is_string($action)) {
        http_response_code(400);
        exit('Choose a valid found item action.');
    }

    try {
        if ($action === 'review' && is_string($decision) && in_array($decision, ['approved', 'rejected'], true)) {
            $update = $pdo->prepare(
                "UPDATE items SET review_status = :review_status, reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP
                WHERE id = :id AND type = 'found' AND review_status = 'pending'"
            );
            $update->execute([
                'review_status' => $decision,
                'reviewed_by' => $staff['id'],
                'id' => $itemId,
            ]);
            if ($update->rowCount() !== 1) {
                $find = $pdo->prepare("SELECT id FROM items WHERE id = :id AND type = 'found'");
                $find->execute(['id' => $itemId]);
                if ($find->fetchColumn() === false) {
                    http_response_code(404);
                    exit('Found item not found.');
                }
                set_flash('error', 'This report is no longer awaiting review.');
                redirect('manage-found.php');
            }
            $successMessage = $decision === 'approved'
                ? 'The found-item report has been approved and is now public.'
                : 'The found-item report has been rejected and will not appear publicly.';
        } elseif ($action === 'delete') {
            require_once __DIR__ . '/../includes/item_uploads.php';

            $find = $pdo->prepare("SELECT image_path FROM items WHERE id = :id AND type = 'found' LIMIT 1");
            $find->execute(['id' => $itemId]);
            $item = $find->fetch();
            if ($item === false) {
                http_response_code(404);
                exit('Found item not found.');
            }

            $delete = $pdo->prepare("DELETE FROM items WHERE id = :id AND type = 'found'");
            $delete->execute(['id' => $itemId]);
            if ($delete->rowCount() !== 1) {
                http_response_code(404);
                exit('Found item not found.');
            }

            delete_item_image($item['image_path']);
            set_flash('success', 'The found item has been deleted.');
            redirect('manage-found.php');
        } elseif ($action === 'status' && is_string($status) && in_array($status, ['open', 'claimed', 'returned'], true)) {
            $update = $pdo->prepare(
                "UPDATE items SET status = :status WHERE id = :id AND type = 'found' AND review_status = 'approved'"
            );
            $update->execute(['status' => $status, 'id' => $itemId]);
            $successMessage = 'The found item status has been updated.';
        } else {
            http_response_code(400);
            exit('Choose a valid review decision or found item status.');
        }

        $find = $pdo->prepare("SELECT review_status FROM items WHERE id = :id AND type = 'found'");
        $find->execute(['id' => $itemId]);
        $currentReviewStatus = $find->fetchColumn();
        if ($currentReviewStatus === false) {
            http_response_code(404);
            exit('Found item not found.');
        }
        if ($action === 'status' && $currentReviewStatus !== 'approved') {
            set_flash('error', 'This report has changed and can no longer be updated with that action.');
            redirect('manage-found.php');
        }

        set_flash('success', $successMessage);
        redirect('manage-found.php');
    } catch (PDOException $exception) {
        error_log('Found item status update failed: ' . $exception->getMessage());
        http_response_code(500);
        $error = 'The requested action could not be completed. Please try again later.';
    }
}

$statement = $pdo->prepare(
    "SELECT items.id, items.title, items.description, items.location, items.date_event, items.status,
        items.review_status, items.image_path, items.image_alt, items.verification_detail,
        categories.name AS category_name, reporters.name AS reporter_name
    FROM items
    INNER JOIN categories ON categories.id = items.category_id
    INNER JOIN users AS reporters ON reporters.id = items.reported_by
    WHERE items.type = 'found'
    ORDER BY CASE WHEN items.review_status = 'pending' THEN 0 ELSE 1 END, items.created_at DESC"
);
$statement->execute();
$items = $statement->fetchAll();

$pageTitle = 'Manage Found Items';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell">
    <div class="surface-card" style="padding: 1.6rem;">
        <div class="section-header" style="margin-bottom: 1rem;">
            <div>
                <p class="eyebrow">Staff tools</p>
                <h1 id="manage-title">Manage found items</h1>
            </div>
            <a class="primary-button" href="upload-found.php">Upload found item</a>
        </div>

        <?php if (isset($error)): ?>
            <p class="error-box" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($items === []): ?>
            <p class="empty-state">There are no found item listings yet.</p>
        <?php else: ?>
            <ul class="claim-list" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                <?php foreach ($items as $item): ?>
                    <li>
                        <article class="claim-card" style="height: 100%;">
                            <div class="claim-card-head">
                                <h2 style="margin: 0;"><?= e($item['title']) ?></h2>
                                <span class="status-pill <?= e(['pending' => 'status-pending', 'approved' => 'status-approved', 'rejected' => 'status-rejected'][$item['review_status']] ?? 'status-open') ?>"><?= e(ucfirst($item['review_status'])) ?> review</span>
                            </div>
                            <p class="field-note" style="margin: 0;">Reported by <?= e($item['reporter_name']) ?> · <?= e(ucwords($item['status'])) ?></p>
                            <p class="field-note" style="margin: 0;">
                                <?= e($item['category_name']) ?> · Found <?= e($item['date_event']) ?>
                            </p>
                            <p style="margin: 0; white-space: pre-line; color: var(--palette-muted);"><?= e($item['description']) ?></p>
                            <p class="field-note" style="margin: 0;"><strong style="color: var(--palette-text);">Location:</strong> <?= e($item['location']) ?></p>
                            <?php if ($item['image_path'] !== null): ?>
                                <img style="display:block; width:100%; max-height: 14rem; object-fit: contain; border-radius: 1rem; background: #eef4ff; border: 1px solid var(--palette-border);" src="../<?= e($item['image_path']) ?>" alt="<?= e(item_image_alt($item['image_alt'], 'Found item: ' . $item['title'])) ?>">
                            <?php endif; ?>
                            <details class="proof-box" style="margin-top: 0;">
                                <summary style="cursor:pointer; font-weight:700;">Private verification details</summary>
                                <p style="margin: 0.75rem 0 0; white-space: pre-line; color: var(--palette-muted);"><?= e($item['verification_detail']) ?></p>
                            </details>
                            <?php if ($item['review_status'] === 'pending'): ?>
                                <form method="post" action="manage-found.php" class="action-row">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                                    <input type="hidden" name="action" value="review">
                                    <button class="primary-button" type="submit" name="decision" value="approved">Approve and publish</button>
                                    <button class="secondary-button" type="submit" name="decision" value="rejected">Reject</button>
                                </form>
                            <?php elseif ($item['review_status'] === 'approved'): ?>
                                <form method="post" action="manage-found.php" style="display: grid; gap: 0.8rem; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                                    <input type="hidden" name="action" value="status">
                                    <div class="field" style="margin-top: 0;">
                                        <label for="status-<?= e($item['id']) ?>">Public item status</label>
                                        <select id="status-<?= e($item['id']) ?>" name="status">
                                            <?php foreach (['open' => 'Open', 'claimed' => 'Claimed', 'returned' => 'Returned'] as $statusValue => $statusLabel): ?>
                                                <option value="<?= e($statusValue) ?>" <?= $item['status'] === $statusValue ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="action-row">
                                        <button class="primary-button" type="submit">Update status</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            <div class="action-row">
                                <a class="secondary-button" href="edit-found.php?id=<?= e($item['id']) ?>">Edit</a>
                                <a class="ghost-button" href="manage-found.php"
                                    data-confirm-record-id="<?= e($item['id']) ?>"
                                    data-confirm-record-name="item_id"
                                    data-confirm-action="delete"
                                    data-confirm-title="Delete found item?"
                                    data-confirm-message="This will permanently delete “<?= e($item['title']) ?>”. Any claims linked to the item will also be deleted."
                                    data-confirm-button="Delete item"
                                    data-confirm-tone="danger">Delete</a>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
