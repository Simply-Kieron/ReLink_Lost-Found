<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/item_uploads.php';

$user = require_role('student');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($reportId === false || $reportId === null) {
        http_response_code(400);
        exit('Choose a valid lost item report.');
    }

    try {
        $find = $pdo->prepare(
            "SELECT image_path FROM items
            WHERE id = :id AND reported_by = :reported_by AND type = 'lost'
            LIMIT 1"
        );
        $find->execute(['id' => $reportId, 'reported_by' => $user['id']]);
        $report = $find->fetch();
        if ($report === false) {
            http_response_code(404);
            exit('Report not found.');
        }

        $delete = $pdo->prepare(
            "DELETE FROM items WHERE id = :id AND reported_by = :reported_by AND type = 'lost'"
        );
        $delete->execute(['id' => $reportId, 'reported_by' => $user['id']]);
        if ($delete->rowCount() !== 1) {
            http_response_code(404);
            exit('Report not found.');
        }

        delete_item_image($report['image_path']);
        set_flash('success', 'Your lost item report has been deleted.');
        redirect('my-claims.php#reports');
    } catch (PDOException $exception) {
        error_log('Lost item report deletion failed: ' . $exception->getMessage());
        http_response_code(500);
        $deleteError = 'The report could not be deleted. Please try again later.';
    }
}

$statement = $pdo->prepare(
    "SELECT claims.id, claims.proof_description, claims.status, claims.created_at,
        items.id AS item_id, items.title, items.location, items.date_event, items.status AS item_status,
        categories.name AS category_name
    FROM claims
    INNER JOIN items ON items.id = claims.item_id
    INNER JOIN categories ON categories.id = items.category_id
    WHERE claims.claimant_id = :claimant_id
    ORDER BY claims.created_at DESC, claims.id DESC"
);
$statement->execute(['claimant_id' => $user['id']]);
$claims = $statement->fetchAll();
$activeClaims = array_filter($claims, static function (array $claim): bool {
    return ($claim['status'] === 'pending' || $claim['status'] === 'approved')
        && $claim['item_status'] !== 'returned';
});

$reportsStatement = $pdo->prepare(
    "SELECT items.id, items.type, items.title, items.description, items.location, items.date_event,
        items.status, items.review_status, items.created_at, categories.name AS category_name
    FROM items
    INNER JOIN categories ON categories.id = items.category_id
    WHERE items.reported_by = :reported_by
    ORDER BY items.created_at DESC, items.id DESC"
);
$reportsStatement->execute(['reported_by' => $user['id']]);
$reports = $reportsStatement->fetchAll();

$activityStatement = $pdo->prepare(
    "SELECT activity_date, activity_type, title, activity_status
    FROM (
        SELECT items.created_at AS activity_date,
            CASE WHEN items.type = 'lost' THEN 'Lost item report' ELSE 'Found item report' END AS activity_type,
            items.title,
            CASE WHEN items.review_status <> 'approved' THEN items.review_status ELSE items.status END AS activity_status
        FROM items
        WHERE items.reported_by = :reported_by
        UNION ALL
        SELECT claims.created_at AS activity_date, 'Item claim' AS activity_type,
            items.title, claims.status AS activity_status
        FROM claims
        INNER JOIN items ON items.id = claims.item_id
        WHERE claims.claimant_id = :claimant_id
    ) AS activity
    ORDER BY activity_date DESC
    LIMIT 50"
);
$activityStatement->execute([
    'reported_by' => $user['id'],
    'claimant_id' => $user['id'],
]);
$activityLog = $activityStatement->fetchAll();

$pageDesign = 'claims';

