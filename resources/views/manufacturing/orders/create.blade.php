@extends('layouts.admin')

@section('title', 'New Production Order — Manufacturing — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <h4 class="fw-bold mb-0">New production order</h4>
</div>

@include('manufacturing.partials.subnav')

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card shadow-sm" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="{{ route('manufacturing.orders.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Bill of Materials <span class="text-danger">*</span></label>
                <select name="bom_id" class="form-select" required>
                    <option value="">— Select BoM —</option>
                    @foreach($boms as $b)
                        <option value="{{ $b->id }}" @selected((string) old('bom_id', request('bom_id')) === (string) $b->id)>
                            {{ $b->name }} → {{ $b->finishedProduct->name }} (batch {{ fmt_num((float) $b->batch_qty, 3) }} {{ $b->finishedProduct->uom }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Quantity to produce <span class="text-danger">*</span></label>
                <input type="number" name="qty_ordered" class="form-control" step="0.001" min="0.001" value="{{ old('qty_ordered', '1') }}" required>
                <div class="form-text">Finished product quantity. Component use = line qty × (this ÷ BoM batch qty).</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Note</label>
                <input type="text" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create draft</button>
                <a href="{{ route('manufacturing.orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
