<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$browseUser = current_user();
$browseType = $browseType ?? '';
if (!in_array($browseType, ['lost', 'found'], true)) {
    throw new InvalidArgumentException('The item browse type is invalid.');
}

$filter = [
    'category_id' => '',
    'status' => '',
    'date_from' => '',
    'date_to' => '',
    'keyword' => '',
];
$errors = [];

foreach (array_keys($filter) as $field) {
    $value = $_GET[$field] ?? '';
    $filter[$field] = is_string($value) ? trim($value) : '';
}

$categoryStatement = $pdo->prepare("SELECT id, name FROM categories ORDER BY CASE WHEN LOWER(name) = 'other' THEN 1 ELSE 0 END, name");
$categoryStatement->execute();
$categories = $categoryStatement->fetchAll();
$categoryIds = array_map(static function (array $category): string {
    return (string) $category['id'];
}, $categories);

if ($filter['category_id'] !== '') {
    if (!ctype_digit($filter['category_id']) || !in_array($filter['category_id'], $categoryIds, true)) {
        $errors[] = 'Choose a valid category.';
        $filter['category_id'] = '';
    }
}

if ($filter['status'] !== '' && !in_array($filter['status'], ['open', 'claimed', 'returned'], true)) {
    $errors[] = 'Choose a valid status.';
    $filter['status'] = '';
}

foreach (['date_from' => 'start date', 'date_to' => 'end date'] as $field => $label) {
    if ($filter[$field] !== '') {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $filter[$field]);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $filter[$field]) {
            $errors[] = 'Enter a valid ' . $label . '.';
            $filter[$field] = '';
        }
    }
}

if ($filter['date_from'] !== '' && $filter['date_to'] !== '' && $filter['date_from'] > $filter['date_to']) {
    $errors[] = 'The start date must be on or before the end date.';
    $filter['date_from'] = '';
    $filter['date_to'] = '';
}

if (strlen($filter['keyword']) > 100) {
    $errors[] = 'Search text must be no more than 100 bytes.';
    $filter['keyword'] = substr($filter['keyword'], 0, 100);
}

$pageInput = $_GET['page'] ?? '1';
$pageNumber = is_string($pageInput) ? filter_var($pageInput, FILTER_VALIDATE_INT) : false;
$page = $pageNumber === false || $pageNumber === null || $pageNumber < 1 ? 1 : $pageNumber;
$perPage = 10;

$conditions = ['items.type = :type', "items.review_status = 'approved'"];
$parameters = ['type' => $browseType];

if ($filter['category_id'] !== '') {
    $conditions[] = 'items.category_id = :category_id';
    $parameters['category_id'] = (int) $filter['category_id'];
}

if ($filter['status'] !== '') {
    $conditions[] = 'items.status = :status';
    $parameters['status'] = $filter['status'];
}

if ($filter['date_from'] !== '') {
    $conditions[] = 'items.date_event >= :date_from';
    $parameters['date_from'] = $filter['date_from'];
}

if ($filter['date_to'] !== '') {
    $conditions[] = 'items.date_event <= :date_to';
    $parameters['date_to'] = $filter['date_to'];
}

if ($filter['keyword'] !== '') {
    $escapedKeyword = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $filter['keyword']);
    $conditions[] = "(items.title LIKE :keyword_title ESCAPE '=' OR items.description LIKE :keyword_description ESCAPE '=' OR items.location LIKE :keyword_location ESCAPE '=')";
    $parameters['keyword_title'] = '%' . $escapedKeyword . '%';
    $parameters['keyword_description'] = '%' . $escapedKeyword . '%';
    $parameters['keyword_location'] = '%' . $escapedKeyword . '%';
}

