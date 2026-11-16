<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$sessionUser = current_user();
$user = $sessionUser !== null && $sessionUser['role'] === 'staff'
    ? require_role('staff')
    : require_verified_student();
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
    'verification_detail' => '',
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
        $errors[] = 'Enter a valid date when the item was found.';
    }

    if ($values['verification_detail'] === '' || strlen($values['verification_detail']) > 10000) {
        $errors[] = 'Enter private verification details using no more than 10,000 bytes.';
    }

    if ($consent !== '1') {
        $errors[] = 'You must agree to the Privacy Policy and Terms before uploading this item.';
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

    if ($errors !== [] && $upload['path'] !== null) {
        delete_item_image($upload['path']);
    }

    if ($errors === []) {
        try {
            $statement = $pdo->prepare(
                "INSERT INTO items (type, title, category_id, description, location, date_event, image_path, image_alt, verification_detail, status, review_status, reviewed_by, reviewed_at, reported_by)
                VALUES ('found', :title, :category_id, :description, :location, :date_event, :image_path, :image_alt, :verification_detail, 'open', :review_status, :reviewed_by, :reviewed_at, :reported_by)"
            );
            $isStaffReport = $user['role'] === 'staff';
            $statement->execute([
                'title' => $values['title'],
                'category_id' => (int) $values['category_id'],
                'description' => $values['description'],
                'location' => $values['location'],
                'date_event' => $values['date_event'],
                'image_path' => $upload['path'],
                'image_alt' => $upload['path'] === null ? null : $values['image_alt'],
                'verification_detail' => $values['verification_detail'],
                'review_status' => $isStaffReport ? 'approved' : 'pending',
                'reviewed_by' => $isStaffReport ? $user['id'] : null,
                'reviewed_at' => $isStaffReport ? date('Y-m-d H:i:s') : null,
                'reported_by' => $user['id'],
            ]);
            set_flash(
                'success',
                $isStaffReport
                    ? 'The found item has been uploaded.'
                    : 'Your found-item report has been submitted for staff review.'
            );
            redirect($isStaffReport ? 'manage-found.php' : 'my-claims.php#reports');
        } catch (PDOException $exception) {
            delete_item_image($upload['path']);
            error_log('Found item creation failed: ' . $exception->getMessage());
            http_response_code(500);
            $errors[] = 'The item could not be saved. Please try again later.';
        }
    }
}

$pageTitle = 'Upload Found Item';
$pageDesign = 'report-found';
require_once __DIR__ . '/../includes/header.php';
?>
<a class="relink-report-back" href="found-items.php" aria-label="Back to found items"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg><span>Back</span></a>
<section class="page-shell report-found-page">
    <div class="report-found-hero">
        <span class="report-hero-icon" aria-hidden="true">♡</span>
        <div><span class="eyebrow">Campus good deed</span><h1>Report a found item</h1><p>Help reunite lost belongings with their classroom owners safely and quickly.</p></div>
        <a class="report-manage-link" href="<?= $user['role'] === 'staff' ? 'manage-found.php' : 'my-claims.php#reports' ?>"><?= $user['role'] === 'staff' ? 'Manage items' : 'My reports' ?></a>
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

    <form class="surface-card form-panel" method="post" action="upload-found.php" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <section class="report-step" aria-labelledby="photo-step-title">
            <div class="report-step-heading"><span>1</span><h2 id="photo-step-title">Photos of item</h2><small>1 image per listing</small></div>
            <p class="report-step-help">Snap a clear photo so the owner can recognize their item.</p>
            <div class="report-photo-upload">
                <div class="field"><label for="image">Choose item photo</label><input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"><span class="field-note">JPG, PNG, GIF, or WebP; maximum 15 MB. An image is saved only when its description text is provided. Avoid showing personal information.</span></div>
            </div>
            <div class="field"><label for="image_alt">Photo description for accessibility</label><input id="image_alt" name="image_alt" type="text" maxlength="255" value="<?= e($values['image_alt']) ?>" placeholder="Describe the item shown in the photo"><span class="field-note">Required when an image is attached.</span></div>
        </section>

        <section class="report-step" aria-labelledby="location-step-title">
            <div class="report-step-heading"><span>2</span><h2 id="location-step-title">Discovery location</h2></div>
            <p class="report-step-help">Where and when did you find this item?</p>
            <div class="field"><label for="location">Building, room, and exact spot</label><input id="location" name="location" type="text" maxlength="255" value="<?= e($values['location']) ?>" placeholder="Science Building · Room 204 · Under lab table #4" required></div>
            <div class="field"><label for="date_event">Date found</label><input id="date_event" name="date_event" type="date" value="<?= e($values['date_event']) ?>" required></div>
        </section>

        <section class="report-step" aria-labelledby="classification-step-title">
            <div class="report-step-heading"><span>3</span><h2 id="classification-step-title">Item classification</h2></div>
            <fieldset class="field category-field"><legend>Item category</legend><div class="category-options"><?php foreach ($categories as $category): ?><label class="category-chip" for="category-<?= e($category['id']) ?>"><input id="category-<?= e($category['id']) ?>" name="category_id" type="radio" value="<?= e($category['id']) ?>" <?= $values['category_id'] === (string) $category['id'] ? 'checked' : '' ?> required><span><?= e($category['name']) ?></span></label><?php endforeach; ?></div></fieldset>
            <div class="field"><label for="title">Brand, model, and color</label><input id="title" name="title" type="text" maxlength="200" value="<?= e($values['title']) ?>" placeholder="Hydro Flask · Olive Green · 32oz" required></div>
            <div class="field"><label for="description">Public item description</label><textarea id="description" name="description" rows="4" maxlength="10000" placeholder="Describe visible features that can help the owner recognize it." required><?= e($values['description']) ?></textarea></div>
        </section>

        <section class="report-step" aria-labelledby="custody-step-title">
            <div class="report-step-heading"><span>4</span><h2 id="custody-step-title">Staff verification notes</h2></div>
            <p class="report-step-help">These details are private and will only be used to verify ownership.</p>
            <div class="field"><label for="verification_detail">Private identifiers or handoff details</label><textarea id="verification_detail" name="verification_detail" rows="4" maxlength="10000" placeholder="Hidden identifying marks, exact contents, or where staff can securely collect the item." required><?= e($values['verification_detail']) ?></textarea><span class="field-note">Keep identifying details out of the public description above.</span></div>
        </section>

        <label class="checkbox-field" for="consent">
            <input id="consent" name="consent" type="checkbox" value="1" required>
            <span>I agree to the <a href="privacy-policy.php">Privacy Policy</a> and <a href="terms.php">Terms</a>.</span>
        </label>

        <p class="report-privacy-note">◈ &nbsp; Safe campus protocol: your private verification notes are not shown in the public catalog. <?= $user['role'] === 'staff' ? 'Staff-uploaded items are listed immediately.' : 'A staff member must approve this report before it appears in Found Items.' ?></p>
        <button class="primary-button report-submit-button" type="submit"><?= $user['role'] === 'staff' ? 'Save found item' : 'Submit for staff review' ?> &nbsp; →</button>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
