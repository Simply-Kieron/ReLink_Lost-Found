<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role('staff');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/item_uploads.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(404);
    exit('Found item not found.');
}

$load = $pdo->prepare(
    "SELECT id, title, category_id, description, location, date_event, image_path, image_alt, verification_detail, status
    FROM items WHERE id = :id AND type = 'found' LIMIT 1"
);
$load->execute(['id' => $id]);
$item = $load->fetch();
if ($item === false) {
    http_response_code(404);
    exit('Found item not found.');
}

$categoryStatement = $pdo->prepare('SELECT id, name FROM categories ORDER BY name');
$categoryStatement->execute();
$categories = $categoryStatement->fetchAll();
$values = [
    'title' => $item['title'],
    'category_id' => (string) $item['category_id'],
    'description' => $item['description'],
    'location' => $item['location'],
    'date_event' => $item['date_event'],
    'image_alt' => $item['image_alt'] ?? '',
    'verification_detail' => $item['verification_detail'] ?? '',
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
        $errors[] = 'Enter a valid date when the item was found.';
    }

    if ($values['verification_detail'] === '' || strlen($values['verification_detail']) > 10000) {
        $errors[] = 'Enter private verification details using no more than 10,000 bytes.';
    }

    if (!in_array($item['status'], ['open', 'claimed', 'returned'], true)) {
        $errors[] = 'The current item status is invalid.';
    }

    $upload = validate_item_image_upload($_FILES['image'] ?? []);
    if ($upload['error'] !== null) {
        $errors[] = $upload['error'];
    }

    if ($removeImage && $upload['path'] !== null) {
        $errors[] = 'Choose either a replacement image or remove the existing image, not both.';
    }

    $hasImageAfterSave = $upload['path'] !== null || ($item['image_path'] !== null && !$removeImage);
    if ($hasImageAfterSave && ($values['image_alt'] === '' || strlen($values['image_alt']) > 255)) {
        $errors[] = 'Provide alt text up to 255 characters for the image.';
    } elseif (strlen($values['image_alt']) > 255) {
        $errors[] = 'Image alt text must be no more than 255 characters.';
    }

    if ($errors !== [] && $upload['path'] !== null) {
        delete_item_image($upload['path']);
    }

    if ($errors === []) {
        $newImagePath = $upload['path'] ?? ($removeImage ? null : $item['image_path']);
        $newImageAlt = $newImagePath === null ? null : $values['image_alt'];

        try {
            $update = $pdo->prepare(
                "UPDATE items SET title = :title, category_id = :category_id, description = :description,
                    location = :location, date_event = :date_event, image_path = :image_path, image_alt = :image_alt,
                    verification_detail = :verification_detail
                WHERE id = :id AND type = 'found'"
            );
            $update->execute([
                'title' => $values['title'],
                'category_id' => (int) $values['category_id'],
                'description' => $values['description'],
                'location' => $values['location'],
                'date_event' => $values['date_event'],
                'image_path' => $newImagePath,
                'image_alt' => $newImageAlt,
                'verification_detail' => $values['verification_detail'],
                'id' => $id,
            ]);

            if ($upload['path'] !== null || $removeImage) {
                delete_item_image($item['image_path']);
            }
            set_flash('success', 'The found item has been updated.');
            redirect('manage-found.php');
        } catch (PDOException $exception) {
            delete_item_image($upload['path']);
            error_log('Found item update failed: ' . $exception->getMessage());
            http_response_code(500);
            $errors[] = 'The item could not be updated. Please try again later.';
        }
    }
}

$pageTitle = 'Edit Found Item';
$pageDesign = 'item-form';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell edit-item-page" aria-labelledby="edit-title">
    <div class="report-found-hero">
        <span class="report-hero-icon" aria-hidden="true">♡</span>
        <div>
            <span class="eyebrow">Found item listing</span>
            <h1 id="edit-title">Update found item</h1>
            <p>Keep the listing accurate to help its owner recognize and claim it.</p>
        </div>
        <a class="report-manage-link" href="manage-found.php">Your items</a>
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

    <form class="surface-card form-panel edit-item-form" method="post" action="edit-found.php?id=<?= e($id) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <section class="report-step" aria-labelledby="found-details-title">
            <div class="report-step-heading"><span>1</span><h2 id="found-details-title">Item details</h2></div>
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
                <label for="description">Public description</label>
                <textarea id="description" name="description" rows="5" maxlength="10000" required><?= e($values['description']) ?></textarea>
            </div>
        </section>

        <section class="report-step" aria-labelledby="found-location-title">
            <div class="report-step-heading"><span>2</span><h2 id="found-location-title">Discovery location</h2></div>
            <div class="field">
                <label for="location">Where was it found?</label>
                <input id="location" name="location" type="text" maxlength="255" value="<?= e($values['location']) ?>" required>
            </div>
            <div class="field">
                <label for="date_event">Date found</label>
                <input id="date_event" name="date_event" type="date" value="<?= e($values['date_event']) ?>" required>
            </div>
        </section>

        <section class="report-step" aria-labelledby="found-photo-title">
            <div class="report-step-heading"><span>3</span><h2 id="found-photo-title">Item photo</h2></div>
            <?php if ($item['image_path'] !== null): ?>
                <div class="edit-current-image">
                    <img src="../<?= e($item['image_path']) ?>" alt="<?= e(item_image_alt($item['image_alt'], 'Found item: ' . $item['title'])) ?>">
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

        <section class="report-step" aria-labelledby="verification-title">
            <div class="report-step-heading"><span>4</span><h2 id="verification-title">Private verification details</h2></div>
            <p class="report-step-help">These notes are not shown in the public listing.</p>
            <div class="field">
                <label for="verification_detail">Private identifiers or handoff details</label>
                <textarea id="verification_detail" name="verification_detail" rows="4" maxlength="10000" required><?= e($values['verification_detail']) ?></textarea>
            </div>
        </section>

        <div class="edit-form-actions">
            <button class="primary-button" type="submit">Save changes</button>
            <a class="secondary-button" href="manage-found.php">Cancel</a>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
