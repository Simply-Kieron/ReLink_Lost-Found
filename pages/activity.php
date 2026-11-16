<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$staff = require_role('staff');
require_once __DIR__ . '/../includes/db.php';
$activityError = null;
$activities = [];
try {
    $statement = $pdo->query("SELECT activity_date, activity_type, title, activity_status, record_kind, item_type
        FROM (
            SELECT items.created_at AS activity_date,
                CASE WHEN items.type = 'lost' THEN 'Lost item report' ELSE 'Found item report' END AS activity_type,
                items.title,
                CASE WHEN items.review_status <> 'approved' THEN items.review_status ELSE items.status END AS activity_status,
                'report' AS record_kind, items.type AS item_type, items.id AS record_id
            FROM items
            UNION ALL
            SELECT claims.created_at AS activity_date, 'Item claim' AS activity_type,
                items.title, claims.status AS activity_status, 'claim' AS record_kind,
                items.type AS item_type, claims.id AS record_id
            FROM claims INNER JOIN items ON items.id = claims.item_id
        ) AS activity
        ORDER BY activity_date DESC, record_id DESC, record_kind
        LIMIT 50");
    $activities = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('Staff activity read failed: ' . $exception->getMessage());
    http_response_code(500);
    $activityError = 'Activity is temporarily unavailable. Please try again later.';
}
$pageTitle = 'Activity';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell staff-activity-page" aria-labelledby="activity-title">
    <div class="relink-page-banner"><span class="relink-banner-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><div><h1 id="activity-title">Activity</h1><p>Recent reports and claims, with their current statuses.</p></div></div>
    <?php if ($activityError !== null): ?><p class="error-box" role="alert"><?= e($activityError) ?></p>
    <?php elseif ($activities === []): ?><p class="empty-state">Reports and claims will appear here when they are submitted.</p>
    <?php else: ?>
        <p class="field-note">Latest <?= count($activities) ?> reports and claims.</p>
        <ol class="staff-activity-list">
            <?php foreach ($activities as $activity): ?>
                <?php $destination = $activity['record_kind'] === 'claim' ? 'review-claims.php' : ($activity['item_type'] === 'found' ? 'manage-found.php' : 'manage-lost.php'); ?>
                <li class="staff-activity-entry"><div><h2><?= e($activity['activity_type']) ?>: <?= e($activity['title']) ?></h2><p><time datetime="<?= e(date('c', strtotime($activity['activity_date']))) ?>"><?= e(date('M j, Y · g:i A', strtotime($activity['activity_date']))) ?></time></p></div><span class="relink-status"><?= e(ucfirst($activity['activity_status'])) ?></span><a class="secondary-button" href="<?= e($destination) ?>">Review</a></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
