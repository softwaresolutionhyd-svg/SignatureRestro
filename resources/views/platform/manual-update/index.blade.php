@extends('layouts.admin')

@section('title', 'Manual update — ' . config('app.name'))

@section('content')
    <div class="mx-auto" style="max-width: 720px;">
        <h4 class="fw-bold mb-2">Manual system update</h4>
        <p class="text-secondary small mb-4">
            Sirf <strong>super admin</strong>. Apna build <strong>.zip</strong> se upload karein — package se sirf yeh paths live par copy hote hain:
            <code>app</code>, <code>resources</code>, <code>routes</code>, <code>database</code>, <code>config</code>, <code>bootstrap</code>, <code>public</code>,
            aur root par <code>composer.json</code> / <code>composer.lock</code> / <code>artisan</code>.
            <code>.env</code>, <code>vendor</code>, <code>storage</code>, <code>public/storage</code> apply <strong>nahi</strong> hote.
        </p>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first('package') }}</div>
        @endif

        @if (session('status_lines'))
            <div class="alert alert-success">
                <div class="fw-semibold mb-1">Update complete</div>
                <ul class="mb-0 small">
                    @foreach (session('status_lines') as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('platform.manual-update.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Update ZIP</label>
                        <input type="file" name="package" accept=".zip,application/zip" class="form-control" required>
                        <div class="form-text">Max ~100 MB (server <code>upload_max_filesize</code> bhi check karein).</div>
                    </div>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Install update? Live files overwrite ho sakti hain (allowed folders ke andar).');">
                        Upload &amp; install
                    </button>
                </form>
            </div>
        </div>

        <p class="text-secondary small mt-3 mb-0">
            ZIP banate waqt project root se folder select karke zip karein (andar <code>app/</code> dikhe). Pehle backup lein.
        </p>
    </div>
@endsection
