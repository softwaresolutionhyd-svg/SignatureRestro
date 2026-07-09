@php
    $manualRateSplit = config('app.manual_rate_split') ?? ['electric' => 300, 'gas' => 400, 'media' => 100];
@endphp
<script>
(function () {
    const lookupUrl = @json(route('guest-rooms.rates.lookup'));
    const bookingTypeEl = document.querySelector('select[name="booking_type"]');
    const catEl = document.getElementById('room_category_id');
    const catValueEl = document.getElementById('room_category_id_value');
    const catIsSelect = catEl && catEl.tagName === 'SELECT';

    function syncCategoryHiddenValue() {
        if (catValueEl && catEl) {
            catValueEl.value = catEl.value || '';
        }
    }
    const catDisplayEl = document.getElementById('room_category_display');
    const catMsgEl = document.getElementById('room-category-msg');
    const personEl = document.getElementById('person_type');
    const roomEl = document.getElementById('guest_room_ids') || document.getElementById('guest_room_id');
    const msgEl = document.getElementById('rate-lookup-msg');
    const titleEl = document.getElementById('rates-panel-title');
    const blocksEl = document.getElementById('rates-blocks-container');
    const combinedEl = document.getElementById('rates-combined-total');
    const roomRateIdEl = document.getElementById('room_rate_id');
    const oldCategoryRates = @json(old('category_rates', []));
    const guestCategoryEl = document.getElementById('guest_category');
    const complimentaryCategories = @json([\App\Models\RoomBooking::GUEST_CATEGORY_A, \App\Models\RoomBooking::GUEST_CATEGORY_B]);
    const manualSplit = @json($manualRateSplit);
    const UTIL = {
        electric: Number(manualSplit.electric) || 300,
        gas: Number(manualSplit.gas) || 400,
        media: Number(manualSplit.media) || 100,
    };
    UTIL.total = UTIL.electric + UTIL.gas + UTIL.media;

    function isComplimentaryGuestCategory() {
        const cat = guestCategoryEl?.value || '';
        return complimentaryCategories.includes(cat);
    }

    function applyGuestCategoryToAmount(baseAmount) {
        return isComplimentaryGuestCategory() ? 0 : (parseFloat(baseAmount) || 0);
    }

    function syncManualAdvancePaid() {
        const paidEl = document.getElementById('paid_amount');
        if (!paidEl || isOnline()) {
            return;
        }
        if (isComplimentaryGuestCategory()) {
            paidEl.value = '0.00';
            paidEl.readOnly = true;
            paidEl.classList.add('bg-light');
        } else {
            paidEl.readOnly = false;
            paidEl.classList.remove('bg-light');
        }
    }

    function isOnline() {
        return (bookingTypeEl?.value || 'manual') === 'online';
    }

    function toggleGuestCategory() {
        const wrap = document.getElementById('guest-category-wrap');
        const policyEl = document.getElementById('guest-category-policy');
        const manual = !isOnline();
        if (wrap) {
            wrap.classList.toggle('d-none', !manual);
        }
        if (guestCategoryEl) {
            guestCategoryEl.required = manual;
            guestCategoryEl.disabled = !manual;
            if (!manual) {
                guestCategoryEl.value = '';
                if (policyEl) {
                    policyEl.textContent = '';
                }
            }
        }
    }

    function fmt(n) {
        return Number(n).toFixed(2);
    }

    const chargeFields = ['room_rent', 'electric_charges', 'gas_charges', 'media_charges'];

    function fieldEl(catId, field) {
        return document.querySelector('[name="category_rates[' + catId + '][' + field + ']"]');
    }

    function splitPerNightGross(perNight) {
        const t = parseFloat(perNight) || 0;
        return {
            room_rent: Math.max(0, Math.round((t - UTIL.total) * 100) / 100),
            electric_charges: UTIL.electric,
            gas_charges: UTIL.gas,
            media_charges: UTIL.media,
        };
    }

    function tariffPerNight(data) {
        if (!data) return 0;
        const total = parseFloat(data.total);
        if (!isNaN(total) && total > 0) return total;
        return (parseFloat(data.room_rent) || 0)
            + (parseFloat(data.electric_charges) || 0)
            + (parseFloat(data.gas_charges) || 0)
            + (parseFloat(data.media_charges) || 0);
    }

    function applyQuickSplit(catId, perNight) {
        const split = splitPerNightGross(perNight);
        chargeFields.forEach(function (field) {
            const el = fieldEl(catId, field);
            if (el) {
                el.dataset.baseAmount = split[field];
            }
        });
        syncBlockChargeFields(catId);
        recalcBlockTotal(catId, false);
    }

    function quickInputEl(catId) {
        return document.querySelector('.rate-per-night-quick[data-cat="' + catId + '"]');
    }

    function getSelectedCategories() {
        const catId = catValueEl?.value || catEl?.value || '';
        if (catIsSelect && catId) {
            const opt = catEl?.selectedOptions?.[0];
            const name = (opt?.textContent || '').trim() || ('Category ' + catId);
            return [{ id: catId, name: name }];
        }
        if (!roomEl) return [];
        const map = new Map();
        [...roomEl.selectedOptions].forEach(function (o) {
            const id = o.dataset.category || '';
            const name = o.dataset.categoryName || ('Category ' + id);
            if (id && !map.has(id)) {
                map.set(id, name);
            }
        });
        return [...map.entries()].map(function ([id, name]) {
            return { id: id, name: name };
        });
    }

    function blockTotal(catId) {
        const rent = parseFloat(document.querySelector('[name="category_rates[' + catId + '][room_rent]"]')?.value) || 0;
        const electric = parseFloat(document.querySelector('[name="category_rates[' + catId + '][electric_charges]"]')?.value) || 0;
        const gas = parseFloat(document.querySelector('[name="category_rates[' + catId + '][gas_charges]"]')?.value) || 0;
        const media = parseFloat(document.querySelector('[name="category_rates[' + catId + '][media_charges]"]')?.value) || 0;
        return rent + electric + gas + media;
    }

    function syncQuickInputFromBlock(catId) {
        const quickEl = quickInputEl(catId);
        if (!quickEl || isOnline()) return;
        quickEl.value = fmt(blockTotal(catId));
    }

    function recalcBlockTotal(catId, syncQuick) {
        if (syncQuick === undefined) syncQuick = true;
        const totalEl = document.getElementById('rate-total-' + catId);
        const total = blockTotal(catId);
        if (totalEl) totalEl.value = fmt(total);
        if (syncQuick) syncQuickInputFromBlock(catId);
        return total;
    }

    function recalcAllBlockTotals() {
        getSelectedCategories().forEach(function (c) {
            recalcBlockTotal(c.id);
        });
    }

    function perNightTotalAllCategories() {
        let total = 0;
        getSelectedCategories().forEach(function (c) {
            total += recalcBlockTotal(c.id);
        });
        return total;
    }

    function parseDmyDate(str) {
        if (!str || typeof str !== 'string') {
            return null;
        }
        const parts = str.trim().split('-');
        if (parts.length !== 3) {
            return null;
        }
        const day = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const year = parseInt(parts[2], 10);
        if (isNaN(day) || isNaN(month) || isNaN(year)) {
            return null;
        }
        const dt = new Date(year, month, day);
        if (dt.getFullYear() !== year || dt.getMonth() !== month || dt.getDate() !== day) {
            return null;
        }
        dt.setHours(0, 0, 0, 0);
        return dt;
    }

    function stayNights() {
        const checkIn = parseDmyDate(document.getElementById('check_in_date')?.value || '');
        const checkOut = parseDmyDate(document.getElementById('check_out_date')?.value || '');
        if (!checkIn || !checkOut) {
            return 1;
        }
        const diffMs = checkOut.getTime() - checkIn.getTime();
        if (diffMs <= 0) {
            return 1;
        }
        return Math.max(1, Math.round(diffMs / 86400000));
    }

    function updateOnlineAdvancePaid() {
        const paidEl = document.getElementById('paid_amount');
        const roomsEl = document.getElementById('rooms_count');
        const hintEl = document.getElementById('paid-amount-online-hint');
        if (!paidEl) {
            return;
        }
        if (!isOnline()) {
            paidEl.readOnly = false;
            paidEl.classList.remove('bg-light');
            hintEl?.classList.add('d-none');
            return;
        }
        paidEl.readOnly = true;
        paidEl.classList.add('bg-light');
        hintEl?.classList.remove('d-none');
        const rooms = Math.max(1, parseInt(roomsEl?.value || '1', 10) || 1);
        const perNight = perNightTotalAllCategories();
        const nights = stayNights();
        if (perNight > 0) {
            paidEl.value = (rooms * perNight * nights).toFixed(2);
        }
        if (hintEl) {
            hintEl.textContent = 'Auto: ' + rooms + ' room(s) × ' + fmt(perNight) + ' / night × ' + nights + ' night(s)';
        }
    }

    function bindStayDateListeners() {
        ['check_in_date', 'check_out_date'].forEach(function (id) {
            const el = document.getElementById(id);
            if (!el || el.dataset.advanceBound) {
                return;
            }
            el.dataset.advanceBound = '1';
            el.addEventListener('change', updateOnlineAdvancePaid);
            el.addEventListener('blur', updateOnlineAdvancePaid);
            el.addEventListener('input', updateOnlineAdvancePaid);
            if (el._flatpickr && Array.isArray(el._flatpickr.config.onChange)) {
                el._flatpickr.config.onChange.push(function () {
                    updateOnlineAdvancePaid();
                });
            }
        });
    }

    function updateCombinedTotal() {
        const cats = getSelectedCategories();
        recalcAllBlockTotals();
        updateOnlineAdvancePaid();
        syncManualAdvancePaid();
        if (!combinedEl || cats.length <= 1) {
            combinedEl?.classList.add('d-none');
            return;
        }
        let sum = 0;
        const roomCounts = {};
        [...roomEl.selectedOptions].forEach(function (o) {
            const cid = o.dataset.category || '';
            if (cid) roomCounts[cid] = (roomCounts[cid] || 0) + 1;
        });
        cats.forEach(function (c) {
            const perNight = blockTotal(c.id);
            const rooms = roomCounts[c.id] || 0;
            sum += perNight * rooms;
        });
        combinedEl.textContent = 'Estimated total / night (all selected rooms): ' + fmt(sum);
        combinedEl.classList.remove('d-none');
    }

    function chargeFieldsHtml(catId, online, readonly) {
        const ro = readonly ? 'readonly' : (online ? 'readonly' : '');
        return '' +
            '<div class="col-md-3"><label class="form-label small mb-0">Room rent</label>' +
            '<input type="number" step="0.01" min="0" name="category_rates[' + catId + '][room_rent]" ' +
            'class="form-control form-control-sm rate-charge-field" data-cat="' + catId + '" data-field="room_rent" value="0" ' + ro + '></div>' +
            '<div class="col-md-3"><label class="form-label small mb-0">Electric</label>' +
            '<input type="number" step="0.01" min="0" name="category_rates[' + catId + '][electric_charges]" ' +
            'class="form-control form-control-sm rate-charge-field" data-cat="' + catId + '" data-field="electric_charges" value="0" ' + ro + '></div>' +
            '<div class="col-md-3"><label class="form-label small mb-0">Gas</label>' +
            '<input type="number" step="0.01" min="0" name="category_rates[' + catId + '][gas_charges]" ' +
            'class="form-control form-control-sm rate-charge-field" data-cat="' + catId + '" data-field="gas_charges" value="0" ' + ro + '></div>' +
            '<div class="col-md-3"><label class="form-label small mb-0">Media</label>' +
            '<input type="number" step="0.01" min="0" name="category_rates[' + catId + '][media_charges]" ' +
            'class="form-control form-control-sm rate-charge-field" data-cat="' + catId + '" data-field="media_charges" value="0" ' + ro + '></div>' +
            '<div class="col-md-3"><label class="form-label small mb-0 fw-semibold">Total / night</label>' +
            '<input type="number" step="0.01" id="rate-total-' + catId + '" class="form-control form-control-sm fw-bold" value="0" readonly></div>';
    }

    function renderRateBlocks(categories) {
        if (!blocksEl) return;
        const online = isOnline();
        blocksEl.innerHTML = '';

        if (categories.length === 0) {
            return;
        }

        categories.forEach(function (cat) {
            const block = document.createElement('div');
            block.className = 'rate-block border rounded p-2 mb-2 bg-white';
            block.dataset.categoryId = cat.id;
            let html = '<div class="small fw-semibold text-primary mb-2">' + cat.name + '</div><div class="row g-2">';
            if (!online) {
                html +=
                    '<div class="col-md-4"><label class="form-label small mb-0 fw-semibold">Per night rate</label>' +
                    '<input type="number" step="0.01" min="0" class="form-control form-control-sm rate-per-night-quick" ' +
                    'data-cat="' + cat.id + '" placeholder="e.g. 5400"></div>' +
                    '<div class="col-md-8 d-flex align-items-end"><p class="small text-secondary mb-2">Auto split: Electric ' + UTIL.electric +
                    ', Gas ' + UTIL.gas + ', Media ' + UTIL.media + ' — room rent = balance</p></div>';
            }
            html += chargeFieldsHtml(cat.id, online, online);
            html += '</div>';
            block.innerHTML = html;
            blocksEl.appendChild(block);
        });

    }

    function syncBlockChargeFields(catId) {
        const online = isOnline();

        chargeFields.forEach(function (field) {
            const el = fieldEl(catId, field);
            if (!el) return;
            const base = applyGuestCategoryToAmount(el.dataset.baseAmount ?? el.dataset.baseRent ?? el.value ?? 0);
            el.value = fmt(base);
            el.readOnly = online || isComplimentaryGuestCategory();
            el.title = isComplimentaryGuestCategory() ? 'Category A/B — no charges' : '';
        });
    }

    function setBlockCharges(catId, data) {
        const bases = {
            room_rent: data?.room_rent ?? 0,
            electric_charges: data?.electric_charges ?? 0,
            gas_charges: data?.gas_charges ?? 0,
            media_charges: data?.media_charges ?? 0,
        };
        chargeFields.forEach(function (field) {
            const el = fieldEl(catId, field);
            if (el) {
                el.dataset.baseAmount = bases[field];
            }
        });
        syncBlockChargeFields(catId);
        recalcBlockTotal(catId);
        const perNight = tariffPerNight(data);
        const quickEl = quickInputEl(catId);
        if (quickEl && perNight > 0) {
            quickEl.value = fmt(perNight);
        }
    }

    async function fetchRateForCategory(catId) {
        const personType = personEl?.value;
        if (!catId || !personType) return null;
        const url = lookupUrl + '?room_category_id=' + encodeURIComponent(catId) + '&person_type=' + encodeURIComponent(personType);
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        return res.json();
    }

    async function fetchAllRates() {
        const categories = getSelectedCategories();
        const personType = personEl?.value;

        if (categories.length === 0) {
            if (msgEl) {
                msgEl.textContent = catIsSelect ? 'Select room category to load rates.' : 'Select room(s) to load rates.';
            }
            if (catIsSelect && catEl) catEl.value = '';
            if (roomRateIdEl) roomRateIdEl.value = '';
            renderRateBlocks([]);
            combinedEl?.classList.add('d-none');
            return;
        }

        if (!personType) {
            renderRateBlocks(categories);
            categories.forEach(function (cat) {
                setBlockCharges(cat.id, null);
            });
            if (msgEl) msgEl.textContent = 'Select guest type to load rates.';
            return;
        }

        if (msgEl) {
            msgEl.textContent = 'Loading rates…';
            msgEl.className = 'small text-secondary mb-0 mt-2';
        }

        renderRateBlocks(categories);

        if (catIsSelect && catEl) {
            catEl.value = categories[0].id;
            syncCategoryHiddenValue();
        } else if (catEl && !catIsSelect) {
            catEl.value = categories[0].id;
        }

        let allFound = true;
        let firstRateId = '';

        try {
            for (const cat of categories) {
                const json = await fetchRateForCategory(cat.id);
                if (json.found) {
                    setBlockCharges(cat.id, json);
                    if (!firstRateId) firstRateId = json.id || '';
                } else {
                    allFound = false;
                    setBlockCharges(cat.id, null);
                }
            }

            if (roomRateIdEl) roomRateIdEl.value = isOnline() ? firstRateId : '';

            updateCombinedTotal();
            updateOnlineAdvancePaid();

            if (msgEl) {
                if (allFound) {
                    msgEl.textContent = categories.length > 1
                        ? 'Rates loaded for ' + categories.length + ' room categories.'
                        : 'Rates loaded for ' + personType + '.';
                    msgEl.className = 'small text-success mb-0 mt-2';
                } else {
                    msgEl.textContent = 'Some categories have no rate for this guest type.';
                    msgEl.className = 'small text-warning mb-0 mt-2';
                }
            }
        } catch (e) {
            if (msgEl) {
                msgEl.textContent = 'Could not load rate.';
                msgEl.className = 'small text-danger mb-0 mt-2';
            }
        }
    }

    function syncCategoryFromRooms() {
        const categories = getSelectedCategories();
        const catNames = categories.map(function (c) { return c.name; });

        if (catDisplayEl) {
            catDisplayEl.value = catNames.length ? catNames.join(' · ') : '';
            catDisplayEl.placeholder = catNames.length ? '' : 'Select room(s) to auto-fill';
        }

        if (catMsgEl) {
            catMsgEl.classList.add('d-none');
        }

        fetchAllRates();
    }

    function onCategoryChange() {
        syncCategoryHiddenValue();
        fetchAllRates();
    }

    function toggleRoomTypeForBookingType() {
        if (!catIsSelect || !catEl) {
            return;
        }
        const online = isOnline();
        const onlineId = catEl.dataset.onlineCategoryId || '';
        const onlineName = catEl.dataset.onlineCategoryName || 'Barian Hut';
        const onlineDisplay = document.getElementById('room-type-online-display');
        const onlineLabel = document.getElementById('room_type_online_label');
        if (online) {
            if (onlineId) {
                catEl.value = onlineId;
                if (catValueEl) {
                    catValueEl.value = onlineId;
                }
            }
            catEl.classList.add('d-none');
            catEl.disabled = true;
            if (onlineDisplay) {
                onlineDisplay.classList.remove('d-none');
            }
            if (onlineLabel) {
                onlineLabel.value = onlineName;
            }
            syncCategoryHiddenValue();
        } else {
            catEl.classList.remove('d-none');
            catEl.disabled = false;
            if (onlineDisplay) {
                onlineDisplay.classList.add('d-none');
            }
            if (catEl.value === onlineId && onlineId) {
                catEl.value = '';
            }
            syncCategoryHiddenValue();
        }
    }

    function toggleOnlineRoomsLimit() {
        const roomsEl = document.getElementById('rooms_count');
        const onlineMax = 6;
        if (!roomsEl) {
            return;
        }
        if (isOnline()) {
            roomsEl.max = String(onlineMax);
            if (parseInt(roomsEl.value || '1', 10) > onlineMax) {
                roomsEl.value = String(onlineMax);
            }
        } else {
            roomsEl.max = '20';
        }
    }

    function toggleVoucherField() {
        const wrap = document.getElementById('voucher-no-wrap');
        const input = document.getElementById('voucher_no');
        const online = isOnline();
        if (wrap) {
            wrap.classList.toggle('d-none', !online);
        }
        if (input) {
            input.required = online;
            if (!online) {
                input.value = '';
            }
        }
    }

    function applyRateMode() {
        toggleGuestCategory();
        toggleRoomTypeForBookingType();
        toggleOnlineRoomsLimit();
        toggleVoucherField();
        if (titleEl) {
            titleEl.textContent = isOnline()
                ? 'Rates (auto from Categories & Rates)'
                : 'Rates — per night total or breakdown';
        }
        if (catIsSelect) {
            onCategoryChange();
        } else {
            syncCategoryFromRooms();
        }
        updateOnlineAdvancePaid();
    }

    guestCategoryEl?.addEventListener('change', function () {
        getSelectedCategories().forEach(function (c) {
            const quickEl = quickInputEl(c.id);
            if (quickEl && isComplimentaryGuestCategory()) {
                quickEl.value = '0.00';
            }
            syncBlockChargeFields(c.id);
            recalcBlockTotal(c.id);
        });
        updateCombinedTotal();
    });

    bookingTypeEl?.addEventListener('change', applyRateMode);
    document.getElementById('rooms_count')?.addEventListener('input', updateOnlineAdvancePaid);
    personEl?.addEventListener('change', fetchAllRates);
    if (catIsSelect) {
        catEl?.addEventListener('change', onCategoryChange);
        syncCategoryHiddenValue();
    }
    roomEl?.addEventListener('change', syncCategoryFromRooms);
    function onPerNightQuickCommit(el) {
        const catId = el?.dataset?.cat;
        if (!catId || !el?.classList?.contains('rate-per-night-quick')) return;
        const raw = String(el.value || '').trim();
        if (raw === '') {
            chargeFields.forEach(function (field) {
                const f = fieldEl(catId, field);
                if (f) f.dataset.baseAmount = '0';
            });
            syncBlockChargeFields(catId);
            recalcBlockTotal(catId, false);
        } else {
            applyQuickSplit(catId, raw);
        }
        updateCombinedTotal();
    }

    blocksEl?.addEventListener('change', function (e) {
        onPerNightQuickCommit(e.target);
    });
    blocksEl?.addEventListener('focusout', function (e) {
        onPerNightQuickCommit(e.target);
    });

    blocksEl?.addEventListener('input', function (e) {
        const el = e.target;
        const catId = el?.dataset?.cat;
        if (el?.classList?.contains('rate-per-night-quick')) {
            return;
        }
        if (!catId || !el?.classList?.contains('rate-charge-field')) return;
        if (!isOnline() && el.dataset.field) {
            el.dataset.baseAmount = el.value;
        }
        recalcBlockTotal(catId, true);
        updateCombinedTotal();
        updateOnlineAdvancePaid();
    });

    applyRateMode();
    syncManualAdvancePaid();
    bindStayDateListeners();
    setTimeout(bindStayDateListeners, 250);

    const bookingForm = document.getElementById('booking-form');
    bookingForm?.addEventListener('submit', function () {
        syncCategoryHiddenValue();
        if (isOnline()) {
            const onlineId = catEl?.dataset?.onlineCategoryId || '';
            if (onlineId && catValueEl) {
                catValueEl.value = onlineId;
            }
        } else if (catValueEl && catEl?.value) {
            catValueEl.value = catEl.value;
        }
    });
})();
</script>
