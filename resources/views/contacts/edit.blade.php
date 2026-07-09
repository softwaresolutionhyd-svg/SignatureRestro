@extends('layouts.admin')
@section('title', 'Edit Contact — ' . $contact->name)
@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between">
    <h4 class="fw-bold mb-0">Edit: {{ $contact->name }}</h4>
    <a href="{{ route('contacts.show', $contact) }}" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>
<form action="{{ route('contacts.update', $contact) }}" method="POST">
    @csrf @method('PUT')
    @include('contacts._form', ['contact' => $contact, 'categoryOptions' => $categoryOptions])
    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">Update</button>
        <a href="{{ route('contacts.show', $contact) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
