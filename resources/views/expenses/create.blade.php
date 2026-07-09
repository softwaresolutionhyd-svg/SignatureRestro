@extends('layouts.admin')
@section('title', 'New Expense — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between">
    <div>
        <h4 class="fw-bold mb-0">New Expense</h4>
        <div class="text-secondary small">Add a new expense claim</div>
    </div>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

<form action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('expenses._form', ['expense' => null])
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">Save Draft</button>
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
