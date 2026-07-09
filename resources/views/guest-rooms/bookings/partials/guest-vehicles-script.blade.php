<script>
(function () {
    var vehiclesInput = document.getElementById('booking_vehicles');
    var vehiclesWrap = document.getElementById('guest-vehicles-rows');
    var initialEl = document.getElementById('guest-vehicles-initial-data');

    if (!vehiclesInput || !vehiclesWrap) {
        return;
    }

    var initial = { vehicles: [] };
    if (initialEl) {
        try {
            initial = JSON.parse(initialEl.textContent || '{}');
        } catch (e) {
            initial = { vehicles: [] };
        }
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

    function readRows() {
        var rows = [];
        vehiclesWrap.querySelectorAll('[data-vehicle-row]').forEach(function (row) {
            var driverWith = row.querySelector('input[name$="[driver_with]"]');
            rows.push({
                vehicle_no: (row.querySelector('input[name$="[vehicle_no]"]') || {}).value || '',
                driver_with: driverWith ? driverWith.checked : false,
                driver_name: (row.querySelector('input[name$="[driver_name]"]') || {}).value || '',
                driver_cnic: (row.querySelector('input[name$="[driver_cnic]"]') || {}).value || '',
                driver_phone: (row.querySelector('input[name$="[driver_phone]"]') || {}).value || '',
            });
        });
        return rows;
    }

    function render() {
        var count = clamp(vehiclesInput.value, 0, 10);
        vehiclesInput.value = count;

        var prevRows = readRows();
        if (prevRows.length === 0 && initial.vehicles && initial.vehicles.length) {
            prevRows = initial.vehicles.slice();
            initial.vehicles = [];
        }

        var html = '';
        if (count > 0) {
            for (var i = 0; i < count; i++) {
                var row = prevRows[i] || {
                    vehicle_no: '',
                    driver_with: false,
                    driver_name: '',
                    driver_cnic: '',
                    driver_phone: '',
                };
                var driverOpen = row.driver_with ? '' : ' d-none';
                html += '<div class="border rounded p-2 mb-2 bg-white" data-vehicle-row>'
                    + '<div class="row g-2 align-items-end">'
                    + '<div class="col-auto"><span class="badge bg-dark">Vehicle ' + (i + 1) + '</span></div>'
                    + '<div class="col-md-3"><label class="form-label small mb-0">Vehicle No</label>'
                    + '<input type="text" name="vehicles[' + i + '][vehicle_no]" class="form-control form-control-sm" value="' + esc(row.vehicle_no) + '" placeholder="e.g. ABC-123"></div>'
                    + '<div class="col-md-3"><div class="form-check mt-4">'
                    + '<input class="form-check-input js-driver-with" type="checkbox" name="vehicles[' + i + '][driver_with]" value="1" id="vehicle_driver_' + i + '"' + (row.driver_with ? ' checked' : '') + '>'
                    + '<label class="form-check-label small" for="vehicle_driver_' + i + '">Driver accompanying</label>'
                    + '</div></div>'
                    + '</div>'
                    + '<div class="row g-2 align-items-end mt-1 js-driver-fields' + driverOpen + '">'
                    + '<div class="col-md-3"><label class="form-label small mb-0">Driver name</label>'
                    + '<input type="text" name="vehicles[' + i + '][driver_name]" class="form-control form-control-sm" value="' + esc(row.driver_name) + '"></div>'
                    + '<div class="col-md-3"><label class="form-label small mb-0">Driver CNIC</label>'
                    + '<input type="text" name="vehicles[' + i + '][driver_cnic]" class="form-control form-control-sm" value="' + esc(row.driver_cnic) + '"></div>'
                    + '<div class="col-md-3"><label class="form-label small mb-0">Driver phone</label>'
                    + '<input type="text" name="vehicles[' + i + '][driver_phone]" class="form-control form-control-sm" value="' + esc(row.driver_phone) + '"></div>'
                    + '</div></div>';
            }
        }

        vehiclesWrap.innerHTML = html;

        vehiclesWrap.querySelectorAll('.js-driver-with').forEach(function (chk) {
            chk.addEventListener('change', function () {
                var row = chk.closest('[data-vehicle-row]');
                var block = row ? row.querySelector('.js-driver-fields') : null;
                if (block) {
                    block.classList.toggle('d-none', !chk.checked);
                }
            });
        });
    }

    vehiclesInput.addEventListener('change', render);
    vehiclesInput.addEventListener('input', render);

    render();
})();
</script>
