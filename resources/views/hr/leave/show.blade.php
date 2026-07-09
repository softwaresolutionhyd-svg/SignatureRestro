@extends('layouts.admin')
@section('title', 'Leave Request — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@php
    $u = auth()->user();
    $st = $statusLabels[$leaveRequest->status] ?? ['label'=>$leaveRequest->status,'color'=>'secondary'];
    $canReview = $leaveRequest->isPending() && ($u->bypassesModulePermissions() || $u->moduleAllows('hr', 'edit'));
    $canCancel = $leaveRequest->isPending() && (
        (int) $leaveRequest->user_id === (int) $u->id
        || $u->moduleAllows('hr', 'delete')
        || $u->moduleAllows('hr', 'edit')
        || $u->bypassesModulePermissions()
    );
@endphp

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Leave Request #{{ $leaveRequest->id }}</span>
                <span class="badge bg-{{ $st['color'] }}">{{ $st['label'] }}</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Employee</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->employee?->name }} ({{ $leaveRequest->employee?->employee_no }})</dd>

                    <dt class="col-sm-4">Department</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->employee?->department?->name ?? '—' }}</dd>

                    <dt class="col-sm-4">Designation</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->employee?->designation?->name ?? '—' }}</dd>

                    <dt class="col-sm-4">Leave type</dt>
                    <dd class="col-sm-8">{{ $typeLabels[$leaveRequest->leave_type] ?? $leaveRequest->leave_type }}</dd>

                    <dt class="col-sm-4">Dates</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->start_date->format('d M Y') }} – {{ $leaveRequest->end_date->format('d M Y') }}</dd>

                    <dt class="col-sm-4">Working days</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->days }}</dd>

                    <dt class="col-sm-4">Reason</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->reason ?: '—' }}</dd>

                    <dt class="col-sm-4">Submitted by</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->submittedBy?->name ?? '—' }} · {{ $leaveRequest->created_at?->format('d M Y H:i') }}</dd>

                    @if($leaveRequest->reviewed_at)
                    <dt class="col-sm-4">Reviewed by</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->reviewer?->name ?? '—' }} · {{ $leaveRequest->reviewed_at->format('d M Y H:i') }}</dd>

                    <dt class="col-sm-4">Review notes</dt>
                    <dd class="col-sm-8">{{ $leaveRequest->review_notes ?: '—' }}</dd>
                    @endif
                </dl>
            </div>
            @if($canCancel)
            <div class="card-footer bg-white">
                <form method="POST" action="{{ route('hr.leave.destroy', $leaveRequest) }}" onsubmit="return confirm('Cancel this leave request?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Request</button>
                </form>
            </div>
            @endif
        </div>
    </div>

    @if($canReview)
    <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold text-success">Approve</div>
            <div class="card-body">
                <form method="POST" action="{{ route('hr.leave.approve', $leaveRequest) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small">Notes (optional)</label>
                        <textarea name="review_notes" rows="2" class="form-control form-control-sm">{{ old('review_notes') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm">Approve Leave</button>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold text-danger">Reject</div>
            <div class="card-body">
                <form method="POST" action="{{ route('hr.leave.reject', $leaveRequest) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small">Reason for rejection <span class="text-danger">*</span></label>
                        <textarea name="review_notes" rows="2" class="form-control form-control-sm @error('review_notes') is-invalid @enderror" required>{{ old('review_notes') }}</textarea>
                        @error('review_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">Reject Leave</button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
