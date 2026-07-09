@extends('layouts.admin')
@section('title', 'Kitchen — ' . config('app.name'))
@section('page-title', 'Kitchen')

@section('content')

<div class="kitchen-app">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">Kitchen Display</h4>
            <div class="text-secondary small">Pehle Preparing → phir Complete Order → akhir mein Order Served (order khatam).</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="kitchen-stat"><span id="kitchenActiveCount">{{ $activeCount }}</span> active</div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="kitchenTodayConsumptionBtn">
                <i class="bi bi-list-check"></i> Aaj ki consumption
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="kitchenRefreshBtn">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 kitchen-workspace">
        <div class="col-lg-9">
            <div id="kitchenBoard">
                @include('kitchen.partials.board', ['orders' => $orders])
            </div>
        </div>
        <div class="col-lg-3">
            <div id="kitchenSummary">
                @include('kitchen.partials.summary', [
                    'pendingDishes' => $pendingDishes,
                    'requiredIngredients' => $requiredIngredients,
                ])
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="kitchenTodayConsumptionModal" tabindex="-1" aria-labelledby="kitchenTodayConsumptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6" id="kitchenTodayConsumptionModalLabel">Aaj ki consumption</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3" id="kitchenTodayConsumptionBody">
                <div class="text-secondary small">Loading…</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="kitchenTodayConsumptionRefreshBtn">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/interactjs@1.10.27/dist/interact.min.js"></script>
