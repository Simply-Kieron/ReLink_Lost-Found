<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_verified_student();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/item_uploads.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(404);
    exit('Report not found.');
}

$loadReport = $pdo->prepare(
    "SELECT id, title, category_id, description, location, date_event, image_path, image_alt, status
    FROM items
    WHERE id = :id AND reported_by = :reported_by AND type = 'lost'
    LIMIT 1"
);
$loadReport->execute(['id' => $id, 'reported_by' => $user['id']]);
$report = $loadReport->fetch();
if ($report === false) {
    http_response_code(404);
    exit('Report not found.');
}

$categoryStatement = $pdo->prepare("SELECT id, name FROM categories ORDER BY CASE WHEN LOWER(name) = 'other' THEN 1 ELSE 0 END, name");
$categoryStatement->execute();
$categories = $categoryStatement->fetchAll();
$values = [
    'title' => $report['title'],
    'category_id' => (string) $report['category_id'],
    'description' => $report['description'],
    'location' => $report['location'],
    'date_event' => $report['date_event'],
    'image_alt' => $report['image_alt'] ?? '',
];
$errors = [];
$removeImage = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && request_body_exceeds_post_limit()) {
    $errors[] = 'The submitted form is too large. Images must be no larger than 15 MB.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    foreach (array_keys($values) as $field) {
        $posted = $_POST[$field] ?? '';
        $values[$field] = is_string($posted) ? trim($posted) : '';
    }
    $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    if ($values['title'] === '' || preg_match('/\A[\P{Cc}]{1,200}\z/u', $values['title']) !== 1) {
        $errors[] = 'Enter a title using no more than 200 characters.';
    }

    if (!ctype_digit($values['category_id']) || (int) $values['category_id'] < 1) {
        $errors[] = 'Choose a category.';
    } else {
        $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :id');
        $categoryCheck->execute(['id' => (int) $values['category_id']]);
        if ($categoryCheck->fetchColumn() === false) {
            $errors[] = 'Choose a valid category.';
        }
    }

    if ($values['description'] === '' || strlen($values['description']) > 10000) {
        $errors[] = 'Enter a description using no more than 10,000 bytes.';
    }

    if ($values['location'] === '' || preg_match('/\A[\P{Cc}]{1,255}\z/u', $values['location']) !== 1) {
        $errors[] = 'Enter a location using no more than 255 characters.';
    }

    $date = DateTime::createFromFormat('!Y-m-d', $values['date_event']);
    if ($date === false || $date->format('Y-m-d') !== $values['date_event']) {
        $errors[] = 'Enter a valid date for when the item was lost.';
    }

    $upload = validate_item_image_upload($_FILES['image'] ?? []);
    if ($upload['error'] !== null) {
        $errors[] = $upload['error'];
    }

    if ($removeImage && $upload['path'] !== null) {
        $errors[] = 'Choose either a replacement image or remove the existing image, not both.';
    }

    $hasImageAfterSave = $upload['path'] !== null || ($report['image_path'] !== null && !$removeImage);
    if ($hasImageAfterSave && ($values['image_alt'] === '' || strlen($values['image_alt']) > 255)) {
        $errors[] = 'Provide alt text up to 255 characters for the image.';
    } elseif (strlen($values['image_alt']) > 255) {
        $errors[] = 'Image alt text must be no more than 255 characters.';
    }

    if ($errors !== [] && $upload['path'] !== null) {
        delete_item_image($upload['path']);
    }

    if ($errors === []) {
        $newImagePath = $upload['path'] ?? ($removeImage ? null : $report['image_path']);
        $newImageAlt = $newImagePath === null ? null : $values['image_alt'];

        try {
            $update = $pdo->prepare(
                "UPDATE items
                SET title = :title, category_id = :category_id, description = :description, location = :location,
                    date_event = :date_event, image_path = :image_path, image_alt = :image_alt
                WHERE id = :id AND reported_by = :reported_by AND type = 'lost'"
            );
            $update->execute([
                'title' => $values['title'],
                'category_id' => (int) $values['category_id'],
                'description' => $values['description'],
                'location' => $values['location'],
                'date_event' => $values['date_event'],
                'image_path' => $newImagePath,
                'image_alt' => $newImageAlt,
                'id' => $id,
                'reported_by' => $user['id'],
            ]);

            if ($update->rowCount() === 0) {
                $exists = $pdo->prepare(
                    "SELECT id FROM items WHERE id = :id AND reported_by = :reported_by AND type = 'lost'"
                );
                $exists->execute(['id' => $id, 'reported_by' => $user['id']]);
                if ($exists->fetchColumn() === false) {
                    delete_item_image($upload['path']);
                    http_response_code(404);
                    exit('Report not found.');
                }
            }

            if ($upload['path'] !== null || $removeImage) {
                delete_item_image($report['image_path']);
            }
            set_flash('success', 'Your lost item report has been updated.');
            redirect('my-claims.php#reports');
        } catch (PDOException $exception) {
            delete_item_image($upload['path']);
            error_log('Lost item report update failed: ' . $exception->getMessage());
            http_response_code(500);
            $errors[] = 'The report could not be updated. Please try again later.';
        }
    }
}

