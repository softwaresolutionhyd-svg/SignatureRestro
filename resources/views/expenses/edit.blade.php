@extends('layouts.admin')
@section('title', 'Edit Expense — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between">
    <div>
        <h4 class="fw-bold mb-0">Edit Expense</h4>
        <div class="text-secondary small">Modify expense details</div>
    </div>
    <a href="{{ route('expenses.show', $expense) }}" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

<form action="{{ route('expenses.update', $expense) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('expenses._form', ['expense' => $expense])
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">Update</button>
        <a href="{{ route('expenses.show', $expense) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
