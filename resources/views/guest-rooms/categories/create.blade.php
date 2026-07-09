@extends('layouts.admin')
@section('title', 'New Room Category')
@section('content')
@include('guest-rooms.partials.subnav')
<div class="card border-0 shadow-sm" style="max-width:560px">
    <div class="card-body">
        <h5 class="fw-bold mb-3">New Room Category</h5>
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="POST" action="{{ route('guest-rooms.categories.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Name *</label>
                <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Sort order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="active" value="1" class="form-check-input" checked id="active">
                <label class="form-check-label" for="active">Active</label>
            </div>
            <button class="btn btn-primary">Save</button>
            <a href="{{ route('guest-rooms.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