$pageTitle = 'My Activity';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell claim-pass-page">
    <div class="student-summary">
        <div>
            <h1><?= e($user['name']) ?></h1>
            <p>Claims, reports, and class activity in one place.</p>
        </div>
        <span class="verified-badge">Student account</span>
    </div>

    <nav class="claim-tabs" role="tablist" aria-label="My activity sections">
        <a id="claims-tab" href="#claims" role="tab" aria-controls="claims" aria-selected="true">Claims (<?= count($claims) ?>)</a>
        <a id="reports-tab" href="#reports" role="tab" aria-controls="reports" aria-selected="false" tabindex="-1">Reports (<?= count($reports) ?>)</a>
        <a id="activity-tab" href="#activity" role="tab" aria-controls="activity" aria-selected="false" tabindex="-1">Activity log</a>
    </nav>

    <section class="my-activity-section" id="claims" role="tabpanel" aria-labelledby="claims-tab" tabindex="0">
        <div class="section-header">
            <h2 id="claims-title">My claims</h2>
            <span class="status-pill status-pending"><?= count($activeClaims) ?> active</span>
        </div>
    <?php if ($activeClaims === []): ?>
        <div class="empty-state claim-empty-state">
            <h2>No active claims</h2>
            <p>When you claim a found item, its review progress will appear here.</p>
            <a class="primary-button" href="found-items.php">Browse found items</a>
        </div>
    <?php else: ?>
        <div class="claim-pass-list">
            <?php foreach ($activeClaims as $claim): ?>
                <article class="claim-pass-card">
                    <div class="claim-item-heading">
                        <div class="claim-item-icon" aria-hidden="true">⌕</div>
                        <div class="claim-item-copy">
                            <h2><?= e($claim['title']) ?></h2>
                            <p><?= e($claim['category_name']) ?> · <?= e($claim['location']) ?></p>
                            <p class="claim-found-date">Found <?= e($claim['date_event']) ?></p>
                        </div>
                        <span class="status-pill status-<?= e($claim['status']) ?>"><?= e(ucfirst($claim['status'])) ?></span>
                    </div>

                    <section class="verification-stepper" aria-label="Claim review progress">
                        <h3>Verification progress</h3>
                        <ol>
                            <li class="step-complete">
                                <span class="step-mark" aria-hidden="true">✓</span>
                                <div><strong>Claim submitted</strong><span><?= e($claim['created_at']) ?></span></div>
                            </li>
                            <li class="<?= $claim['status'] === 'approved' ? 'step-complete' : 'step-current' ?>">
                                <span class="step-mark" aria-hidden="true"><?= $claim['status'] === 'approved' ? '✓' : '↻' ?></span>
                                <div>
                                    <strong><?= $claim['status'] === 'approved' ? 'Staff verification complete' : 'Staff verification' ?></strong>
                                    <span><?= $claim['status'] === 'approved' ? 'Your claim was approved.' : 'A staff member is reviewing your proof.' ?></span>
                                </div>
                            </li>
                            <li class="<?= $claim['status'] === 'approved' ? 'step-current' : '' ?>">
                                <span class="step-mark" aria-hidden="true">⌖</span>
                                <div><strong>Front office pickup</strong><span><?= $claim['status'] === 'approved' ? 'Show your student ID to staff for pickup.' : 'Pickup details will be available after approval.' ?></span></div>
                            </li>
                        </ol>
                    </section>

                    <div class="claim-pass-actions">
                        <a class="primary-button" href="item-detail.php?id=<?= e($claim['item_id']) ?>">View claim details</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $historyClaims = array_filter($claims, static function (array $claim): bool {
        return $claim['status'] === 'rejected' || $claim['item_status'] === 'returned';
    });
    ?>
    <?php if ($historyClaims !== []): ?>
        <section class="claim-history" aria-labelledby="claim-history-title">
            <div class="history-heading">
                <h2 id="claim-history-title">Resolved &amp; history</h2>
                <span>Past claims</span>
            </div>
            <ul>
                <?php foreach ($historyClaims as $claim): ?>
                    <li>
                        <span class="history-icon" aria-hidden="true">▣</span>
                        <div><strong><?= e($claim['title']) ?></strong><span><?= e($claim['location']) ?> · <?= $claim['item_status'] === 'returned' ? 'Returned' : e(ucfirst($claim['status'])) ?></span></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php elseif ($claims !== []): ?>
        <p class="empty-state">There are no resolved claims yet.</p>
    <?php endif; ?>
    </section>

    <section class="my-activity-section" id="reports" role="tabpanel" aria-labelledby="reports-tab" tabindex="0" hidden>
        <div class="section-header">
            <div>
                <h2 id="reports-title">My reports</h2>
                <p class="field-note">Lost and found reports you have submitted.</p>
            </div>
        </div>

        <?php if (isset($deleteError)): ?>
            <p class="error-box" role="alert"><?= e($deleteError) ?></p>
        <?php endif; ?>

        <?php if ($reports === []): ?>
            <p class="empty-state">You have not submitted any reports yet.</p>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($reports as $report): ?>
                    <?php
                    $isLostReport = $report['type'] === 'lost';
                    $reviewLabel = $report['review_status'] === 'pending'
                        ? 'Pending staff review'
                        : ($report['review_status'] === 'rejected' ? 'Not approved' : 'Approved · public');
                    ?>
                    <li class="activity-item">
                        <div class="activity-head">
                            <div>
                                <p class="activity-title"><?= e($report['title']) ?></p>
                                <p class="field-note"><?= e($report['category_name']) ?> · <?= $isLostReport ? 'Lost' : 'Found' ?> <?= e($report['date_event']) ?></p>
                                <p class="field-note" style="margin-top: 0.2rem;">Submitted <?= e($report['created_at']) ?></p>
                            </div>
                            <?php $reportStatusLabel = $isLostReport
                                ? ($report['review_status'] === 'rejected' ? 'Not approved' : ($report['review_status'] === 'pending' ? 'Pending staff review' : ucfirst($report['status'])))
                                : $reviewLabel; ?>
                            <span class="status-pill <?= $report['review_status'] === 'rejected' ? 'status-rejected' : ($report['review_status'] === 'pending' ? 'status-pending' : e(['open' => 'status-open', 'claimed' => 'status-pending', 'returned' => 'status-approved'][$report['status']] ?? 'status-open')) ?>">
                                <?= e($reportStatusLabel) ?>
                            </span>
                        </div>
                        <p><?= e($report['description']) ?></p>
                        <p class="field-note"><strong>Location:</strong> <?= e($report['location']) ?></p>
                        <div class="action-row">
                            <?php if ($isLostReport): ?>
                                <?php if ($report['review_status'] === 'rejected'): ?>
                                    <p class="field-note">Staff hid this report from public listings. Editing it will not republish it; contact staff if you have questions.</p>
                                    <a class="secondary-button" href="support-chat.php">Contact customer support</a>
                                <?php endif; ?>
                                <a class="secondary-button" href="edit-report.php?id=<?= e($report['id']) ?>">Edit</a>
                                <a class="ghost-button" href="my-claims.php#reports"
                                    data-confirm-record-id="<?= e($report['id']) ?>"
                                    data-confirm-record-name="report_id"
                                    data-confirm-title="Delete lost item report?"
                                    data-confirm-message="This will permanently delete “<?= e($report['title']) ?>”. Any claims linked to this report will also be deleted."
                                    data-confirm-button="Delete report"
                                    data-confirm-tone="danger">Delete</a>
                            <?php elseif ($report['review_status'] === 'approved'): ?>
                                <a class="secondary-button" href="item-detail.php?id=<?= e($report['id']) ?>">View public listing</a>
                            <?php else: ?>
                                <span class="field-note"><?= $report['review_status'] === 'pending' ? 'Staff will review this report before it appears publicly.' : 'Contact staff if you need more information.' ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="my-activity-section" id="activity" role="tabpanel" aria-labelledby="activity-tab" tabindex="0" hidden>
        <div class="section-header">
            <h2 id="activity-title">Class activity log</h2>
            <span class="field-note">Latest <?= count($activityLog) ?> activities</span>
        </div>
        <?php if ($activityLog === []): ?>
            <p class="empty-state">Your reports and claims will appear here.</p>
        <?php else: ?>
            <ol class="activity-list">
                <?php foreach ($activityLog as $activity): ?>
                    <li class="activity-item">
                        <div class="activity-head">
                            <div>
                                <p class="activity-title"><?= e($activity['activity_type']) ?>: <?= e($activity['title']) ?></p>
                                <p class="field-note" style="margin-top: 0.2rem;"><?= e($activity['activity_date']) ?></p>
                            </div>
                            <span class="status-pill <?= e(['open' => 'status-open', 'claimed' => 'status-pending', 'returned' => 'status-approved', 'pending' => 'status-pending', 'approved' => 'status-approved', 'rejected' => 'status-rejected'][$activity['activity_status']] ?? 'status-open') ?>"><?= e(ucfirst($activity['activity_status'])) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
    <a class="report-item-link" href="found-items.php">⌕ &nbsp; Browse found items</a>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
