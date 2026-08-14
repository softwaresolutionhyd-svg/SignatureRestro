<datalist id="stockCheckProductOptions"></datalist>
<script>
    const products = @json($productsJs);
    const initialLines = @json($initialLines);
    const body = document.getElementById('linesBody');
    const addBtn = document.getElementById('addLineBtn');
    const form = addBtn?.closest('form');
    const datalist = document.getElementById('stockCheckProductOptions');

    function productById(pid) {
        return products.find(x => String(x.id) === String(pid)) || null;
    }

    function productLabel(p) {
        return p ? String(p.label || '') : '';
    }

    function factorForUom(product, uomCode) {
        if (!product) return 1;
        const row = (product.uoms || []).find(u => String(u.uom) === String(uomCode));
        return row && Number(row.factor) > 0 ? Number(row.factor) : 1;
    }

    function bookLabel(pid, uomCode) {
        const p = productById(pid);
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

    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    function findExact(term) {
        const q = normalize(term);
        if (!q) return null;
        return products.find(p => normalize(p.label) === q) || null;
    }

    function findContains(term, exceptIds) {
        const q = normalize(term);
        if (!q) return null;
        const skip = exceptIds instanceof Set ? exceptIds : new Set();
        return products.find((p) => {
            if (skip.has(String(p.id))) return false;
            return normalize(p.label).includes(q);
        }) || null;
    }

    function usedProductIds(exceptHidden) {
        const ids = new Set();
        body.querySelectorAll('.line-product-id').forEach((el) => {
            if (el === exceptHidden) return;
            if (el.value) ids.add(String(el.value));
        });
        return ids;
    }

    function fillDatalist(term, exceptHidden) {
        const q = normalize(term);
        const skip = usedProductIds(exceptHidden);
        const list = products.filter((p) => {
            if (skip.has(String(p.id))) return false;
            return !q || normalize(p.label).includes(q);
        }).slice(0, 80);

        datalist.innerHTML = list.map((p) => {
            const label = productLabel(p).replace(/"/g, '&quot;');
            return `<option value="${label}"></option>`;
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

    function addLine(line = {}, { focus = false } = {}) {
        const idx = body.querySelectorAll('tr').length;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input class="form-control line-product-search" type="text" list="stockCheckProductOptions"
                       placeholder="Type name / SKU…" autocomplete="off">
                <input type="hidden" class="line-product-id" name="lines[${idx}][product_id]" value="${line.product_id ?? ''}" required>
            </td>
            <td class="text-end text-secondary small line-book">—</td>
            <td>
                <select class="form-select line-uom" name="lines[${idx}][uom]" required></select>
            </td>
            <td><input class="form-control text-end" type="number" step="0.000001" min="0" name="lines[${idx}][qty]" value="${line.qty ?? ''}" placeholder="optional draft"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger removeLine">×</button></td>
        `;
        const search = tr.querySelector('.line-product-search');
        const hidden = tr.querySelector('.line-product-id');
        const uomSel = tr.querySelector('.line-uom');
        const bookCell = tr.querySelector('.line-book');
        const initialProduct = productById(line.product_id);
        if (initialProduct) {
            search.value = productLabel(initialProduct);
        }

        function refreshUoms(selectedUom) {
            uomSel.innerHTML = uomOptions(hidden.value, selectedUom);
        }

        function refreshBook() {
            bookCell.textContent = bookLabel(hidden.value, uomSel.value);
        }

        function setProduct(product, selectedUom) {
            if (!product) {
                hidden.value = '';
                search.classList.toggle('is-invalid', Boolean(String(search.value || '').trim()));
                refreshUoms('');
                refreshBook();
                return;
            }
            hidden.value = String(product.id);
            search.value = productLabel(product);
            search.classList.remove('is-invalid');
            refreshUoms(selectedUom || product.uom);
            refreshBook();
        }

        function resolveTyped() {
            const skip = usedProductIds(hidden);
            const exact = findExact(search.value);
            if (exact && !skip.has(String(exact.id))) {
                setProduct(exact);
                return;
            }
            const partial = findContains(search.value, skip);
            if (partial) {
                setProduct(partial);
                return;
            }
            if (!String(search.value || '').trim()) {
                setProduct(null);
                search.classList.remove('is-invalid');
                return;
            }
            setProduct(null);
        }

        search.addEventListener('focus', () => fillDatalist(search.value, hidden));
        search.addEventListener('input', () => {
            const skip = usedProductIds(hidden);
            const exact = findExact(search.value);
            if (exact && !skip.has(String(exact.id))) {
                setProduct(exact);
            } else {
                hidden.value = '';
                search.classList.remove('is-invalid');
                refreshUoms('');
                refreshBook();
            }
            fillDatalist(search.value, hidden);
        });
        search.addEventListener('blur', resolveTyped);
        search.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            resolveTyped();
        });
        uomSel.addEventListener('change', refreshBook);
        tr.querySelector('.removeLine').addEventListener('click', () => {
            tr.remove();
            reindex();
        });

        body.appendChild(tr);
        setProduct(initialProduct, line.uom || '');
        if (focus) {
            search.focus();
        }
    }

    function reindex() {
        [...body.querySelectorAll('tr')].forEach((row, i) => {
            row.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/lines\[\d+]/, 'lines[' + i + ']');
            });
        });
    }

    form?.addEventListener('submit', (e) => {
        let ok = true;
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
        initialLines.forEach(l => addLine({ product_id: l.product_id, uom: l.uom, qty: l.qty }));
    }
</script>
