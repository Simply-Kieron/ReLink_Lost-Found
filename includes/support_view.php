<?php
$selectedIsClosed = $selectedConversation !== null && $selectedConversation['status'] === 'closed';
$initialRequests = isset($_GET['view']) && $_GET['view'] === 'requests';
?>
<section class="relink-support<?= $chatWidget ? ' relink-support-embedded' : '' ?>" aria-labelledby="support-chat-title" data-support-root data-initial-tab="<?= $initialRequests ? 'requests' : 'chat' ?>">
    <div class="relink-page-banner relink-support-banner">
        <span class="relink-banner-icon" aria-hidden="true">&#9743;</span>
        <div><p class="relink-eyebrow">ReLink help desk</p><h1 id="support-chat-title">Customer Service</h1><p>Get help, report issues, and follow up on your requests.</p></div>
        <?php if ($chatWidget): ?><button class="relink-icon-button" type="button" data-support-close aria-label="Close customer service">&times;</button><?php endif; ?>
    </div>
    <?php if ($errors !== []): ?><div class="error-box" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="relink-support-tabs" aria-label="Customer service views">
        <button type="button" data-support-tab="chat" aria-controls="support-chat-panel">Chat</button>
        <button type="button" data-support-tab="requests" aria-controls="support-requests-panel">My Requests <span><?= count($conversations) ?></span></button>
    </div>
    <div class="relink-support-layout">
        <section class="relink-request-panel" id="support-requests-panel" aria-labelledby="support-requests-title">
            <div class="relink-panel-heading"><h2 id="support-requests-title">My Requests</h2><a href="<?= e($chatRoot) ?>">New chat</a></div>
            <label class="sr-only" for="support-request-search">Search requests</label>
            <input class="relink-request-search" id="support-request-search" type="search" placeholder="Search subject or reference" data-request-search>
            <div class="relink-request-filters" aria-label="Filter requests"><button type="button" data-request-filter="all" aria-pressed="true">All</button><button type="button" data-request-filter="open" aria-pressed="false">Open</button><button type="button" data-request-filter="closed" aria-pressed="false">Closed</button></div>
            <ul class="relink-request-list">
                <?php foreach ($conversations as $conversation): ?>
                    <li data-request-status="<?= e($conversation['status']) ?>" data-request-text="<?= e($conversation['subject'] . ' ' . $conversation['reference_code']) ?>">
                        <a href="<?= e($chatRoot . $chatQuerySeparator . 'id=' . $conversation['id']) ?>" <?= $selectedConversation !== null && (int) $conversation['id'] === (int) $selectedConversation['id'] ? 'aria-current="page"' : '' ?>>
                            <strong><?= e($conversation['subject']) ?></strong><span class="relink-request-ref">#<?= e($conversation['reference_code']) ?></span><p><?= e($conversation['last_message'] ?? 'No messages yet') ?></p>
                            <span class="relink-request-meta"><span class="relink-status"><?= e(ucfirst($conversation['status'])) ?></span><time datetime="<?= e(date('c', strtotime($conversation['updated_at']))) ?>"><?= e(date('M j, g:i A', strtotime($conversation['updated_at']))) ?></time></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="relink-empty-note" data-requests-empty <?= $conversations !== [] ? 'hidden' : '' ?>><?= $conversations === [] ? 'Your support requests will appear here.' : 'No requests match your search.' ?></p>
        </section>
        <section class="relink-chat-panel" id="support-chat-panel" aria-label="Support conversation">
            <div class="relink-agent-heading"><span class="brand-mark" aria-hidden="true">&#9743;</span><div><strong>ReLink Support</strong><p>Replies may not be immediate</p></div><?php if ($selectedConversation !== null): ?><span class="relink-status"><?= $selectedIsClosed ? 'Closed' : 'Open' ?></span><?php endif; ?></div>
            <?php if ($selectedConversation !== null): ?>
                <div class="relink-chat-subject"><h2><?= e($selectedConversation['subject']) ?></h2><span>Reference #<?= e($selectedConversation['reference_code']) ?></span></div>
                <ol class="relink-messages" aria-label="Conversation history" data-support-messages>
                    <?php $lastDate = ''; foreach ($messages as $message): $timestamp = strtotime($message['created_at']); $messageDate = date('Y-m-d', $timestamp); ?>
                        <?php if ($lastDate !== $messageDate): $lastDate = $messageDate; ?><li class="relink-message-date"><time datetime="<?= e($messageDate) ?>"><?= e(date('F j, Y', $timestamp)) ?></time></li><?php endif; ?>
                        <li class="relink-message <?= $message['sender_role'] === 'staff' ? 'is-support' : 'is-user' ?>"><span><?= $message['sender_role'] === 'staff' ? 'ReLink Support' : 'You' ?></span><p><?= e($message['message']) ?></p><time datetime="<?= e(date('c', $timestamp)) ?>"><?= e(date('g:i A', $timestamp)) ?></time></li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!$selectedIsClosed): ?>
                    <form class="relink-composer" method="post" action="<?= e($chatRoot . $chatQuerySeparator . 'id=' . $selectedConversation['id']) ?>" data-support-form>
                        <?= csrf_field() ?><?= support_submission_field('reply', (int) $selectedConversation['id']) ?>
                        <input type="hidden" name="action" value="reply"><input type="hidden" name="conversation_id" value="<?= e($selectedConversation['id']) ?>">
                        <label class="sr-only" for="support-message">Your message</label><div><textarea id="support-message" name="message" maxlength="4000" rows="2" required placeholder="Type a message…"><?= e($messageInput) ?></textarea><button class="primary-button" type="submit" aria-label="Send message">Send <span aria-hidden="true">&#10148;</span></button></div><p class="relink-send-status" role="status" aria-live="polite"><?= isset($_GET['sent']) ? 'Message sent.' : 'Messages are saved to this conversation.' ?></p>
                    </form>
                <?php else: ?><div class="relink-closed-note"><p>This conversation is closed. Its history is preserved.</p><a class="primary-button" href="<?= e($chatRoot) ?>">Start a new chat</a></div><?php endif; ?>
            <?php else: ?>
                <div class="relink-chat-empty"><span aria-hidden="true">&#9993;</span><h2>How can we help?</h2><p>Start a private conversation, or choose an existing request. Please keep passwords and verification codes private.</p></div>
                <form class="relink-new-chat" method="post" action="<?= e($chatRoot) ?>" data-support-form>
                    <?= csrf_field() ?><?= support_submission_field('start') ?><input type="hidden" name="action" value="start">
                    <label for="support-subject">Subject</label><input id="support-subject" name="subject" maxlength="150" required value="<?= e($subjectInput) ?>" placeholder="For example: Help with my claim">
                    <label for="support-first-message">How can we help?</label><textarea id="support-first-message" name="message" maxlength="4000" rows="3" required placeholder="Tell us what happened…"><?= e($messageInput) ?></textarea><button class="primary-button" type="submit">Start conversation</button><p class="relink-send-status" role="status" aria-live="polite">Your message will receive a request reference.</p>
                </form>
            <?php endif; ?>
        </section>
    </div>
    <section class="relink-quick-help" aria-labelledby="quick-help-title"><h2 id="quick-help-title">Quick Help</h2><div><a href="<?= e($chatRoot . $chatQuerySeparator . 'help=issue') ?>">Report Issue</a><a href="my-claims.php#claims" <?= $chatWidget ? 'target="_top"' : '' ?>>Claim Follow-up</a><a href="#support-faq">FAQ</a></div></section>
    <details class="relink-faq" id="support-faq"><summary>Frequently asked questions</summary><dl><dt>How do I claim a found item?</dt><dd>Open Found Items, choose an item and select the claim action. Follow the existing ownership verification steps; staff review claims before return.</dd><dt>Where can I follow up on a claim?</dt><dd>Open Account and My activity to review your claims. Include your claim reference when contacting support.</dd><dt>Why is my report not visible yet?</dt><dd>Found reports are reviewed by staff before publication. Check My activity for your report status.</dd></dl></details>
</section>
