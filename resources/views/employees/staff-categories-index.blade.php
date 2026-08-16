@extends('layouts.admin')

@section('title', 'Staff Categories — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="mb-3 text-secondary small">
        Category ke andar Sub Categories banao, phir employees sub category pe assign karo. Employees report Category → Sub Category me group hogi.
    </div>

    <div class="row g-3">
        @foreach($categories as $category)
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between py-2">
                        <div class="fw-semibold">{{ $category->name }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSubModal{{ $category->id }}">
                                <i class="bi bi-plus-circle me-1"></i> Add Sub Category
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#assignCatModal{{ $category->id }}">
                                <i class="bi bi-person-plus me-1"></i> Category only (no sub)
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        @forelse($category->subCategories as $sub)
                            <div class="border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between px-3 py-2 bg-light border-bottom">
                                    <div class="fw-semibold small">
                                        <i class="bi bi-diagram-3 me-1 text-secondary"></i>{{ $sub->name }}
                                        <span class="text-secondary fw-normal">({{ $sub->employees->count() }})</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-primary py-0" data-bs-toggle="modal" data-bs-target="#assignSubModal{{ $sub->id }}">
                                            <i class="bi bi-person-plus me-1"></i> Add Employees
                                        </button>
                                        <form method="POST" action="{{ route('employees.staff-categories.sub-categories.destroy', [$category, $sub]) }}"
                                              onsubmit="return confirm('Delete sub category {{ $sub->name }}? Employees unassign ho jayenge.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0" title="Delete sub category">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 align-middle">
                                        <thead class="table-light">
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Name</th>
                                            <th>Designation</th>
                                            <th class="text-end"></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($sub->employees as $employee)
                                            <tr>
                                                <td class="fw-semibold">{{ $employee->employee_no }}</td>
                                                <td>{{ $employee->name }}</td>
                                                <td class="small text-secondary">{{ $employee->designation?->name ?? '—' }}</td>
                                                <td class="text-end">
                                                    <form method="POST" action="{{ route('employees.staff-categories.sub-categories.remove-employee', [$category, $sub, $employee]) }}" class="d-inline"
                                                          onsubmit="return confirm('Remove from {{ $sub->name }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-secondary py-2 small">Koi employee nahi.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="modal fade" id="assignSubModal{{ $sub->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('employees.staff-categories.sub-categories.assign', [$category, $sub]) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Add Employees — {{ $category->name }} / {{ $sub->name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="text" class="form-control form-control-sm mb-2 emp-filter" placeholder="Search employee…" data-target="emp-sub-list-{{ $sub->id }}">
                                                <div class="border rounded p-2 emp-list" id="emp-sub-list-{{ $sub->id }}" style="max-height: 360px; overflow-y: auto;">
                                                    @foreach($allEmployees as $employee)
                                                        <div class="form-check py-1 emp-row">
                                                            <input class="form-check-input" type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                                                   id="emp-sub-{{ $sub->id }}-{{ $employee->id }}"
                                                                   @checked((int) $employee->staff_sub_category_id === (int) $sub->id)>
                                                            <label class="form-check-label w-100" for="emp-sub-{{ $sub->id }}-{{ $employee->id }}">
                                                                <span class="fw-semibold">{{ $employee->employee_no }}</span>
                                                                — {{ $employee->name }}
                                                                <span class="text-secondary small">· {{ $employee->designation?->name ?? '—' }}</span>
                                                                @if($employee->staffSubCategory && (int) $employee->staff_sub_category_id !== (int) $sub->id)
                                                                    <span class="badge text-bg-light text-dark ms-1">{{ $employee->staffCategory?->name }} / {{ $employee->staffSubCategory->name }}</span>
                                                                @elseif($employee->staffCategory && (int) $employee->staff_category_id !== (int) $category->id)
                                                                    <span class="badge text-bg-light text-dark ms-1">{{ $employee->staffCategory->name }}</span>
                                                                @endif
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-secondary small mb-3">Abhi koi sub category nahi — pehle Add Sub Category karein.</div>
                        @endforelse

                        @if($category->employees->isNotEmpty())
                            <div class="border rounded-3 overflow-hidden">
                                <div class="px-3 py-2 bg-light border-bottom small fw-semibold text-secondary">
                                    Category only (no sub category) — {{ $category->employees->count() }}
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 align-middle">
                                        <thead class="table-light">
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Name</th>
                                            <th>Designation</th>
                                            <th class="text-end"></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($category->employees as $employee)
                                            <tr>
                                                <td class="fw-semibold">{{ $employee->employee_no }}</td>
                                                <td>{{ $employee->name }}</td>
                                                <td class="small text-secondary">{{ $employee->designation?->name ?? '—' }}</td>
                                                <td class="text-end">
                                                    <form method="POST" action="{{ route('employees.staff-categories.remove-employee', [$category, $employee]) }}" class="d-inline"
                                                          onsubmit="return confirm('Remove from {{ $category->name }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="modal fade" id="addSubModal{{ $category->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('employees.staff-categories.sub-categories.store', $category) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Add Sub Category — {{ $category->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label">Sub Category Name</label>
                                <input type="text" name="name" class="form-control" required maxlength="100" placeholder="e.g. Hot Kitchen, Grill, Waiters">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="assignCatModal{{ $category->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('employees.staff-categories.assign', $category) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Category only — {{ $category->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small text-secondary mb-2">Ye list sirf un employees ki hai jo category pe hain lekin sub category pe nahi. Prefer sub category assign karein.</p>
                                <input type="text" class="form-control form-control-sm mb-2 emp-filter" placeholder="Search employee…" data-target="emp-cat-list-{{ $category->id }}">
                                <div class="border rounded p-2 emp-list" id="emp-cat-list-{{ $category->id }}" style="max-height: 360px; overflow-y: auto;">
                                    @foreach($allEmployees as $employee)
                                        <div class="form-check py-1 emp-row">
                                            <input class="form-check-input" type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                                   id="emp-cat-{{ $category->id }}-{{ $employee->id }}"
                                                   @checked((int) $employee->staff_category_id === (int) $category->id && empty($employee->staff_sub_category_id))>
                                            <label class="form-check-label w-100" for="emp-cat-{{ $category->id }}-{{ $employee->id }}">
                                                <span class="fw-semibold">{{ $employee->employee_no }}</span>
                                                — {{ $employee->name }}
                                                <span class="text-secondary small">· {{ $employee->designation?->name ?? '—' }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('.emp-filter').forEach((input) => {
    input.addEventListener('input', () => {
        const list = document.getElementById(input.dataset.target);
        if (!list) return;
        const q = input.value.trim().toLowerCase();
        list.querySelectorAll('.emp-row').forEach((row) => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});
</script>
@endsection
