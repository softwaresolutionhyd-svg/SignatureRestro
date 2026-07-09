@extends('layouts.admin')
@section('title', 'New Category — Expenses')

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between">
    <h4 class="fw-bold mb-0">New Expense Category</h4>
    <a href="{{ route('expenses.categories.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

@if ($errors->any())
<div class="alert alert-danger mb-3"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card border-0 shadow-sm" style="max-width:560px;">
    <div class="card-body">
        <form action="{{ route('expenses.categories.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                    placeholder="e.g. Travel, Meals, Office Supplies" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description') }}"
                    placeholder="Optional short description">
            </div>
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="active" id="activeToggle" value="1"
                        {{ old('active', '1') ? 'checked' : '' }}>
                    <label class="form-check-label" for="activeToggle">Active</label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Create Category</button>
                <a href="{{ route('expenses.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
