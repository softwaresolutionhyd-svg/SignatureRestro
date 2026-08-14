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

    function hasInner(product) {
        return !!(product && Number(product.pkg_qty) > 0 && String(product.pkg_uom || '').trim());
    }

    function fmt(n) {
        if (!Number.isFinite(n)) return '0';
        let s = (Math.round(n * 1000000) / 1000000).toString();
        if (s.includes('.')) s = s.replace(/\.?0+$/, '');
        return s === '-0' ? '0' : s;
    }

    function bookLabel(pid) {
        const p = productById(pid);
        if (!p) return '—';
        let text = `${fmt(Number(p.book))} ${p.uom || ''}`.trim();
        if (hasInner(p)) {
            const innerBook = Number(p.book) * Number(p.pkg_qty);
            text += `\n≈ ${fmt(innerBook)} ${p.pkg_uom}`;
        }
        return text;
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

    function addLine(line = {}, { focus = false } = {}) {
        const idx = body.querySelectorAll('tr').length;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input class="form-control line-product-search" type="text" list="stockCheckProductOptions"
                       placeholder="Type name / SKU…" autocomplete="off">
                <input type="hidden" class="line-product-id" name="lines[${idx}][product_id]" value="${line.product_id ?? ''}" required>
            </td>
            <td class="text-end text-secondary small line-book" style="white-space: pre-line;">—</td>
            <td>
                <div class="input-group input-group-sm">
                    <input class="form-control text-end line-qty-base" type="number" step="0.000001" min="0"
                           name="lines[${idx}][qty_base]" value="${line.qty_base ?? ''}" placeholder="0">
                    <span class="input-group-text line-base-uom">—</span>
                </div>
            </td>
            <td>
                <div class="input-group input-group-sm line-inner-wrap">
                    <input class="form-control text-end line-qty-inner" type="number" step="0.000001" min="0"
                           name="lines[${idx}][qty_inner]" value="${line.qty_inner ?? ''}" placeholder="0">
                    <span class="input-group-text line-inner-uom">—</span>
                </div>
                <div class="small text-secondary line-inner-empty d-none">Inner UOM nahi</div>
            </td>
            <td><button type="button" class="btn btn-sm btn-outline-danger removeLine">×</button></td>
        `;
        const search = tr.querySelector('.line-product-search');
        const hidden = tr.querySelector('.line-product-id');
        const bookCell = tr.querySelector('.line-book');
        const baseUom = tr.querySelector('.line-base-uom');
        const innerUom = tr.querySelector('.line-inner-uom');
        const innerWrap = tr.querySelector('.line-inner-wrap');
        const innerEmpty = tr.querySelector('.line-inner-empty');
        const innerInput = tr.querySelector('.line-qty-inner');
        const initialProduct = productById(line.product_id);
        if (initialProduct) {
            search.value = productLabel(initialProduct);
        }

        function refreshMeta(product) {
            bookCell.textContent = bookLabel(product ? product.id : '');
            baseUom.textContent = product?.uom || '—';
            const innerOk = hasInner(product);
            innerWrap.classList.toggle('d-none', !innerOk);
            innerEmpty.classList.toggle('d-none', innerOk);
            if (innerOk) {
                innerUom.textContent = product.pkg_uom;
                innerInput.disabled = false;
            } else {
                innerInput.value = '';
                innerInput.disabled = true;
            }
        }

        function setProduct(product) {
            if (!product) {
                hidden.value = '';
                search.classList.toggle('is-invalid', Boolean(String(search.value || '').trim()));
                refreshMeta(null);
                return;
            }
            hidden.value = String(product.id);
            search.value = productLabel(product);
            search.classList.remove('is-invalid');
            refreshMeta(product);
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
                refreshMeta(null);
            }
            fillDatalist(search.value, hidden);
        });
        search.addEventListener('blur', resolveTyped);
        search.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            resolveTyped();
        });
        tr.querySelector('.removeLine').addEventListener('click', () => {
            tr.remove();
            reindex();
        });

        body.appendChild(tr);
        setProduct(initialProduct);
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
        initialLines.forEach(l => addLine({
            product_id: l.product_id,
            qty_base: l.qty_base ?? l.qty ?? '',
            qty_inner: l.qty_inner ?? '',
        }));
    }
</script>
