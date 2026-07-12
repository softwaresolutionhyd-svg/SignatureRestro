{{-- Expects: $currentPerms (array) from old() or user permissions --}}
@php
    use App\Support\ModuleAccess;
    $permModules = ModuleAccess::matrixDefinitions();
    $permActions = ['view' => 'View', 'create' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete'];
@endphp
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0 permissions-matrix-table">
        <thead>
        <tr>
            <th>Module</th>
            <th class="text-center">All</th>
            @foreach($permActions as $aLabel)
                <th class="text-center">{{ $aLabel }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($permModules as $mKey => $mLabel)
            <tr data-perm-module="{{ $mKey }}">
                <td class="fw-semibold">{{ $mLabel }}</td>
                <td class="text-center">
                    <input type="checkbox"
                           class="perm-row-all"
                           name="permissions[{{ $mKey }}][all]"
                           value="1"
                           @checked((bool) data_get($currentPerms, $mKey.'.all', false))>
                </td>
                @foreach($permActions as $aKey => $aLabel)
                    <td class="text-center">
                        <input type="checkbox"
                               class="perm-row-cell"
                               name="permissions[{{ $mKey }}][{{ $aKey }}]"
                               value="1"
                               @checked((bool) data_get($currentPerms, $mKey.'.'.$aKey, false))>
                    </td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="small text-secondary mb-0 mt-2">
    <strong>All</strong> = poora module (view + add + edit + delete). Super admin (<code>admin</code> role) ko sab modules ki full access hoti hai.
</p>
<script>
(function () {
    function syncAllFromCells(row) {
        const allCb = row.querySelector('.perm-row-all');
        const cells = row.querySelectorAll('.perm-row-cell');
        if (!allCb || !cells.length) return;
        allCb.checked = Array.from(cells).every(function (c) { return c.checked; });
    }
    document.querySelectorAll('.permissions-matrix-table tr[data-perm-module]').forEach(function (row) {
        syncAllFromCells(row);
        const allCb = row.querySelector('.perm-row-all');
        if (allCb) {
            allCb.addEventListener('change', function () {
                row.querySelectorAll('.perm-row-cell').forEach(function (c) { c.checked = allCb.checked; });
            });
        }
        row.querySelectorAll('.perm-row-cell').forEach(function (c) {
            c.addEventListener('change', function () { syncAllFromCells(row); });
        });
    });
})();
</script>
