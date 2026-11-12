<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(404);
    exit('Found item not found.');
}

$statement = $pdo->prepare(
    "SELECT items.id, items.title, items.description, items.location, items.date_event, items.status,
        items.image_path, items.image_alt, items.reported_by, categories.name AS category_name
    FROM items
    INNER JOIN categories ON categories.id = items.category_id
    WHERE items.id = :id AND items.type = 'found' AND items.review_status = 'approved'
    LIMIT 1"
);
$statement->execute(['id' => $id]);
$item = $statement->fetch();
if ($item === false) {
    http_response_code(404);
    exit('Found item not found.');
}

$user = current_user();
$errors = [];
$canClaim = $item['status'] === 'open';
$itemStatusLabel = [
    'claimed' => 'Already claimed',
    'returned' => 'Returned to owner',
][$item['status']] ?? ucfirst(str_replace('_', ' ', $item['status']));
$ownItem = $user !== null && $user['role'] === 'student' && (int) $item['reported_by'] === $user['id'];
$isVerifiedStudent = false;
if ($user !== null && $user['role'] === 'student') {
    $verificationStatus = $pdo->prepare(
        "SELECT status FROM student_verifications WHERE user_id = :user_id LIMIT 1"
    );
    $verificationStatus->execute(['user_id' => $user['id']]);
    $isVerifiedStudent = $verificationStatus->fetchColumn() === 'verified';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student = require_verified_student();
    verify_csrf_token();
    $brandInput = $_POST['bt_name'] ?? '';
    $brand = is_string($brandInput) ? trim($brandInput) : '';
    $detailInput = $_POST['case_detail'] ?? '';
    $caseDetail = is_string($detailInput) ? trim($detailInput) : '';
    $proofDescription = implode("\n\n", [$brand, $caseDetail]);
    $consent = $_POST['consent'] ?? '';

    $proofCharacterCount = preg_match_all('/./us', $proofDescription);
    if ($brand === '' || $caseDetail === '') {
        $errors[] = 'Please fill in both identifying detail fields.';
    } elseif ($proofCharacterCount === false || $proofCharacterCount > 1000) {
        $errors[] = 'Please keep your identifying details to 1,000 characters or fewer.';
    }

    if ($consent !== '1') {
        $errors[] = 'You must agree to the Privacy Policy and Terms before submitting a claim.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $lockItem = $pdo->prepare(
                "SELECT id, reported_by, status, review_status FROM items WHERE id = :id AND type = 'found' FOR UPDATE"
            );
            $lockItem->execute(['id' => $id]);
            $lockedItem = $lockItem->fetch();

            if ($lockedItem === false) {
                $pdo->rollBack();
                http_response_code(404);
                exit('Found item not found.');
            }

            if ((int) $lockedItem['reported_by'] === $student['id']) {
                $pdo->rollBack();
            } elseif ($lockedItem['review_status'] !== 'approved' || $lockedItem['status'] !== 'open') {
                $pdo->rollBack();
                $errors[] = 'This item is no longer available to claim.';
            } else {
                $pendingClaim = $pdo->prepare(
                    "SELECT id FROM claims WHERE item_id = :item_id AND claimant_id = :claimant_id AND status = 'pending' LIMIT 1"
                );
                $pendingClaim->execute(['item_id' => $id, 'claimant_id' => $student['id']]);

                if ($pendingClaim->fetchColumn() !== false) {
                    $pdo->rollBack();
                    $errors[] = 'You already have a pending claim for this item.';
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO claims (item_id, claimant_id, proof_description, status)
                        VALUES (:item_id, :claimant_id, :proof_description, 'pending')"
                    );
                    $insert->execute([
                        'item_id' => $id,
                        'claimant_id' => $student['id'],
                        'proof_description' => $proofDescription,
                    ]);
                    $pdo->commit();
                    set_flash('success', 'Your claim has been submitted for staff review.');
                    redirect('my-claims.php');
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Claim submission failed: ' . $exception->getMessage());
            http_response_code(500);
            $errors[] = 'The claim could not be submitted right now. Please try again later.';
        }
    }
}

