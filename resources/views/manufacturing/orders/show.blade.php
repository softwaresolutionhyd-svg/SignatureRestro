@extends('layouts.admin')

@section('title', ($order->reference ?? 'Order') . ' — Manufacturing — ' . config('app.name'))

@section('content')
<div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4 class="fw-bold mb-0">{{ $order->reference }}</h4>
        <div class="text-secondary small">{{ $order->bom->name }} · {{ $order->bom->finishedProduct->name }}</div>
    </div>
    <div class="d-flex gap-2">
        @if($order->isDraft())
            <form method="POST" action="{{ route('manufacturing.orders.complete', $order) }}" onsubmit="return confirm('Complete production? Components will be removed from stock and finished goods added.');">
                @csrf
                <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Complete &amp; update stock</button>
            </form>
            <form method="POST" action="{{ route('manufacturing.orders.destroy', $order) }}" onsubmit="return confirm('Delete this draft order?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">Delete draft</button>
            </form>
        @endif
        <a href="{{ route('manufacturing.orders.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>

@include('manufacturing.partials.subnav')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Summary</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Status</span>
                    @if($order->status === \App\Models\ManufacturingOrder::STATUS_DONE)
                        <span class="badge bg-success">Done</span>
                    @elseif($order->isDraft())
                        <span class="badge bg-warning text-dark">Draft</span>
                    @else
                        <span class="badge bg-secondary">{{ $order->status }}</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Qty ordered</span><span class="fw-semibold">{{ fmt_num((float) $order->qty_ordered, 3) }} {{ $order->bom->finishedProduct->uom }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-secondary">BoM batch</span><span>{{ fmt_num((float) $order->bom->batch_qty, 3) }} {{ $order->bom->finishedProduct->uom }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Multiplier</span><span>{{ fmt_num((float) $order->qty_ordered / (float) $order->bom->batch_qty, 6) }}×</span></div>
                @if($order->completed_at)
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Completed</span><span>{{ $order->completed_at->format('d M Y H:i') }}</span></div>
                @endif
                <div class="d-flex justify-content-between"><span class="text-secondary">Created by</span><span>{{ $order->user->name ?? '—' }}</span></div>
                @if($order->note)
                    <hr>
                    <div class="text-secondary">Note</div>
                    <div>{{ $order->note }}</div>
                @endif
                <hr>
                <div class="text-secondary mb-1">Inventory reference</div>
                <code>MFG-ORD-{{ $order->id }}</code>
                <div class="form-text mt-1">Shown on stock moves in Inventory → Stock moves.</div>
                @if($order->status === \App\Models\ManufacturingOrder::STATUS_DONE)
                    <div class="form-text mt-2">Finished goods are received at <strong>FIFO absorbed cost</strong>: sum of each component’s OUT move total cost ÷ quantity produced.</div>
                @else
                    <div class="form-text mt-2">On completion, each component is deducted in its <strong>stock UOM</strong> (e.g. kg), even if the BoM line is entered in g, pkt, etc.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Planned consumption</div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Component</th>
                        <th>Required</th>
                        <th>On hand</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php $mult = (float) $order->qty_ordered / (float) $order->bom->batch_qty; @endphp
                    @foreach($order->bom->lines as $line)
                        @php
                            $needBase = $line->qtyInBasePerBatch() * $mult;
                            $needLineUom = (float) $line->qty * $mult;
                            $effUom = $line->effectiveUom();
                        @endphp
                        <tr>
                            <td>
                                <div class="small text-secondary">{{ $line->component->sku }}</div>
                                {{ $line->component->name }}
                            </td>
                            <td>
                                <span class="fw-semibold">{{ fmt_num($needLineUom, 3) }} {{ $effUom }}</span>
                                <div class="small text-secondary">= {{ fmt_num($needBase, 4) }} {{ $line->component->uom }} stock</div>
                            </td>
                            <td class="{{ $order->isDraft() && (float) $line->component->qty_on_hand + 1e-9 < $needBase ? 'text-danger fw-semibold' : '' }}">
                                {{ fmt_num((float) $line->component->qty_on_hand, 3) }} {{ $line->component->uom }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
