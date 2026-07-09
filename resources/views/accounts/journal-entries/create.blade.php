@extends('layouts.admin')
@section('title', 'New Journal Entry — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">New Journal Entry</h4>
        <div class="text-secondary small">Next number: <strong>{{ $entryNumber }}</strong></div>
    </div>
</div>

@include('accounts.partials.subnav')

<form method="POST" action="{{ route('accounts.journal-entries.store') }}">
    @csrf
    @include('accounts.journal-entries._form')
    <div class="d-flex gap-2">
        <button class="btn btn-primary">Save Draft</button>
        <a href="{{ route('accounts.journal-entries.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