$pageTitle = 'Found Item Details';
$pageDesign = 'detail';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell item-detail-shell">
    <div class="page-topbar detail-topbar">
        <a class="subtle-link" href="found-items.php">Back to feed</a>
        <div class="mini-actions">
            <span class="detail-page-label">Item detail</span>
        </div>
    </div>

    <div class="detail-gallery surface-card">
        <div class="detail-media">
            <?php
            $normalizedCategory = strtolower(trim($item['category_name']));
            $sensitiveCategory = $normalizedCategory === 'personal items'
                || in_array($normalizedCategory, ['id', 'ids', 'phone', 'phones', 'wallet', 'wallets'], true);
            ?>
            <div class="media-frame">
                <?php if ($sensitiveCategory): ?>
                    <div class="media-placeholder" role="img" aria-label="Image not displayed for this sensitive item category">Image hidden for privacy</div>
                <?php elseif ($item['image_path'] !== null): ?>
                    <img src="../<?= e($item['image_path']) ?>" alt="<?= e(item_image_alt($item['image_alt'], 'Found item: ' . $item['title'])) ?>">
                <?php else: ?>
                    <div class="media-placeholder">No image provided</div>
                <?php endif; ?>
                <span class="serial-redacted">◈ &nbsp; PRIVATE DETAILS HIDDEN</span>
                <?php if ($item['image_path'] !== null && !$sensitiveCategory): ?>
                    <span class="detail-image-count">1 / 1</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <section class="surface-card detail-summary" aria-labelledby="item-title">
        <div class="detail-summary-meta"><span class="secure-label"><?= e($itemStatusLabel) ?></span><span><?= e($item['date_event']) ?></span></div>
        <h1 id="item-title"><?= e($item['title']) ?></h1>
        <p class="detail-category"><?= e($item['category_name']) ?></p>
        <div class="detail-info-list">
            <div class="detail-info-row"><span class="detail-info-icon" aria-hidden="true">⌖</span><div><small>Found location</small><strong><?= e($item['location']) ?></strong><span>Location supplied by campus staff</span></div></div>
            <div class="detail-info-row"><span class="detail-info-icon" aria-hidden="true">▣</span><div><small>Item status</small><strong><?= e(ucwords(str_replace('_', ' ', $item['status']))) ?></strong><span>Inventory ID: #LF-<?= e($id) ?></span></div></div>
        </div>
        <div class="detail-public-description"><small>Item description</small><p><?= e($item['description']) ?></p></div>
    </section>

    <aside class="security-callout detail-security">
        <span class="security-icon" aria-hidden="true">◈</span>
        <div><h2>Ownership verification protocol</h2><p>To prevent false claims and protect students, staff will verify ownership using details that are not shown in this public listing.</p></div>
    </aside>

    <section class="surface-card detail-claim-card" aria-labelledby="claim-title">
        <div class="claim-card-intro"><span class="availability-dot" aria-hidden="true"></span><span><?= $ownItem ? 'Your found item' : 'Is this yours?' ?></span><?php if (!$ownItem): ?><span class="claim-review-label">Staff verification</span><?php endif; ?></div>
        <h2 id="claim-title"><?= $ownItem ? 'You reported this item' : 'Claim this item' ?></h2>
        <?php if ($ownItem): ?>
            <p>This is your found-item report, so you don’t need to submit a claim for it.</p>
        <?php else: ?>
            <p>Fill in both fields with identifying details so staff can verify that the item belongs to you.</p>
            <div class="proof-chips" aria-label="Examples of useful proof"><span>Brand or model</span><span>Distinctive feature</span><span>Engraving or mark</span></div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <div class="error-box mt-4" role="alert">
                <strong>Please review the following:</strong>
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if (!$ownItem && $user !== null && $user['role'] === 'student' && !$isVerifiedStudent): ?>
            <p class="field-note mt-4">Your student account is not verified, so you can’t submit a claim yet. Verify your school ID and enter the code sent to your school email; then return here to claim this item.</p>
            <a class="primary-button inline-block mt-3" href="profile.php">Verify your student account</a>
        <?php elseif (!$ownItem && !$canClaim): ?>
            <p class="empty-state mt-4"><?= $item['status'] === 'claimed' ? 'This item has already been claimed.' : ($item['status'] === 'returned' ? 'This item has already been returned to its owner.' : 'This item is not currently open for claims.') ?></p>
        <?php elseif (!$ownItem && ($user === null || $user['role'] !== 'student')): ?>
            <p class="field-note mt-4">Log in with a student account to submit a claim.</p>
            <a class="primary-button inline-block mt-3" href="login.php">Log in to claim</a>
        <?php elseif (!$ownItem): ?>
            <button class="primary-button verification-button" type="button" data-open-claim>Claim this item</button>
        <?php endif; ?>
    </section>

    <section class="surface-card pickup-card">
        <div class="pickup-heading"><span class="pickup-icon" aria-hidden="true">⌖</span><div><h2>Campus lost &amp; found desk</h2><p>Ask staff for help with collection</p></div><span class="desk-open-label">Campus office</span></div>
        <div class="pickup-hours"><span>⌚ &nbsp; Next steps</span><strong>Staff will provide collection instructions after verifying your claim.</strong></div>
        <p class="pickup-contact">For help, contact the staff member reviewing your claim.</p>
    </section>
