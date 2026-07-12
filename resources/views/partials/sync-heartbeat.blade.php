@if(config('sync.enabled') && config('sync.role') === 'local')
<script>
(function () {
    const statusUrl = @json(route('sync.status'));
    const pushUrl = @json(route('sync.push'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const heartbeatMs = {{ max(10, (int) config('sync.heartbeat_seconds', 15)) * 1000 }};
    const badge = document.getElementById('sync-status-badge');
    const dot = document.getElementById('sync-status-dot');
    const label = document.getElementById('sync-status-label');
    let syncing = false;

    function paint(status) {
        if (!dot || !label) return;
        const online = !!status.online;
        const pending = Number(status.pending || 0);
        if (!navigator.onLine) {
            dot.style.background = '#f59e0b';
            label.textContent = pending > 0 ? ('Offline · ' + pending) : 'Offline';
            return;
        }
        if (!online) {
            dot.style.background = '#ef4444';
            label.textContent = pending > 0 ? ('No net · ' + pending) : 'No net';
            return;
        }
        if (syncing) {
            dot.style.background = '#3b82f6';
            label.textContent = 'Syncing…';
            return;
        }
        if (pending > 0) {
            dot.style.background = '#3b82f6';
            label.textContent = 'Sync ' + pending;
            return;
        }
        dot.style.background = '#22c55e';
        label.textContent = 'Online';
    }

    async function refreshStatus() {
        try {
            const res = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!res.ok) return;
            const data = await res.json();
            paint(data);
            if (navigator.onLine && Number(data.pending || 0) > 0 && !syncing) {
                pushNow();
            }
        } catch (e) {
            paint({ online: false, pending: 0 });
        }
    }

    async function pushNow() {
        if (syncing || !navigator.onLine) return;
        syncing = true;
        paint({ online: true, pending: 0 });
        try {
            const res = await fetch(pushUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                },
                credentials: 'same-origin'
            });
            const data = await res.json().catch(() => ({}));
            paint({
                online: data.online !== false,
                pending: data.pending ?? 0
            });
        } catch (e) {
            paint({ online: false, pending: 0 });
        } finally {
            syncing = false;
            refreshStatus();
        }
    }

    window.addEventListener('online', function () {
        pushNow();
    });
    window.addEventListener('offline', function () {
        paint({ online: false, pending: 0 });
        refreshStatus();
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && navigator.onLine) {
            pushNow();
        }
    });

    if (badge) {
        badge.addEventListener('click', function () {
            pushNow();
        });
    }

    refreshStatus();
    if (navigator.onLine) {
        pushNow();
    }
    setInterval(function () {
        if (navigator.onLine) {
            pushNow();
        } else {
            refreshStatus();
        }
    }, heartbeatMs);
})();
</script>
@endif
