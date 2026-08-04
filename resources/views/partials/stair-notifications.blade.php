{{-- Stair activity notifications bootstrap --}}
@auth
<script>
window.STAIR_NOTIFICATIONS = {
    csrf: @json(csrf_token()),
    routes: {
        index: @json(route('notifications.index')),
        readAll: @json(route('notifications.readAll')),
        readOne: @json(url('/notifications/__ID__/read')),
    }
};
</script>
<script src="{{ asset('js/stair-notifications.js') }}?v=1" defer></script>
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
</style>
@endauth
