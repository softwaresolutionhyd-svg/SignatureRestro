@if(config('sync.enabled') && config('sync.role') === 'local')
<script>
(function () {
    const statusUrl = @json(route('sync.status'));
    const pushUrl = @json(route('sync.push'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const heartbeatMs = {{ max(30, (int) config('sync.heartbeat_seconds', 60)) * 1000 }};
    const autoPush = {{ config('sync.auto_push_heartbeat') ? 'true' : 'false' }};
    const badge = document.getElementById('sync-status-badge');
    const dot = document.getElementById('sync-status-dot');
    const label = document.getElementById('sync-status-label');
    let syncing = false;
    let lastStatus = { online: false, pending: 0 };

    function paint(status) {
        if (!dot || !label) return;
        lastStatus = status;
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
            // Full two-way sync: push pending + pull hosting changes (server debounce applies)
            if (autoPush && navigator.onLine && data.online && !syncing) {
                pushNow(false);
            }
        } catch (e) {
            paint({ online: false, pending: lastStatus.pending || 0 });
        }
    }

    async function pushNow(force) {
        if (syncing || !navigator.onLine) return;
        syncing = true;
        paint({ online: true, pending: lastStatus.pending || 0 });
        try {
            const res = await fetch(pushUrl + (force ? '?force=1' : ''), {
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
                pending: data.pending ?? lastStatus.pending ?? 0
            });
            if (Number(data.pulled || 0) > 0 && label) {
                label.textContent = 'Pulled ' + data.pulled;
            }
        } catch (e) {
            paint({ online: false, pending: lastStatus.pending || 0 });
        } finally {
            syncing = false;
        }
    }

    window.addEventListener('online', refreshStatus);
    window.addEventListener('offline', function () {
        paint({ online: false, pending: lastStatus.pending || 0 });
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            refreshStatus();
        }
    });

    if (badge) {
        badge.addEventListener('click', function () {
            pushNow(true);
        });
    }

    refreshStatus();
    setInterval(refreshStatus, heartbeatMs);
})();
</script>
@endif
