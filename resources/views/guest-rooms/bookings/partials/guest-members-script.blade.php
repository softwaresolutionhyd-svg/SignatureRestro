<script>
(function () {
    var adultsInput = document.getElementById('booking_adults');
    var childrenInput = document.getElementById('booking_children');
    var adultsWrap = document.getElementById('guest-members-adults');
    var childrenWrap = document.getElementById('guest-members-children');
    var membersSection = document.getElementById('guest-members-section');
    var membersHeading = document.getElementById('guest-members-heading');
    var selfStayingInput = document.getElementById('primary_guest_staying');
    var fieldGuestName = document.getElementById('field_guest_name');
    var fieldGuestCnic = document.getElementById('field_guest_cnic');
    var fieldGuestCnicWrap = document.getElementById('guest-field-cnic-wrap');
    var fieldGuestNameLabel = document.getElementById('field_guest_name_label');
    var initialEl = document.getElementById('guest-members-initial-data');

    if (!adultsInput || !childrenInput || !adultsWrap || !childrenWrap) {
        return;
    }

    var initial = { adults: [], children: [], primary_guest_staying: true };
    if (initialEl) {
        try {
            initial = JSON.parse(initialEl.textContent || '{}');
        } catch (e) {
            initial = { adults: [], children: [], primary_guest_staying: true };
        }
    }

    if (fieldGuestCnic && initial.adults && initial.adults[0] && initial.adults[0].cnic && !fieldGuestCnic.value) {
        fieldGuestCnic.value = initial.adults[0].cnic;
    }

    function selfStaying() {
        return !selfStayingInput || selfStayingInput.checked;
    }

    function clamp(n, min, max) {
        n = parseInt(n, 10);
        if (isNaN(n)) {
            n = min;
        }
        return Math.max(min, Math.min(max, n));
    }

    function esc(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function readPrimaryRelation() {
        var row = adultsWrap.querySelector('[data-member-primary]');
        if (!row) {
            return 'Self';
        }
        var rel = row.querySelector('input[name="members[adults][0][relation]"]');
        return rel ? rel.value : 'Self';
    }

    function readAllAdultRows() {
        var rows = [];
        adultsWrap.querySelectorAll('[data-member-row]').forEach(function (row) {
            rows.push({
                name: (row.querySelector('input[name$="[name]"]') || {}).value || '',
                cnic: (row.querySelector('input[name$="[cnic]"]') || {}).value || '',
                relation: (row.querySelector('input[name$="[relation]"]') || {}).value || '',
            });
        });
        return rows;
    }

    function readChildren() {
        var rows = [];
        childrenWrap.querySelectorAll('[data-member-row]').forEach(function (row) {
            rows.push({
                name: (row.querySelector('input[name$="[name]"]') || {}).value || '',
                relation: (row.querySelector('input[name$="[relation]"]') || {}).value || '',
            });
        });
        return rows;
    }

    function syncPrimaryFromTop() {
        if (!selfStaying()) {
            return;
        }
        var hiddenName = adultsWrap.querySelector('input[name="members[adults][0][name]"]');
        var hiddenCnic = adultsWrap.querySelector('input[name="members[adults][0][cnic]"]');
        var name = fieldGuestName ? fieldGuestName.value.trim() : '';
        var cnic = fieldGuestCnic ? fieldGuestCnic.value.trim() : '';
        if (hiddenName) {
            hiddenName.value = name;
        }
        if (hiddenCnic) {
            hiddenCnic.value = cnic;
        }
    }

    function updateLabels(staying) {
        if (fieldGuestNameLabel) {
            fieldGuestNameLabel.textContent = staying ? 'Name *' : 'Contact / Booked by *';
        }
        if (fieldGuestName) {
            fieldGuestName.placeholder = staying ? 'Full name' : 'Who called / booked';
        }
        if (fieldGuestCnicWrap) {
            fieldGuestCnicWrap.classList.toggle('d-none', !staying);
        }
        if (membersHeading) {
            membersHeading.textContent = staying ? 'Other members' : 'Staying guests';
        }
    }

    function render() {
        var staying = selfStaying();
        var adultCount = clamp(adultsInput.value, 1, 20);
        var childCount = clamp(childrenInput.value, 0, 20);
        adultsInput.value = adultCount;
        childrenInput.value = childCount;

        updateLabels(staying);

        var prevRelation = readPrimaryRelation();
        var prevAllRows = readAllAdultRows();
        var prevChildren = readChildren();

        if (prevAllRows.length === 0 && initial.adults && initial.adults.length) {
            if (staying) {
                prevRelation = (initial.adults[0] || {}).relation || 'Self';
                prevAllRows = initial.adults.slice(1);
            } else {
                prevAllRows = initial.adults.slice();
            }
            initial.adults = [];
        }
        if (prevChildren.length === 0 && initial.children && initial.children.length) {
            prevChildren = initial.children.slice();
            initial.children = [];
        }

        var topName = fieldGuestName ? fieldGuestName.value.trim() : '';
        var topCnic = fieldGuestCnic ? fieldGuestCnic.value.trim() : '';
        var adultHtml = '';

        if (staying) {
            var primaryName = topName || '';
            var primaryRelation = prevRelation || 'Self';

            adultHtml += '<div class="row g-2 align-items-end mb-2" data-member-primary>'
                + '<input type="hidden" name="members[adults][0][name]" value="' + esc(primaryName) + '">'
                + '<input type="hidden" name="members[adults][0][cnic]" value="' + esc(topCnic) + '">'
                + '<div class="col-md-4"><label class="form-label small mb-0">Relation</label>'
                + '<input type="text" name="members[adults][0][relation]" class="form-control form-control-sm" value="' + esc(primaryRelation) + '" placeholder="e.g. Self"></div>'
                + '</div>';

            if (adultCount > 1) {
                adultHtml += '<div class="small fw-semibold text-secondary mb-1 mt-2">Other adults</div>';
                for (var i = 1; i < adultCount; i++) {
                    var row = prevAllRows[i - 1] || { name: '', cnic: '', relation: '' };
                    adultHtml += adultRowHtml(i, row);
                }
            }
        } else {
            if (adultCount > 0) {
                adultHtml += '<div class="small fw-semibold text-secondary mb-1">Adults staying</div>';
            }
            for (var j = 0; j < adultCount; j++) {
                var guestRow = prevAllRows[j] || { name: '', cnic: '', relation: '' };
                adultHtml += adultRowHtml(j, guestRow);
            }
        }

        adultsWrap.innerHTML = adultHtml;

        var childHtml = '';
        if (childCount > 0) {
            childHtml += '<div class="small fw-semibold text-secondary mb-1 mt-2">Children</div>';
        }
        for (var c = 0; c < childCount; c++) {
            var crow = prevChildren[c] || { name: '', relation: '' };
            childHtml += '<div class="row g-2 align-items-end mb-2" data-member-row>'
                + '<div class="col-auto"><span class="badge bg-info text-dark">Child ' + (c + 1) + '</span></div>'
                + '<div class="col-md-5"><label class="form-label small mb-0">Name</label>'
                + '<input type="text" name="members[children][' + c + '][name]" class="form-control form-control-sm" value="' + esc(crow.name) + '"></div>'
                + '<div class="col-md-4"><label class="form-label small mb-0">Relation</label>'
                + '<input type="text" name="members[children][' + c + '][relation]" class="form-control form-control-sm" value="' + esc(crow.relation) + '" placeholder="e.g. Son, Daughter"></div>'
                + '</div>';
        }
        childrenWrap.innerHTML = childHtml;

        if (membersSection) {
            var hideMembers = staying && adultCount <= 1 && childCount === 0;
            membersSection.classList.toggle('d-none', hideMembers);
        }

        syncPrimaryFromTop();
    }

    function adultRowHtml(index, row) {
        return '<div class="row g-2 align-items-end mb-2" data-member-row>'
            + '<div class="col-auto"><span class="badge bg-secondary">Adult ' + (index + 1) + '</span></div>'
            + '<div class="col-md-4"><label class="form-label small mb-0">Name</label>'
            + '<input type="text" name="members[adults][' + index + '][name]" class="form-control form-control-sm" value="' + esc(row.name) + '"></div>'
            + '<div class="col-md-3"><label class="form-label small mb-0">CNIC</label>'
            + '<input type="text" name="members[adults][' + index + '][cnic]" class="form-control form-control-sm" value="' + esc(row.cnic) + '"></div>'
            + '<div class="col-md-3"><label class="form-label small mb-0">Relation</label>'
            + '<input type="text" name="members[adults][' + index + '][relation]" class="form-control form-control-sm" value="' + esc(row.relation) + '" placeholder="e.g. Spouse"></div>'
            + '</div>';
    }

    if (fieldGuestName) {
        fieldGuestName.addEventListener('input', syncPrimaryFromTop);
    }
    if (fieldGuestCnic) {
        fieldGuestCnic.addEventListener('input', syncPrimaryFromTop);
    }
    if (selfStayingInput) {
        selfStayingInput.addEventListener('change', render);
    }

    adultsInput.addEventListener('change', render);
    adultsInput.addEventListener('input', render);
    childrenInput.addEventListener('change', render);
    childrenInput.addEventListener('input', render);

    var form = adultsInput.closest('form');
    if (form) {
        form.addEventListener('submit', syncPrimaryFromTop);
    }

    render();
})();
</script>
