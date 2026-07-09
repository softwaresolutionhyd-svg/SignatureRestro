@extends('layouts.admin')

@section('title', 'Users & roles — ' . config('app.name'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="fw-semibold">Users & roles</div>
            <form class="d-flex gap-2" method="GET" action="{{ route('admin.users.index') }}">
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Search name or email..." style="min-width: 220px;">
                <button class="btn btn-sm btn-outline-primary" type="submit">Search</button>
                @if($q !== '')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users.index') }}">Clear</a>
                @endif
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td class="fw-semibold">{{ $u->name }}</td>
                        <td class="text-secondary">{{ $u->email }}</td>
                        <td><span class="badge text-bg-{{ in_array($u->role, ['company_admin', 'admin'], true) ? 'danger' : 'secondary' }}">{{ $u->role === 'admin' ? 'company_admin' : $u->role }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $u) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-4">No users.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $users->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
