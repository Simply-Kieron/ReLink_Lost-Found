<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/support_submission.php';
require_once __DIR__ . '/../includes/profile_avatar.php';

$student = require_role('student');
require_once __DIR__ . '/../includes/db.php';

$errors = [];
$notice = null;
$subjectInput = '';
$messageInput = '';
$chatWidget = isset($_GET['widget']) && $_GET['widget'] === '1';
$chatRoot = 'support-chat.php' . ($chatWidget ? '?widget=1' : '');
$chatQuerySeparator = $chatWidget ? '&' : '?';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    $submissionError = in_array($action, ['start', 'reply'], true) ? support_submission_check($action, $chatRoot, $chatQuerySeparator) : '';
    if ($submissionError !== '') {
        $errors[] = $submissionError;
    }

    if ($action === 'start') {
        $subjectValue = $_POST['subject'] ?? '';
        $messageValue = $_POST['message'] ?? '';
        $subjectInput = is_string($subjectValue) ? trim($subjectValue) : '';
        $messageInput = is_string($messageValue) ? trim($messageValue) : '';

        if ($subjectInput === '' || strlen($subjectInput) > 150) {
            $errors[] = 'Enter a subject using no more than 150 characters.';
        }
        if ($messageInput === '' || strlen($messageInput) > 4000) {
            $errors[] = 'Enter a message using no more than 4,000 characters.';
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();
                $referenceCode = generate_support_chat_reference_code();
                $conversation = $pdo->prepare(
                    "INSERT INTO support_conversations (reference_code, student_id, subject, status)
                    VALUES (:reference_code, :student_id, :subject, 'open')"
                );
                $conversation->execute([
                    'reference_code' => $referenceCode,
                    'student_id' => $student['id'],
                    'subject' => $subjectInput,
                ]);
                $conversationId = (int) $pdo->lastInsertId();
                $message = $pdo->prepare(
                    'INSERT INTO support_messages (conversation_id, sender_id, message)
                    VALUES (:conversation_id, :sender_id, :message)'
                );
                $message->execute([
                    'conversation_id' => $conversationId,
                    'sender_id' => $student['id'],
                    'message' => $messageInput,
                ]);
                $touchConversation = $pdo->prepare(
                    'UPDATE support_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id'
                );
                $touchConversation->execute(['id' => $conversationId]);
                $pdo->commit();
                support_submission_complete($conversationId);
                redirect($chatRoot . $chatQuerySeparator . 'id=' . $conversationId . '&sent=1');
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Support conversation creation failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Your support chat could not be started. Please try again later.';
            }
        }
    } elseif ($action === 'reply') {
        $conversationId = filter_var($_POST['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $messageValue = $_POST['message'] ?? '';
        $messageInput = is_string($messageValue) ? trim($messageValue) : '';
        if ($conversationId === false || $conversationId === null) {
            http_response_code(400);
            exit('Choose a valid support conversation.');
        }
        if ($messageInput === '' || strlen($messageInput) > 4000) {
            $errors[] = 'Enter a message using no more than 4,000 characters.';
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();
                $conversation = $pdo->prepare(
                    'SELECT status FROM support_conversations WHERE id = :id AND student_id = :student_id FOR UPDATE'
                );
                $conversation->execute(['id' => $conversationId, 'student_id' => $student['id']]);
                $status = $conversation->fetchColumn();
                if ($status === false) {
                    $pdo->rollBack();
                    http_response_code(404);
                    exit('Support conversation not found.');
                }
                if ($status !== 'open') {
                    $pdo->rollBack();
                    $errors[] = 'This conversation is closed. Start a new chat to contact staff.';
                } else {
                    $message = $pdo->prepare(
                        'INSERT INTO support_messages (conversation_id, sender_id, message)
                        VALUES (:conversation_id, :sender_id, :message)'
                    );
                    $message->execute([
                        'conversation_id' => $conversationId,
                        'sender_id' => $student['id'],
                        'message' => $messageInput,
                    ]);
                    $touchConversation = $pdo->prepare(
                        'UPDATE support_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id'
                    );
                    $touchConversation->execute(['id' => $conversationId]);
                    $pdo->commit();
                    support_submission_complete($conversationId);
                    redirect($chatRoot . $chatQuerySeparator . 'id=' . $conversationId . '&sent=1');
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Support message submission failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Your message could not be sent. Please try again later.';
            }
        }
    } else {
        http_response_code(400);
        exit('Choose a valid support chat action.');
    }
}

try {
    $conversationsStatement = $pdo->prepare(
        'SELECT support_conversations.id, support_conversations.reference_code, support_conversations.subject,
            support_conversations.status, support_conversations.created_at, support_conversations.updated_at,
            (SELECT support_messages.message
            FROM support_messages
            WHERE support_messages.conversation_id = support_conversations.id
            ORDER BY support_messages.id DESC
            LIMIT 1) AS last_message
        FROM support_conversations
        WHERE support_conversations.student_id = :student_id
        ORDER BY updated_at DESC, id DESC'
    );
    $conversationsStatement->execute(['student_id' => $student['id']]);
    $conversations = $conversationsStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('Support chat history query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Support chat history is temporarily unavailable.');
}

$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedConversation = null;
$messages = [];
if ($selectedId !== false && $selectedId !== null) {
    foreach ($conversations as $conversation) {
        if ((int) $conversation['id'] === $selectedId) {
            $selectedConversation = $conversation;
            break;
        }
    }
    if ($selectedConversation === null) {
        http_response_code(404);
        exit('Support conversation not found.');
    }
    $messagesStatement = $pdo->prepare(
        'SELECT support_messages.message, support_messages.created_at, support_messages.sender_id,
            users.name AS sender_name, users.role AS sender_role, users.avatar_path AS sender_avatar
        FROM support_messages
        INNER JOIN users ON users.id = support_messages.sender_id
        WHERE support_messages.conversation_id = :conversation_id
        ORDER BY support_messages.id ASC'
    );
    $messagesStatement->execute(['conversation_id' => $selectedId]);
    $messages = $messagesStatement->fetchAll();
}

$pageTitle = 'Customer Service';
if ($chatWidget): ?>
<!doctype html>
<html lang="en" class="support-chat-widget-document"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>Customer Service | ReLink</title><link rel="stylesheet" href="../assets/tailwind.generated.css?v=20261023-1"><link rel="stylesheet" href="../assets/style-base.php?v=20261116-4"><link rel="icon" href="../assets/relink-icon.svg" type="image/svg+xml"><link rel="stylesheet" href="../assets/ui-consistency.css?v=<?= (int) filemtime(__DIR__ . '/../assets/ui-consistency.css') ?>"></head><body class="site-app website-shell design-reference design-zeta relink-phase5 relink-support-document design-support-chat-widget"><main>
<?php else: require_once __DIR__ . '/../includes/header.php'; endif; ?>
<?php $isEmbeddedSupport = $chatWidget; $chatWidget = true; require __DIR__ . '/../includes/legacy_support_view.php'; $chatWidget = $isEmbeddedSupport; ?>
<script src="../assets/legacy-support.js?v=<?= (int) filemtime(__DIR__ . '/../assets/legacy-support.js') ?>" defer></script>
<?php if ($chatWidget): ?>
</main><script src="../assets/phase5.js?v=<?= (int) filemtime(__DIR__ . '/../assets/phase5.js') ?>" defer></script></body></html>
<?php else: require_once __DIR__ . '/../includes/footer.php'; endif; ?>