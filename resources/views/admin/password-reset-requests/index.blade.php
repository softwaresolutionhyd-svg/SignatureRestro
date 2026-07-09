@extends('layouts.admin')

@section('title', 'Password reset requests — ' . config('app.name'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <div class="fw-semibold">Pending password reset requests</div>
            <div class="text-secondary small">Reset par user ka password <code>Abcd1234</code> set ho jata hai.</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Requested</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($requests as $req)
                    <tr>
                        <td class="fw-semibold">{{ $req->user?->name ?? '—' }}</td>
                        <td class="text-secondary">{{ $req->user?->email ?? '—' }}</td>
                        <td class="text-secondary small">{{ $req->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.password-reset-requests.reset', $req) }}" class="d-inline"
                                  onsubmit="return confirm('User ka password Abcd1234 set kar dein?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">Reset password</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-4">Koi pending request nahi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $requests->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
