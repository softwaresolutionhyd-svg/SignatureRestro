@csrf

<div class="row g-3">
    <div class="col-12 col-md-8">
        <label class="form-label">Name</label>
        <input type="text" name="name" value="{{ old('name', $department->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required maxlength="150">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="activeSwitch" name="active" value="1"
                   @checked(old('active', ($department->active ?? true)) ? true : false)>
            <label class="form-check-label" for="activeSwitch">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Save</button>
    <a href="{{ route('employees.departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

