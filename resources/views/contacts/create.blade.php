@extends('layouts.admin')
@section('title', 'New Contact')
@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between">
    <h4 class="fw-bold mb-0">New Contact</h4>
    <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>
<form action="{{ route('contacts.store') }}" method="POST">
    @csrf
    @include('contacts._form', ['contact' => null, 'categoryOptions' => $categoryOptions])
    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">Save Contact</button>
        <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
