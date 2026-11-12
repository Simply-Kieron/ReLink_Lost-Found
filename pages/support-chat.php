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

$pageTitle = 'Customer Support';
if ($chatWidget) {
    ?>
    <!doctype html>
    <html lang="en" class="support-chat-widget-document">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Customer Support | ReLink</title>
        <link rel="stylesheet" href="../assets/tailwind.generated.css?v=20261023-1">
        <link rel="stylesheet" href="../assets/site.css?v=20261112-17">
    </head>
    <body class="site-app website-shell design-reference design-support-chat-widget">
    <main class="chat-widget-main">
    <?php
} else {
    require_once __DIR__ . '/../includes/header.php';
}
?>
<?php if ($chatWidget): ?>
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
                                <span class="chat-reference-code">Chat #<?= e($conversation['reference_code']) ?></span>
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
                <section class="<?= $chatWidget ? 'chat-new-conversation widget-chat-window' : 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm' ?>" <?= $chatWidget ? 'aria-label="Customer support conversation"' : 'aria-labelledby="conversation-title"' ?>>
                    <?php if ($chatWidget): ?>
                        <div class="widget-chat-header">
                            <div class="widget-chat-header-top">
                                <span class="widget-chat-avatar" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 3.5h3l1.5 4-2 1.5a15 15 0 0 0 5 5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.3 16.3 0 0 1 5.5 5.7a2 2 0 0 1 2-2.2Z" /></svg>
                                </span>
                                <div class="widget-chat-heading">
                                    <strong>Customer Support</strong>
                                    <span><i aria-hidden="true"></i>Replies may not be immediate</span>
                                </div>
                                <div class="chat-header-actions">
                                    <button class="chat-widget-minimize" type="button" aria-label="Minimize chat" title="Minimize chat">−</button>
                                    <button class="chat-header-close" type="button" aria-label="Close chat" title="Close chat">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                                            <path d="M5 5l14 14M19 5L5 19" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="widget-chat-reference">
                                <div>
                                    <small>CHAT REFERENCE NUMBER</small>
                                    <strong class="widget-chat-reference-code"><?= e($selectedConversation['reference_code']) ?></strong>
                                </div>
                                <button class="widget-chat-copy" type="button" aria-label="Copy reference number" title="Copy reference number">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" /><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3" /></svg>
                                </button>
                                <span class="sr-only widget-chat-copy-status" role="status" aria-live="polite"></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="conversation-header flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
                            <div class="conversation-header-copy">
                                <h2 id="conversation-title" class="text-xl font-bold"><?= e($selectedConversation['subject']) ?></h2>
                                <p class="mt-1 text-sm capitalize text-slate-600">Chat #<?= e($selectedConversation['reference_code']) ?> · Customer support · <?= e($selectedConversation['status']) ?></p>
                            </div>
                            <?php if ($selectedConversation['status'] === 'closed'): ?><span class="closed-chat-status">Closed · history only</span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <ol class="chat-messages max-h-[24rem] space-y-4 overflow-y-auto p-5<?= $chatWidget ? '' : ' bg-slate-50' ?>" aria-label="Messages">
                        <?php $lastWidgetMessageDate = null; ?>
                        <?php foreach ($messages as $message): ?>
                            <?php if ($chatWidget): ?>
                                <?php
                                $messageTimestamp = strtotime($message['created_at']);
                                $messageDate = date('Y-m-d', $messageTimestamp);
                                if ($messageDate !== $lastWidgetMessageDate):
                                    $lastWidgetMessageDate = $messageDate;
                                    $messageDateLabel = $messageDate === date('Y-m-d')
                                        ? 'Today · ' . date('F j, Y', $messageTimestamp)
                                        : date('F j, Y', $messageTimestamp);
                                ?>
                                    <li class="widget-chat-date"><time datetime="<?= e(date('Y-m-d', $messageTimestamp)) ?>"><?= e($messageDateLabel) ?></time></li>
                                <?php endif; ?>
                                <li class="chat-message-row <?= $message['sender_role'] === 'staff' ? 'is-staff' : 'is-user' ?>">
                                    <?php if ($message['sender_role'] === 'staff'): ?>
                                        <span class="widget-chat-agent-avatar" aria-hidden="true">☎</span>
                                    <?php endif; ?>
                                    <article class="chat-message-bubble <?= $message['sender_role'] === 'staff' ? 'is-incoming' : 'is-outgoing' ?>">
                                        <p class="whitespace-pre-wrap break-words"><?= e($message['message']) ?></p>
                                        <time class="widget-chat-timestamp" datetime="<?= e(date('c', strtotime($message['created_at']))) ?>"><?= e(date('g:i A', strtotime($message['created_at']))) ?></time>
                                    </article>
                                </li>
                            <?php else: ?>
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
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                    <?php if ($selectedConversation['status'] === 'open'): ?>
                        <form class="<?= $chatWidget ? 'chat-composer' : 'chat-composer border-t border-slate-200 p-5' ?>" method="post" action="<?= e($chatRoot . $chatQuerySeparator . 'id=' . $selectedConversation['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="conversation_id" value="<?= e($selectedConversation['id']) ?>">
                            <label class="sr-only" for="reply-message">Message to customer support</label>
                            <div class="chat-composer-row">
                                <textarea id="reply-message" name="message" maxlength="4000" placeholder="Type a message..." required><?= e($messageInput) ?></textarea>
                                <button class="chat-send-button" type="submit" aria-label="Send message" title="Send message" <?= $chatWidget ? 'disabled' : '' ?>>
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.5 2.5 14 21.5l-4.2-8.3L1.5 9l20-6.5ZM9.8 13.2l5.1-5.1" /></svg>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    <?php if ($chatWidget): ?>
                        <div class="widget-chat-footer">Keep your chat reference number for follow-ups.</div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <?php if ($selectedConversation === null): ?>
                <?php if ($chatWidget): ?>
                    <section class="new-chat-panel chat-new-conversation" aria-label="New conversation with customer support">
                        <div class="widget-chat-header">
                            <div class="widget-chat-header-top">
                                <span class="widget-chat-avatar" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 3.5h3l1.5 4-2 1.5a15 15 0 0 0 5 5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.3 16.3 0 0 1 5.5 5.7a2 2 0 0 1 2-2.2Z" /></svg>
                                </span>
                                <div class="widget-chat-heading">
                                    <strong>Customer Support</strong>
                                    <span><i aria-hidden="true"></i>Replies may not be immediate</span>
                                </div>
                                <div class="chat-header-actions">
                                    <button class="chat-widget-minimize" type="button" aria-label="Minimize chat" title="Minimize chat">−</button>
                                    <button class="chat-header-close" type="button" aria-label="Close chat" title="Close chat">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                                            <path d="M5 5l14 14M19 5L5 19" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="widget-chat-reference">
                                <div>
                                    <small>CHAT REFERENCE NUMBER</small>
                                    <strong class="widget-chat-reference-pending">Assigned after your first message</strong>
                                </div>
                                <button class="widget-chat-copy" type="button" aria-label="Copy reference number" title="Copy reference number" disabled>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" /><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3" /></svg>
                                </button>
                                <span class="sr-only widget-chat-copy-status" role="status" aria-live="polite"></span>
                            </div>
                        </div>
                        <div class="chat-new-thread" aria-label="Conversation"></div>
                        <form class="chat-composer chat-new-composer" method="post" action="<?= e($chatRoot) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="start">
                            <input type="hidden" name="subject" value="General support">
                            <div class="chat-composer-row">
                                <label class="sr-only" for="first-message">Type a message</label>
                                <textarea id="first-message" name="message" maxlength="4000" placeholder="Type a message..." required><?= e($messageInput) ?></textarea>
                                <button class="chat-send-button" type="submit" aria-label="Send message" title="Send message" disabled>
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.5 2.5 14 21.5l-4.2-8.3L1.5 9l20-6.5ZM9.8 13.2l5.1-5.1" /></svg>
                                </button>
                            </div>
                        </form>
                        <div class="widget-chat-footer">A reference number is assigned after you send your first message.</div>
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
<?php else: ?>
<section class="support-chat-center" aria-labelledby="support-chat-title">
    <p class="support-chat-center-breadcrumb">ReLink Help Desk</p>
    <div class="support-chat-center-heading">
        <div>
            <h1 id="support-chat-title">Customer support</h1>
            <p>Pick up where you left off, follow up on your concerns, or review your completed conversations.</p>
        </div>
        <button class="support-chat-primary" type="button" data-open-support-chat>+ New conversation</button>
    </div>

    <?php if ($errors !== []): ?>
        <div class="support-chat-center-error" role="alert">
            <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="support-chat-center-layout">
        <aside class="support-chat-center-panel" aria-label="Your conversations">
            <div class="support-chat-center-sidebar-head">
                <h2>Your conversations</h2>
                <label class="sr-only" for="support-chat-search">Search subject or reference number</label>
                <input id="support-chat-search" class="support-chat-search" type="search" placeholder="Search subject or reference number">
                <div class="support-chat-filters" aria-label="Filter conversations">
                    <button class="is-active" type="button" data-chat-filter="all" aria-pressed="true">All</button>
                    <button type="button" data-chat-filter="open" aria-pressed="false">Open</button>
                    <button type="button" data-chat-filter="closed" aria-pressed="false">Ended</button>
                </div>
            </div>
            <ul class="support-chat-thread-list">
                <?php foreach ($conversations as $conversation): ?>
                    <?php
                    $conversationSearch = $conversation['subject'] . ' ' . $conversation['reference_code'];
                    $conversationPreview = trim((string) ($conversation['last_message'] ?? ''));
                    ?>
                    <li data-chat-status="<?= e($conversation['status']) ?>" data-chat-search="<?= e($conversationSearch) ?>">
                        <a class="support-chat-thread<?= $selectedConversation !== null && (int) $selectedConversation['id'] === (int) $conversation['id'] ? ' is-selected' : '' ?>" href="<?= e($chatRoot . $chatQuerySeparator . 'id=' . $conversation['id']) ?>"<?= $selectedConversation !== null && (int) $selectedConversation['id'] === (int) $conversation['id'] ? ' aria-current="page"' : '' ?>>
                            <span class="support-chat-thread-top">
                                <span class="support-chat-thread-subject"><?= e($conversation['subject']) ?></span>
                                <time datetime="<?= e(date('c', strtotime($conversation['updated_at']))) ?>"><?= e(date('M j, g:i A', strtotime($conversation['updated_at']))) ?></time>
                            </span>
                            <span class="support-chat-thread-reference">#<?= e($conversation['reference_code']) ?></span>
                            <span class="support-chat-thread-bottom">
                                <span class="support-chat-thread-preview"><?= e($conversationPreview !== '' ? $conversationPreview : 'No messages yet') ?></span>
                                <span class="support-chat-status is-<?= e($conversation['status']) ?>"><?= $conversation['status'] === 'closed' ? 'Ended' : 'Open' ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($conversations === []): ?>
                <p class="support-chat-list-empty">Your conversations will appear here.</p>
            <?php else: ?>
                <p class="support-chat-list-empty" data-chat-no-results hidden>No conversations match your search.</p>
            <?php endif; ?>
        </aside>

        <section class="support-chat-center-panel support-chat-center-details" aria-label="Selected conversation">
            <?php if ($selectedConversation !== null): ?>
                <?php $selectedIsClosed = $selectedConversation['status'] === 'closed'; ?>
                <header class="support-chat-center-chat-head">
                    <div class="support-chat-center-chat-title">
                        <div>
                            <h2><?= e($selectedConversation['subject']) ?></h2>
                            <p><?= $selectedIsClosed ? 'This conversation has ended. You can still review the messages.' : 'This conversation is active. Continue where you left off.' ?></p>
                        </div>
                        <span class="support-chat-status is-<?= e($selectedConversation['status']) ?>"><?= $selectedIsClosed ? 'Ended' : 'Open' ?></span>
                    </div>
                    <div class="support-chat-center-header-actions">
                        <div class="support-chat-center-reference">
                            <span>CHAT REFERENCE</span>
                            <strong id="support-chat-reference"><?= e($selectedConversation['reference_code']) ?></strong>
                            <button type="button" data-copy-support-chat aria-label="Copy chat reference">Copy</button>
                            <span class="sr-only" data-support-chat-copy-status role="status" aria-live="polite"></span>
                        </div>
                    </div>
                </header>
                <ol class="support-chat-center-messages" aria-label="Conversation messages">
                    <li class="support-chat-center-date">Conversation history</li>
                    <?php foreach ($messages as $message): ?>
                        <?php $messageIsStaff = $message['sender_role'] === 'staff'; ?>
                        <li class="support-chat-center-message<?= $messageIsStaff ? ' is-agent' : ' is-customer' ?>">
                            <span class="support-chat-center-who"><?= $messageIsStaff ? 'ReLink Support' : 'You' ?></span>
                            <article>
                                <p><?= e($message['message']) ?></p>
                            </article>
                            <time datetime="<?= e(date('c', strtotime($message['created_at']))) ?>"><?= e(date('M j, g:i A', strtotime($message['created_at']))) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!$selectedIsClosed): ?>
                    <form class="support-chat-center-composer" method="post" action="<?= e($chatRoot . $chatQuerySeparator . 'id=' . $selectedConversation['id']) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="conversation_id" value="<?= e($selectedConversation['id']) ?>">
                        <label class="sr-only" for="support-chat-reply">Your message</label>
                        <div>
                            <textarea id="support-chat-reply" name="message" maxlength="4000" placeholder="Type a follow-up message..." required><?= e($messageInput) ?></textarea>
                            <button class="support-chat-primary" type="submit">Send</button>
                        </div>
                        <p>Replies appear here under the same reference number.</p>
                    </form>
                <?php else: ?>
                    <div class="support-chat-ended">
                        <span><strong>Conversation ended</strong><br>You can review this chat. For additional assistance, start a new conversation.</span>
                        <button class="support-chat-secondary" type="button" data-open-support-chat>New chat</button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="support-chat-center-empty">
                    <h2><?= $conversations === [] ? 'Start a conversation' : 'Choose a conversation' ?></h2>
                    <p><?= $conversations === [] ? 'Send a message to the ReLink customer support team and follow up here.' : 'Select a conversation to review its messages or continue chatting with support.' ?></p>
                    <?php if ($conversations === []): ?>
                        <button class="support-chat-primary" type="button" data-open-support-chat>+ New conversation</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <p class="support-chat-center-note">Closing the chat page does not end your conversation. Return here anytime to review or continue it.</p>

    <dialog class="support-chat-dialog" id="new-support-chat-dialog" aria-labelledby="new-support-chat-title">
        <form method="post" action="<?= e($chatRoot) ?>">
            <?= csrf_field() ?>
            <h2 id="new-support-chat-title">New support conversation</h2>
            <p>Describe your concern. Your message will be saved under a new reference number.</p>
            <label for="support-chat-subject">Subject</label>
            <input id="support-chat-subject" name="subject" type="text" maxlength="150" value="<?= e($subjectInput) ?>" placeholder="For example: Help with my claim" required>
            <label for="support-chat-first-message">How can we help?</label>
            <textarea id="support-chat-first-message" name="message" maxlength="4000" placeholder="Tell us what happened..." required><?= e($messageInput) ?></textarea>
            <p class="support-chat-dialog-note">Please do not include passwords or verification codes.</p>
            <div class="support-chat-dialog-actions">
                <button class="support-chat-secondary" type="button" data-close-support-chat>Cancel</button>
                <button class="support-chat-primary" type="submit" name="action" value="start">Start conversation</button>
            </div>
        </form>
    </dialog>
