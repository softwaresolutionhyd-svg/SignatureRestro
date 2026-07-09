@extends('layouts.admin')

@section('title', 'Company updates — ' . config('app.name'))

@section('content')
@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Updates — {{ $company->name }}</h4>
        <p class="text-secondary small mb-0">Sirf is company ke users ko <code>/updates</code> par yeh entries dikhen gi.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
        <a href="{{ route('platform.updates.create') }}" class="btn btn-primary btn-sm">Naya update</a>
    </div>
</div>

<div class="table-responsive card shadow-sm">
    <table class="table table-hover mb-0">
        <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Feature</th>
                    <th>Version</th>
                    <th>Published</th>
                    <th class="text-end">Actions</th>
                </tr>
        </thead>
        <tbody>
            @forelse ($updates as $u)
                <tr>
                    <td class="fw-medium">{{ $u->title }}</td>
                    <td>
                        @if($u->feature_key)
                            <code>{{ $u->feature_key }}</code>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>@if($u->version)<code>{{ $u->version }}</code>@else<span class="text-muted">—</span>@endif</td>
                    <td>
                        @if ($u->published_at)
                            <span class="badge text-bg-success">Live</span>
                            <span class="text-secondary small">{{ $u->published_at->format('Y-m-d H:i') }}</span>
                        @else
                            <span class="badge text-bg-secondary">Draft</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('platform.updates.edit', $u) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form action="{{ route('platform.updates.destroy', $u) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this update?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Koi entry nahi — &quot;Naya update&quot; se module / custom changes likhain.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">
    {{ $updates->links() }}
</div>
@endsection
