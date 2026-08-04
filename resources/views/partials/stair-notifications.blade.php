{{-- Stair activity notifications bootstrap --}}
@auth
@php
    $stairVapidPublic = '';
    try {
        $stairVapidPublic = app(\App\Services\WebPushService::class)->publicKey();
    } catch (\Throwable $e) {
        $stairVapidPublic = '';
    }
@endphp
<script>
window.STAIR_NOTIFICATIONS = {
    csrf: @json(csrf_token()),
    vapidPublicKey: @json($stairVapidPublic),
    routes: {
        index: @json(route('notifications.index')),
        readAll: @json(route('notifications.readAll')),
        readOne: @json(url('/notifications/__ID__/read')),
        pushSubscribe: @json(route('push-subscriptions.store')),
        pushUnsubscribe: @json(route('push-subscriptions.destroy')),
        vapidKey: @json(route('push-subscriptions.vapid')),
    }
};
</script>
<script src="{{ asset('js/stair-notifications.js') }}?v=4" defer></script>
<style>
.stair-notif-btn { min-width: 2.1rem; }
.stair-notif-badge {
    position: absolute;
    top: -2px;
    right: -4px;
    min-width: 1.05rem;
    height: 1.05rem;
    padding: 0 4px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    font-size: 0.62rem;
    font-weight: 700;
    line-height: 1.05rem;
    text-align: center;
}
.stair-notif-menu {
    width: min(360px, 92vw);
    padding: 0;
    overflow: hidden;
    border-radius: 12px !important;
}
.stair-notif-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(0,0,0,.06);
    background: #fafafa;
}
.stair-notif-list {
    max-height: 360px;
    overflow-y: auto;
}
.stair-notif-item {
    display: flex;
    gap: 0.7rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid rgba(0,0,0,.04);
}
.stair-notif-item:hover { background: #f8fafc; color: inherit; }
.stair-notif-item.is-unread { background: #eff6ff; }
.stair-notif-item--danger.is-unread { background: #fef2f2; }
.stair-notif-item--warning.is-unread { background: #fffbeb; }
.stair-notif-item--success.is-unread { background: #f0fdf4; }
.stair-notif-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: #4f46e5;
    flex-shrink: 0;
}
.stair-notif-item--danger .stair-notif-icon { background: #fee2e2; color: #dc2626; }
.stair-notif-item--warning .stair-notif-icon { background: #fef3c7; color: #d97706; }
.stair-notif-item--success .stair-notif-icon { background: #dcfce7; color: #16a34a; }
.stair-notif-body { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.stair-notif-title { font-size: 0.82rem; font-weight: 700; }
.stair-notif-msg { font-size: 0.75rem; color: #475569; word-break: break-word; }
.stair-notif-meta { font-size: 0.68rem; color: #94a3b8; }
.stair-notif-actor { margin-left: 0.25rem; }
.stair-notif-foot { padding: 0.65rem 0.75rem; border-top: 1px solid rgba(0,0,0,.06); }
.admin-topbar .stair-notif-menu { z-index: 1080; }

/* Right-side toast popups */
.stair-toast-host {
    position: fixed;
    top: 4.5rem;
    right: 1rem;
    z-index: 2000;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    width: min(360px, calc(100vw - 1.5rem));
    pointer-events: none;
}
.stair-toast {
    pointer-events: auto;
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 2rem 0.85rem 0.85rem;
    border-radius: 14px;
    background: #111827;
    color: #f9fafb;
    box-shadow: 0 12px 40px rgba(0,0,0,.35);
    overflow: hidden;
    opacity: 0;
    transform: translateX(120%);
    transition: transform .28s ease, opacity .28s ease;
    cursor: pointer;
}
.stair-toast.is-in {
    opacity: 1;
    transform: translateX(0);
}
.stair-toast.is-out {
    opacity: 0;
    transform: translateX(110%);
}
.stair-toast--success { border-left: 4px solid #22c55e; }
.stair-toast--warning { border-left: 4px solid #f59e0b; }
.stair-toast--danger { border-left: 4px solid #ef4444; }
.stair-toast--info { border-left: 4px solid #6366f1; }
.stair-toast-icon {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.1);
    flex-shrink: 0;
    font-size: 1rem;
}
.stair-toast--success .stair-toast-icon { color: #86efac; }
.stair-toast--warning .stair-toast-icon { color: #fcd34d; }
.stair-toast--danger .stair-toast-icon { color: #fca5a5; }
.stair-toast--info .stair-toast-icon { color: #a5b4fc; }
.stair-toast-body { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.stair-toast-title { font-size: 0.88rem; font-weight: 700; line-height: 1.25; }
.stair-toast-msg { font-size: 0.78rem; color: #d1d5db; line-height: 1.35; word-break: break-word; }
.stair-toast-close {
    position: absolute;
    top: 0.35rem;
    right: 0.45rem;
    border: 0;
    background: transparent;
    color: #9ca3af;
    font-size: 1.2rem;
    line-height: 1;
    padding: 0.15rem 0.35rem;
    cursor: pointer;
}
.stair-toast-close:hover { color: #fff; }
.stair-toast-progress {
    position: absolute;
    left: 0;
    bottom: 0;
    height: 3px;
    width: 100%;
    background: rgba(255,255,255,.35);
    transform-origin: left center;
    animation: stairToastProgress 15s linear forwards;
}
@keyframes stairToastProgress {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}
@media (max-width: 576px) {
    .stair-toast-host {
        top: auto;
        bottom: 1rem;
        right: 0.75rem;
        left: 0.75rem;
        width: auto;
    }
}
</style>
@endauth
