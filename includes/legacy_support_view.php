<div class="legacy-support-view"><label class="legacy-history-label" for="legacy-history">Conversation history</label><select id="legacy-history" aria-label="Choose a conversation"><option value="<?= e($chatRoot) ?>">Start a new conversation</option><?php foreach ($conversations as $thread): ?><option value="<?= e($chatRoot . $chatQuerySeparator . 'id=' . $thread['id']) ?>" <?= $selectedConversation !== null && (int) $selectedConversation['id'] === (int) $thread['id'] ? 'selected' : '' ?>><?= e($thread['subject'] . ' · #' . $thread['reference_code']) ?></option><?php endforeach; ?></select><section class="support-chat-page mx-auto max-w-5xl" aria-labelledby="support-chat-title">
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
                                <li class="chat-message-row <?= $message['sender_role'] === 'staff' ? 'is-staff' : 'is-user' ?>" data-message-role="<?= $message['sender_role'] === 'staff' ? 'staff' : 'user' ?>">
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
                                <li class="chat-message-row <?= $message['sender_role'] === 'staff' ? 'is-staff' : 'is-user' ?>" data-message-role="<?= $message['sender_role'] === 'staff' ? 'staff' : 'user' ?>">
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
                            <?= csrf_field() ?><?= support_submission_field($selectedConversation !== null ? 'reply' : 'start', $selectedConversation !== null ? (int) $selectedConversation['id'] : 0) ?>
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
                            <?= csrf_field() ?><?= support_submission_field($selectedConversation !== null ? 'reply' : 'start', $selectedConversation !== null ? (int) $selectedConversation['id'] : 0) ?>
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
                            <?= csrf_field() ?><?= support_submission_field($selectedConversation !== null ? 'reply' : 'start', $selectedConversation !== null ? (int) $selectedConversation['id'] : 0) ?>
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
</div>