$pageTitle = 'Edit Lost Item Report';
$pageDesign = 'item-form';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell edit-item-page" aria-labelledby="edit-title">
    <div class="report-found-hero">
        <span class="report-hero-icon" aria-hidden="true">⌕</span>
        <div>
            <span class="eyebrow">Lost item report</span>
            <h1 id="edit-title">Update your report</h1>
            <p>Keep the details current so staff can help reunite you with your item.</p>
        </div>
        <a class="report-manage-link" href="my-claims.php#reports">My reports</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="error-box report-errors" role="alert">
            <strong>Please review the following:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="surface-card form-panel edit-item-form" method="post" action="edit-report.php?id=<?= e($id) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <section class="report-step" aria-labelledby="report-details-title">
            <div class="report-step-heading"><span>1</span><h2 id="report-details-title">Item details</h2></div>
            <div class="field">
                <label for="title">Item title</label>
                <input id="title" name="title" type="text" maxlength="200" value="<?= e($values['title']) ?>" required>
            </div>
            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Choose a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id']) ?>" <?= $values['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" maxlength="10000" required><?= e($values['description']) ?></textarea>
            </div>
        </section>

        <section class="report-step" aria-labelledby="report-location-title">
            <div class="report-step-heading"><span>2</span><h2 id="report-location-title">Where and when you lost it</h2></div>
            <div class="field">
                <label for="location">Location</label>
                <input id="location" name="location" type="text" maxlength="255" value="<?= e($values['location']) ?>" required>
            </div>
            <div class="field">
                <label for="date_event">Date lost</label>
                <input id="date_event" name="date_event" type="date" value="<?= e($values['date_event']) ?>" required>
            </div>
        </section>

        <section class="report-step" aria-labelledby="report-photo-title">
            <div class="report-step-heading"><span>3</span><h2 id="report-photo-title">Item photo</h2></div>
            <?php if ($report['image_path'] !== null): ?>
                <div class="edit-current-image">
                    <img src="../<?= e($report['image_path']) ?>" alt="<?= e(item_image_alt($report['image_alt'], 'Lost item: ' . $report['title'])) ?>">
                    <label class="checkbox-field">
                        <input name="remove_image" type="checkbox" value="1" <?= $removeImage ? 'checked' : '' ?>>
                        <span>Remove current image</span>
                    </label>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="image">Replacement image (optional)</label>
                <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                <span class="field-note">JPG, PNG, GIF, or WebP; maximum 15 MB. An image is saved only when its description text is provided.</span>
            </div>
            <div class="field">
                <label for="image_alt">Image description</label>
                <input id="image_alt" name="image_alt" type="text" maxlength="255" value="<?= e($values['image_alt']) ?>">
                <span class="field-note">Required when an image is attached.</span>
            </div>
        </section>

        <div class="edit-form-actions">
            <button class="primary-button" type="submit">Save changes</button>
            <a class="secondary-button" href="my-claims.php#reports">Cancel</a>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
