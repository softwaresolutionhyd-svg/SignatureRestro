@extends('layouts.admin')
@section('title', 'Edit Journal Entry — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Edit {{ $entry->entry_number }}</h4>
</div>

@include('accounts.partials.subnav')

<form method="POST" action="{{ route('accounts.journal-entries.update', $entry) }}">
    @csrf @method('PUT')
    @include('accounts.journal-entries._form', ['entry' => $entry])
    <div class="d-flex gap-2">
        <button class="btn btn-primary">Update Draft</button>
        <a href="{{ route('accounts.journal-entries.show', $entry) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