</section>

<?php if (($user !== null && $user['role'] === 'student' && $isVerifiedStudent && $canClaim && !$ownItem)): ?>
    <div class="claim-modal-backdrop" id="claim-modal" aria-hidden="true">
        <div class="claim-modal-card" role="dialog" aria-modal="true" aria-labelledby="claim-modal-title">
            <div class="claim-modal-header">
                <div>
                    <span class="eyebrow dark">Proof of ownership</span>
                    <h3 id="claim-modal-title">Verify ownership</h3>
                    <p>Provide identifying details for <?= e($item['title']) ?>.</p>
                </div>
                <button type="button" class="claim-modal-close" aria-label="Close dialog" data-close-claim>×</button>
            </div>

            <form class="claim-modal-form" method="post" action="item-detail.php?id=<?= e($id) ?>" id="claim-modal-form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="bt_name">Brand, model, or identifying name</label>
                    <input id="bt_name" name="bt_name" type="text" placeholder="Enter a name or identifying detail" required>
                </div>

                <div class="field">
                    <label for="case_detail">Unique marks, contents, or other details</label>
                    <textarea id="case_detail" name="case_detail" rows="3" placeholder="Describe a mark, sticker, engraving, or other identifying feature." required></textarea>
                </div>

                <div class="checkbox-field">
                    <input id="consent_modal" name="consent" type="checkbox" value="1" required>
                    <label for="consent_modal">I agree to the <a href="privacy-policy.php">Privacy Policy</a> and <a href="terms.php">Terms</a>.</label>
                </div>

                <button class="primary-button" type="submit">Submit claim for review</button>
                <button class="ghost-button w-full" type="button" data-close-claim>Cancel</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    const modal = document.getElementById('claim-modal');
    if (!modal) {
        return;
    }

    const openButtons = document.querySelectorAll('[data-open-claim]');
    const closeButtons = document.querySelectorAll('[data-close-claim]');
    const form = document.getElementById('claim-modal-form');
    const brandInput = document.getElementById('bt_name');
    const caseInput = document.getElementById('case_detail');

    const validateProof = function (event) {
        const missingInput = [brandInput, caseInput].find(function (input) {
            return !input.value.trim();
        });
        if (!missingInput) {
            return;
        }

        event.preventDefault();
        missingInput.setCustomValidity('Please fill in this field.');
        missingInput.reportValidity();
    };

    [brandInput, caseInput].forEach(function (input) {
        if (input) {
            input.addEventListener('input', function () {
                input.setCustomValidity('');
            });
        }
    });

    const setOpen = function (open) {
        modal.style.display = open ? 'flex' : 'none';
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setOpen(true);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setOpen(false);
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            setOpen(false);
        }
    });

    if (form) {
        form.addEventListener('submit', validateProof);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.style.display !== 'none') {
            setOpen(false);
        }
    });

    setOpen(new URLSearchParams(window.location.search).get('claim') === '1');
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
