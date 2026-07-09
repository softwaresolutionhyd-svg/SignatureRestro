@extends('layouts.admin')

@section('title', 'Designations - Employees - ' . config('app.name'))
@section('page_title', 'Employees / Designations')

@section('content')
    @include('hr.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Designations</div>
            <a href="{{ route('employees.designations.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> New Designation
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($designations as $d)
                    <tr>
                        <td class="fw-semibold">{{ $d->name }}</td>
                        <td>
                            @if($d->active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.designations.edit', $d) }}">Edit</a>
                            <form class="d-inline" method="POST" action="{{ route('employees.designations.destroy', $d) }}"
                                  onsubmit="return confirm('Delete designation?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-4">No designations yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $designations->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

