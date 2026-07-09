@extends('layouts.admin')
@section('title', 'Add Room')
@section('content')
@include('guest-rooms.partials.subnav')
<div class="card border-0 shadow-sm" style="max-width:560px">
    <div class="card-body">
        <form method="POST" action="{{ route('guest-rooms.rooms.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Room number *</label>
                <input name="room_number" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="room_category_id" class="form-select">
                    <option value="">—</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Floor</label>
                <input name="floor" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    @foreach(\App\Models\GuestRoom::statusLabels() as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="active" value="1" class="form-check-input" checked> Active
            </div>
            <button class="btn btn-primary">Save</button>
            <a href="{{ route('guest-rooms.rooms.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
