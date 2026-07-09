@extends('layouts.admin')
@section('title', 'Order Status — ' . config('app.name'))
@section('page-title', 'Order Status')

@section('content')
<div class="order-status-app">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">Restaurant Order Status</h4>
            <div class="text-secondary small">Kitchen se Preparing / Complete par yahan live dikhega — Served par hat jayega.</div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="order-status-stat"><span id="orderStatusCount">{{ $activeCount }}</span> on screen</div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="orderStatusRefreshBtn">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    <div id="orderStatusBoard">
        @include('order-status.partials.board', ['orders' => $orders])
    </div>
</div>

<script>
(() => {
    const boardWrap = document.getElementById('orderStatusBoard');
    const countEl = document.getElementById('orderStatusCount');
    const refreshBtn = document.getElementById('orderStatusRefreshBtn');
    const boardUrl = @json(route('order-status.board'));

    async function fetchBoardHtml(url) {
        const res = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin',
            redirect: 'manual',
        });

        if (res.type === 'opaqueredirect' || [301, 302, 401, 403, 419].includes(res.status)) {
            return null;
        }
        if (!res.ok) return null;

        const html = await res.text();
        if (/<form[^>]+login|sign in to continue|name=["']password["']/i.test(html)) {
            return null;
        }
        return html;
    }

    async function refreshBoard() {
        try {
            const html = await fetchBoardHtml(boardUrl);
            if (html === null) return;
            boardWrap.innerHTML = html;
            updateCount();
        } catch (e) {
            /* ignore */
        }
    }

    function updateCount() {
        if (!countEl) return;
        countEl.textContent = String(boardWrap.querySelectorAll('.order-status-card').length);
    }

    refreshBtn?.addEventListener('click', refreshBoard);
    setInterval(refreshBoard, 4000);
})();
</script>
@endsection