</section>
<script>
    const conversationSearch = document.getElementById('support-chat-search');
    const conversationItems = Array.from(document.querySelectorAll('.support-chat-thread-list > li'));
    const noConversationMatches = document.querySelector('[data-chat-no-results]');
    const conversationFilters = Array.from(document.querySelectorAll('[data-chat-filter]'));
    let activeConversationFilter = 'all';
    const supportChatCenterKey = 'support-chat-center-after-selection';
    window.addEventListener('pageshow', function () {
        const shouldCenterConversation = sessionStorage.getItem(supportChatCenterKey) === 'true';
        if (!shouldCenterConversation) {
            return;
        }
        sessionStorage.removeItem(supportChatCenterKey);
        const requestedConversation = new URLSearchParams(window.location.search).has('id');
        const selectedConversationPanel = document.querySelector('.support-chat-center-details');
        if (requestedConversation && selectedConversationPanel instanceof HTMLElement) {
            selectedConversationPanel.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        }
    });

    document.querySelectorAll('.support-chat-thread').forEach(function (threadLink) {
        threadLink.addEventListener('click', function (event) {
            if (event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
                sessionStorage.setItem(supportChatCenterKey, 'true');
            }
        });
    });

    function filterSupportConversations() {
        const searchTerm = conversationSearch.value.trim().toLowerCase();
        let visibleCount = 0;
        conversationItems.forEach(function (item) {
            const matchesFilter = activeConversationFilter === 'all' || item.dataset.chatStatus === activeConversationFilter;
            const matchesSearch = item.dataset.chatSearch.toLowerCase().includes(searchTerm);
            item.hidden = !matchesFilter || !matchesSearch;
            if (!item.hidden) {
                visibleCount += 1;
            }
        });
        if (noConversationMatches instanceof HTMLElement) {
            noConversationMatches.hidden = visibleCount > 0;
        }
    }

    conversationSearch.addEventListener('input', filterSupportConversations);
    conversationFilters.forEach(function (filterButton) {
        filterButton.addEventListener('click', function () {
            activeConversationFilter = filterButton.dataset.chatFilter;
            conversationFilters.forEach(function (button) {
                const isActive = button === filterButton;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', String(isActive));
            });
            filterSupportConversations();
        });
    });

    const newConversationDialog = document.getElementById('new-support-chat-dialog');
    document.querySelectorAll('[data-open-support-chat]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (newConversationDialog instanceof HTMLDialogElement && !newConversationDialog.open) {
                newConversationDialog.showModal();
                document.getElementById('support-chat-subject').focus();
            }
        });
    });
    document.querySelector('[data-close-support-chat]').addEventListener('click', function () {
        if (newConversationDialog instanceof HTMLDialogElement) {
            newConversationDialog.close();
        }
    });
    if (newConversationDialog instanceof HTMLDialogElement) {
        newConversationDialog.addEventListener('click', function (event) {
            if (event.target === newConversationDialog) {
                newConversationDialog.close();
            }
        });
        <?php if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'start' && $errors !== []): ?>
        newConversationDialog.showModal();
        <?php endif; ?>
    }

    const copySupportChatButton = document.querySelector('[data-copy-support-chat]');
    const supportChatReference = document.getElementById('support-chat-reference');
    const supportChatCopyStatus = document.querySelector('[data-support-chat-copy-status]');
    if (copySupportChatButton instanceof HTMLButtonElement && supportChatReference instanceof HTMLElement && supportChatCopyStatus instanceof HTMLElement) {
        copySupportChatButton.addEventListener('click', function () {
            if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                supportChatCopyStatus.textContent = 'Copy is unavailable. Select the reference number to copy it manually.';
                return;
            }
            navigator.clipboard.writeText(supportChatReference.textContent.trim()).then(function () {
                supportChatCopyStatus.textContent = 'Reference number copied.';
                copySupportChatButton.textContent = 'Copied';
            }).catch(function () {
                supportChatCopyStatus.textContent = 'Could not copy the reference number. Select it to copy manually.';
            });
        });
    }
