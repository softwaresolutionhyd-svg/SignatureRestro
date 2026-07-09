@csrf

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" value="{{ old('name', $category->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required maxlength="150">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label">Parent category</label>
        <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
            <option value="">— Main category —</option>
            @foreach($parents as $p)
                <option value="{{ $p->id }}" @selected((string)old('parent_id', $category->parent_id ?? '') === (string)$p->id)>
                    {{ $p->name }}
                </option>
            @endforeach
        </select>
        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Save</button>
    <a href="{{ route('inventory.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

