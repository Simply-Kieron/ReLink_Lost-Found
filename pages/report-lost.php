<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_verified_student();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/item_uploads.php';

$categoryStatement = $pdo->prepare("SELECT id, name FROM categories ORDER BY CASE WHEN LOWER(name) = 'other' THEN 1 ELSE 0 END, name");
$categoryStatement->execute();
$categories = $categoryStatement->fetchAll();
$values = [
    'title' => '',
    'category_id' => '',
    'description' => '',
    'location' => '',
    'date_event' => '',
    'image_alt' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && request_body_exceeds_post_limit()) {
    $errors[] = 'The submitted form is too large. Images must be no larger than 15 MB.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    foreach (array_keys($values) as $field) {
        $posted = $_POST[$field] ?? '';
        $values[$field] = is_string($posted) ? trim($posted) : '';
    }
    $consent = $_POST['consent'] ?? '';

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

    if ($consent !== '1') {
        $errors[] = 'You must agree to the Privacy Policy and Terms before submitting this report.';
    }

    $upload = validate_item_image_upload($_FILES['image'] ?? []);
    if ($upload['error'] !== null) {
        $errors[] = $upload['error'];
    }

    if ($upload['path'] !== null && ($values['image_alt'] === '' || strlen($values['image_alt']) > 255)) {
        $errors[] = 'Provide alt text up to 255 characters for the uploaded image.';
    } elseif (strlen($values['image_alt']) > 255) {
        $errors[] = 'Image alt text must be no more than 255 characters.';
    }

    if ($errors === []) {
        try {
            $statement = $pdo->prepare(
                "INSERT INTO items (type, title, category_id, description, location, date_event, image_path, image_alt, status, reported_by)
                VALUES ('lost', :title, :category_id, :description, :location, :date_event, :image_path, :image_alt, 'open', :reported_by)"
            );
            $statement->execute([
                'title' => $values['title'],
                'category_id' => (int) $values['category_id'],
                'description' => $values['description'],
                'location' => $values['location'],
                'date_event' => $values['date_event'],
                'image_path' => $upload['path'],
                'image_alt' => $upload['path'] === null ? null : $values['image_alt'],
                'reported_by' => $user['id'],
            ]);
            set_flash('success', 'Your lost item report has been created.');
            redirect('my-claims.php#reports');
        } catch (PDOException $exception) {
            delete_item_image($upload['path']);
            error_log('Lost item report creation failed: ' . $exception->getMessage());
            http_response_code(500);
            $errors[] = 'The report could not be saved. Please try again later.';
        }
    } elseif ($upload['path'] !== null) {
        delete_item_image($upload['path']);
    }
}

$pageTitle = 'Report a Lost Item';
$pageDesign = 'report-found';
require_once __DIR__ . '/../includes/header.php';
?>
<a class="relink-report-back" href="lost-items.php" aria-label="Back to lost items"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg><span>Back</span></a>
<section class="page-shell report-found-page" aria-labelledby="report-title">
    <div class="report-found-hero report-lost-hero">
        <div>
            <span class="eyebrow">Personal belongings</span>
            <h1 id="report-title">Report a lost item</h1>
            <p>Share enough information to help staff identify your report. Do not include passwords or other sensitive personal information.</p>
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

            <form class="surface-card form-panel" method="post" action="report-lost.php" enctype="multipart/form-data">
                <?= csrf_field() ?>

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

                <div class="field">
                    <label for="location">Where was it lost?</label>
                    <input id="location" name="location" type="text" maxlength="255" value="<?= e($values['location']) ?>" required>
                </div>

                <div class="field">
                    <label for="date_event">Date lost</label>
                    <input id="date_event" name="date_event" type="date" value="<?= e($values['date_event']) ?>" required>
                </div>

                <div class="field">
                    <label for="image">Image (optional)</label>
                    <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                    <span class="field-note">JPG, PNG, GIF, or WebP; maximum 15 MB. An image is saved only when its description text is provided.</span>
                </div>

                <div class="field">
                    <label for="image_alt">Image alt text</label>
                    <input id="image_alt" name="image_alt" type="text" maxlength="255" value="<?= e($values['image_alt']) ?>">
                    <span class="field-note">Required when you attach an image. Describe what it shows.</span>
                </div>

                <div class="checkbox-field">
                    <input id="consent" name="consent" type="checkbox" value="1" required>
                    <label for="consent">I agree to the <a href="privacy-policy.php">Privacy Policy</a> and <a href="terms.php">Terms</a>.</label>
                </div>

                <button class="primary-button w-full" type="submit">Submit lost item report</button>
            </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
