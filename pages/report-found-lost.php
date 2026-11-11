<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(404);
    exit('Lost item not found.');
}

$sessionUser = current_user();
if ($sessionUser === null) {
    redirect('login.php?continue=' . rawurlencode('report-found-lost.php?id=' . $id));
}
$reporter = require_role($sessionUser['role']);
require_once __DIR__ . '/../includes/db.php';

try {
    $itemStatement = $pdo->prepare(
        "SELECT items.id, items.title, items.location, items.date_event, items.status, items.reported_by,
            categories.name AS category_name
        FROM items
        INNER JOIN categories ON categories.id = items.category_id
        WHERE items.id = :id AND items.type = 'lost' AND items.review_status = 'approved'
        LIMIT 1"
    );
    $itemStatement->execute(['id' => $id]);
    $item = $itemStatement->fetch();
} catch (PDOException $exception) {
    error_log('Lost item finding report lookup failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The lost item is temporarily unavailable. Please try again later.');
}

if ($item === false) {
    http_response_code(404);
    exit('Lost item not found.');
}

$errors = [];
$findingDetails = '';
$ownItem = (int) $item['reported_by'] === $reporter['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $detailsInput = $_POST['finding_details'] ?? '';
    $findingDetails = is_string($detailsInput) ? trim($detailsInput) : '';

    if ($findingDetails === '' || strlen($findingDetails) > 3500) {
        $errors[] = 'Describe where and when you found the item using no more than 3,500 bytes.';
    }
    if ($ownItem) {
        $errors[] = 'You cannot report finding an item that you reported as lost.';
    }
    if ($item['status'] !== 'open') {
        $errors[] = 'This lost-item report is no longer open for found-item reports.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $lockItem = $pdo->prepare(
                "SELECT title, date_event, status, reported_by FROM items
                WHERE id = :id AND type = 'lost' AND review_status = 'approved' FOR UPDATE"
            );
            $lockItem->execute(['id' => $id]);
            $lockedItem = $lockItem->fetch();

            if ($lockedItem === false) {
                $pdo->rollBack();
                http_response_code(404);
                exit('Lost item not found.');
            }
            if ((int) $lockedItem['reported_by'] === $reporter['id']) {
                $pdo->rollBack();
                $errors[] = 'You cannot report finding an item that you reported as lost.';
            } elseif ($lockedItem['status'] !== 'open') {
                $pdo->rollBack();
                $errors[] = 'This lost-item report is no longer open for found-item reports.';
            } else {
                $isStaffFinder = $reporter['role'] === 'staff';
                $subject = ($isStaffFinder ? 'We found your lost item #' : 'Found item report #') . $id;
                $messageText = $isStaffFinder
                    ? "A staff member found an item you reported as lost.\n"
                        . 'Lost item: ' . $lockedItem['title'] . "\n"
                        . 'Lost-item report ID: #LF-' . $id . "\n"
                        . 'Date reported lost: ' . $lockedItem['date_event'] . "\n\n"
                        . "Where and when it was found:\n" . $findingDetails
                    : "A student reported finding an item from a lost-item listing.\n"
                        . 'Lost item: ' . $lockedItem['title'] . "\n"
                        . 'Lost-item report ID: #LF-' . $id . "\n"
                        . 'Date reported lost: ' . $lockedItem['date_event'] . "\n\n"
                        . "Finder's details:\n" . $findingDetails;

                $conversation = $pdo->prepare(
                    "INSERT INTO support_conversations (student_id, subject, status)
                    VALUES (:student_id, :subject, 'open')"
                );
                $conversation->execute([
                    'student_id' => $isStaffFinder ? (int) $lockedItem['reported_by'] : $reporter['id'],
                    'subject' => $subject,
                ]);
                $conversationId = (int) $pdo->lastInsertId();

                $message = $pdo->prepare(
                    'INSERT INTO support_messages (conversation_id, sender_id, message)
                    VALUES (:conversation_id, :sender_id, :message)'
                );
                $message->execute([
                    'conversation_id' => $conversationId,
                    'sender_id' => $reporter['id'],
                    'message' => $messageText,
                ]);

                $pdo->commit();
                if ($isStaffFinder) {
                    set_flash('success', 'A private update was sent to the student who reported this item.');
                    redirect('support-inbox.php?id=' . $conversationId);
                }
                set_flash('success', 'Your found-item report was sent to staff. They will help notify the person who reported it.');
                redirect('support-chat.php?id=' . $conversationId);
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Lost item finding report submission failed: ' . $exception->getMessage());
            http_response_code(500);
            $errors[] = 'Your report could not be sent right now. Please try again later.';
        }
    }
}

$pageTitle = 'Did You Find This Item?';
$pageDesign = 'report-found';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell report-found-page" aria-labelledby="report-found-lost-title">
    <div class="report-found-hero">
        <span class="report-hero-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 3 8 4v10l-8 4-8-4V7l8-4Z"/>
                <path d="m4.5 7.3 7.5 4 7.5-4M12 11.3V21M9 15l2 2 4-4"/>
            </svg>
        </span>
        <div>
            <p class="eyebrow">Help reunite an item</p>
            <h1 id="report-found-lost-title">Did you find this item?</h1>
            <p><?= $reporter['role'] === 'staff'
                ? 'Send a private update to the student who reported this item.'
                : 'Send a private report to staff. They will help verify the item and contact the person who reported it lost.' ?></p>
        </div>
    </div>

    <section class="surface-card mt-4 p-5" aria-labelledby="lost-item-title">
        <p class="eyebrow">Lost item #LF-<?= e($item['id']) ?></p>
        <h2 id="lost-item-title"><?= e($item['title']) ?></h2>
        <p><?= e($item['category_name']) ?> · Lost <?= e($item['date_event']) ?></p>
        <p><strong>Last seen at:</strong> <?= e($item['location']) ?></p>
    </section>

    <?php if ($errors !== []): ?>
        <div class="error-box mt-4" role="alert">
            <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($ownItem): ?>
        <p class="empty-state mt-4">You cannot report finding your own lost-item listing.</p>
    <?php elseif ($item['status'] !== 'open'): ?>
        <p class="empty-state mt-4">This lost-item report is no longer open.</p>
    <?php else: ?>
        <form class="surface-card form-panel mt-4" method="post" action="report-found-lost.php?id=<?= e($id) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="finding_details">Where and when did you find it?</label>
                <textarea id="finding_details" name="finding_details" maxlength="3500" required><?= e($findingDetails) ?></textarea>
                <span class="field-note"><?= $reporter['role'] === 'staff'
                    ? 'Include where the item is being held and how the owner can collect it. The student will receive this as a private message.'
                    : 'Include enough detail for staff to follow up. Your report is sent to the staff inbox.' ?></span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button class="primary-button" type="submit"><?= $reporter['role'] === 'staff' ? 'Send private update to owner' : 'Send report to staff' ?></button>
                <a class="secondary-button" href="lost-items.php">Back to lost items</a>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
