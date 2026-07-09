@extends('layouts.admin')

@section('title', 'Units & conversions — Inventory — ' . config('app.name'))
@section('page_title', 'Inventory / Units')

@section('content')
@include('inventory.partials.subnav')

<div class="mb-3">
    <h4 class="fw-bold mb-1">Units &amp; conversions</h4>
    <p class="text-secondary small mb-0">Yahan jo <strong>unit codes</strong> banaoge wahi <strong>products</strong> par base / alternate UOM ke liye choose ho sakte hain — alag spellings (KG vs kg) nahi. <strong>Conversion rules</strong> (e.g. g → kg = ×0.001) se product form par quick-add bhi chalta hai. POS, purchase, stock moves, BoM har jagah product ki wahi units dikhengi jo yahan aur product par set hon.</p>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Units (codes)</div>
            <div class="card-body">
                <form method="POST" action="{{ route('inventory.uom-library.units.store') }}" class="row g-2 align-items-end mb-4">
                    @csrf
                    <div class="col-6">
                        <label class="form-label small">Code</label>
                        <input type="text" name="code" class="form-control form-control-sm" maxlength="30" placeholder="e.g. kg, g, pkt" required value="{{ old('code') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" maxlength="120" placeholder="Display name" required value="{{ old('name') }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Add unit</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Code</th><th>Name</th><th></th></tr></thead>
                        <tbody>
                        @forelse($units as $u)
                            <tr>
                                <td><code>{{ $u->code }}</code></td>
                                <td>{{ $u->name }}</td>
                                <td class="text-end">
                                    @if(($u->conversions_from_count ?? 0) === 0 && ($u->conversions_to_count ?? 0) === 0)
                                        <form method="POST" action="{{ route('inventory.uom-library.units.destroy', $u) }}" class="d-inline" onsubmit="return confirm('Delete this unit?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0">Delete</button>
                                        </form>
                                    @else
                                        <span class="text-secondary small">In use</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-secondary small">No units yet. Add kg, g, ltr… or run <code>php artisan db:seed --class=UomLibrarySeeder</code></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Conversion rules</div>
            <div class="card-body">
                <p class="small text-secondary mb-3"><strong>Factor</strong> = kitne <em>to</em> units banenge <strong>1</strong> <em>from</em> unit se. Example: 1 g = 0.001 kg → from <code>g</code>, to <code>kg</code>, factor <code>0.001</code>. Product base <code>kg</code> par “factor to base” bhi yahi hoga.</p>

                <form method="POST" action="{{ route('inventory.uom-library.conversions.store') }}" class="row g-2 align-items-end mb-4">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label small">From</label>
                        <select name="from_unit_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}">{{ $u->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">To</label>
                        <select name="to_unit_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}">{{ $u->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Factor</label>
                        <input type="text" name="factor" class="form-control form-control-sm" inputmode="decimal" placeholder="0.001" required value="{{ old('factor') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Note</label>
                        <input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="optional" value="{{ old('note') }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Add rule</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>From</th><th>To</th><th>Factor</th><th>Meaning</th><th></th></tr>
                        </thead>
                        <tbody>
                        @forelse($conversions as $c)
                            <tr>
                                <td><code>{{ $c->fromUnit->code }}</code></td>
                                <td><code>{{ $c->toUnit->code }}</code></td>
                                <td>{{ fmt_num($c->factor, 8) }}</td>
                                <td class="small text-secondary">{{ $c->explainFactor() }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('inventory.uom-library.conversions.destroy', $c) }}" class="d-inline" onsubmit="return confirm('Delete this rule?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-secondary small">No rules. Example: g → kg with factor 0.001</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
