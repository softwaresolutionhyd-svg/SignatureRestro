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
    let primed = false; // first fetch: seed seen, no toast flood
    let lastSoundAt = 0;
    let audioCtx = null;

    const toastHost = document.createElement('div');
    toastHost.id = 'stairToastHost';
    toastHost.className = 'stair-toast-host';
    toastHost.setAttribute('aria-live', 'polite');
    document.body.appendChild(toastHost);

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
        const arr = Array.from(set).slice(-120);
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

    function toastLevelClass(level) {
        if (level === 'danger') return 'stair-toast--danger';
        if (level === 'warning') return 'stair-toast--warning';
        if (level === 'success') return 'stair-toast--success';
        return 'stair-toast--info';
    }

    function ensureAudio() {
        if (audioCtx) return audioCtx;
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        audioCtx = new Ctx();
        return audioCtx;
    }

    /** Soft two-tone ding — no external file needed. */
    function playTune() {
        const now = Date.now();
        if (now - lastSoundAt < 400) return;
        lastSoundAt = now;
        try {
            const ctx = ensureAudio();
            if (!ctx) return;
            if (ctx.state === 'suspended') ctx.resume();

            const beep = (freq, start, dur, gain) => {
                const osc = ctx.createOscillator();
                const g = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                g.gain.setValueAtTime(0.0001, start);
                g.gain.exponentialRampToValueAtTime(gain, start + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
                osc.connect(g);
                g.connect(ctx.destination);
                osc.start(start);
                osc.stop(start + dur + 0.02);
            };

            const t = ctx.currentTime;
            beep(880, t, 0.12, 0.18);
            beep(1174.7, t + 0.11, 0.18, 0.14);
        } catch (_) { /* ignore */ }
    }

    function showToastPopup(item) {
        const data = item.data || {};
        const el = document.createElement('div');
        el.className = `stair-toast ${toastLevelClass(data.level)}`;
        el.innerHTML = `
            <span class="stair-toast-icon"><i class="bi ${esc(data.icon || 'bi-bell')}"></i></span>
            <span class="stair-toast-body">
                <span class="stair-toast-title">${esc(data.title || 'Notification')}</span>
                <span class="stair-toast-msg">${esc(data.message || '')}</span>
            </span>
            <button type="button" class="stair-toast-close" aria-label="Close">&times;</button>
            <span class="stair-toast-progress" aria-hidden="true"></span>
        `;

        const close = () => {
            el.classList.add('is-out');
            window.setTimeout(() => el.remove(), 280);
        };

        el.querySelector('.stair-toast-close')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            close();
        });

        el.addEventListener('click', () => {
            if (data.url) window.location.href = data.url;
        });

        toastHost.appendChild(el);
        requestAnimationFrame(() => el.classList.add('is-in'));
        playTune();
        window.setTimeout(close, 15000);
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
            ensureAudio();
            const perm = await Notification.requestPermission();
            updateEnableBtn();
            if (perm === 'granted') {
                playTune();
                showToastPopup({
                    data: {
                        title: 'Alerts on',
                        message: 'Ab changes right side pe popup + sound ke sath aayenge.',
                        icon: 'bi-bell',
                        level: 'success',
                    },
                });
            }
        } catch (_) {
            updateEnableBtn();
        }
    }

    function showBrowserAlert(item) {
        if (!browserNotifyAllowed() || document.visibilityState === 'visible') return;
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

            // First load: mark current items seen so old 99+ don't all toast.
            if (!primed) {
                items.forEach((item) => {
                    if (item.id) seen.add(item.id);
                });
                saveSeen(seen);
                primed = true;
                return;
            }

            // Newest first — toast newest few so stack stays readable.
            const fresh = items.filter((item) => item.id && !item.read_at && !seen.has(item.id));
            fresh.slice(0, 4).reverse().forEach((item) => {
                showToastPopup(item);
                showBrowserAlert(item);
                seen.add(item.id);
                changed = true;
            });
            fresh.slice(4).forEach((item) => {
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

    // Unlock audio on first user gesture (browser autoplay policy).
    const unlock = () => {
        try {
            const ctx = ensureAudio();
            if (ctx && ctx.state === 'suspended') ctx.resume();
        } catch (_) { /* ignore */ }
        document.removeEventListener('click', unlock);
        document.removeEventListener('keydown', unlock);
        document.removeEventListener('touchstart', unlock);
    };
    document.addEventListener('click', unlock, { once: true });
    document.addEventListener('keydown', unlock, { once: true });
    document.addEventListener('touchstart', unlock, { once: true });

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
    pollTimer = window.setInterval(refresh, 5000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });

    window.addEventListener('beforeunload', () => {
        if (pollTimer) window.clearInterval(pollTimer);
    });
})();
