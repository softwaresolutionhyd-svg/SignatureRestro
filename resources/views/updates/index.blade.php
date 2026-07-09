@extends('layouts.admin')

@section('title', 'Updates — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <h4 class="fw-bold mb-1">Updates</h4>
    <p class="text-secondary small mb-0">
        Yahan sirf <strong>is workspace</strong> ke liye likhi gayi release notes dikhte hain — doosri companies ko nahi.
        Agar update ke sath <strong>feature</strong> choose kiya gaya ho to company admin <strong>Install feature</strong> se us company ke database par woh module lagha sakta hai (predefined migrations — koi random file upload nahi).
    </p>
</div>

@if ($errors->has('install'))
    <div class="alert alert-danger">{{ $errors->first('install') }}</div>
@endif

@if ($updates->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-secondary py-5 text-center">
            Abhi koi published update nahi. Jab platform par aap ke liye naya update add ho ga, yahan nazar aaye ga.
        </div>
    </div>
@else
    <div class="d-flex flex-column gap-3">
        @foreach ($updates as $u)
            <article class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                        <h5 class="card-title mb-0">{{ $u->title }}</h5>
                        <div class="text-secondary small text-nowrap">
                            @if ($u->version)
                                <span class="badge bg-secondary me-1">{{ $u->version }}</span>
                            @endif
                            {{ $u->published_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                        </div>
                    </div>
                    <div class="card-text text-body-secondary mb-3" style="white-space: pre-wrap;">{{ $u->body }}</div>

                    @if ($u->feature_key)
                        <div class="border-top pt-3 d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-dark">{{ \App\Support\CompanyFeatures::label($u->feature_key) }}</span>
                            @if (in_array($u->feature_key, $installedFeatureKeys, true))
                                <span class="badge text-bg-success">Installed</span>
                            @elseif (! $u->published_at)
                                <span class="text-muted small">Publish ke baad install available.</span>
                            @elseif (auth()->user()->isPlatformSuperAdmin() || auth()->user()->isCompanyAdmin() || (auth()->user()->role ?? '') === 'admin')
                                <form method="POST" action="{{ route('updates.install', $u) }}" class="d-inline"
                                      onsubmit="return confirm('Is company ke tenant DB par yeh feature install karein?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="bi bi-download me-1"></i> Install feature
                                    </button>
                                </form>
                            @else
                                <span class="text-muted small">Feature install ke liye company admin se rabta karein.</span>
                            @endif
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
    <div class="mt-3">
        {{ $updates->links() }}
    </div>
@endif
@endsection