$whereClause = implode(' AND ', $conditions);
$countStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM items INNER JOIN categories ON categories.id = items.category_id WHERE $whereClause"
);
$countStatement->execute($parameters);
$totalItems = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $pdo->prepare(
    "SELECT items.id, items.title, items.description, items.location, items.date_event, items.status,
        items.reported_by,
        items.image_path, items.image_alt, categories.name AS category_name
    FROM items
    INNER JOIN categories ON categories.id = items.category_id
    WHERE $whereClause
    ORDER BY items.created_at DESC, items.id DESC
    LIMIT :limit OFFSET :offset"
);
foreach ($parameters as $parameterName => $parameterValue) {
    $listStatement->bindValue(':' . $parameterName, $parameterValue, is_int($parameterValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$items = $listStatement->fetchAll();

$basePath = $browseType === 'lost' ? 'lost-items.php' : 'found-items.php';
$heading = $browseType === 'lost' ? 'Lost items' : 'Found items';
$pageTitle = $heading;
$statusClasses = [
    'open' => 'status-open',
    'claimed' => 'status-pending',
    'returned' => 'status-approved',
];
$pageDesign = 'zeta';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-shell feed-page">
    <div class="relink-page-banner relink-browse-banner"><span class="relink-banner-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $navigationItems[$browseType][2] ?></svg></span><div><h1 id="browse-title"><?= e(ucwords($heading)) ?></h1><p><?= $browseType === 'found' ? 'Browse and claim items turned in on campus.' : 'Browse lost reports and help items find their owners.' ?></p></div></div>
    <div class="feed-content"><div class="relink-list-tools">
        <form class="feed-search" method="get" action="<?= e($basePath) ?>" role="search">
            <label class="sr-only" for="keyword">Search <?= e($heading) ?></label><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></span>
            <input id="keyword" name="keyword" type="search" maxlength="100" value="<?= e($filter['keyword']) ?>" placeholder="Search items or locations…">
            <?php foreach ($filter as $name => $value): if ($name === 'keyword' || $value === '') continue; ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>"><?php endforeach; ?>
            <button type="submit">Search</button>
        </form>
        <details class="feed-more-filters" <?= $errors !== [] ? 'open' : '' ?>><summary aria-label="Filter items"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h18l-7 8v7l-4 2v-9Z"/></svg><span class="relink-filter-label">Filter items</span></summary><form method="get" action="<?= e($basePath) ?>">
            <input type="hidden" name="keyword" value="<?= e($filter['keyword']) ?>"><input type="hidden" name="category_id" value="<?= e($filter['category_id']) ?>">
            <div class="field"><label for="feed-status">Status</label><select id="feed-status" name="status"><option value="">All statuses</option><?php foreach (['open' => $browseType === 'lost' ? 'Reported (open)' : 'Available (open)', 'claimed' => 'Claimed', 'returned' => 'Returned'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $filter['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="feed-from">From date</label><input id="feed-from" name="date_from" type="date" value="<?= e($filter['date_from']) ?>"></div><div class="field"><label for="feed-to">To date</label><input id="feed-to" name="date_to" type="date" value="<?= e($filter['date_to']) ?>"></div>
            <button class="primary-button" type="submit">Apply filters</button><a class="secondary-button" href="<?= e($basePath) ?>">Clear filters</a>
        </form></details></div>
        <div class="feed-section-heading"><div class="feed-heading-copy"><h2><?= e(ucwords($heading)) ?> (<?= e($totalItems) ?>)</h2><p>Staff-approved reports · Latest first</p></div><div class="feed-heading-actions"><a class="secondary-button" href="<?= $browseType === 'found' ? 'upload-found.php' : 'report-lost.php' ?>">Report <?= $browseType === 'found' ? 'found' : 'lost' ?> item</a></div></div>
        <?php if ($errors !== []): ?><div class="error-box" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    </div>
            <?php
            $categoryParameters = array_filter($filter, static function (string $value): bool {
                return $value !== '';
            });
            unset($categoryParameters['category_id']);
            ?>
            <nav class="feed-category-list" aria-label="Filter by category">
                <a class="<?= $filter['category_id'] === '' ? 'is-selected' : '' ?>" href="<?= e($basePath . ($categoryParameters === [] ? '' : '?' . http_build_query($categoryParameters))) ?>">All</a>
                <?php foreach ($categories as $category): ?>
                    <?php $categoryUrl = $basePath . '?' . http_build_query(array_merge($categoryParameters, ['category_id' => (string) $category['id']])); ?>
                    <a class="<?= $filter['category_id'] === (string) $category['id'] ? 'is-selected' : '' ?>" href="<?= e($categoryUrl) ?>"><?= e($category['name']) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($items === []): ?>
                <p class="empty-state">No items match your filters. Try changing or clearing your search.</p>
            <?php else: ?>
                <ul class="claim-list browse-grid feed-item-list">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $normalizedCategory = strtolower(trim($item['category_name']));
                        $sensitiveCategory = $normalizedCategory === 'personal items'
                            || in_array($normalizedCategory, ['id', 'ids', 'phone', 'phones', 'wallet', 'wallets'], true);
                        ?>
                        <li>
                            <article class="claim-card browse-card feed-card"><div class="relink-item-media">
                                <?php if ($browseType === 'found' && $sensitiveCategory): ?>
                                    <div class="media-placeholder" style="min-height: 12.5rem;">Image not displayed for privacy</div>
                                <?php elseif ($item['image_path'] !== null): ?>
                                    <img class="relink-item-image" src="../<?= e($item['image_path']) ?>" alt="<?= e(item_image_alt($item['image_alt'], ucfirst($browseType) . ' item: ' . $item['title'])) ?>">
                                <?php else: ?>
                                    <div class="media-placeholder" style="min-height: 12.5rem;">No image provided</div>
                                <?php endif; ?>
                                </div>
                                <div class="relink-item-copy">
                                <div class="claim-card-head"><h2 style="margin: 0;"><?= e($item['title']) ?></h2><span class="status-pill <?= e($statusClasses[$item['status']] ?? 'status-open') ?>"><?= e($browseType === 'found' && $item['status'] === 'claimed' ? 'Claimed' : ucfirst($item['status'])) ?></span></div>
                                <p class="field-note" style="margin: 0;"><?= e($item['category_name']) ?> · <?= $browseType === 'lost' ? 'Lost' : 'Found' ?> <?= e($item['date_event']) ?></p>

                                <p class="field-note browse-card-location"><strong><?= $browseType === 'lost' ? 'Last seen at:' : 'Found at:' ?></strong> <?= e($item['location']) ?></p>
                                </div>
                                <p class="browse-card-copy relink-item-description"><span class="relink-description-label">Description</span><span class="relink-description-text"><?= e($item['description']) ?></span></p>
                                <?php if ($browseType === 'found'): ?>
                                    <div class="feed-card-actions">
                                    <?php if ($browseUser !== null && $browseUser['role'] === 'student' && (int) $item['reported_by'] === $browseUser['id']): ?>
                                        <span class="feed-own-item browse-card-action">
                                            <svg class="feed-action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="m5 12.5 4.2 4.2L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span>Your found item</span>
                                        </span>
                                    <?php elseif ($item['status'] === 'open'): ?>
                                        <a class="primary-button feed-claim-link browse-card-action" href="item-detail.php?id=<?= e($item['id']) ?>&amp;claim=1">
                                            <svg class="feed-action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M20 13.2 13.2 20 4 10.8V4h6.8L20 13.2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                                <circle cx="8" cy="8" r="1.2" fill="currentColor"/>
                                            </svg>
                                            <span>Claim this item</span>
                                        </a>
                                    <?php elseif ($item['status'] === 'claimed'): ?>
                                        <span class="feed-claimed-state browse-card-action">
                                            <svg class="feed-action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/>
                                                <path d="m8.5 12.1 2.3 2.3 4.8-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span>Already claimed</span>
                                        </span>
                                    <?php elseif ($item['status'] === 'returned'): ?>
                                        <span class="feed-claimed-state browse-card-action">
                                            <svg class="feed-action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M4 12a8 8 0 1 0 2.34-5.66L4 8.67" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M4 4v4.67h4.67M12 8v4l2.8 1.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span>Already returned</span>
                                        </span>
                                    <?php endif; ?>
                                        <a class="feed-card-link browse-card-action" href="item-detail.php?id=<?= e($item['id']) ?>">
                                            <svg class="feed-action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M2.8 12s3.2-5 9.2-5 9.2 5 9.2 5-3.2 5-9.2 5-9.2-5-9.2-5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                                <circle cx="12" cy="12" r="2.2" stroke="currentColor" stroke-width="1.7"/>
                                            </svg>
                                            <span>View item details</span>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="feed-card-actions">
                                    <?php if ($item['status'] === 'open'): ?>
                                        <a class="primary-button browse-card-action" href="report-found-lost.php?id=<?= e($item['id']) ?>">
                                            <svg class="feed-action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="m12 3 8 4v10l-8 4-8-4V7l8-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                                <path d="m4.5 7.3 7.5 4 7.5-4M12 11.3V21M9 15l2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span>I found this item</span>
                                        </a>
                                    <?php endif; ?>
                                        <a class="feed-card-link browse-card-action" href="lost-item-detail.php?id=<?= e($item['id']) ?>">
                                            <svg class="feed-action-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M2.8 12s3.2-5 9.2-5 9.2 5 9.2 5-3.2 5-9.2 5-9.2-5-9.2-5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                                <circle cx="12" cy="12" r="2.2" stroke="currentColor" stroke-width="1.7"/>
                                            </svg>
                                            <span>View item details</span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($totalPages > 1): ?>
                    <nav class="feed-pagination" aria-label="Item list pages">
                        <?php $paginationParameters = array_filter($filter, static function (string $value): bool { return $value !== ''; }); ?>
                        <?php if ($page > 1): ?><a class="secondary-button" href="<?= e($basePath . '?' . http_build_query(array_merge($paginationParameters, ['page' => $page - 1]))) ?>">Previous</a><?php endif; ?>
                        <span class="field-note">Page <?= e($page) ?> of <?= e($totalPages) ?></span>
                        <?php if ($page < $totalPages): ?><a class="secondary-button" href="<?= e($basePath . '?' . http_build_query(array_merge($paginationParameters, ['page' => $page + 1]))) ?>">Next</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
