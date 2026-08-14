@extends('layouts.admin')

@php
    $isEdit = $deal !== null;
@endphp

@section('title', ($isEdit ? 'Edit deal' : 'New deal') . ' — Inventory — ' . config('app.name'))

@section('content')
    @include('inventory.partials.subnav')

    <div class="mb-3">
        <a href="{{ route('inventory.deals.index') }}" class="text-decoration-none small">&larr; Deals</a>
        <h4 class="fw-bold mt-2 mb-0">{{ $isEdit ? 'Edit deal' : 'Nayi deal' }}</h4>
        <p class="text-secondary small mb-0">Menu items type karke add karo. Permanent = hamesha POS pe; scheduled = start/end date ke darmiyan.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('inventory.deals.update', $deal) : route('inventory.deals.store') }}"
          class="card shadow-sm"
          id="dealForm">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="dealName">Deal name</label>
                    <input type="text" name="name" id="dealName" class="form-control" required maxlength="150"
                           value="{{ old('name', $deal->name ?? '') }}" placeholder="e.g. Family Deal">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="dealPrice">Deal price</label>
                    <input type="number" name="price" id="dealPrice" class="form-control" required min="0" step="0.01"
                           value="{{ old('price', $deal->price ?? '') }}">
                    <div class="form-text" id="itemsValueHint">Items ki regular price sum neeche dikhegi.</div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="hidden" name="active" value="0">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="dealActive"
                               @checked(old('active', $deal->active ?? true))>
                        <label class="form-check-label" for="dealActive">Active (POS pe dikhao jab duration match ho)</label>
                    </div>
                </div>
            </div>

            @php
                $durationType = old('duration_type', ($deal && ! $deal->is_permanent) ? 'scheduled' : 'permanent');
            @endphp
            <div class="mb-3">
                <div class="fw-semibold mb-2">Duration</div>
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="duration_type" id="durPermanent" value="permanent"
                               @checked($durationType === 'permanent')>
                        <label class="form-check-label" for="durPermanent">Permanent (pakki deal)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="duration_type" id="durScheduled" value="scheduled"
                               @checked($durationType === 'scheduled')>
                        <label class="form-check-label" for="durScheduled">Limited time</label>
                    </div>
                </div>
                <div class="row g-3" id="scheduleFields">
                    <div class="col-md-4">
                        <label class="form-label" for="startsAt">Start date</label>
                        <input type="date" name="starts_at" id="startsAt" class="form-control"
                               value="{{ old('starts_at', isset($deal) && $deal->starts_at ? $deal->starts_at->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="endsAt">End date</label>
                        <input type="date" name="ends_at" id="endsAt" class="form-control"
                               value="{{ old('ends_at', isset($deal) && $deal->ends_at ? $deal->ends_at->format('Y-m-d') : '') }}">
                    </div>
                </div>
            </div>

            @error('items')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="fw-semibold">Menu items</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addDealLineBtn">
                    <i class="bi bi-plus-circle me-1"></i> Add item
                </button>
            </div>
            <div class="table-responsive border rounded-3">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width: 280px;">Item</th>
                        <th class="text-end" style="width: 120px;">Menu price</th>
                        <th style="width: 140px;">Qty</th>
                        <th style="width:1%;"></th>
                    </tr>
                    </thead>
                    <tbody id="dealLinesBody"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update deal' : 'Save deal' }}</button>
            <a href="{{ route('inventory.deals.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    @php
        $productsJs = $menuProducts->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->sku.' — '.$p->name,
            'price' => (float) $p->price,
            'uom' => (string) $p->uom,
        ])->values();
        $oldLines = old('items');
        if (is_array($oldLines)) {
            $initialLines = $oldLines;
        } elseif ($deal) {
            $initialLines = $deal->items->map(fn ($l) => [
                'product_id' => $l->product_id,
                'qty' => (string) $l->qty,
            ])->values()->all();
        } else {
            $initialLines = [];
        }
    @endphp
    <datalist id="dealProductOptions"></datalist>
    <script>
        const products = @json($productsJs);
        const initialLines = @json($initialLines);
        const body = document.getElementById('dealLinesBody');
        const addBtn = document.getElementById('addDealLineBtn');
        const datalist = document.getElementById('dealProductOptions');
        const priceInput = document.getElementById('dealPrice');
        const hint = document.getElementById('itemsValueHint');
        const form = document.getElementById('dealForm');

        function productById(pid) {
            return products.find(x => String(x.id) === String(pid)) || null;
        }
        function normalize(v) { return String(v || '').trim().toLowerCase(); }
        function findExact(term) {
            const q = normalize(term);
            if (!q) return null;
            return products.find(p => normalize(p.label) === q) || null;
        }
        function findContains(term, skip) {
            const q = normalize(term);
            if (!q) return null;
            return products.find(p => !skip.has(String(p.id)) && normalize(p.label).includes(q)) || null;
        }
        function usedIds(exceptHidden) {
            const ids = new Set();
            body.querySelectorAll('.line-product-id').forEach((el) => {
                if (el !== exceptHidden && el.value) ids.add(String(el.value));
            });
            return ids;
        }
        function fillDatalist(term, exceptHidden) {
            const q = normalize(term);
            const skip = usedIds(exceptHidden);
            const list = products.filter(p => {
                if (skip.has(String(p.id))) return false;
                return !q || normalize(p.label).includes(q);
            }).slice(0, 80);
            datalist.innerHTML = list.map(p => `<option value="${String(p.label).replace(/"/g, '&quot;')}"></option>`).join('');
        }
        function money(n) {
            if (!Number.isFinite(n)) return '0';
            return (Math.round(n * 100) / 100).toFixed(2);
        }
        function refreshHint() {
            let sum = 0;
            body.querySelectorAll('tr').forEach((row) => {
                const p = productById(row.querySelector('.line-product-id')?.value);
                const qty = Number(row.querySelector('.line-qty')?.value || 0);
                if (p) sum += Number(p.price || 0) * qty;
            });
            if (hint) hint.textContent = 'Items ki regular price: ' + money(sum);
        }

        function addLine(line = {}, { focus = false } = {}) {
            const idx = body.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input class="form-control line-product-search" type="text" list="dealProductOptions"
                           placeholder="Type name / SKU…" autocomplete="off">
                    <input type="hidden" class="line-product-id" name="items[${idx}][product_id]" value="${line.product_id ?? ''}" required>
                </td>
                <td class="text-end text-secondary small line-menu-price">—</td>
                <td><input class="form-control text-end line-qty" type="number" step="0.001" min="0.001" name="items[${idx}][qty]" value="${line.qty ?? '1'}" required></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger removeLine">×</button></td>
            `;
            const search = tr.querySelector('.line-product-search');
            const hidden = tr.querySelector('.line-product-id');
            const priceCell = tr.querySelector('.line-menu-price');
            const qty = tr.querySelector('.line-qty');
            const initial = productById(line.product_id);
            if (initial) search.value = initial.label;

            function setProduct(product) {
                if (!product) {
                    hidden.value = '';
                    priceCell.textContent = '—';
                    refreshHint();
                    return;
                }
                hidden.value = String(product.id);
                search.value = product.label;
                search.classList.remove('is-invalid');
                priceCell.textContent = money(Number(product.price || 0));
                refreshHint();
            }
            function resolveTyped() {
                const skip = usedIds(hidden);
                const exact = findExact(search.value);
                if (exact && !skip.has(String(exact.id))) { setProduct(exact); return; }
                const partial = findContains(search.value, skip);
                if (partial) { setProduct(partial); return; }
                if (!String(search.value || '').trim()) { setProduct(null); return; }
                setProduct(null);
                search.classList.add('is-invalid');
            }

            search.addEventListener('focus', () => fillDatalist(search.value, hidden));
            search.addEventListener('input', () => {
                const skip = usedIds(hidden);
                const exact = findExact(search.value);
                if (exact && !skip.has(String(exact.id))) setProduct(exact);
                else { hidden.value = ''; priceCell.textContent = '—'; refreshHint(); }
                fillDatalist(search.value, hidden);
            });
            search.addEventListener('blur', resolveTyped);
            qty.addEventListener('input', refreshHint);
            tr.querySelector('.removeLine').addEventListener('click', () => {
                tr.remove();
                [...body.querySelectorAll('tr')].forEach((row, i) => {
                    row.querySelectorAll('[name]').forEach(el => {
                        el.name = el.name.replace(/items\[\d+]/, 'items[' + i + ']');
                    });
                });
                refreshHint();
            });
            body.appendChild(tr);
            setProduct(initial);
            if (focus) search.focus();
        }

        function syncSchedule() {
            const scheduled = document.getElementById('durScheduled')?.checked;
            document.getElementById('scheduleFields')?.classList.toggle('d-none', !scheduled);
            document.getElementById('startsAt').required = !!scheduled;
            document.getElementById('endsAt').required = !!scheduled;
        }
        document.querySelectorAll('input[name="duration_type"]').forEach((el) => {
            el.addEventListener('change', syncSchedule);
        });
        syncSchedule();

        form?.addEventListener('submit', (e) => {
            let ok = body.querySelectorAll('tr').length > 0;
            body.querySelectorAll('tr').forEach((row) => {
                const hidden = row.querySelector('.line-product-id');
                const search = row.querySelector('.line-product-search');
                if (!hidden?.value) {
                    ok = false;
                    search?.classList.add('is-invalid');
                }
            });
            if (!ok) {
                e.preventDefault();
                body.querySelector('.line-product-search.is-invalid')?.focus();
            }
        });

        addBtn?.addEventListener('click', () => addLine({}, { focus: true }));
        if (initialLines.length) {
            initialLines.forEach((l) => addLine({ product_id: l.product_id, qty: l.qty }));
        } else {
            addLine({});
        }
    </script>
@endsection
