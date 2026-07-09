@extends('layouts.admin')

@section('title', 'New company update — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <a href="{{ route('platform.updates.index') }}" class="text-decoration-none small">&larr; Back to updates</a>
    <h4 class="fw-bold mt-2 mb-0">Naya update — {{ $company->name }}</h4>
</div>

<div class="card shadow-sm" style="max-width: 720px;">
    <div class="card-body">
        <form method="POST" action="{{ route('platform.updates.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="title">Title</label>
                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title') }}" required maxlength="200">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="version">Version (optional)</label>
                <input type="text" name="version" id="version" class="form-control @error('version') is-invalid @enderror"
                       value="{{ old('version') }}" maxlength="50" placeholder="e.g. 1.4.0">
                @error('version')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="feature_key">Installable feature (optional)</label>
                <select name="feature_key" id="feature_key" class="form-select @error('feature_key') is-invalid @enderror">
                    <option value="">— Sirf text / notes —</option>
                    @foreach (\App\Support\CompanyFeatures::packageKeys() as $key)
                        <option value="{{ $key }}" @selected(old('feature_key') === $key)>{{ \App\Support\CompanyFeatures::label($key) }} ({{ $key }})</option>
                    @endforeach
                </select>
                <div class="form-text">Publish ke baad us company ke users <strong>Updates</strong> se <strong>Install</strong> dabayein — tenant DB par predefined migrations chalengi (arbitrary upload nahi).</div>
                @error('feature_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="body">Details</label>
                <textarea name="body" id="body" rows="10" class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="published_at">Schedule publish (optional)</label>
                <input type="datetime-local" name="published_at" id="published_at" class="form-control @error('published_at') is-invalid @enderror"
                       value="{{ old('published_at') }}">
                @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="publish_now" id="publish_now" value="1" {{ old('publish_now') ? 'checked' : '' }}>
                <label class="form-check-label" for="publish_now">Abhi publish karein (ignore schedule)</label>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
@endsection
