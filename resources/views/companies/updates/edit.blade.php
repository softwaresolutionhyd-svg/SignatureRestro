@extends('layouts.admin')

@section('title', 'Edit company update — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <a href="{{ route('platform.updates.index') }}" class="text-decoration-none small">&larr; Back to updates</a>
    <h4 class="fw-bold mt-2 mb-0">Edit — {{ $company->name }}</h4>
</div>

<div class="card shadow-sm" style="max-width: 720px;">
    <div class="card-body">
        <form method="POST" action="{{ route('platform.updates.update', $update) }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label" for="title">Title</label>
                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title', $update->title) }}" required maxlength="200">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="version">Version (optional)</label>
                <input type="text" name="version" id="version" class="form-control @error('version') is-invalid @enderror"
                       value="{{ old('version', $update->version) }}" maxlength="50">
                @error('version')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="feature_key">Installable feature (optional)</label>
                <select name="feature_key" id="feature_key" class="form-select @error('feature_key') is-invalid @enderror">
                    <option value="">— Sirf text / notes —</option>
                    @foreach (\App\Support\CompanyFeatures::packageKeys() as $key)
                        <option value="{{ $key }}" @selected(old('feature_key', $update->feature_key) === $key)>{{ \App\Support\CompanyFeatures::label($key) }} ({{ $key }})</option>
                    @endforeach
                </select>
                @error('feature_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="body">Details</label>
                <textarea name="body" id="body" rows="10" class="form-control @error('body') is-invalid @enderror" required>{{ old('body', $update->body) }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="published_at">Publish date</label>
                <input type="datetime-local" name="published_at" id="published_at" class="form-control @error('published_at') is-invalid @enderror"
                       value="{{ old('published_at', $update->published_at?->format('Y-m-d\TH:i')) }}">
                @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="publish_now" id="publish_now" value="1">
                <label class="form-check-label" for="publish_now">Abhi publish / dubara live karein</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="clear_publish" id="clear_publish" value="1">
                <label class="form-check-label" for="clear_publish">Draft bana dein (users ko na dikhe)</label>
            </div>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
    </div>
</div>
@endsection
