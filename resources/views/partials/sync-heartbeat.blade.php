@if(config('sync.enabled') && config('sync.role') === 'local')
<script>
(function () {
    const statusUrl = @json(route('sync.status'));
    const pushUrl = @json(route('sync.push'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const heartbeatMs = {{ max(8, (int) config('sync.heartbeat_seconds', 12)) * 1000 }};
    const autoPush = {{ config('sync.auto_push_heartbeat') ? 'true' : 'false' }};
    const pullEvery = {{ max(2, (int) config('sync.heartbeat_pull_every', 4)) }};
    const badge = document.getElementById('sync-status-badge');
    const dot = document.getElementById('sync-status-dot');
    const label = document.getElementById('sync-status-label');
    let syncing = false;
    let pullTick = 0;
    let lastStatus = { online: true, pending: 0 };

    function paint(status) {
        if (!dot || !label) return;
        const pending = Number(status.pending || 0);
        const online = status.online === undefined ? !!lastStatus.online : !!status.online;
        lastStatus = { online: online, pending: pending };
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
            if (!autoPush || !navigator.onLine || !data.online || syncing) return;

            const pending = Number(data.pending || 0);
            if (pending > 0) {
                syncNow(true, false);
                return;
            }

            pullTick++;
            if (pullTick >= pullEvery) {
                pullTick = 0;
                syncNow(false, true);
            }
        } catch (e) {
            // Keep last known state — don't flash No net on status glitches
        }
    }

    async function syncNow(force, withPull) {
        if (syncing || !navigator.onLine) return;
        syncing = true;
        paint({ online: true, pending: lastStatus.pending || 0 });
        try {
            const qs = new URLSearchParams();
            if (force) qs.set('force', '1');
            qs.set('pull', withPull ? '1' : '0');
            const res = await fetch(pushUrl + '?' + qs.toString(), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                },
                credentials: 'same-origin'
            });
            const data = await res.json().catch(() => ({}));
            // Prefer explicit online; if missing, keep previous (avoid false No net)
            const online = (typeof data.online === 'boolean') ? data.online : lastStatus.online;
            paint({
                online: online,
                pending: data.pending ?? lastStatus.pending ?? 0
            });
            if (Number(data.pulled || 0) > 0 && label && online) {
                label.textContent = 'Pulled ' + data.pulled;
            }
        } catch (e) {
            // Network blip on local request — re-check status instead of forcing No net
            syncing = false;
            refreshStatus();
            return;
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
            syncNow(true, true);
        });
    }

    refreshStatus();
    setInterval(refreshStatus, heartbeatMs);
})();
</script>
@endif
