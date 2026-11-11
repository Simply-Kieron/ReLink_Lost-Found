<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/profile_avatar.php';

$staff = require_role('staff');
require_once __DIR__ . '/../includes/db.php';

$errors = [];
$notice = null;
$messageInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $conversationId = filter_var($_POST['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    if ($conversationId === false || $conversationId === null || !is_string($action) || !in_array($action, ['reply', 'close'], true)) {
        http_response_code(400);
        exit('Choose a valid support inbox action.');
    }

    try {
        $pdo->beginTransaction();
        $conversationStatement = $pdo->prepare(
            'SELECT status FROM support_conversations WHERE id = :id FOR UPDATE'
        );
        $conversationStatement->execute(['id' => $conversationId]);
        $status = $conversationStatement->fetchColumn();
        if ($status === false) {
            $pdo->rollBack();
            http_response_code(404);
            exit('Support conversation not found.');
        }

        if ($action === 'close') {
            if ($status !== 'open') {
                $pdo->rollBack();
                $errors[] = 'This conversation is already closed.';
            } else {
                $closeStatement = $pdo->prepare(
                    "UPDATE support_conversations
                    SET status = 'closed', closed_at = CURRENT_TIMESTAMP, closed_by = :staff_id
                    WHERE id = :id AND status = 'open'"
                );
                $closeStatement->execute(['staff_id' => $staff['id'], 'id' => $conversationId]);
                $pdo->commit();
                $notice = 'The support session was closed.';
            }
        } else {
            $messageValue = $_POST['message'] ?? '';
            $messageInput = is_string($messageValue) ? trim($messageValue) : '';
            if ($messageInput === '' || strlen($messageInput) > 4000) {
                $pdo->rollBack();
                $errors[] = 'Enter a message using no more than 4,000 characters.';
            } elseif ($status !== 'open') {
                $pdo->rollBack();
                $errors[] = 'This conversation is closed and cannot receive replies.';
            } else {
                $messageStatement = $pdo->prepare(
                    'INSERT INTO support_messages (conversation_id, sender_id, message)
                    VALUES (:conversation_id, :sender_id, :message)'
                );
                $messageStatement->execute([
                    'conversation_id' => $conversationId,
                    'sender_id' => $staff['id'],
                    'message' => $messageInput,
                ]);
                $touchConversation = $pdo->prepare(
                    'UPDATE support_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id'
                );
                $touchConversation->execute(['id' => $conversationId]);
                $pdo->commit();
                $notice = 'Your reply was sent.';
            }
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Support inbox action failed: ' . $exception->getMessage());
        http_response_code(500);
        $errors[] = 'The support session could not be updated. Please try again later.';
    }
}

$statusFilter = $_GET['status'] ?? 'open';
$statusFilter = is_string($statusFilter) && in_array($statusFilter, ['open', 'closed', 'all'], true) ? $statusFilter : 'open';
$where = $statusFilter === 'all' ? '' : 'WHERE support_conversations.status = :status';
try {
    $conversationsStatement = $pdo->prepare(
        "SELECT support_conversations.id, support_conversations.student_id, support_conversations.subject,
            support_conversations.status, support_conversations.updated_at, users.name AS student_name,
            users.role AS reporter_role, users.avatar_path AS student_avatar,
            (SELECT support_messages.message FROM support_messages
                WHERE support_messages.conversation_id = support_conversations.id
                ORDER BY support_messages.id DESC LIMIT 1) AS latest_message
        FROM support_conversations
        INNER JOIN users ON users.id = support_conversations.student_id
        $where
        ORDER BY (support_conversations.status = 'open') DESC, support_conversations.updated_at DESC"
    );
    if ($statusFilter !== 'all') {
        $conversationsStatement->execute(['status' => $statusFilter]);
    } else {
        $conversationsStatement->execute();
    }
    $conversations = $conversationsStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('Support inbox query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Support inbox is temporarily unavailable.');
}

$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (($selectedId === false || $selectedId === null) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = filter_var($_POST['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $selectedId = $postedId === false ? null : $postedId;
}
$selectedConversation = null;
$messages = [];
if ($selectedId !== false && $selectedId !== null) {
    $selectedStatement = $pdo->prepare(
        'SELECT support_conversations.id, support_conversations.student_id, support_conversations.subject,
            support_conversations.status, support_conversations.created_at, support_conversations.updated_at,
            users.name AS student_name, users.email AS student_email, users.role AS reporter_role,
            users.avatar_path AS student_avatar
        FROM support_conversations
        INNER JOIN users ON users.id = support_conversations.student_id
        WHERE support_conversations.id = :id'
    );
    $selectedStatement->execute(['id' => $selectedId]);
    $selectedConversation = $selectedStatement->fetch() ?: null;
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

$pageTitle = 'Customer Service Desk';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-6xl support-inbox-page" aria-labelledby="support-inbox-title">
    <div class="support-inbox-heading">
        <p class="support-chat-eyebrow">ReLink service desk</p>
        <h1 id="support-inbox-title" class="text-3xl font-bold tracking-tight">Customer service</h1>
        <p>Reply to support conversations and review finder reports from the ReLink community.</p>
    </div>
    <?php if ($notice !== null): ?><p class="mb-5 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="mb-5 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900" role="alert"><ul class="list-inside list-disc"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form class="mb-5 flex flex-wrap items-end gap-3" method="get" action="support-inbox.php">
        <div>
            <label class="mb-1 block font-medium" for="status">Conversations</label>
            <select class="min-h-11 rounded-md border border-slate-400 px-3" id="status" name="status">
                <?php foreach (['open' => 'Open', 'closed' => 'Closed', 'all' => 'All sessions'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white" type="submit">Filter sessions</button>
    </form>
    <div class="support-chat-layout support-inbox-layout <?= $selectedConversation !== null ? 'has-selected-chat' : '' ?>">
        <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="sessions-title">
            <div class="chat-history-heading">
                <h2 id="sessions-title" class="text-xl font-bold">Conversations</h2>
            </div>
            <?php if ($conversations === []): ?>
                <p class="mt-4 text-slate-600">No conversations match this filter.</p>
            <?php else: ?>
                <ul class="mt-4 space-y-2">
                    <?php foreach ($conversations as $conversation): ?>
                        <li>
                            <a class="block rounded-lg border p-3 <?= $selectedConversation !== null && (int) $selectedConversation['id'] === (int) $conversation['id'] ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:bg-slate-50' ?>" href="support-inbox.php?status=<?= e($statusFilter) ?>&amp;id=<?= e($conversation['id']) ?>">
                                <span class="flex items-center justify-between gap-2">
                                    <strong class="chat-history-subject">
                                        <svg class="chat-history-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 18.5 3.5 20l1.2-3.7A8 8 0 1 1 8 19.2" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5" />
                                        </svg>
                                        <span><?= e($conversation['subject']) ?></span>
                                    </strong>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $conversation['status'] === 'open' ? 'bg-green-100 text-green-900' : 'bg-slate-100 text-slate-700' ?>"><?= e($conversation['status']) ?></span>
                                </span>
                                <span class="mt-1 flex items-center gap-2 text-sm text-slate-700">
                                    <?php $studentAvatar = profile_avatar_src($conversation['student_avatar']); ?>
                                    <?php if ($studentAvatar !== null): ?><img class="profile-avatar profile-avatar-chat" src="<?= e($studentAvatar) ?>" alt=""><?php endif; ?>
                                    <?= e($conversation['student_name']) ?> · <?= e(ucfirst($conversation['reporter_role'])) ?>
                                </span>
                                <span class="mt-1 block truncate text-sm text-slate-500"><?= e($conversation['latest_message'] ?? '') ?></span>
                                <span class="mt-1 block text-xs text-slate-500"><?= e($conversation['updated_at']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
        <div class="chat-main-column">
        <?php if ($selectedConversation !== null): ?>
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="selected-session-title">
                <div class="conversation-header flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
                <div class="conversation-header-copy">
                        <h2 id="selected-session-title" class="text-xl font-bold"><?= e($selectedConversation['subject']) ?></h2>
                        <p class="mt-1 flex items-center gap-2 text-sm text-slate-600">
                            <?php $selectedAvatar = profile_avatar_src($selectedConversation['student_avatar']); ?>
                            <?php if ($selectedAvatar !== null): ?><img class="profile-avatar profile-avatar-chat" src="<?= e($selectedAvatar) ?>" alt=""><?php endif; ?>
                            <?= e($selectedConversation['student_name']) ?> · <?= e($selectedConversation['student_email']) ?> · <?= e(ucfirst($selectedConversation['reporter_role'])) ?>
                        </p>
                    </div>
                    <?php if ($selectedConversation['status'] === 'open'): ?>
                        <form method="post" action="support-inbox.php?status=<?= e($statusFilter) ?>&amp;id=<?= e($selectedConversation['id']) ?>" onsubmit="return confirm('Close this conversation? It will remain in the inbox as read-only history.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conversation_id" value="<?= e($selectedConversation['id']) ?>">
                            <input type="hidden" name="action" value="close">
                            <button class="min-h-10 rounded-lg border border-red-300 px-4 py-2 font-semibold text-red-800 hover:bg-red-50" type="submit">Close conversation</button>
                        </form>
                    <?php else: ?>
                        <span class="closed-chat-status">Closed session · history only</span>
                    <?php endif; ?>
                </div>
                <ol class="chat-messages max-h-[34rem] space-y-4 overflow-y-auto bg-slate-50 p-5" aria-label="Messages">
                    <?php foreach ($messages as $message): ?>
                        <?php $senderAvatar = profile_avatar_src($message['sender_avatar']); ?>
                        <li class="chat-message-row <?= $message['sender_role'] === 'staff' ? 'is-user' : 'is-staff' ?>">
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
                    <form class="chat-composer border-t border-slate-200 p-5" method="post" action="support-inbox.php?status=<?= e($statusFilter) ?>&amp;id=<?= e($selectedConversation['id']) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= e($selectedConversation['id']) ?>">
                        <input type="hidden" name="action" value="reply">
                        <label class="sr-only" for="staff-reply">Reply to customer</label>
                        <textarea class="min-h-28 w-full rounded-md border border-slate-400 p-3" id="staff-reply" name="message" maxlength="4000" placeholder="Write a helpful reply..." required><?= e($messageInput) ?></textarea>
                        <button class="chat-send-button" type="submit" aria-label="Send reply" title="Send reply">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.5 2.5 14 21.5l-4.2-8.3L1.5 9l20-6.5ZM9.8 13.2l5.1-5.1" /></svg>
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <div class="flex min-h-56 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">Choose a conversation to view its history and reply.</div>
        <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
