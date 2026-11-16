    </main>
    <dialog class="confirmation-dialog" id="confirmation-dialog" aria-labelledby="confirmation-dialog-title" aria-describedby="confirmation-dialog-message">
        <form class="confirmation-dialog-form" method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="report_id" value="">
            <input type="hidden" name="item_id" value="">
            <input type="hidden" name="claim_id" value="">
            <input type="hidden" name="action" value="">
            <input type="hidden" name="decision" value="">
            <h2 id="confirmation-dialog-title"></h2>
            <p id="confirmation-dialog-message"></p>
            <div class="action-row">
                <button class="secondary-button" type="button" data-confirm-cancel autofocus>Cancel</button>
                <button class="primary-button" id="confirmation-dialog-submit" type="submit"></button>
            </div>
        </form>
    </dialog>
    <?php if ($currentUser !== null && $currentUser['role'] === 'student' && $currentPage !== 'support-chat.php'): ?>
        <div class="support-chat-widget">
            <dialog class="support-chat-widget-panel" id="support-chat-widget-panel" aria-label="Customer support chat">
                <iframe src="../pages/support-chat.php?widget=1" title="Customer support" loading="lazy"></iframe>
            </dialog>
            <button class="support-chat-widget-launcher" id="support-chat-widget-launcher" type="button" aria-label="Chat with customer support" aria-controls="support-chat-widget-panel" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.5h3l1.5 4-2 1.5a15 15 0 0 0 5 5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.3 16.3 0 0 1 5.5 5.7a2 2 0 0 1 2-2.2Z"></path>
                </svg>
                <span>Customer Support</span>
            </button>
        </div>
    <?php endif; ?>
    <?php if ($hasMobileBottomNav): ?>
        <?php
        $homeHref = $currentUser !== null ? '../pages/dashboard.php' : '../public/index.php';
        $isHomePage = in_array($currentPage, ['index.php', 'dashboard.php'], true);
        $isAccountPage = in_array($currentPage, ['profile.php', 'my-claims.php', 'my-reports.php', 'my-found-reports.php'], true);
        ?>
        <nav class="design-bottom-nav" aria-label="Main navigation">
            <a href="<?= e($homeHref) ?>" <?= $isHomePage ? 'aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3.5 10 8.5-7 8.5 7v10a1 1 0 0 1-1 1h-5.3v-6.2H9.8V21H4.5a1 1 0 0 1-1-1V10Z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                <span>Home</span>
            </a>
            <a href="../pages/lost-items.php" <?= $currentPage === 'lost-items.php' ? 'aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 13.2 13.2 20 4 10.8V4h6.8L20 13.2Z" stroke-linejoin="round"></path><circle cx="8" cy="8" r="1.1"></circle></svg>
                <span>Lost</span>
            </a>
            <a href="../pages/found-items.php" <?= $currentPage === 'found-items.php' ? 'aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3Z" stroke-linejoin="round"></path><path d="m3.8 7.7 8.2 4.4 8.2-4.4M12 12.1V21" stroke-linejoin="round"></path></svg>
                <span>Found</span>
            </a>
            <a href="<?= $currentUser !== null ? '../pages/profile.php' : '../pages/login.php' ?>" <?= $isAccountPage ? 'aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 20a7.5 7.5 0 0 1 15 0" stroke-linecap="round"></path></svg>
                <span>Account</span>
            </a>
        </nav>
    <?php endif; ?>
    <footer>
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p>&copy; <?= date('Y') ?> ReLink · Classroom Lost &amp; Found Management System</p>
            <nav aria-label="Footer navigation">
                <ul class="flex gap-5">
                    <li><a class="rounded underline underline-offset-4 focus:outline focus:outline-2 focus:outline-offset-4 focus:outline-blue-700" href="../pages/privacy-policy.php">Privacy Policy</a></li>
                    <li><a class="rounded underline underline-offset-4 focus:outline focus:outline-2 focus:outline-offset-4 focus:outline-blue-700" href="../pages/terms.php">Terms</a></li>
                </ul>
            </nav>
        </div>
    </footer>
    <script>
        const accountMenu = document.querySelector('.design-account-menu');
        const confirmationDialog = document.getElementById('confirmation-dialog');
        const confirmationForm = confirmationDialog.querySelector('form');
        const confirmationTitle = document.getElementById('confirmation-dialog-title');
        const confirmationMessage = document.getElementById('confirmation-dialog-message');
        const confirmationSubmit = document.getElementById('confirmation-dialog-submit');
        const confirmationFields = confirmationForm.querySelectorAll('input[type="hidden"]:not([name="csrf_token"])');

        const activityTabs = document.querySelector('.claim-tabs[role="tablist"]');
        if (activityTabs instanceof HTMLElement) {
            const tabs = Array.from(activityTabs.querySelectorAll('[role="tab"]'));
            const panels = tabs
                .map(function (tab) {
                    return document.getElementById(tab.getAttribute('aria-controls'));
                })
                .filter(function (panel) {
                    return panel instanceof HTMLElement;
                });

            function activateActivityTab(panelId, addHistoryEntry) {
                const activeTab = tabs.find(function (tab) {
                    return tab.getAttribute('aria-controls') === panelId;
                });
                if (!activeTab) {
                    return;
                }

                tabs.forEach(function (tab) {
                    const isActive = tab === activeTab;
                    tab.setAttribute('aria-selected', String(isActive));
                    tab.tabIndex = isActive ? 0 : -1;
                    tab.classList.toggle('is-active', isActive);
                });
                panels.forEach(function (panel) {
                    panel.hidden = panel.id !== panelId;
                });

                if (addHistoryEntry && window.location.hash !== '#' + panelId) {
                    window.history.pushState(null, '', '#' + panelId);
                }
            }

            const initialPanel = tabs.some(function (tab) {
                return tab.getAttribute('aria-controls') === window.location.hash.slice(1);
            }) ? window.location.hash.slice(1) : 'claims';
            activateActivityTab(initialPanel, false);

            activityTabs.addEventListener('click', function (event) {
                const tab = event.target instanceof Element ? event.target.closest('[role="tab"]') : null;
                if (tab instanceof HTMLAnchorElement) {
                    event.preventDefault();
                    activateActivityTab(tab.getAttribute('aria-controls'), true);
                }
            });

            activityTabs.addEventListener('keydown', function (event) {
                const currentTab = event.target instanceof Element ? event.target.closest('[role="tab"]') : null;
                if (!(currentTab instanceof HTMLAnchorElement)) {
                    return;
                }

                const currentIndex = tabs.indexOf(currentTab);
                let nextIndex = currentIndex;
                if (event.key === 'ArrowRight') {
                    nextIndex = (currentIndex + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = tabs.length - 1;
                } else {
                    return;
                }

                event.preventDefault();
                tabs[nextIndex].focus();
                activateActivityTab(tabs[nextIndex].getAttribute('aria-controls'), true);
            });

            window.addEventListener('popstate', function () {
                activateActivityTab(window.location.hash.slice(1) || 'claims', false);
            });
        }

        document.addEventListener('click', function (event) {
            if (accountMenu instanceof HTMLDetailsElement && accountMenu.open && event.target instanceof Node && !accountMenu.contains(event.target)) {
                accountMenu.open = false;
            }

            const trigger = event.target instanceof Element ? event.target.closest('[data-confirm-title]') : null;
            if (trigger instanceof HTMLAnchorElement && confirmationDialog instanceof HTMLDialogElement) {
                event.preventDefault();
                confirmationForm.action = trigger.href;
                confirmationTitle.textContent = trigger.dataset.confirmTitle;
                confirmationMessage.textContent = trigger.dataset.confirmMessage;
                confirmationSubmit.textContent = trigger.dataset.confirmButton;
                confirmationFields.forEach(function (field) {
                    field.value = '';
                });
                const recordField = confirmationForm.querySelector('input[name="' + trigger.dataset.confirmRecordName + '"]');
                if (recordField instanceof HTMLInputElement) {
                    recordField.value = trigger.dataset.confirmRecordId;
                }
                const actionField = confirmationForm.querySelector('input[name="action"]');
                actionField.value = trigger.dataset.confirmAction || '';
                const decisionField = confirmationForm.querySelector('input[name="decision"]');
                decisionField.value = trigger.dataset.confirmDecision || '';
                confirmationSubmit.classList.toggle('danger-button', trigger.dataset.confirmTone === 'danger');
                confirmationSubmit.classList.toggle('primary-button', trigger.dataset.confirmTone !== 'danger');
                confirmationDialog.showModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && accountMenu instanceof HTMLDetailsElement) {
                accountMenu.open = false;
            }
        });

        document.querySelector('[data-confirm-cancel]').addEventListener('click', function () {
            confirmationDialog.close();
        });

        const chatPanel = document.getElementById('support-chat-widget-panel');
        const chatLauncher = document.getElementById('support-chat-widget-launcher');
        const chatFrame = chatPanel instanceof HTMLDialogElement ? chatPanel.querySelector('iframe') : null;
        const chatWidget = document.querySelector('.support-chat-widget');

        function closeSupportChat() {
            if (chatPanel instanceof HTMLDialogElement && chatLauncher instanceof HTMLButtonElement && chatPanel.open) {
                chatPanel.close();
                chatLauncher.setAttribute('aria-expanded', 'false');
            }
        }

        function setSupportChatOpen(isOpen) {
            if (chatPanel instanceof HTMLDialogElement && chatLauncher instanceof HTMLButtonElement) {
                if (isOpen && !chatPanel.open) {
                    chatPanel.classList.remove('is-minimized');
                    if (chatFrame instanceof HTMLIFrameElement && chatFrame.contentDocument !== null) {
                        const widgetWindow = chatFrame.contentDocument.querySelector('.widget-chat-window, .chat-new-conversation');
                        const minimizeButton = chatFrame.contentDocument.querySelector('.chat-widget-minimize');
                        if (widgetWindow !== null) {
                            widgetWindow.classList.remove('is-minimized');
                        }
                        if (minimizeButton !== null) {
                            minimizeButton.textContent = '−';
                            minimizeButton.setAttribute('aria-label', 'Minimize chat');
                            minimizeButton.title = 'Minimize chat';
                        }
                    }
                    chatPanel.show();
                    chatLauncher.setAttribute('aria-expanded', 'true');
                    if (chatFrame instanceof HTMLIFrameElement) {
                        chatFrame.focus();
                    }
                } else if (!isOpen && chatPanel.open) {
                    closeSupportChat();
                }
            }
        }

        if (chatLauncher instanceof HTMLButtonElement) {
            chatLauncher.addEventListener('click', function () {
                setSupportChatOpen(!(chatPanel instanceof HTMLDialogElement && chatPanel.open));
            });
        }

        if (chatPanel instanceof HTMLDialogElement && chatLauncher instanceof HTMLButtonElement) {
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && chatPanel.open) {
                    event.preventDefault();
                    closeSupportChat();
                }
            });
            chatPanel.addEventListener('cancel', function (event) {
                event.preventDefault();
                closeSupportChat();
            });
            chatPanel.addEventListener('close', function () {
                chatLauncher.setAttribute('aria-expanded', 'false');
                chatLauncher.focus();
            });
            document.addEventListener('pointerdown', function (event) {
                if (chatPanel.open && !chatPanel.contains(event.target) && !chatLauncher.contains(event.target)) {
                    closeSupportChat();
                }
            });
        }

        if (chatWidget instanceof HTMLElement) {
            function updateSupportChatLauncher() {
                chatWidget.classList.toggle('is-scrolled', window.scrollY > 100);
            }

            updateSupportChatLauncher();
            window.addEventListener('scroll', updateSupportChatLauncher, { passive: true });
        }

        document.querySelectorAll('[data-open-support-chat]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (chatPanel instanceof HTMLDialogElement) {
                    event.preventDefault();
                    if (accountMenu instanceof HTMLDetailsElement) {
                        accountMenu.open = false;
                    }
                    setSupportChatOpen(true);
                }
            });
        });

        document.querySelectorAll('input[type="password"]:not([data-password-toggle="false"])').forEach(function (input) {
            const wrapper = document.createElement('span');
            wrapper.className = 'password-input-wrapper';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            const toggle = document.createElement('button');
            toggle.className = 'password-visibility-toggle';
            toggle.type = 'button';
            toggle.setAttribute('aria-label', 'Show password');
            toggle.setAttribute('aria-pressed', 'false');
            toggle.innerHTML = '<svg class="password-eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="password-eye-off-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="m3 3 18 18"></path><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.9"></path><path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 4.1-.8"></path></svg>';
            wrapper.appendChild(toggle);

            toggle.addEventListener('click', function () {
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
                toggle.setAttribute('aria-pressed', String(!isVisible));
                toggle.querySelector('.password-eye-icon').hidden = !isVisible;
                toggle.querySelector('.password-eye-off-icon').hidden = isVisible;
            });
        });
    </script>
</body>
</html>
