<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Dashboard';
if ($user['role'] === 'student') {
    $countsStatement = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM items WHERE reported_by = :reported_by AND type = 'lost') AS lost_reports,
            (SELECT COUNT(*) FROM items WHERE reported_by = :found_reporter_id AND type = 'found') AS found_reports,
            (SELECT COUNT(*) FROM claims WHERE claimant_id = :claimant_id) AS claims_count,
            (SELECT COUNT(*) FROM claims WHERE claimant_id = :approved_claimant_id AND status = 'approved') AS returned_items"
    );
    $countsStatement->execute([
        'reported_by' => $user['id'],
        'found_reporter_id' => $user['id'],
        'claimant_id' => $user['id'],
        'approved_claimant_id' => $user['id'],
    ]);
    $counts = $countsStatement->fetch();

} else {
    $countsStatement = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM items WHERE type = 'lost' AND review_status = 'approved') AS public_lost_reports,
            (SELECT COUNT(*) FROM items WHERE type = 'found' AND review_status = 'approved' AND status = 'open') AS open_found,
            (SELECT COUNT(*) FROM items WHERE type = 'found' AND review_status = 'pending') AS pending_found_reports,
            (SELECT COUNT(*) FROM claims WHERE status = 'pending') AS pending_claims,
            (SELECT COUNT(*) FROM items WHERE type = 'found' AND review_status = 'approved' AND status = 'returned') AS returned_items"
    );
    $countsStatement->execute();
    $counts = $countsStatement->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell">
    <?php if ($user['role'] === 'student'): ?>
        <div class="surface-card page-card student-dashboard">
            <div class="student-dashboard-hero">
                <p class="eyebrow">Your classroom lost &amp; found</p>
                <h1 id="dashboard-title">Welcome, <?= e($user['name']) ?>! <span aria-hidden="true">👋</span></h1>
                <p>Report, find, and reclaim your lost and found items in the classroom.</p>
            </div>
            <div class="stack">
                <p>Here is a summary of your lost reports, found reports, and claims.</p>
                <dl class="stat-grid">
                    <div class="stat-card">
                        <span class="student-stat-icon" aria-hidden="true">⌕</span>
                        <dt>My Lost Reports</dt>
                        <dd><?= e($counts['lost_reports']) ?></dd>
                        <a href="my-claims.php#reports">View reports</a>
                    </div>
                    <div class="stat-card">
                        <span class="student-stat-icon" aria-hidden="true">◇</span>
                        <dt>My Found Reports</dt>
                        <dd><?= e($counts['found_reports']) ?></dd>
                        <a href="my-claims.php#reports">View review status</a>
                    </div>
                    <div class="stat-card">
                        <span class="student-stat-icon" aria-hidden="true">✓</span>
                        <dt>My claims</dt>
                        <dd><?= e($counts['claims_count']) ?></dd>
                        <a href="my-claims.php#claims">View claims</a>
                    </div>
                    <div class="stat-card">
                        <span class="student-stat-icon" aria-hidden="true">♧</span>
                        <dt>Items returned to me</dt>
                        <dd><?= e($counts['returned_items']) ?></dd>
                        <a href="my-claims.php#claims">View approved claims</a>
                    </div>
                </dl>

                <section class="student-dashboard-modules" aria-labelledby="student-modules-title">
                    <h2 id="student-modules-title">Main Modules</h2>
                    <div class="student-module-list">
                        <a href="found-items.php">
                            <span class="student-module-icon" aria-hidden="true">◇</span>
                            <span><strong>Found Items</strong><small>Browse items that were turned in.</small></span>
                            <span class="student-module-arrow" aria-hidden="true">›</span>
                        </a>
                        <a href="report-lost.php">
                            <span class="student-module-icon" aria-hidden="true">⌕</span>
                            <span><strong>Lost Items</strong><small>Report an item you lost.</small></span>
                            <span class="student-module-arrow" aria-hidden="true">›</span>
                        </a>
                        <a href="my-claims.php#activity">
                            <span class="student-module-icon" aria-hidden="true">✓</span>
                            <span><strong>My Reports</strong><small>Track your reports and claims.</small></span>
                            <span class="student-module-arrow" aria-hidden="true">›</span>
                        </a>
                    </div>
                </section>
            </div>
        </div>
    <?php else: ?>
        <div class="surface-card page-card">
            <div class="stack">
                <div>
                    <p class="eyebrow">Overview</p>
                    <h1 id="dashboard-title">Welcome, <?= e($user['name']) ?></h1>
                </div>
                <p>Review lost-item reports, found items, and claims.</p>
                <dl class="stat-grid">
                    <div class="stat-card">
                        <dt>Public lost reports</dt>
                        <dd><?= e($counts['public_lost_reports']) ?></dd>
                        <a href="manage-lost.php">Review lost reports</a>
                    </div>
                    <div class="stat-card">
                        <dt>Found reports awaiting review</dt>
                        <dd><?= e($counts['pending_found_reports']) ?></dd>
                        <a href="manage-found.php">Review found reports</a>
                    </div>
                    <div class="stat-card">
                        <dt>Open found items</dt>
                        <dd><?= e($counts['open_found']) ?></dd>
                        <a href="manage-found.php">Manage found items</a>
                    </div>
                    <div class="stat-card">
                        <dt>Pending claims</dt>
                        <dd><?= e($counts['pending_claims']) ?></dd>
                        <a href="review-claims.php">Review claims</a>
                    </div>
                    <div class="stat-card">
                        <dt>Returned items</dt>
                        <dd><?= e($counts['returned_items']) ?></dd>
                        <a href="manage-found.php">Manage found items</a>
                    </div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a class="primary-button" href="upload-found.php">Upload found item</a>
                    <a class="secondary-button" href="review-claims.php">Review pending claims</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