<script>
(() => {
    const boardWrap = document.getElementById('kitchenBoard');
    const summaryWrap = document.getElementById('kitchenSummary');
    const countEl = document.getElementById('kitchenActiveCount');
    const refreshBtn = document.getElementById('kitchenRefreshBtn');
    const todayConsumptionBtn = document.getElementById('kitchenTodayConsumptionBtn');
    const todayConsumptionModalEl = document.getElementById('kitchenTodayConsumptionModal');
    const todayConsumptionBody = document.getElementById('kitchenTodayConsumptionBody');
    const todayConsumptionRefreshBtn = document.getElementById('kitchenTodayConsumptionRefreshBtn');
    const boardUrl = @json(route('kitchen.board'));
    const summaryUrl = @json(route('kitchen.summary'));
    const todayConsumptionUrl = @json(route('kitchen.today-consumption'));
    const csrf = @json(csrf_token());

    let zCounter = 20;
    let refreshPaused = false;

    function freeBoard() {
        return boardWrap?.querySelector('#kitchenFreeBoard');
    }

    function clamp(n, min, max) {
        return Math.max(min, Math.min(max, n));
    }

    function cardEl(event) {
        return event.target.closest('.kitchen-free-card');
    }

    function dragBounds(board, card) {
        return {
            maxX: Math.max(0, board.clientWidth - card.offsetWidth),
            maxY: Math.max(0, board.clientHeight - card.offsetHeight),
        };
    }

    function resizeBoard() {
        const board = freeBoard();
        if (!board) return;

        let maxBottom = window.innerHeight * 0.75;
        board.querySelectorAll('.kitchen-free-card').forEach((card) => {
            const bottom = card.offsetTop + card.offsetHeight + 48;
            if (bottom > maxBottom) maxBottom = bottom;
        });
        board.style.minHeight = Math.round(maxBottom) + 'px';
    }

    async function savePosition(card, xPx, yPx) {
        const url = card.dataset.posUrl;
        if (!url) return;

        try {
            await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    x: Math.round(xPx),
                    y: Math.round(yPx),
                }),
            });
        } catch (e) {
            /* ignore */
        }
    }

    function initKitchenDrag() {
        if (typeof interact === 'undefined') return;

        interact('.kitchen-free-card').unset();

        interact('.kitchen-free-card')
            .draggable({
                ignoreFrom: '.kitchen-status-form, .kitchen-status-form *, .kitchen-item-serve-form, .kitchen-item-serve-form *, button, a, input',
                inertia: false,
                listeners: {
                    start(event) {
                        const el = cardEl(event);
                        if (!el) return;

                        refreshPaused = true;
                        el.classList.add('is-free-dragging');
                        el.style.transform = '';
                        zCounter += 1;
                        el.style.zIndex = String(zCounter);
                    },
                    move(event) {
                        const el = cardEl(event);
                        const board = freeBoard();
                        if (!el || !board) return;

                        const { maxX, maxY } = dragBounds(board, el);
                        const left = clamp(el.offsetLeft + event.dx, 0, maxX);
                        const top = clamp(el.offsetTop + event.dy, 0, maxY);

                        el.style.left = left + 'px';
                        el.style.top = top + 'px';
                    },
                    end(event) {
                        const el = cardEl(event);
                        if (!el) return;

                        el.classList.remove('is-free-dragging');
                        refreshPaused = false;
                        resizeBoard();
                        savePosition(el, el.offsetLeft, el.offsetTop);
                    },
                },
            });
    }

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

    async function refreshSummary() {
        if (!summaryWrap) return;
        try {
            const html = await fetchBoardHtml(summaryUrl);
            if (html === null) return;
            summaryWrap.innerHTML = html;
        } catch (e) {
            /* ignore */
        }
    }

    async function loadTodayConsumption() {
        if (!todayConsumptionBody) return;
        todayConsumptionBody.innerHTML = '<div class="text-secondary small">Loading…</div>';
        try {
            const html = await fetchBoardHtml(todayConsumptionUrl);
            if (html === null) {
                todayConsumptionBody.innerHTML = '<div class="text-danger small">Load nahi ho saka.</div>';
                return;
            }
            todayConsumptionBody.innerHTML = html;
        } catch (e) {
            todayConsumptionBody.innerHTML = '<div class="text-danger small">Network error.</div>';
        }
    }

    function openTodayConsumptionModal() {
        if (!todayConsumptionModalEl || typeof bootstrap === 'undefined') return;
        bootstrap.Modal.getOrCreateInstance(todayConsumptionModalEl).show();
        loadTodayConsumption();
    }

    function applyServeBlink() {
        if (!boardWrap) return;
        const now = Date.now();
        boardWrap.querySelectorAll('.kitchen-free-card').forEach((card) => {
            const raw = card.dataset.serveAt;
            if (!raw) {
                card.classList.remove('is-serve-soon');
                return;
            }
            const serveMs = Date.parse(raw);
            if (Number.isNaN(serveMs)) return;
            const blinkStart = serveMs - (60 * 60 * 1000);
            card.classList.toggle('is-serve-soon', now >= blinkStart);
        });
    }

    async function refreshBoard(force = false) {
        if (refreshPaused && !force) return;
        try {
            const html = await fetchBoardHtml(boardUrl);
            if (html === null) return;
            boardWrap.innerHTML = html;
            bindStatusForms();
            bindItemServeForms();
            resizeBoard();
            initKitchenDrag();
            updateCount();
            applyServeBlink();
            await refreshSummary();
        } catch (e) {
            /* ignore */
        }
    }

    function updateCount() {
        if (!countEl) return;
        countEl.textContent = String(boardWrap.querySelectorAll('.kitchen-order-card').length);
    }

    function bindStatusForms() {
        boardWrap.querySelectorAll('.kitchen-status-form').forEach((form) => {
            if (form.dataset.bound === '1') return;
            form.dataset.bound = '1';
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const card = form.closest('.kitchen-order-card');
                const remove = form.dataset.remove === '1';
                card?.classList.add('is-completing');
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: new FormData(form),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        card?.classList.remove('is-completing');
                        alert(data.message || 'Update nahi ho saka.');
                        return;
                    }
                    if (remove || data.removed) {
                        card?.remove();
                        updateCount();
                        resizeBoard();
                        await refreshSummary();
                        if (!boardWrap.querySelector('.kitchen-order-card')) {
                            await refreshBoard(true);
                        }
                    } else {
                        card?.classList.remove('is-completing');
                        await refreshBoard(true);
                    }
                } catch (err) {
                    card?.classList.remove('is-completing');
                    alert('Network error — dubara try karein.');
                }
            });
        });
    }

    function bindItemServeForms() {
        boardWrap.querySelectorAll('.kitchen-item-serve-form').forEach((form) => {
            if (form.dataset.bound === '1') return;
            form.dataset.bound = '1';
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const card = form.closest('.kitchen-order-card');
                const row = form.closest('.kitchen-item-row');
                const btn = form.querySelector('button');
                if (btn) btn.disabled = true;
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: new FormData(form),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        if (btn) btn.disabled = false;
                        alert(data.message || 'Item served nahi ho saka.');
                        return;
                    }
                    row?.remove();
                    if (data.removed) {
                        card?.remove();
                        updateCount();
                        resizeBoard();
                        await refreshSummary();
                        if (!boardWrap.querySelector('.kitchen-order-card')) {
                            await refreshBoard(true);
                        }
                    } else {
                        await refreshBoard(true);
                    }
                } catch (err) {
                    if (btn) btn.disabled = false;
                    alert('Network error — dubara try karein.');
                }
            });
        });
    }

    refreshBtn?.addEventListener('click', async () => {
        await refreshBoard(true);
        await refreshSummary();
    });
    todayConsumptionBtn?.addEventListener('click', openTodayConsumptionModal);
    todayConsumptionRefreshBtn?.addEventListener('click', loadTodayConsumption);
    window.addEventListener('resize', () => resizeBoard());
    bindStatusForms();
    bindItemServeForms();
    resizeBoard();
    initKitchenDrag();
    applyServeBlink();
    setInterval(() => applyServeBlink(), 30000);
    setInterval(() => refreshBoard(false), 15000);
})();
</script>
@endsection
