<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$staff = require_role('staff');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $claimId = filter_var($_POST['claim_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $decision = $_POST['decision'] ?? '';
    if ($claimId === false || $claimId === null || !is_string($decision) || !in_array($decision, ['approve', 'reject'], true)) {
        http_response_code(400);
        exit('Choose a valid claim and review action.');
    }

    try {
        $pdo->beginTransaction();

        $findClaimItem = $pdo->prepare('SELECT item_id FROM claims WHERE id = :id');
        $findClaimItem->execute(['id' => $claimId]);
        $itemId = $findClaimItem->fetchColumn();
        if ($itemId === false) {
            $pdo->rollBack();
            http_response_code(404);
            exit('Claim not found.');
        }

        $lockItem = $pdo->prepare("SELECT id, status, review_status FROM items WHERE id = :id AND type = 'found' FOR UPDATE");
        $lockItem->execute(['id' => (int) $itemId]);
        $lockedItem = $lockItem->fetch();
        $lockClaim = $pdo->prepare(
            'SELECT id, item_id, status FROM claims WHERE id = :id AND item_id = :item_id FOR UPDATE'
        );
        $lockClaim->execute(['id' => $claimId, 'item_id' => (int) $itemId]);
        $lockedClaim = $lockClaim->fetch();

        if ($lockedItem === false || $lockedClaim === false) {
            $pdo->rollBack();
            http_response_code(404);
            exit('Claim not found.');
        }
        if ($lockedClaim['status'] !== 'pending') {
            $pdo->rollBack();
            set_flash('error', 'This claim has already been reviewed.');
            redirect('review-claims.php');
        }
        if ($decision === 'approve' && ($lockedItem['review_status'] !== 'approved' || $lockedItem['status'] !== 'open')) {
            $pdo->rollBack();
            set_flash('error', 'This item is no longer open, so the claim cannot be approved.');
            redirect('review-claims.php');
        }

        $newClaimStatus = $decision === 'approve' ? 'approved' : 'rejected';
        $updateClaim = $pdo->prepare(
            "UPDATE claims SET status = :status, reviewed_by = :reviewed_by
            WHERE id = :id AND status = 'pending'"
        );
        $updateClaim->execute([
            'status' => $newClaimStatus,
            'reviewed_by' => $staff['id'],
            'id' => $claimId,
        ]);
        if ($updateClaim->rowCount() !== 1) {
            throw new RuntimeException('The claim could not be transitioned from pending.');
        }

        if ($decision === 'approve') {
            $updateItem = $pdo->prepare(
                "UPDATE items SET status = 'returned' WHERE id = :id AND status = 'open'"
            );
            $updateItem->execute(['id' => (int) $itemId]);
            if ($updateItem->rowCount() !== 1) {
                throw new RuntimeException('The item could not be marked returned.');
            }

            $rejectOtherClaims = $pdo->prepare(
                "UPDATE claims SET status = 'rejected', reviewed_by = :reviewed_by
                WHERE item_id = :item_id AND id <> :claim_id AND status = 'pending'"
            );
            $rejectOtherClaims->execute([
                'reviewed_by' => $staff['id'],
                'item_id' => (int) $itemId,
                'claim_id' => $claimId,
            ]);
        } else {
            $reopenItem = $pdo->prepare(
                "UPDATE items SET status = 'open'
                WHERE id = :id AND status = 'claimed' AND review_status = 'approved'"
            );
            $reopenItem->execute(['id' => (int) $itemId]);
        }

        $pdo->commit();
        set_flash('success', $decision === 'approve' ? 'The claim was approved and the item marked returned.' : 'The claim was rejected.');
        redirect('review-claims.php');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Claim review failed: ' . $exception->getMessage());
        http_response_code(500);
        $reviewError = 'The claim could not be reviewed. No changes were saved. Please try again later.';
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Claim review transaction failed: ' . $exception->getMessage());
        http_response_code(500);
        $reviewError = 'The claim could not be reviewed. No changes were saved. Please try again later.';
    }
}

$statement = $pdo->prepare(
    "SELECT claims.id, claims.item_id, claims.proof_description, claims.created_at,
        items.title, items.location, items.date_event, items.verification_detail,
        claimants.name AS claimant_name, claimants.email AS claimant_email,
        categories.name AS category_name
    FROM claims
    INNER JOIN items ON items.id = claims.item_id
    INNER JOIN users AS claimants ON claimants.id = claims.claimant_id
    INNER JOIN categories ON categories.id = items.category_id
    WHERE claims.status = 'pending'
    ORDER BY claims.created_at ASC, claims.id ASC"
);
$statement->execute();
$claims = $statement->fetchAll();

$pageTitle = 'Review Claims';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell">
    <div class="section-header">
        <div>
            <span class="eyebrow">Campus review queue</span>
            <h1>Pending claims</h1>
        </div>
        <span class="status-pill status-pending"><?= count($claims) ?> pending</span>
    </div>

    <?php if (isset($reviewError)): ?>
        <p class="error-box" role="alert"><?= e($reviewError) ?></p>
    <?php endif; ?>

    <?php if ($claims === []): ?>
        <div class="empty-state mt-4">There are no pending claims to review.</div>
    <?php else: ?>
        <ul class="review-list">
            <?php foreach ($claims as $claim): ?>
                <li class="review-card">
                    <div class="claim-card-head">
                        <h2><?= e($claim['title']) ?></h2>
                        <span class="status-pill status-pending">Pending</span>
                    </div>

                    <div class="claim-meta">
                        <span><?= e($claim['category_name']) ?></span>
                        <span>Found <?= e($claim['date_event']) ?></span>
                        <span><?= e($claim['location']) ?></span>
                    </div>

                    <div class="info-grid">
                        <div class="info-box">
                            <strong>Claimant</strong>
                            <p><?= e($claim['claimant_name']) ?> · <?= e($claim['claimant_email']) ?></p>
                        </div>
                        <div class="info-box">
                            <strong>Submitted</strong>
                            <p><?= e($claim['created_at']) ?></p>
                        </div>
                    </div>

                    <div class="proof-box">
                        <strong>Proof provided</strong>
                        <p><?= e($claim['proof_description']) ?></p>
                    </div>

                    <div class="verification-box">
                        <strong>Private verification details</strong>
                        <p><?= e($claim['verification_detail'] ?? '') ?></p>
                    </div>

                    <div class="action-row">
                        <a class="primary-button" href="review-claims.php"
                            data-confirm-record-id="<?= e($claim['id']) ?>"
                            data-confirm-record-name="claim_id"
                            data-confirm-title="Approve this claim?"
                            data-confirm-message="Approving this claim marks the item returned and rejects all other pending claims for it."
                            data-confirm-button="Approve claim"
                            data-confirm-decision="approve">Approve</a>
                        <a class="ghost-button" href="review-claims.php"
                            data-confirm-record-id="<?= e($claim['id']) ?>"
                            data-confirm-record-name="claim_id"
                            data-confirm-title="Reject this claim?"
                            data-confirm-message="This will reject the claim from <?= e($claim['claimant_name']) ?>. The item will remain available for other claims."
                            data-confirm-button="Reject claim"
                            data-confirm-decision="reject"
                            data-confirm-tone="danger">Reject</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
