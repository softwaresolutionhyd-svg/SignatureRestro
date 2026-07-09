@extends('layouts.admin')
@section('title', 'Expense #' . $expense->id . ' — ' . config('app.name'))

@section('content')
@php
    $s = $statusMap[$expense->status] ?? ['label' => $expense->status, 'color' => 'secondary'];
    $isAdmin = auth()->user()?->role === 'admin';
@endphp

{{-- Alerts --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('error') }}</div>
@endif

{{-- Header bar --}}
<div class="mb-4 d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold mb-0">Expense #{{ $expense->id }}</h4>
            <span class="badge bg-{{ $s['color'] }} bg-opacity-15 text-{{ $s['color'] }} border border-{{ $s['color'] }} border-opacity-25 px-3 py-2 fs-6">
                {{ $s['label'] }}
            </span>
        </div>
        <div class="text-secondary small">{{ $expense->description }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>

        {{-- Submit --}}
        @if($expense->status === 'draft')
        <form method="POST" action="{{ route('expenses.submit', $expense) }}" class="d-inline">
            @csrf
            <button class="btn btn-info btn-sm text-white" onclick="return confirm('Submit this expense for approval?')">
                Submit for Approval
            </button>
        </form>
        @endif

        {{-- Approve (admin) --}}
        @if($isAdmin && $expense->status === 'submitted')
        <form method="POST" action="{{ route('expenses.approve', $expense) }}" class="d-inline">
            @csrf
            <button class="btn btn-primary btn-sm">Approve</button>
        </form>
        @endif

        {{-- Mark Paid (admin) --}}
        @if($isAdmin && $expense->status === 'approved')
        <form method="POST" action="{{ route('expenses.markPaid', $expense) }}" class="d-inline">
            @csrf
            <button class="btn btn-success btn-sm">Mark as Paid</button>
        </form>
        @endif

        {{-- Refuse (admin) --}}
        @if($isAdmin && in_array($expense->status, ['submitted', 'approved']))
        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#refuseModal">Refuse</button>
        @endif

        {{-- Edit --}}
        @if(in_array($expense->status, ['draft', 'refused']))
        <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        @endif

        {{-- Delete --}}
        @if(in_array($expense->status, ['draft', 'refused']))
        <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="d-inline"
            onsubmit="return confirm('Delete this expense permanently?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm">Delete</button>
        </form>
        @endif
    </div>
</div>

{{-- Status timeline --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-0 flex-wrap">
            @php
                $steps = [
                    ['key'=>'draft',     'label'=>'Draft',     'color'=>'#64748b'],
                    ['key'=>'submitted', 'label'=>'Submitted', 'color'=>'#0ea5e9'],
                    ['key'=>'approved',  'label'=>'Approved',  'color'=>'#7c3aed'],
                    ['key'=>'paid',      'label'=>'Paid',      'color'=>'#22c55e'],
                ];
                $statusOrder = ['draft'=>0,'submitted'=>1,'approved'=>2,'paid'=>3,'refused'=>99];
                $currentOrder = $statusOrder[$expense->status] ?? 0;
            @endphp
            @foreach($steps as $i => $step)
                @php
                    $stepOrder = $statusOrder[$step['key']];
                    $isDone = ($expense->status === 'refused') ? false : ($currentOrder >= $stepOrder);
                    $isActive = ($expense->status !== 'refused') && ($currentOrder === $stepOrder);
                @endphp
                @if($i > 0)
                <div style="flex:1;height:2px;background:{{ $isDone ? $step['color'] : '#e2e8f0' }};min-width:20px;max-width:60px;" class="mx-1"></div>
                @endif
                <div class="d-flex flex-column align-items-center" style="min-width:60px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                        style="width:32px;height:32px;background:{{ $isDone ? $step['color'] : '#e2e8f0' }};color:{{ $isDone ? '#fff' : '#94a3b8' }};font-size:13px;
                        {{ $isActive ? 'box-shadow:0 0 0 4px '.$step['color'].'30;' : '' }}">
                        @if($isDone && !$isActive)✓@else{{ $i+1 }}@endif
                    </div>
                    <div class="small mt-1 fw-{{ $isActive ? 'bold' : 'normal' }}" style="color:{{ $isDone ? $step['color'] : '#94a3b8' }};font-size:11px;">
                        {{ $step['label'] }}
                    </div>
                </div>
            @endforeach

            @if($expense->status === 'refused')
            <div class="ms-3 d-flex flex-column align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                    style="width:32px;height:32px;background:#ef4444;color:#fff;font-size:14px;">✕</div>
                <div class="small mt-1 fw-bold" style="color:#ef4444;font-size:11px;">Refused</div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Main details --}}
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold py-3">Expense Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-secondary fw-normal">Employee</dt>
                    <dd class="col-sm-8 fw-semibold">{{ $expense->employee?->name ?? '—' }}</dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Category</dt>
                    <dd class="col-sm-8">{{ $expense->category?->name ?? '—' }}</dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Date</dt>
                    <dd class="col-sm-8">{{ $expense->expense_date?->format('d M Y') }}</dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Description</dt>
                    <dd class="col-sm-8">{{ $expense->description }}</dd>

                    @if($expense->notes)
                    <dt class="col-sm-4 text-secondary fw-normal">Notes</dt>
                    <dd class="col-sm-8" style="white-space:pre-line;">{{ $expense->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Amount breakdown --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold py-3">Amount Breakdown</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Description</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end pe-3">Tax ({{ $expense->tax_percent }}%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3">{{ $expense->description }}</td>
                            <td class="text-end">{{ fmt_num($expense->qty, 3) }}</td>
                            <td class="text-end">{{ fmt_num($expense->unit_amount, 2) }}</td>
                            <td class="text-end">{{ fmt_num($expense->total_amount, 2) }}</td>
                            <td class="text-end pe-3">{{ fmt_num($expense->tax_amount, 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-bold ps-3">Grand Total</td>
                            <td colspan="2" class="text-end pe-3 fw-bold fs-5" style="color:#14b8a6;">
                                {{ fmt_num($expense->grand_total, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if($expense->refuse_reason)
        <div class="alert alert-danger">
            <strong>Refuse Reason:</strong> {{ $expense->refuse_reason }}
        </div>
        @endif
    </div>

    {{-- Right column --}}
    <div class="col-12 col-lg-4">
        {{-- Receipt --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold py-3">Receipt</div>
            <div class="card-body text-center">
                @if($expense->receipt_path)
                    @php $ext = pathinfo($expense->receipt_path, PATHINFO_EXTENSION); @endphp
                    @if(in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp']))
                        <img src="{{ Storage::url($expense->receipt_path) }}" class="img-fluid rounded" style="max-height:250px;" alt="Receipt">
                        <div class="mt-2">
                            <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">View Full</a>
                        </div>
                    @else
                        <div class="py-4">
                            <svg width="40" height="40" fill="none" viewBox="0 0 40 40" class="text-secondary mb-2"><path d="M8 8h24v24a2 2 0 01-2 2H10a2 2 0 01-2-2V8z" stroke="currentColor" stroke-width="2"/><path d="M16 8V4h8v4" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                            <div class="small text-secondary mb-2">PDF Document</div>
                            <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">Open PDF</a>
                        </div>
                    @endif
                @else
                    <div class="py-4 text-secondary small">No receipt attached.</div>
                @endif
            </div>
        </div>

        {{-- Timeline log --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3">Activity Log</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex gap-2 py-2">
                        <span class="text-secondary mt-1">●</span>
                        <div>
                            <div class="fw-semibold">Created</div>
                            <div class="text-secondary">{{ $expense->created_at?->format('d M Y H:i') }}</div>
                        </div>
                    </li>
                    @if($expense->submitted_at)
                    <li class="list-group-item d-flex gap-2 py-2">
                        <span style="color:#0ea5e9;" class="mt-1">●</span>
                        <div>
                            <div class="fw-semibold">Submitted</div>
                            <div class="text-secondary">{{ $expense->submitted_at->format('d M Y H:i') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($expense->approved_at)
                    <li class="list-group-item d-flex gap-2 py-2">
                        <span style="color:#7c3aed;" class="mt-1">●</span>
                        <div>
                            <div class="fw-semibold">Approved by {{ $expense->approvedBy?->name ?? 'Admin' }}</div>
                            <div class="text-secondary">{{ $expense->approved_at->format('d M Y H:i') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($expense->paid_at)
                    <li class="list-group-item d-flex gap-2 py-2">
                        <span style="color:#22c55e;" class="mt-1">●</span>
                        <div>
                            <div class="fw-semibold">Paid</div>
                            <div class="text-secondary">{{ $expense->paid_at->format('d M Y H:i') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($expense->status === 'refused')
                    <li class="list-group-item d-flex gap-2 py-2">
                        <span style="color:#ef4444;" class="mt-1">●</span>
                        <div>
                            <div class="fw-semibold text-danger">Refused</div>
                            <div class="text-secondary">{{ $expense->updated_at?->format('d M Y H:i') }}</div>
                        </div>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Refuse Modal --}}
@if($isAdmin && in_array($expense->status, ['submitted','approved']))
<div class="modal fade" id="refuseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Refuse Expense #{{ $expense->id }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('expenses.refuse', $expense) }}">
                @csrf
                <div class="modal-body">
                    <label class="form-label">Reason for refusal <span class="text-danger">*</span></label>
                    <textarea name="refuse_reason" class="form-control" rows="3" required
                        placeholder="Explain why this expense is being refused…"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Refuse</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
