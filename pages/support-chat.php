<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
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
                $conversation = $pdo->prepare(
                    "INSERT INTO support_conversations (student_id, subject, status)
                    VALUES (:student_id, :subject, 'open')"
                );
                $conversation->execute(['student_id' => $student['id'], 'subject' => $subjectInput]);
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
                redirect($chatRoot . $chatQuerySeparator . 'id=' . $conversationId);
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
                    redirect($chatRoot . $chatQuerySeparator . 'id=' . $conversationId);
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
        'SELECT id, subject, status, created_at, updated_at
        FROM support_conversations WHERE student_id = :student_id
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

$pageTitle = 'Customer Support';
if ($chatWidget) {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Customer Support | ReLink</title>
        <link rel="stylesheet" href="../assets/tailwind.generated.css?v=20261023-1">
        <link rel="stylesheet" href="../assets/site.css?v=20261111-6">
    </head>
    <body class="site-app website-shell design-reference design-zeta design-support-chat-widget">
    <main class="chat-widget-main">
    <?php
} else {
    require_once __DIR__ . '/../includes/header.php';
}
?>
<section class="support-chat-page mx-auto max-w-5xl" aria-labelledby="support-chat-title">
    <div class="support-chat-heading">
        <div class="support-chat-title-row">
            <span class="support-chat-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 18.5 3.5 20l1.2-3.7A8 8 0 1 1 8 19.2"></path><path d="M8 10h8M8 14h5"></path></svg>
            </span>
            <div>
                <p class="support-chat-eyebrow">ReLink help desk</p>
                <h1 id="support-chat-title" class="text-3xl font-bold tracking-tight">Customer support</h1>
            </div>
            <span class="support-chat-private"><span aria-hidden="true"></span> Private conversation</span>
        </div>
        <p class="support-chat-intro">Need help with a lost report, found item, claim, or your account? Send our support team a message and check back here for a reply. Responses may not be immediate.</p>
    </div>
    <?php if ($errors !== []): ?>
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900" role="alert">
            <ul class="list-inside list-disc"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <div class="support-chat-layout <?= $selectedConversation !== null ? 'has-selected-chat' : '' ?><?= $conversations !== [] ? ' has-conversations' : '' ?>">
        <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="chat-history-title">
            <div class="chat-history-heading">
                <h2 id="chat-history-title" class="text-xl font-bold">Your conversations</h2>
                <a href="<?= e($chatRoot) ?>">New message</a>
            </div>
            <?php if ($conversations === []): ?>
                <p class="mt-4 text-slate-600">Your previous conversations will appear here.</p>
            <?php else: ?>
                <ul class="mt-4 space-y-2">
                    <?php foreach ($conversations as $conversation): ?>
                        <li>
                            <a class="block rounded-lg border p-3 <?= $selectedConversation !== null && (int) $selectedConversation['id'] === (int) $conversation['id'] ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:bg-slate-50' ?>" href="<?= e($chatRoot . $chatQuerySeparator . 'id=' . $conversation['id']) ?>">
                                <span class="chat-history-subject">
                                    <svg class="chat-history-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 18.5 3.5 20l1.2-3.7A8 8 0 1 1 8 19.2" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5" />
                                    </svg>
                                    <span><?= e($conversation['subject']) ?></span>
                                </span>
                                <span class="mt-1 block text-sm capitalize text-slate-600"><?= e($conversation['status']) ?> · <?= e($conversation['updated_at']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!$chatWidget): ?>
                <div class="chat-help-card">
                    <strong>Before you message us</strong>
                    <p>Never send your password, sign-in code, or other sensitive account details.</p>
                </div>
            <?php endif; ?>
        </aside>
        <div class="chat-main-column">
            <?php if ($selectedConversation !== null): ?>
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="conversation-title">
                    <div class="conversation-header flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
                        <?php if ($chatWidget): ?><span class="chat-agent-avatar" aria-hidden="true">R</span><?php endif; ?>
                        <div class="conversation-header-copy">
                            <h2 id="conversation-title" class="text-xl font-bold"><?= $chatWidget ? 'ReLink Support' : e($selectedConversation['subject']) ?></h2>
                            <p class="mt-1 text-sm capitalize text-slate-600"><?= $chatWidget ? e($selectedConversation['subject']) . ' · ' . e($selectedConversation['status']) : 'Customer support · ' . e($selectedConversation['status']) ?></p>
                        </div>
                        <?php if ($chatWidget): ?><a class="chat-widget-history-link" href="<?= e($chatRoot) ?>">Chat history</a><?php endif; ?>
                        <?php if ($selectedConversation['status'] === 'closed'): ?><span class="closed-chat-status">Closed · history only</span><?php endif; ?>
                    </div>
                    <ol class="chat-messages max-h-[24rem] space-y-4 overflow-y-auto bg-slate-50 p-5" aria-label="Messages">
                        <?php foreach ($messages as $message): ?>
                            <?php $senderAvatar = profile_avatar_src($message['sender_avatar']); ?>
                            <li class="chat-message-row <?= $message['sender_role'] === 'staff' ? 'is-staff' : 'is-user' ?>">
                                <?php if ($senderAvatar !== null): ?>
                                    <img class="chat-message-avatar" src="<?= e($senderAvatar) ?>" alt="">
                                <?php else: ?>
                                    <span class="chat-message-avatar chat-message-avatar-fallback" aria-hidden="true"><?= $message['sender_role'] === 'staff' ? 'S' : 'U' ?></span>
                                <?php endif; ?>
                                <article class="chat-message-bubble <?= $message['sender_role'] === 'staff' ? 'is-incoming' : 'is-outgoing' ?>">
                                    <p class="chat-message-identity">
                                        <span><?= e($message['sender_name']) ?> · <?= e($message['created_at']) ?></span>
                                    </p>
                                    <p class="mt-2 whitespace-pre-wrap break-words text-slate-900"><?= e($message['message']) ?></p>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php if ($selectedConversation['status'] === 'open'): ?>
                        <form class="chat-composer border-t border-slate-200 p-5" method="post" action="<?= e($chatRoot . $chatQuerySeparator . 'id=' . $selectedConversation['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="conversation_id" value="<?= e($selectedConversation['id']) ?>">
                            <label class="sr-only" for="reply-message">Message to customer support</label>
                            <textarea class="min-h-20 w-full rounded-md border border-slate-400 p-3 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" id="reply-message" name="message" maxlength="4000" placeholder="Write a message to the support team..." required><?= e($messageInput) ?></textarea>
                            <button class="chat-send-button" type="submit" aria-label="Send message" title="Send message">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.5 2.5 14 21.5l-4.2-8.3L1.5 9l20-6.5ZM9.8 13.2l5.1-5.1" /></svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <?php if ($selectedConversation === null): ?>
                <?php if ($chatWidget): ?>
                    <section class="new-chat-panel chat-new-conversation" aria-label="New conversation with customer support">
                        <div class="chat-agent-header">
                            <span class="chat-agent-avatar" aria-hidden="true">R</span>
                            <div><h2>ReLink Support</h2><p>Customer service</p></div>
                            <span class="chat-agent-status"><span aria-hidden="true"></span> Here to help</span>
                        </div>
                        <div class="chat-new-thread" aria-label="Conversation">
                            <div class="chat-message-row is-staff">
                                <span class="chat-message-avatar chat-message-avatar-fallback" aria-hidden="true">R</span>
                                <article class="chat-message-bubble is-incoming">
                                    <p class="chat-message-identity"><span>ReLink Support</span></p>
                                    <p>Hi! Welcome to ReLink support. How can we help you today?</p>
                                    <p class="chat-privacy-note">For your safety, never share your password or sign-in codes.</p>
                                </article>
                            </div>
                        </div>
                        <form class="chat-composer chat-new-composer" method="post" action="<?= e($chatRoot) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="start">
                            <label class="sr-only" for="subject">Conversation subject</label>
                            <input class="chat-subject-input" id="subject" name="subject" type="text" maxlength="150" value="<?= e($subjectInput) ?>" placeholder="Subject (for example: Help with my claim)" required>
                            <div class="chat-composer-row">
                                <label class="sr-only" for="first-message">Type a message</label>
                                <textarea id="first-message" name="message" maxlength="4000" placeholder="Type a message..." required><?= e($messageInput) ?></textarea>
                                <button class="chat-send-button" type="submit" aria-label="Send message" title="Send message">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.5 2.5 14 21.5l-4.2-8.3L1.5 9l20-6.5ZM9.8 13.2l5.1-5.1" /></svg>
                                </button>
                            </div>
                        </form>
                    </section>
                <?php else: ?>
                    <section class="new-chat-panel rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="new-chat-title">
                        <div class="new-chat-heading">
                            <span class="new-chat-heading-icon" aria-hidden="true">?</span>
                            <div><h2 id="new-chat-title" class="text-xl font-bold">How can we help?</h2><p>Send a message to the ReLink customer support team.</p></div>
                        </div>
                        <form class="mt-4 space-y-4" method="post" action="<?= e($chatRoot) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="start">
                            <div>
                                <label class="mb-1 block font-medium" for="subject">Subject</label>
                                <input class="min-h-11 w-full rounded-md border border-slate-400 px-3" id="subject" name="subject" type="text" maxlength="150" value="<?= e($subjectInput) ?>" placeholder="For example: Help with my claim" required>
                            </div>
                            <div>
                                <label class="mb-1 block font-medium" for="first-message">How can we help?</label>
                                <textarea class="min-h-20 w-full rounded-md border border-slate-400 p-3" id="first-message" name="message" maxlength="4000" placeholder="Tell us what happened and include any relevant item or report details." required><?= e($messageInput) ?></textarea>
                            </div>
                            <p class="chat-form-note">Replies will be sent to this conversation. Please do not include passwords or verification codes.</p>
                            <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white hover:bg-blue-900" type="submit">Send to customer support</button>
                        </form>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php if ($chatWidget): ?>
    <script>
        const chatMessages = document.querySelector('.chat-messages, .chat-new-thread');
        if (chatMessages instanceof HTMLElement) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
    </main>
    </body>
    </html>
<?php else: ?>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
