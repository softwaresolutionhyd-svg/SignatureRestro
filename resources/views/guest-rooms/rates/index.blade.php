@extends('layouts.admin')
@section('title', 'Categories & Rates')
@section('content')
@include('guest-rooms.partials.subnav')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Categories & Rates</h4>
        <div class="text-secondary small">Pehle guest types aur categories banao, phir rates set karo</div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-folder-plus me-1"></i> New Category</div>
            <div class="card-body">
                <form method="POST" action="{{ route('guest-rooms.rates.categories.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Name *</label>
                        <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="e.g. Deluxe, Standard">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Description</label>
                        <input name="description" class="form-control" value="{{ old('description') }}" placeholder="Optional">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Sort order</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="active" value="1" class="form-check-input" checked id="catActive">
                        <label class="form-check-label" for="catActive">Active</label>
                    </div>
                    <button class="btn btn-primary btn-sm w-100">Add Category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">All Categories</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Name</th>
                            <th>Description</th>
                            <th class="text-center">Rates</th>
                            <th class="text-center">Rooms</th>
                            <th class="text-center">Active</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $cat->name }}</td>
                            <td class="small text-secondary">{{ $cat->description ?? '—' }}</td>
                            <td class="text-center">{{ $cat->rates->count() }}</td>
                            <td class="text-center">{{ $cat->guest_rooms_count }}</td>
                            <td class="text-center">
                                @if($cat->active)<span class="badge bg-success">Yes</span>@else<span class="badge bg-secondary">No</span>@endif
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-cat-{{ $cat->id }}">Edit</button>
                                @if($cat->rates->isEmpty() && $cat->guest_rooms_count === 0)
                                <form method="POST" action="{{ route('guest-rooms.rates.categories.destroy', $cat) }}" class="d-inline" onsubmit="return confirm('Delete category?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Del</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        <tr class="collapse" id="edit-cat-{{ $cat->id }}">
                            <td colspan="6" class="bg-light">
                                <form method="POST" action="{{ route('guest-rooms.rates.categories.update', $cat) }}" class="row g-2 p-2">
                                    @csrf @method('PUT')
                                    <div class="col-md-3">
                                        <input name="name" class="form-control form-control-sm" value="{{ $cat->name }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input name="description" class="form-control form-control-sm" value="{{ $cat->description }}">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $cat->sort_order }}" min="0">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-center">
                                        <div class="form-check">
                                            <input type="checkbox" name="active" value="1" class="form-check-input" @checked($cat->active)>
                                            <label class="form-check-label small">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-primary w-100">Save</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center py-4 text-secondary">No categories yet. Add one on the left.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-plus me-1"></i> New Guest Type</div>
            <div class="card-body">
                <form method="POST" action="{{ route('guest-rooms.rates.person-types.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Name *</label>
                        <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="e.g. Single, Double, Family">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Sort order</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="active" value="1" class="form-check-input" checked id="ptActive">
                        <label class="form-check-label" for="ptActive">Active</label>
                    </div>
                    <button class="btn btn-primary btn-sm w-100">Add Guest Type</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">All Guest Types</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Name</th>
                            <th class="text-center">Sort</th>
                            <th class="text-center">Active</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($personTypes as $pt)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $pt->name }}</td>
                            <td class="text-center">{{ $pt->sort_order }}</td>
                            <td class="text-center">
                                @if($pt->active)<span class="badge bg-success">Yes</span>@else<span class="badge bg-secondary">No</span>@endif
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-pt-{{ $pt->id }}">Edit</button>
                                @if(!$pt->isInUse())
                                <form method="POST" action="{{ route('guest-rooms.rates.person-types.destroy', $pt) }}" class="d-inline" onsubmit="return confirm('Delete guest type?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Del</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        <tr class="collapse" id="edit-pt-{{ $pt->id }}">
                            <td colspan="4" class="bg-light">
                                <form method="POST" action="{{ route('guest-rooms.rates.person-types.update', $pt) }}" class="row g-2 p-2">
                                    @csrf @method('PUT')
                                    <div class="col-md-4">
                                        <input name="name" class="form-control form-control-sm" value="{{ $pt->name }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $pt->sort_order }}" min="0">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-center">
                                        <div class="form-check">
                                            <input type="checkbox" name="active" value="1" class="form-check-input" @checked($pt->active)>
                                            <label class="form-check-label small">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-primary w-100">Save</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No guest types yet. Add Single, Double, etc. on the left.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<h5 class="fw-semibold mb-3">Rates by Category</h5>

