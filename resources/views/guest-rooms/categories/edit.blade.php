@extends('layouts.admin')
@section('title', 'Edit Room Category')
@section('content')
@include('guest-rooms.partials.subnav')
<div class="card border-0 shadow-sm" style="max-width:560px"><div class="card-body">
<h5 class="fw-bold mb-3">Edit: {{ $category->name }}</h5>
<form method="POST" action="{{ route('guest-rooms.categories.update', $category) }}">@csrf @method('PUT')
<div class="mb-3"><label class="form-label">Name *</label><input name="name" class="form-control" value="{{ old('name', $category->name) }}" required></div>
<div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2">{{ old('description', $category->description) }}</textarea></div>
<div class="mb-3"><label class="form-label">Sort order</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $category->sort_order) }}" min="0"></div>
<div class="form-check mb-3"><input type="checkbox" name="active" value="1" class="form-check-input" @checked(old('active', $category->active)) id="active"><label class="form-check-label" for="active">Active</label></div>
<button class="btn btn-primary">Update</button> <a href="{{ route('guest-rooms.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
</form></div></div>
@endsection