<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) { http_response_code(404); exit('Lost item not found.'); }
$statement = $pdo->prepare("SELECT items.id, items.type, items.title, items.description, items.location, items.date_event, items.status, items.image_path, items.image_alt, items.reported_by, categories.name AS category_name FROM items INNER JOIN categories ON categories.id=items.category_id WHERE items.id=:id AND items.type='lost' AND items.review_status='approved' LIMIT 1");
$statement->execute(['id' => $id]);
$item = $statement->fetch();
if (!$item) { http_response_code(404); exit('Lost item not found.'); }
$pageTitle = 'Lost Item Details';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell feed-page relink-lost-detail">
    <nav class="relink-detail-back" aria-label="Back to lost items"><a class="secondary-button" href="lost-items.php">&#8592; Back to Lost Items</a></nav>
    <div class="relink-page-banner relink-browse-banner"><span class="relink-banner-icon" aria-hidden="true">&#9671;</span><div><h1><?= e($item['title']) ?></h1><p>Lost item report · <?= e($item['category_name']) ?></p></div></div>
    <div class="relink-lost-detail-grid">
        <?php if ($item['image_path'] !== null): ?><img src="../<?= e($item['image_path']) ?>" alt="<?= e(item_image_alt($item['image_alt'], 'Lost item: ' . $item['title'])) ?>"><?php else: ?><div class="media-placeholder">No image provided</div><?php endif; ?>
        <div class="relink-lost-detail-copy"><span class="relink-status"><?= $item['status'] === 'open' ? 'Reported (open)' : e(ucfirst($item['status'])) ?></span><dl><dt>Last seen at</dt><dd><?= e($item['location']) ?></dd><dt>Date lost</dt><dd><time datetime="<?= e($item['date_event']) ?>"><?= e($item['date_event']) ?></time></dd><dt>Description</dt><dd class="relink-detail-description"><?= e($item['description']) ?></dd></dl>
            <div class="relink-detail-actions">
            <?php if ($item['status'] === 'open' && ($currentUser === null || (int) $item['reported_by'] !== $currentUser['id'])): ?><a class="primary-button" href="report-found-lost.php?id=<?= e($item['id']) ?>">I found this item</a><?php elseif ($currentUser !== null && (int) $item['reported_by'] === $currentUser['id']): ?><a class="secondary-button" href="my-claims.php#reports">Manage my report</a><?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