@forelse($categories as $category)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <span class="fw-semibold">{{ $category->name }}</span>
            @if($category->description)<span class="text-secondary small ms-2">{{ $category->description }}</span>@endif
            <span class="badge bg-light text-dark ms-2">{{ $category->guest_rooms_count }} rooms</span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="border rounded p-3 bg-light">
                    <div class="small fw-semibold text-secondary mb-2">Add rate for {{ $category->name }}</div>
                    <form method="POST" action="{{ route('guest-rooms.rates.store') }}">
                        @csrf
                        <input type="hidden" name="room_category_id" value="{{ $category->id }}">
                        <div class="mb-2">
                            <label class="form-label small mb-0">Guest type *</label>
                            <select name="person_type" class="form-select form-select-sm" required @disabled($personTypes->where('active', true)->isEmpty())>
                                <option value="">@if($personTypes->where('active', true)->isEmpty())Pehle guest type add karein @else Select @endif</option>
                                @foreach($personTypes->where('active', true) as $pt)
                                    <option value="{{ $pt->name }}">{{ $pt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Room rent *</label>
                            <input type="number" step="0.01" name="room_rent" class="form-control form-control-sm charge-in" value="0" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Electric charges</label>
                            <input type="number" step="0.01" name="electric_charges" class="form-control form-control-sm charge-in" value="0">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Gas charges</label>
                            <input type="number" step="0.01" name="gas_charges" class="form-control form-control-sm charge-in" value="0">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Media charges</label>
                            <input type="number" step="0.01" name="media_charges" class="form-control form-control-sm charge-in" value="0">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Total (per night)</label>
                            <input type="text" class="form-control form-control-sm total-out bg-white" readonly value="0.00">
                        </div>
                        <button class="btn btn-primary btn-sm w-100">Add Rate</button>
                    </form>
                </div>
            </div>
            <div class="col-md-8">
                @if($category->rates->isEmpty())
                    <p class="text-secondary small mb-0">Is category ke liye abhi koi rate nahi.</p>
                @else
                <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Guest type</th>
                            <th>Room rent</th>
                            <th>Electric</th>
                            <th>Gas</th>
                            <th>Media</th>
                            <th>Total</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($category->rates as $rate)
                        <tr>
                            <td class="fw-semibold">{{ $rate->person_type ?? $rate->name }}</td>
                            <td>{{ number_format($rate->room_rent, 2) }}</td>
                            <td>{{ number_format($rate->electric_charges, 2) }}</td>
                            <td>{{ number_format($rate->gas_charges, 2) }}</td>
                            <td>{{ number_format($rate->media_charges, 2) }}</td>
                            <td class="fw-bold">{{ number_format($rate->total ?: $rate->amount, 2) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('guest-rooms.rates.destroy', $rate) }}" class="d-inline" onsubmit="return confirm('Delete this rate?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger py-0">Del</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@empty
<div class="alert alert-info">Pehle upar se category add karein, phir rates set kar sakte hain.</div>
@endforelse
@endsection
@section('scripts')
<script>
document.querySelectorAll('.charge-in').forEach(function (input) {
    input.addEventListener('input', function () {
        const form = input.closest('form');
        if (!form) return;
        const sum = ['room_rent', 'electric_charges', 'gas_charges', 'media_charges'].reduce(function (t, name) {
            const el = form.querySelector('[name="' + name + '"]');
            return t + (parseFloat(el?.value) || 0);
        }, 0);
        const out = form.querySelector('.total-out');
        if (out) out.value = sum.toFixed(2);
    });
});
</script>
@endsection
