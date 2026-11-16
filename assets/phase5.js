(() => {
    'use strict';
    const panel = document.getElementById('support-chat-widget-panel');
    const launcher = document.getElementById('support-chat-widget-launcher');
    if (panel && launcher) {
        const frame = panel.querySelector('iframe');
        const loading = panel.querySelector('[data-support-loading]');
        frame.addEventListener('load', () => {
            if (!frame.hasAttribute('src')) return;
            frame.hidden = false;
            if (loading) loading.hidden = true;
        });
        panel.querySelector('[data-support-loading-close]')?.addEventListener('click', () => panel.close());
        // The footer opens this dialog with showModal(), keeping focus inside it.
        panel.addEventListener('click', event => {
            if (event.target === panel) panel.close();
        });
    }
    const viewport = window.visualViewport;
    function fitViewport() {
        if (viewport) {
            document.documentElement.style.setProperty('--relink-viewport-height', `${viewport.height}px`);
            document.documentElement.style.setProperty('--relink-viewport-top', `${viewport.offsetTop}px`);
            const bottomInset = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
            document.documentElement.style.setProperty('--relink-viewport-bottom', bottomInset + 'px');
        }
    }
    fitViewport();
    window.addEventListener('resize', fitViewport);
    window.addEventListener('orientationchange', fitViewport);
    if (viewport) {
        viewport.addEventListener('resize', fitViewport);
        viewport.addEventListener('scroll', fitViewport);
    }
    const root = document.querySelector('[data-support-root]');
    if (root) {
        const tabs = Array.from(root.querySelectorAll('[data-support-tab]'));
        const tabBar = root.querySelector('.relink-support-tabs');
        tabBar.setAttribute('role', 'tablist');
        function selectTab(name, focus = false) {
            root.dataset.activeTab = name;
            tabs.forEach(tab => {
                const active = tab.dataset.supportTab === name;
                tab.setAttribute('role', 'tab');
                tab.id = `support-tab-${tab.dataset.supportTab}`;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                const target = document.getElementById(tab.getAttribute('aria-controls'));
                target.setAttribute('role', 'tabpanel');
                target.setAttribute('aria-labelledby', tab.id);
                // Desktop displays both panels; narrow layouts show the selected tab.
                target.hidden = window.matchMedia('(max-width: 760px)').matches && !active;
                if (focus && active) tab.focus();
            });
        }
        tabs.forEach(tab => {
            tab.addEventListener('click', () => selectTab(tab.dataset.supportTab));
            tab.addEventListener('keydown', event => {
                if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                    event.preventDefault();
                    const next = event.key === 'Home' ? tabs[0] : event.key === 'End' ? tabs[1] : tabs.find(item => item !== tab);
                    selectTab(next.dataset.supportTab, true);
                }
            });
        });
        selectTab(root.dataset.initialTab || 'chat');
        window.matchMedia('(max-width: 760px)').addEventListener('change', () => selectTab(root.dataset.activeTab));
        const search = root.querySelector('[data-request-search]');
        const requests = Array.from(root.querySelectorAll('[data-request-status]'));
        const filters = Array.from(root.querySelectorAll('[data-request-filter]'));
        let activeFilter = 'all';
        function filterRequests() {
            let visible = 0;
            requests.forEach(request => {
                request.hidden = (activeFilter !== 'all' && request.dataset.requestStatus !== activeFilter)
                    || !request.dataset.requestText.toLowerCase().includes(search.value.trim().toLowerCase());
                if (!request.hidden) visible++;
            });
            root.querySelector('[data-requests-empty]').hidden = visible > 0;
        }
        search.addEventListener('input', filterRequests);
        filters.forEach(button => button.addEventListener('click', () => {
            activeFilter = button.dataset.requestFilter;
            filters.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
            filterRequests();
        }));
        const messages = root.querySelector('[data-support-messages]');
        if (messages) messages.scrollTop = messages.scrollHeight;
        root.querySelector('a[href="#support-faq"]').addEventListener('click', () => {
            root.querySelector('#support-faq').open = true;
        });
        if (new URLSearchParams(location.search).get('help') === 'issue') {
            const subject = root.querySelector('#support-subject');
            if (subject && !subject.value) subject.value = 'Report an issue';
        }
        root.querySelectorAll('[data-support-form]').forEach(form => {
            form.addEventListener('submit', event => {
                if (form.dataset.sending === 'true') { event.preventDefault(); return; }
                form.dataset.sending = 'true';
                form.setAttribute('aria-busy', 'true');
                const button = form.querySelector('button[type="submit"]');
                button.disabled = true;
                button.dataset.label = button.textContent;
                button.textContent = 'Sending…';
                form.querySelector('[role="status"]').textContent = 'Sending your message…';
            });
        });
        window.addEventListener('pageshow', () => {
            root.querySelectorAll('[data-support-form]').forEach(form => {
                delete form.dataset.sending;
                form.removeAttribute('aria-busy');
                const button = form.querySelector('button[type="submit"]');
                button.disabled = false;
                if (button.dataset.label) button.textContent = button.dataset.label;
            });
        });
        const close = root.querySelector('[data-support-close]');
        if (close) close.addEventListener('click', () => {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'relink-support-close' }, location.origin);
            } else location.href = 'dashboard.php';
        });
        window.addEventListener('keydown', event => {
            if (event.key === 'Escape' && close && window.parent !== window) close.click();
        });
    }
    window.addEventListener('message', event => {
        if (event.origin === location.origin && panel && event.source === panel.querySelector('iframe').contentWindow
            && event.data && event.data.type === 'relink-support-close') panel.close();
    });
})();