</script>
<?php endif; ?>
<?php if ($chatWidget): ?>
    <script>
        const chatMessages = document.querySelector('.chat-messages, .chat-new-thread');
        if (chatMessages instanceof HTMLElement) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        document.querySelectorAll('.chat-composer-row textarea').forEach(function (textarea) {
            const sendButton = textarea.parentElement.querySelector('.chat-send-button');
            if (textarea instanceof HTMLTextAreaElement && sendButton instanceof HTMLButtonElement) {
                function updateSendButton() {
                    sendButton.disabled = textarea.value.trim() === '';
                }
                textarea.addEventListener('input', updateSendButton);
                updateSendButton();
            }
        });

        const chatPanel = window.parent.document.getElementById('support-chat-widget-panel');
        const chatLauncher = window.parent.document.getElementById('support-chat-widget-launcher');
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && chatPanel !== null && chatPanel.tagName === 'DIALOG' && chatPanel.open) {
                event.preventDefault();
                chatPanel.close();
                if (chatLauncher !== null) {
                    chatLauncher.setAttribute('aria-expanded', 'false');
                }
            }
        });

        const minimizeButton = document.querySelector('.chat-widget-minimize');
        const widgetWindow = document.querySelector('.widget-chat-window, .chat-new-conversation');
        if (chatPanel !== null && chatPanel.tagName === 'DIALOG' && minimizeButton instanceof HTMLButtonElement && widgetWindow instanceof HTMLElement) {
            minimizeButton.addEventListener('click', function () {
                const isMinimized = chatPanel.classList.toggle('is-minimized');
                widgetWindow.classList.toggle('is-minimized', isMinimized);
                minimizeButton.textContent = isMinimized ? '□' : '−';
                minimizeButton.setAttribute('aria-label', isMinimized ? 'Expand chat' : 'Minimize chat');
                minimizeButton.title = isMinimized ? 'Expand chat' : 'Minimize chat';
            });
        }

        document.querySelectorAll('.chat-header-close').forEach(function (button) {
            button.addEventListener('click', function () {
                if (chatPanel !== null && chatPanel.tagName === 'DIALOG' && chatPanel.open) {
                    chatPanel.close();
                    if (chatLauncher !== null) {
                        chatLauncher.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });

        const copyButton = document.querySelector('.widget-chat-copy');
        const referenceCode = document.querySelector('.widget-chat-reference-code');
        const copyStatus = document.querySelector('.widget-chat-copy-status');
        if (copyButton instanceof HTMLButtonElement && referenceCode instanceof HTMLElement && copyStatus instanceof HTMLElement) {
            const copyIcon = copyButton.innerHTML;
            copyButton.addEventListener('click', function () {
                if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                    copyStatus.textContent = 'Copy is unavailable. Select the reference number to copy it manually.';
                    return;
                }
                navigator.clipboard.writeText(referenceCode.textContent.trim()).then(function () {
                    copyStatus.textContent = 'Reference number copied.';
                    copyButton.textContent = '✓';
                    window.setTimeout(function () {
                        copyButton.innerHTML = copyIcon;
                    }, 1200);
                }).catch(function () {
                    copyStatus.textContent = 'Could not copy the reference number. Select it to copy manually.';
                });
            });
        }
    </script>
    </main>
    </body>
    </html>
<?php else: ?>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
