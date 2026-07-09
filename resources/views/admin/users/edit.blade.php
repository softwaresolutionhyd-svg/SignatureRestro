@extends('layouts.admin')

@section('title', 'Edit user — ' . config('app.name'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Edit user</div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required maxlength="150">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required maxlength="200">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror">
                            <option value="user" @selected(old('role', $user->role) === 'user')>User</option>
                            <option value="company_admin" @selected(in_array(old('role', $user->role), ['company_admin', 'admin'], true))>Company admin</option>
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">New password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" maxlength="120" autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Confirm password</label>
                        <input type="password" name="password_confirmation" class="form-control" maxlength="120" autocomplete="new-password">
                    </div>
                </div>

                @php
                    $currentPerms = old('permissions', $user->permissions ?? []);
                @endphp

                <div class="border rounded-3 p-3 bg-light mt-4" id="permMatrix" style="{{ in_array(old('role', $user->role), ['company_admin', 'admin'], true) ? 'display:none' : '' }}">
                    <div class="fw-semibold mb-2">Module access (non-admin)</div>
                    @include('partials.permissions-matrix', ['currentPerms' => $currentPerms])
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    const role = document.querySelector('select[name="role"]');
    const matrix = document.getElementById('permMatrix');
    if (!role || !matrix) return;
    function sync() {
        matrix.style.display = role.value === 'company_admin' ? 'none' : '';
    }
    role.addEventListener('change', sync);
})();
</script>
@endsection
