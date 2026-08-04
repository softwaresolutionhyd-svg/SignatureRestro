(() => {
    const listEl = document.getElementById('stairNotifList');
    const badgeEl = document.getElementById('stairNotifBadge');
    const markAllBtn = document.getElementById('stairNotifMarkAll');
    const enableBtn = document.getElementById('stairNotifEnable');
    if (!listEl || !badgeEl) return;

    const boot = window.STAIR_NOTIFICATIONS || {};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || boot.csrf || '';
    const routes = boot.routes || {};
    if (!routes.index) return;

    const seenKey = 'stair_notif_seen_ids';
    const baseTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');
    let pollTimer = null;

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function loadSeen() {
        try {
            return new Set(JSON.parse(localStorage.getItem(seenKey) || '[]'));
        } catch (_) {
            return new Set();
        }
    }

    function saveSeen(set) {
        const arr = Array.from(set).slice(-80);
        try {
            localStorage.setItem(seenKey, JSON.stringify(arr));
        } catch (_) { /* ignore */ }
    }

    function levelClass(level) {
        if (level === 'danger') return 'stair-notif-item--danger';
        if (level === 'warning') return 'stair-notif-item--warning';
        if (level === 'success') return 'stair-notif-item--success';
        return '';
    }

    function canBrowserNotify() {
        return typeof Notification !== 'undefined';
    }

    function browserNotifyAllowed() {
        return canBrowserNotify() && Notification.permission === 'granted';
    }

    function updateEnableBtn() {
        if (!enableBtn) return;
        if (!canBrowserNotify()) {
            enableBtn.classList.add('d-none');
            return;
        }
        if (Notification.permission === 'granted') {
            enableBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Alerts on (phone / PC)';
            enableBtn.disabled = true;
            enableBtn.classList.remove('btn-outline-secondary');
            enableBtn.classList.add('btn-outline-success');
        } else if (Notification.permission === 'denied') {
            enableBtn.innerHTML = '<i class="bi bi-slash-circle me-1"></i> Alerts blocked — browser settings';
            enableBtn.disabled = true;
        } else {
            enableBtn.disabled = false;
        }
    }

    async function requestBrowserPermission() {
        if (!canBrowserNotify()) {
            alert('Is browser mein desktop/phone alerts support nahi.');
            return;
        }
        try {
            const perm = await Notification.requestPermission();
            updateEnableBtn();
            if (perm === 'granted') {
                new Notification('Stair alerts on', {
                    body: 'Ab order / cancel activity isi device pe dikhegi.',
                    icon: '/favicon.svg',
                    tag: 'stair-enabled',
                });
            }
        } catch (_) {
            updateEnableBtn();
        }
    }

    function showBrowserAlert(item) {
        if (!browserNotifyAllowed()) return;
        const data = item.data || {};
        try {
            const n = new Notification(data.title || 'Stair', {
                body: data.message || '',
                icon: '/images/stair-logo.svg',
                badge: '/favicon.svg',
                tag: 'stair-' + (item.id || data.action || Date.now()),
                renotify: true,
            });
            n.onclick = () => {
                window.focus();
                if (data.url) window.location.href = data.url;
                n.close();
            };
        } catch (_) { /* ignore */ }
    }

    function renderList(items) {
        if (!items.length) {
            listEl.innerHTML = `<div class="stair-notif-empty text-secondary small px-3 py-4 text-center">No new notifications</div>`;
            return;
        }

        listEl.innerHTML = items.map((item) => {
            const data = item.data || {};
            const unread = !item.read_at;
            const icon = data.icon || 'bi-bell';
            const ago = item.created_ago || '';
            const actor = data.actor ? `<span class="stair-notif-actor">by ${esc(data.actor)}</span>` : '';
            const href = data.url || '#';
            return `<a href="${esc(href)}" class="stair-notif-item ${levelClass(data.level)} ${unread ? 'is-unread' : ''}" data-id="${esc(item.id)}" data-url="${esc(href)}">
                <span class="stair-notif-icon"><i class="bi ${esc(icon)}"></i></span>
                <span class="stair-notif-body">
                    <span class="stair-notif-title">${esc(data.title || 'Activity')}</span>
                    <span class="stair-notif-msg">${esc(data.message || '')}</span>
                    <span class="stair-notif-meta">${esc(ago)} ${actor}</span>
                </span>
            </a>`;
        }).join('');
    }

    function setBadge(count) {
        if (count > 0) {
            badgeEl.textContent = count > 99 ? '99+' : String(count);
            badgeEl.classList.remove('d-none');
            document.title = `(${count}) ${baseTitle}`;
        } else {
            badgeEl.classList.add('d-none');
            document.title = baseTitle;
        }
    }

    async function refresh() {
        try {
            const res = await fetch(routes.index, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            const items = Array.isArray(data.notifications) ? data.notifications : [];
            renderList(items);
            setBadge(Number(data.unread_count || 0));

            const seen = loadSeen();
            let changed = false;
            items.forEach((item) => {
                if (item.read_at || seen.has(item.id)) return;
                showBrowserAlert(item);
                seen.add(item.id);
                changed = true;
            });
            if (changed) saveSeen(seen);
        } catch (_) { /* ignore */ }
    }

    async function markAllRead() {
        try {
            await fetch(routes.readAll, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });
            await refresh();
        } catch (_) { /* ignore */ }
    }

    async function markOne(id) {
        if (!id || !routes.readOne) return;
        const url = String(routes.readOne).replace('__ID__', encodeURIComponent(id));
        try {
            await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });
        } catch (_) { /* ignore */ }
    }

    listEl.addEventListener('click', (e) => {
        const item = e.target.closest('.stair-notif-item');
        if (!item) return;
        const id = item.getAttribute('data-id');
        if (id) markOne(id);
    });

    markAllBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        markAllRead();
    });

    enableBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        requestBrowserPermission();
    });

    updateEnableBtn();
    refresh();
    pollTimer = window.setInterval(refresh, 8000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });

    window.addEventListener('beforeunload', () => {
        if (pollTimer) window.clearInterval(pollTimer);
    });
})();
