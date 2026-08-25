<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>All Recipes — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 7mm 8mm; }
        body {
            margin: 0;
            padding: 10px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            line-height: 1.25;
            color: #111;
            background: #f8fafc;
        }
        .noprint { margin-bottom: 10px; text-align: center; }
        .noprint button, .noprint a {
            display: inline-block;
            padding: 6px 12px;
            margin: 0 4px;
            border: 1px solid #666;
            border-radius: 4px;
            background: #fff;
            color: #111;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        #saveStatus {
            display: inline-block;
            min-width: 120px;
            margin-left: 8px;
            font-size: 12px;
            color: #166534;
            font-weight: 600;
        }
        #saveStatus.error { color: #b91c1c; }
        #saveStatus.pending { color: #92400e; }
        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 6mm 8mm;
            border: 1px solid #ddd;
        }
        .doc-head {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid #333;
        }
        .doc-head h1 { margin: 0; font-size: 14px; line-height: 1.2; }
        .doc-head .meta { margin: 3px 0 0; font-size: 8px; color: #555; }
        .recipe-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            table-layout: fixed;
        }
        .recipe-table th,
        .recipe-table td {
            border: 1px solid #666;
            padding: 2px 4px;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .recipe-table thead th {
            background: #e5e7eb;
            font-weight: 700;
            font-size: 8px;
            padding: 3px 4px;
        }
        .col-dish { width: 18%; }
        .col-ing { width: 26%; }
        .col-qty { width: 10%; text-align: right; }
        .col-uom { width: 10%; }
        .col-rate { width: 12%; text-align: right; white-space: nowrap; }
        .col-amt { width: 12%; text-align: right; white-space: nowrap; }
        .col-act { width: 12%; text-align: center; }
        tr.dish-row td {
            background: #f3f4f6;
            font-weight: 700;
            font-size: 9px;
            padding: 3px 4px;
            border-top: 1.5px solid #333;
        }
        tr.dish-row:first-child td { border-top: 1px solid #666; }
        tr.total-row td {
            background: #fafafa;
            font-weight: 700;
            font-size: 8px;
            padding: 2px 4px 4px;
            border-bottom: 1.5px solid #999;
        }
        tr.total-row .summary-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px 18px;
            text-align: right;
            align-items: center;
        }
        tr.total-row .summary-item .label {
            font-weight: 600;
            color: #555;
            margin-right: 4px;
        }
        tr.total-row .summary-item .value { font-weight: 700; }
        tr.total-row .summary-item.profit .value { color: #166534; }
        tr.total-row .summary-item.loss .value { color: #b91c1c; }
        tr.ing-row td.col-dish { color: transparent; font-size: 0; border-top-color: #ddd; }
        tr.empty-row td { font-style: italic; color: #666; font-size: 8px; }
        tr.add-row td {
            background: #f8fafc;
            border-bottom: none;
            padding: 3px 4px;
        }
        .cell-input, .cell-select {
            width: 100%;
            max-width: 100%;
            font-size: 8.5px;
            padding: 2px 3px;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            background: #fff;
        }
        .cell-input:focus, .cell-select:focus {
            outline: 2px solid #6366f1;
            border-color: #6366f1;
        }
        .cell-input.qty { text-align: right; }
        .sale-price-input {
            width: 72px;
            font-size: 8.5px;
            padding: 2px 4px;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            text-align: right;
            font-weight: 700;
        }
        .btn-mini {
            font-size: 8px;
            padding: 2px 6px;
            border: 1px solid #64748b;
            border-radius: 3px;
            background: #fff;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-mini.add { border-color: #166534; color: #166534; }
        .btn-mini.del { border-color: #b91c1c; color: #b91c1c; }
        .add-line-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }
        .add-line-wrap .ing-search { flex: 1; min-width: 120px; }
        .add-line-wrap .qty { width: 64px; }
        .add-line-wrap .uom { width: 70px; }
        .print-only { display: none; }
        @media print {
            body { padding: 0; background: #fff; font-size: 8.5px; }
            .noprint, .no-print, .btn-mini, .add-row, .col-act, th.col-act { display: none !important; }
            .edit-only { display: none !important; }
            .print-only { display: inline !important; }
            .sheet { max-width: none; border: none; padding: 0; }
            .recipe-table { font-size: 8px; }
            .recipe-table thead { display: table-header-group; }
            tr.dish-row { page-break-after: avoid; break-after: avoid-page; }
            tr.total-row { page-break-after: avoid; break-after: avoid-page; }
            .col-qty { width: 12%; }
            .col-uom { width: 10%; }
            .col-ing { width: 30%; }
            .col-dish { width: 22%; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('manufacturing.boms.index', request()->only(['q', 'finished_product', 'return'])) }}">Back to BoMs</a>
        <span id="saveStatus"></span>
    </div>

    <div class="sheet">
        <header class="doc-head">
            <h1>{{ $companyName }} — All Recipes</h1>
            <p class="meta">
                {{ now()->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
                · {{ $boms->count() }} recipes
                @if(($q ?? '') !== '')
                    · “{{ $q }}”
                @endif
                <span class="noprint"> · Qty / UoM edit = auto-save</span>
            </p>
        </header>

        @if($boms->isEmpty())
            <p style="text-align:center;color:#666;padding:16px 0;">No recipes found.</p>
        @else
            <table class="recipe-table" id="recipesTable">
                <thead>
                    <tr>
                        <th class="col-dish">Dish</th>
                        <th class="col-ing">Ingredient</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-uom">UoM</th>
                        <th class="col-rate">Rate</th>
                        <th class="col-amt">Amount</th>
                        <th class="col-act no-print"> </th>
                    </tr>
                </thead>
                    @foreach($boms as $bom)
                        @php
                            $dishName = $bom->finishedProduct?->name ?? '—';
                            $materialPerBatch = (float) $bom->materialCostPerBatch();
                            $batchQty = (float) $bom->batch_qty;
                            $totalCost = $batchQty > 0 ? ($materialPerBatch / $batchQty) : $materialPerBatch;
                            $salePrice = (float) ($bom->finishedProduct?->price ?? 0);
                            $profit = $salePrice - $totalCost;
                            $finishedUom = (string) ($bom->finishedProduct?->uom ?? '');
                            $uomSuffix = $finishedUom !== '' ? '/'.$finishedUom : '';
                            $bomId = (int) $bom->id;
                        @endphp

                        <tbody class="bom-block" data-bom-id="{{ $bomId }}" data-dish="{{ $dishName }}">
                            @if($bom->lines->isEmpty())
                                <tr class="dish-row empty-row bom-empty">
                                    <td class="col-dish">{{ $dishName }}</td>
                                    <td class="col-ing" colspan="5">No ingredients</td>
                                    <td class="col-act no-print"></td>
                                </tr>
                            @else
                                @foreach($bom->lines as $line)
                                    @php
                                        $qty = (float) $line->qty;
                                        $uom = $line->effectiveUom();
                                        $lineAmount = (float) $line->lineMaterialCostPerBatch();
                                        $ratePerQtyUom = $qty > 0 ? ($lineAmount / $qty) : (float) ($line->component?->cost ?? 0);
                                        $lineUoms = collect($line->component?->uomsForForms() ?? [])->pluck('uom')->map(fn ($u) => (string) $u)->all();
                                        if ($lineUoms === [] && $uom !== '') {
                                            $lineUoms = [$uom];
                                        }
                                    @endphp
                                    <tr class="{{ $loop->first ? 'dish-row' : 'ing-row' }} line-row"
                                        data-line-id="{{ $line->id }}"
                                        data-component-id="{{ $line->component_product_id }}">
                                        <td class="col-dish">{{ $loop->first ? $dishName : '·' }}</td>
                                        <td class="col-ing">{{ $line->component?->name ?? '—' }}</td>
                                        <td class="col-qty">
                                            <input type="number" class="cell-input qty edit-only line-qty" step="0.001" min="0.001" value="{{ rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.') ?: '0' }}">
                                            <span class="print-only">{{ fmt_num($qty, 3) }}</span>
                                        </td>
                                        <td class="col-uom">
                                            <select class="cell-select edit-only line-uom">
                                                @foreach($lineUoms as $code)
                                                    <option value="{{ $code }}" @selected(strcasecmp($code, $uom) === 0)>{{ $code }}</option>
                                                @endforeach
                                            </select>
                                            <span class="print-only">{{ $uom }}</span>
                                        </td>
                                        <td class="col-rate line-rate">{{ fmt_num($ratePerQtyUom, 2) }}</td>
                                        <td class="col-amt line-amt">{{ fmt_num($lineAmount, 2) }}</td>
                                        <td class="col-act no-print">
                                            <button type="button" class="btn-mini del line-remove" title="Remove">&times;</button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif

                            <tr class="add-row no-print">
                                <td class="col-dish"></td>
                                <td class="col-ing" colspan="5">
                                    <div class="add-line-wrap">
                                        <input type="text" class="cell-input ing-search" list="ingredientOptions" placeholder="Add ingredient…" autocomplete="off">
                                        <input type="number" class="cell-input qty add-qty" step="0.001" min="0.001" placeholder="Qty" value="1">
                                        <select class="cell-select uom add-uom"><option value="">UoM</option></select>
                                        <button type="button" class="btn-mini add add-line-btn">+ Add line</button>
                                    </div>
                                </td>
                                <td class="col-act"></td>
                            </tr>

                            <tr class="total-row">
                                <td class="col-dish"></td>
                                <td class="col-ing" colspan="5">
                                    <div class="summary-bar">
                                        <span class="summary-item">
                                            <span class="label">Total Cost:</span>
                                            <span class="value bom-total-cost">{{ fmt_num($totalCost, 2) }}{{ $uomSuffix }}</span>
                                        </span>
                                        <span class="summary-item">
                                            <span class="label">Sale Price:</span>
                                            <input type="number" class="sale-price-input edit-only bom-sale-price" step="0.01" min="0" value="{{ number_format($salePrice, 2, '.', '') }}">
                                            <span class="print-only bom-sale-price-print">{{ fmt_num($salePrice, 2) }}</span>
                                        </span>
                                        <span class="summary-item {{ $profit >= 0 ? 'profit' : 'loss' }} bom-profit-wrap">
                                            <span class="label">Profit:</span>
                                            <span class="value bom-profit">{{ fmt_num($profit, 2) }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="col-act no-print"></td>
                            </tr>
                        </tbody>
                    @endforeach
            </table>
        @endif
    </div>

    <datalist id="ingredientOptions">
        @foreach($ingredientMeta as $ing)
            <option value="{{ $ing['label'] }}"></option>
        @endforeach
    </datalist>

    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const ingredients = @json($ingredientMeta);
        const urls = {
            updateLine: @json(route('manufacturing.boms.lines.update', ['bom' => '__BOM__', 'line' => '__LINE__'])),
            storeLine: @json(route('manufacturing.boms.lines.store', ['bom' => '__BOM__'])),
            destroyLine: @json(route('manufacturing.boms.lines.destroy', ['bom' => '__BOM__', 'line' => '__LINE__'])),
            salePrice: @json(route('manufacturing.boms.sale-price', ['bom' => '__BOM__'])),
        };
        const statusEl = document.getElementById('saveStatus');
        const timers = new WeakMap();

        function fmt(n, digits = 2) {
            const num = Number(n || 0);
            return num.toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: digits,
            });
        }

        function setStatus(text, type) {
            if (!statusEl) return;
            statusEl.textContent = text || '';
            statusEl.className = type || '';
        }

        function findIngredientByLabel(label) {
            const q = String(label || '').trim().toLowerCase();
            if (!q) return null;
            return ingredients.find((i) => String(i.label).toLowerCase() === q)
                || ingredients.find((i) => String(i.label).toLowerCase().includes(q)
                    || `${i.sku} ${i.name}`.toLowerCase().includes(q))
                || null;
        }

        function fillAddUom(select, ingredient) {
            const list = ingredient?.uoms || [];
            select.innerHTML = list.length
                ? list.map((u, idx) => `<option value="${u.uom}" ${idx === 0 ? 'selected' : ''}>${u.uom}</option>`).join('')
                : '<option value="">UoM</option>';
        }

        async function api(url, method, body) {
            setStatus('Saving…', 'pending');
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : undefined,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.message || Object.values(data.errors || {}).flat()[0] || 'Save failed';
                setStatus(msg, 'error');
                throw new Error(msg);
            }
            setStatus('Saved', '');
            return data;
        }

        function applySnapshot(block, snap) {
            if (!snap) return;
            const dish = block.dataset.dish || '';
            const uomSuffix = snap.finished_uom ? `/${snap.finished_uom}` : '';
            const totalEl = block.querySelector('.bom-total-cost');
            const profitEl = block.querySelector('.bom-profit');
            const profitWrap = block.querySelector('.bom-profit-wrap');
            const saleInput = block.querySelector('.bom-sale-price');
            const salePrint = block.querySelector('.bom-sale-price-print');

            if (totalEl) totalEl.textContent = `${fmt(snap.total_cost, 2)}${uomSuffix}`;
            if (profitEl) profitEl.textContent = fmt(snap.profit, 2);
            if (profitWrap) {
                profitWrap.classList.toggle('profit', Number(snap.profit) >= 0);
                profitWrap.classList.toggle('loss', Number(snap.profit) < 0);
            }
            if (saleInput && document.activeElement !== saleInput) {
                saleInput.value = Number(snap.sale_price || 0).toFixed(2);
            }
            if (salePrint) salePrint.textContent = fmt(snap.sale_price, 2);

            const byId = Object.fromEntries((snap.lines || []).map((l) => [String(l.id), l]));
            block.querySelectorAll('.line-row').forEach((row) => {
                const line = byId[String(row.dataset.lineId)];
                if (!line) return;
                const qtyInput = row.querySelector('.line-qty');
                const uomSelect = row.querySelector('.line-uom');
                if (qtyInput && document.activeElement !== qtyInput) {
                    qtyInput.value = String(line.qty);
                    const printQty = row.querySelector('.col-qty .print-only');
                    if (printQty) printQty.textContent = fmt(line.qty, 3);
                }
                if (uomSelect && document.activeElement !== uomSelect) {
                    const codes = line.uoms?.length ? line.uoms : [line.uom];
                    uomSelect.innerHTML = codes.map((c) =>
                        `<option value="${c}" ${String(c).toLowerCase() === String(line.uom).toLowerCase() ? 'selected' : ''}>${c}</option>`
                    ).join('');
                    const printUom = row.querySelector('.col-uom .print-only');
                    if (printUom) printUom.textContent = line.uom;
                }
                const rate = row.querySelector('.line-rate');
                const amt = row.querySelector('.line-amt');
                if (rate) rate.textContent = fmt(line.rate, 2);
                if (amt) amt.textContent = fmt(line.amount, 2);
            });

            // First line dish name styling
            const lines = [...block.querySelectorAll('.line-row')];
            lines.forEach((row, idx) => {
                row.classList.toggle('dish-row', idx === 0);
                row.classList.toggle('ing-row', idx !== 0);
                const dishCell = row.querySelector('.col-dish');
                if (dishCell) dishCell.textContent = idx === 0 ? dish : '·';
            });

            const empty = block.querySelector('.bom-empty');
            if (empty) empty.remove();
            if (lines.length === 0 && !block.querySelector('.bom-empty')) {
                const addRow = block.querySelector('.add-row');
                const tr = document.createElement('tr');
                tr.className = 'dish-row empty-row bom-empty';
                tr.innerHTML = `<td class="col-dish">${dish}</td><td class="col-ing" colspan="5">No ingredients</td><td class="col-act no-print"></td>`;
                block.insertBefore(tr, addRow);
            }
        }

        function scheduleSave(row, fn) {
            const prev = timers.get(row);
            if (prev) clearTimeout(prev);
            timers.set(row, setTimeout(() => {
                fn().catch(() => {});
            }, 450));
        }

        function bindLineRow(block, row) {
            const qty = row.querySelector('.line-qty');
            const uom = row.querySelector('.line-uom');
            const removeBtn = row.querySelector('.line-remove');

            const save = () => {
                const bomId = block.dataset.bomId;
                const lineId = row.dataset.lineId;
                const url = urls.updateLine
                    .replace('__BOM__', encodeURIComponent(bomId))
                    .replace('__LINE__', encodeURIComponent(lineId));
                return api(url, 'PATCH', {
                    qty: qty.value,
                    uom: uom.value,
                }).then((snap) => applySnapshot(block, snap));
            };

            qty?.addEventListener('input', () => scheduleSave(row, save));
            qty?.addEventListener('change', () => scheduleSave(row, save));
            uom?.addEventListener('change', () => scheduleSave(row, save));

            removeBtn?.addEventListener('click', async () => {
                if (!confirm('Is ingredient ko recipe se hataein?')) return;
                const bomId = block.dataset.bomId;
                const lineId = row.dataset.lineId;
                const url = urls.destroyLine
                    .replace('__BOM__', encodeURIComponent(bomId))
                    .replace('__LINE__', encodeURIComponent(lineId));
                try {
                    const snap = await api(url, 'DELETE');
                    row.remove();
                    applySnapshot(block, snap);
                } catch (e) {}
            });
        }

        function appendLineRow(block, line, isFirst) {
            const dish = block.dataset.dish || '';
            const tr = document.createElement('tr');
            tr.className = `${isFirst ? 'dish-row' : 'ing-row'} line-row`;
            tr.dataset.lineId = String(line.id);
            tr.dataset.componentId = String(line.component_product_id);
            const uoms = (line.uoms && line.uoms.length) ? line.uoms : [line.uom];
            tr.innerHTML = `
                <td class="col-dish">${isFirst ? dish : '·'}</td>
                <td class="col-ing"></td>
                <td class="col-qty">
                    <input type="number" class="cell-input qty edit-only line-qty" step="0.001" min="0.001" value="${line.qty}">
                    <span class="print-only">${fmt(line.qty, 3)}</span>
                </td>
                <td class="col-uom">
                    <select class="cell-select edit-only line-uom">
                        ${uoms.map((c) => `<option value="${c}" ${String(c).toLowerCase() === String(line.uom).toLowerCase() ? 'selected' : ''}>${c}</option>`).join('')}
                    </select>
                    <span class="print-only">${line.uom}</span>
                </td>
                <td class="col-rate line-rate">${fmt(line.rate, 2)}</td>
                <td class="col-amt line-amt">${fmt(line.amount, 2)}</td>
                <td class="col-act no-print">
                    <button type="button" class="btn-mini del line-remove" title="Remove">&times;</button>
                </td>
            `;
            tr.querySelector('.col-ing').textContent = line.component_name || '—';
            const addRow = block.querySelector('.add-row');
            block.insertBefore(tr, addRow);
            bindLineRow(block, tr);
            return tr;
        }

        document.querySelectorAll('.bom-block').forEach((block) => {
            block.querySelectorAll('.line-row').forEach((row) => bindLineRow(block, row));

            const search = block.querySelector('.ing-search');
            const addQty = block.querySelector('.add-qty');
            const addUom = block.querySelector('.add-uom');
            const addBtn = block.querySelector('.add-line-btn');

            search?.addEventListener('change', () => {
                const ing = findIngredientByLabel(search.value);
                fillAddUom(addUom, ing);
            });
            search?.addEventListener('input', () => {
                const ing = findIngredientByLabel(search.value);
                if (ing) fillAddUom(addUom, ing);
            });

            addBtn?.addEventListener('click', async () => {
                const ing = findIngredientByLabel(search.value);
                if (!ing) {
                    alert('Ingredient select karein.');
                    return;
                }
                const qty = addQty.value;
                const uom = addUom.value;
                if (!qty || Number(qty) <= 0) {
                    alert('Valid qty likhein.');
                    return;
                }
                if (!uom) {
                    alert('UoM select karein.');
                    return;
                }
                const url = urls.storeLine.replace('__BOM__', encodeURIComponent(block.dataset.bomId));
                try {
                    const snap = await api(url, 'POST', {
                        component_product_id: ing.id,
                        qty,
                        uom,
                    });
                    const newId = snap.new_line_id;
                    const line = (snap.lines || []).find((l) => Number(l.id) === Number(newId))
                        || (snap.lines || []).find((l) => Number(l.component_product_id) === Number(ing.id));
                    if (line && !block.querySelector(`.line-row[data-line-id="${line.id}"]`)) {
                        const isFirst = block.querySelectorAll('.line-row').length === 0;
                        block.querySelector('.bom-empty')?.remove();
                        appendLineRow(block, line, isFirst);
                    }
                    applySnapshot(block, snap);
                    search.value = '';
                    addQty.value = '1';
                    addUom.innerHTML = '<option value="">UoM</option>';
                } catch (e) {}
            });

            const saleInput = block.querySelector('.bom-sale-price');
            const saveSale = () => {
                const url = urls.salePrice.replace('__BOM__', encodeURIComponent(block.dataset.bomId));
                return api(url, 'PATCH', { sale_price: saleInput.value }).then((snap) => applySnapshot(block, snap));
            };
            saleInput?.addEventListener('change', () => scheduleSave(saleInput, saveSale));
            saleInput?.addEventListener('blur', () => scheduleSave(saleInput, saveSale));
        });

        if (new URLSearchParams(window.location.search).get('auto') === '1') {
            window.addEventListener('load', () => window.print());
        }
    })();
    </script>
</body>
</html>
