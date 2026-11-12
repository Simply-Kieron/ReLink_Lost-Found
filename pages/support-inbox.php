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
$statusFilter = $_GET['status'] ?? 'open';
$statusFilter = is_string($statusFilter) && in_array($statusFilter, ['open', 'closed', 'all'], true) ? $statusFilter : 'open';

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
                set_flash('success', 'Your reply was sent.');
                redirect('support-inbox.php?status=' . rawurlencode($statusFilter) . '&id=' . $conversationId . '#staff-reply');
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

$where = $statusFilter === 'all' ? '' : 'WHERE support_conversations.status = :status';
try {
    $conversationsStatement = $pdo->prepare(
        "SELECT support_conversations.id, support_conversations.reference_code, support_conversations.student_id, support_conversations.subject,
            support_conversations.status, support_conversations.updated_at, users.name AS student_name, users.email AS student_email,
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
        'SELECT support_conversations.id, support_conversations.reference_code, support_conversations.student_id, support_conversations.subject,
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
<section class="support-chat-center support-inbox-center" aria-labelledby="support-inbox-title">
    <p class="support-chat-center-breadcrumb">ReLink Help Desk · Staff</p>
    <div class="support-chat-center-heading">
        <div>
            <h1 id="support-inbox-title">Customer service</h1>
            <p>Review student conversations, reply to open concerns, and keep completed sessions for reference.</p>
        </div>
    </div>

    <?php if ($notice !== null): ?><p class="support-chat-center-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="support-chat-center-error" role="alert">
            <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="support-chat-center-layout">
        <aside class="support-chat-center-panel" aria-label="Student conversations">
            <div class="support-chat-center-sidebar-head">
                <h2>Conversations</h2>
                <label class="sr-only" for="support-inbox-search">Search conversations</label>
                <input id="support-inbox-search" class="support-chat-search" type="search" placeholder="Search subject, chat number, or student">
                <div class="support-chat-filters" aria-label="Filter conversations">
                    <?php foreach (['all' => 'All', 'open' => 'Open', 'closed' => 'Ended'] as $value => $label): ?>
                        <form method="get" action="support-inbox.php">
                            <button class="<?= $statusFilter === $value ? 'is-active' : '' ?>" type="submit" name="status" value="<?= e($value) ?>" aria-pressed="<?= $statusFilter === $value ? 'true' : 'false' ?>"><?= e($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
            <ul class="support-chat-thread-list">
                <?php foreach ($conversations as $conversation): ?>
                    <?php
                    $conversationPreview = trim((string) ($conversation['latest_message'] ?? ''));
                    $conversationSearch = implode(' ', [
                        $conversation['subject'],
                        $conversation['reference_code'],
                        $conversation['student_name'],
                        $conversation['student_email'],
                        $conversationPreview,
                    ]);
                    $conversationIsSelected = $selectedConversation !== null
                        && (int) $selectedConversation['id'] === (int) $conversation['id'];
                    $conversationUpdatedAt = strtotime($conversation['updated_at']);
                    ?>
                    <li data-chat-search="<?= e($conversationSearch) ?>">
                        <a class="support-chat-thread<?= $conversationIsSelected ? ' is-selected' : '' ?>" href="support-inbox.php?status=<?= e($statusFilter) ?>&amp;id=<?= e($conversation['id']) ?>"<?= $conversationIsSelected ? ' aria-current="page"' : '' ?>>
                            <span class="support-chat-thread-top">
                                <span class="support-chat-thread-subject"><?= e($conversation['subject']) ?></span>
                                <time datetime="<?= e(date('c', $conversationUpdatedAt)) ?>"><?= e(date('M j, g:i A', $conversationUpdatedAt)) ?></time>
                            </span>
                            <span class="support-chat-thread-reference">Chat #<?= e($conversation['reference_code']) ?></span>
                            <span class="support-chat-inbox-thread-student">
                                <?php $studentAvatar = profile_avatar_src($conversation['student_avatar']); ?>
                                <?php if ($studentAvatar !== null): ?><img class="profile-avatar profile-avatar-chat" src="<?= e($studentAvatar) ?>" alt=""><?php endif; ?>
                                <?= e($conversation['student_name']) ?> · <?= e(ucfirst($conversation['reporter_role'])) ?>
                            </span>
                            <span class="support-chat-thread-bottom">
                                <span class="support-chat-thread-preview"><?= e($conversationPreview !== '' ? $conversationPreview : 'No messages yet') ?></span>
                                <span class="support-chat-status is-<?= e($conversation['status']) ?>"><?= $conversation['status'] === 'closed' ? 'Ended' : 'Open' ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($conversations === []): ?>
                <p class="support-chat-list-empty">No conversations match this filter.</p>
            <?php else: ?>
                <p class="support-chat-list-empty" data-inbox-no-results hidden>No conversations match your search.</p>
            <?php endif; ?>
        </aside>

        <section class="support-chat-center-panel support-chat-center-details" aria-label="Selected conversation">
            <?php if ($selectedConversation !== null): ?>
                <?php
                $selectedIsClosed = $selectedConversation['status'] === 'closed';
                $selectedAvatar = profile_avatar_src($selectedConversation['student_avatar']);
                ?>
                <header class="support-chat-center-chat-head">
                    <div class="support-chat-center-chat-title">
                        <div>
                            <h2><?= e($selectedConversation['subject']) ?></h2>
                            <p class="support-chat-inbox-identity">
                                <?php if ($selectedAvatar !== null): ?><img class="profile-avatar profile-avatar-chat" src="<?= e($selectedAvatar) ?>" alt=""><?php endif; ?>
                                <span><?= e($selectedConversation['student_name']) ?> · <?= e($selectedConversation['student_email']) ?> · <?= e(ucfirst($selectedConversation['reporter_role'])) ?></span>
                            </p>
                        </div>
                        <span class="support-chat-status is-<?= e($selectedConversation['status']) ?>"><?= $selectedIsClosed ? 'Ended' : 'Open' ?></span>
                    </div>
                    <div class="support-chat-center-header-actions">
                        <div class="support-chat-center-reference">
                            <span>CHAT REFERENCE</span>
                            <strong id="support-inbox-reference"><?= e($selectedConversation['reference_code']) ?></strong>
                            <button type="button" data-copy-inbox-reference aria-label="Copy chat reference">Copy</button>
                            <span class="sr-only" data-inbox-copy-status role="status" aria-live="polite"></span>
                        </div>
                        <?php if (!$selectedIsClosed): ?>
                            <form method="post" action="support-inbox.php?status=<?= e($statusFilter) ?>&amp;id=<?= e($selectedConversation['id']) ?>" onsubmit="return confirm('Close this conversation? It will remain in the inbox as read-only history.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="conversation_id" value="<?= e($selectedConversation['id']) ?>">
                                <input type="hidden" name="action" value="close">
                                <button class="support-chat-inbox-close" type="submit">Close conversation</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </header>
                <ol class="support-chat-center-messages" aria-label="Conversation messages">
                    <li class="support-chat-center-date">Conversation history</li>
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $messageIsStaff = $message['sender_role'] === 'staff';
                        $messageTimestamp = strtotime($message['created_at']);
                        ?>
                        <li class="support-chat-center-message<?= $messageIsStaff ? ' is-customer' : ' is-agent' ?>">
                            <span class="support-chat-center-who"><?= $messageIsStaff ? 'You' : e($message['sender_name']) ?></span>
                            <article>
                                <p><?= e($message['message']) ?></p>
                            </article>
                            <time datetime="<?= e(date('c', $messageTimestamp)) ?>"><?= e(date('M j, g:i A', $messageTimestamp)) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!$selectedIsClosed): ?>
                    <form class="support-chat-center-composer" method="post" action="support-inbox.php?status=<?= e($statusFilter) ?>&amp;id=<?= e($selectedConversation['id']) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="conversation_id" value="<?= e($selectedConversation['id']) ?>">
                        <label class="sr-only" for="staff-reply">Reply to student</label>
                        <div>
                            <textarea id="staff-reply" name="message" maxlength="4000" placeholder="Write a helpful reply..." required><?= e($messageInput) ?></textarea>
                            <button class="support-chat-primary" type="submit">Send reply</button>
                        </div>
                        <p>Your reply will appear under this chat reference.</p>
                    </form>
                <?php else: ?>
                    <div class="support-chat-ended">
                        <span><strong>Conversation ended</strong><br>This session is read-only and can no longer receive replies.</span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="support-chat-center-empty">
                    <h2><?= $conversations === [] ? 'No conversations yet' : 'Choose a conversation' ?></h2>
                    <p><?= $conversations === [] ? 'New student support conversations will appear here.' : 'Select a conversation to review its messages, copy its reference, or reply to the student.' ?></p>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <p class="support-chat-center-note">Closed conversations remain available here as read-only history.</p>
</section>
<script>
    const supportMessages = document.querySelector('.support-inbox-center .support-chat-center-messages');
    if (supportMessages instanceof HTMLElement) {
        supportMessages.scrollTop = supportMessages.scrollHeight;
    }

    const inboxSearch = document.getElementById('support-inbox-search');
    const inboxItems = Array.from(document.querySelectorAll('.support-chat-thread-list > li'));
    const inboxNoResults = document.querySelector('[data-inbox-no-results]');
    if (inboxSearch instanceof HTMLInputElement && inboxNoResults instanceof HTMLElement) {
        inboxSearch.addEventListener('input', function () {
            const searchTerm = inboxSearch.value.trim().toLowerCase();
            let visibleCount = 0;
            inboxItems.forEach(function (item) {
                item.hidden = !item.dataset.chatSearch.toLowerCase().includes(searchTerm);
                if (!item.hidden) {
                    visibleCount += 1;
                }
            });
            inboxNoResults.hidden = visibleCount > 0;
        });
    }

    const inboxSelectionKey = 'support-inbox-center-after-selection';
    window.addEventListener('pageshow', function () {
        if (sessionStorage.getItem(inboxSelectionKey) !== 'true') {
            return;
        }
        sessionStorage.removeItem(inboxSelectionKey);
        const selectedConversationPanel = document.querySelector('.support-chat-center-details');
        if (new URLSearchParams(window.location.search).has('id') && selectedConversationPanel instanceof HTMLElement) {
            selectedConversationPanel.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        }
    });
    document.querySelectorAll('.support-inbox-center .support-chat-thread').forEach(function (threadLink) {
        threadLink.addEventListener('click', function (event) {
            if (event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
                sessionStorage.setItem(inboxSelectionKey, 'true');
            }
        });
    });

    const copyInboxReferenceButton = document.querySelector('[data-copy-inbox-reference]');
    const inboxReference = document.getElementById('support-inbox-reference');
    const inboxCopyStatus = document.querySelector('[data-inbox-copy-status]');
    if (copyInboxReferenceButton instanceof HTMLButtonElement && inboxReference instanceof HTMLElement && inboxCopyStatus instanceof HTMLElement) {
        copyInboxReferenceButton.addEventListener('click', function () {
            if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                inboxCopyStatus.textContent = 'Copy is unavailable. Select the reference number to copy it manually.';
                return;
            }
            navigator.clipboard.writeText(inboxReference.textContent.trim()).then(function () {
                inboxCopyStatus.textContent = 'Reference number copied.';
                copyInboxReferenceButton.textContent = 'Copied';
            }).catch(function () {
                inboxCopyStatus.textContent = 'Could not copy the reference number. Select it to copy manually.';
            });
        });
    }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
