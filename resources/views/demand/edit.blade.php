@extends('layouts.admin')

@section('title', 'Edit Demand — ' . config('app.name'))
@section('page_title', 'Edit Demand')

@section('content')
@include('partials.super-admin-reason-modal')

<div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Edit Demand</h4>
        <div class="text-secondary small">{{ $demand->demand_no }} · Super Admin only</div>
    </div>
    <a href="{{ route('purchases-demands.index', ['tab' => 'demands']) }}" class="btn btn-outline-secondary btn-sm">← Purchases &amp; Demands</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">{{ $demand->demand_no }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('demand.update', $demand) }}" data-sa-require-reason="1">
            @csrf
            @method('PUT')
            @if(!empty($sessionReason))
                <input type="hidden" name="admin_reason" value="{{ $sessionReason }}">
            @endif

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select name="department_id" class="form-select" required>
                        @foreach($departments as $dep)
                            <option value="{{ $dep->id }}" @selected((string) old('department_id', $demand->department_id) === (string) $dep->id)>
                                {{ $dep->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Note</label>
                    <input type="text" name="note" class="form-control" maxlength="255"
                           value="{{ old('note', $demand->note) }}">
                </div>
            </div>

            <div class="table-responsive border rounded mb-3">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th style="width:120px;">Qty</th>
                        <th style="width:100px;">UOM</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($demand->lines as $i => $line)
                        <tr>
                            <td>
                                <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $line->id }}">
                                <input type="hidden" name="lines[{{ $i }}][product_id]" value="{{ $line->product_id }}">
                                <div class="fw-semibold">{{ $line->product->name ?? '—' }}</div>
                                <div class="small text-secondary">{{ $line->product->sku ?? '' }}</div>
                            </td>
                            <td>
                                <input type="number" step="0.001" min="0.001" required
                                       name="lines[{{ $i }}][qty_uom]" class="form-control form-control-sm"
                                       value="{{ old('lines.'.$i.'.qty_uom', fmt_num((float) $line->qty_uom, 3)) }}">
                            </td>
                            <td>
                                <input type="text" name="lines[{{ $i }}][uom]" class="form-control form-control-sm" required
                                       value="{{ old('lines.'.$i.'.uom', $line->uom) }}">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-primary">Save demand</button>
        </form>
    </div>
</div>
@endsection
