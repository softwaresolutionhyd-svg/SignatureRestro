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

    <div class="row g-3">
        @foreach($categories as $category)
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
                        <div class="fw-semibold">{{ $category->name }}</div>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal{{ $category->id }}">
                            <i class="bi bi-person-plus me-1"></i> Add Employees
                        </button>
                    </div>
                    <div class="card-body p-0">
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
                                @forelse($category->employees as $employee)
                                    <tr>
                                        <td class="fw-semibold">{{ $employee->employee_no }}</td>
                                        <td>{{ $employee->name }}</td>
                                        <td class="small text-secondary">{{ $employee->designation?->name ?? '—' }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('employees.staff-categories.remove-employee', [$category, $employee]) }}" class="d-inline" onsubmit="return confirm('Remove from {{ $category->name }}?');">
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
                                        <td colspan="4" class="text-center text-secondary py-3 small">Koi employee assign nahi.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white small text-secondary">
                        {{ $category->employees->count() }} employee(s)
                    </div>
                </div>
            </div>

            <div class="modal fade" id="assignModal{{ $category->id }}" tabindex="-1" aria-labelledby="assignModalLabel{{ $category->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('employees.staff-categories.assign', $category) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="assignModalLabel{{ $category->id }}">Add Employees — {{ $category->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small text-secondary mb-2">Jo employees select karein woh is category mein aa jayenge. Pehle se is category mein selected dikhenge.</p>
                                <input type="text" class="form-control form-control-sm mb-2 emp-filter" placeholder="Search employee…" data-target="emp-list-{{ $category->id }}">
                                <div class="border rounded p-2 emp-list" id="emp-list-{{ $category->id }}" style="max-height: 360px; overflow-y: auto;">
                                    @foreach($allEmployees as $employee)
                                        <div class="form-check py-1 emp-row">
                                            <input class="form-check-input" type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" id="emp-{{ $category->id }}-{{ $employee->id }}"
                                                @checked((int)$employee->staff_category_id === (int)$category->id)>
                                            <label class="form-check-label w-100" for="emp-{{ $category->id }}-{{ $employee->id }}">
                                                <span class="fw-semibold">{{ $employee->employee_no }}</span>
                                                — {{ $employee->name }}
                                                <span class="text-secondary small">· {{ $employee->designation?->name ?? '—' }}</span>
                                                @if($employee->staffCategory && (int)$employee->staff_category_id !== (int)$category->id)
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
