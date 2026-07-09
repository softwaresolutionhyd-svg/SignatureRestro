@extends('layouts.admin')

@section('title', 'Edit stock check — ' . config('app.name'))

@section('content')
    @include('inventory.partials.subnav')

    <div class="mb-3">
        <a href="{{ route('inventory.stock-check.show', $stockCheck) }}" class="text-decoration-none small">&larr; {{ $stockCheck->number }}</a>
        <h4 class="fw-bold mt-2 mb-0">Edit draft — {{ $stockCheck->number }}</h4>
    </div>

    <form method="POST" action="{{ route('inventory.stock-check.update', $stockCheck) }}" class="card shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="title">Title (optional)</label>
                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title', $stockCheck->title) }}" maxlength="200">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @error('lines')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="fw-semibold">Lines</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn"><i class="bi bi-plus-circle me-1"></i> Add line</button>
            </div>

            <div class="table-responsive border rounded-3">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width: 280px;">Product</th>
                        <th class="text-end" style="min-width: 140px;">Book</th>
                        <th style="min-width: 150px;">UOM</th>
                        <th class="text-end" style="min-width: 160px;">Counted</th>
                        <th style="width:1%;"></th>
                    </tr>
                    </thead>
                    <tbody id="linesBody"></tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save draft</button>
                <a href="{{ route('inventory.stock-check.show', $stockCheck) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>

    @php
        $productsJs = $products->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->sku . ' — ' . $p->name,
            'uom' => (string) $p->uom,
            'uoms' => $p->uomsForForms(),
            'book' => (float) $p->qty_on_hand,
        ])->values();
        $oldLines = old('lines');
        if (is_array($oldLines)) {
            $initialLines = $oldLines;
        } else {
            $initialLines = $stockCheck->lines->map(fn ($l) => [
                'product_id' => $l->product_id,
                'uom' => (string) ($l->product?->uom ?? ''),
                'qty' => $l->counted_qty !== null ? (string) $l->counted_qty : '',
            ])->values()->all();
        }
    @endphp
    <script>
        const products = @json($productsJs);
        const initialLines = @json($initialLines);
        const body = document.getElementById('linesBody');
        const addBtn = document.getElementById('addLineBtn');

        function productById(pid) {
            return products.find(x => String(x.id) === String(pid)) || null;
        }

        function factorForUom(product, uomCode) {
            if (!product) return 1;
            const row = (product.uoms || []).find(u => String(u.uom) === String(uomCode));
            return row && Number(row.factor) > 0 ? Number(row.factor) : 1;
        }

        function bookLabel(pid, uomCode) {
            const p = products.find(x => String(x.id) === String(pid));
            if (!p) return '—';
            const factor = factorForUom(p, uomCode || p.uom);
            const qtyInSelectedUom = Number(p.book) / factor;
            const shownUom = uomCode || p.uom;
            return `${fmt(qtyInSelectedUom)} ${shownUom}`;
        }

        function fmt(n) {
            if (!Number.isFinite(n)) return '0';
            let s = (Math.round(n * 1000000) / 1000000).toString();
            if (s.includes('.')) s = s.replace(/\.?0+$/, '');
            return s === '-0' ? '0' : s;
        }

        function productOptions(selected) {
            return '<option value="">Select product…</option>' + products.map(p => {
                const sel = selected && String(selected) === String(p.id) ? 'selected' : '';
                return `<option value="${p.id}" ${sel}>${p.label}</option>`;
            }).join('');
        }

        function uomOptions(pid, selectedUom) {
            const p = productById(pid);
            if (!p) {
                return '<option value="">Select UOM…</option>';
            }

            const rows = (p.uoms || []);
            if (!rows.length) {
                return `<option value="${p.uom}" selected>${p.uom}</option>`;
            }

            const fallback = selectedUom || p.uom;
            return rows.map((row) => {
                const code = String(row.uom);
                const isSelected = String(fallback) === code ? 'selected' : '';
                const tag = code === p.uom ? ' (base)' : '';
                return `<option value="${code}" ${isSelected}>${code}${tag}</option>`;
            }).join('');
        }

        function addLine(line = {}) {
            const idx = body.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select class="form-select line-product" name="lines[${idx}][product_id]" required>
                        ${productOptions(line.product_id)}
                    </select>
                </td>
                <td class="text-end text-secondary small line-book">—</td>
                <td>
                    <select class="form-select line-uom" name="lines[${idx}][uom]" required></select>
                </td>
                <td><input class="form-control text-end" type="number" step="0.000001" min="0" name="lines[${idx}][qty]" value="${line.qty ?? ''}" placeholder="optional draft"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger removeLine">×</button></td>
            `;
            const prodSel = tr.querySelector('.line-product');
            const uomSel = tr.querySelector('.line-uom');
            const bookCell = tr.querySelector('.line-book');

            function refreshUoms() {
                const p = productById(prodSel.value);
                const selected = line.uom || p?.uom || '';
                uomSel.innerHTML = uomOptions(prodSel.value, selected);
            }

            function refreshBook() {
                bookCell.textContent = bookLabel(prodSel.value, uomSel.value);
            }

            prodSel.addEventListener('change', () => {
                line.uom = '';
                refreshUoms();
                refreshBook();
            });
            uomSel.addEventListener('change', refreshBook);
            refreshUoms();
            refreshBook();
            tr.querySelector('.removeLine').addEventListener('click', () => {
                tr.remove();
                reindex();
            });
            body.appendChild(tr);
        }

        function reindex() {
            [...body.querySelectorAll('tr')].forEach((row, i) => {
                row.querySelectorAll('[name]').forEach(el => {
                    el.name = el.name.replace(/lines\[\d+]/, 'lines[' + i + ']');
                });
            });
        }

        addBtn.addEventListener('click', () => addLine({}));
        if (initialLines.length) {
            initialLines.forEach(l => addLine({ product_id: l.product_id, uom: l.uom, qty: l.qty }));
        } else {
            addLine({});
        }
    </script>
@endsection
