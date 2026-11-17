(() => {
    const root = document.querySelector('.legacy-support-view');
    if (!root) return;
    let parentPanel = null;
    try {
        if (window.parent !== window) parentPanel = window.parent.document.getElementById('support-chat-widget-panel');
    } catch { /* Standalone form controls also work outside a same-origin parent. */ }
    if (parentPanel) parentPanel.addEventListener('close', () => root.classList.remove('is-minimized'));
    root.querySelector('#legacy-history').addEventListener('change', event => location.href = event.target.value);
    root.querySelectorAll('.chat-header-close').forEach(button => button.addEventListener('click', () => {
        if (parentPanel) parentPanel.close(); else location.href = 'dashboard.php';
    }));
    root.querySelectorAll('.chat-widget-minimize').forEach(button => button.addEventListener('click', () => {
        const minimized = root.classList.toggle('is-minimized');
        if (parentPanel) parentPanel.classList.toggle('is-minimized', minimized);
        button.textContent = minimized ? '□' : '−';
        button.setAttribute('aria-label', minimized ? 'Expand chat' : 'Minimize chat');
    }));
    window.addEventListener('keydown', event => { if (event.key === 'Escape' && parentPanel) parentPanel.close(); });
    const copy = root.querySelector('.widget-chat-copy:not(:disabled)');
    if (copy) copy.addEventListener('click', async () => {
        const status = root.querySelector('.widget-chat-copy-status');
        try { await navigator.clipboard.writeText(root.querySelector('.widget-chat-reference-code').textContent.trim()); status.textContent = 'Reference copied.'; }
        catch { status.textContent = 'Select the reference number to copy it.'; }
    });
    const messages = root.querySelector('.chat-messages');
    if (messages) messages.scrollTop = messages.scrollHeight;
    root.querySelectorAll('form').forEach(form => {
        const textarea = form.querySelector('textarea');
        const button = form.querySelector('button[type="submit"]');
        if (!textarea || !button) return;
        function refresh() { button.disabled = textarea.value.trim() === ''; }
        refresh(); textarea.addEventListener('input', refresh);
        textarea.addEventListener('change', refresh);
        const status = document.createElement('p'); status.className = 'legacy-send-status'; status.setAttribute('role','status'); status.setAttribute('aria-live','polite'); status.textContent = new URLSearchParams(location.search).has('sent') ? 'Message sent.' : ''; form.append(status);
        form.addEventListener('submit', event => {
            if (!textarea.value.trim()) { event.preventDefault(); refresh(); textarea.focus(); return; }
            if (form.dataset.sending) { event.preventDefault(); return; }
            form.dataset.sending='true'; button.disabled=true; status.textContent='Sending…';
        });
        window.addEventListener('pageshow', () => { delete form.dataset.sending; refresh(); });
    });
})();
