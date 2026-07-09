@csrf

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" value="{{ old('name', $vendor->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required maxlength="200">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email', $vendor->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" maxlength="200">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $vendor->phone ?? '') }}"
               class="form-control @error('phone') is-invalid @enderror" maxlength="60">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Tax ID</label>
        <input type="text" name="tax_id" value="{{ old('tax_id', $vendor->tax_id ?? '') }}"
               class="form-control @error('tax_id') is-invalid @enderror" maxlength="80">
        @error('tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="activeSwitch" name="active" value="1"
                   @checked(old('active', ($vendor->active ?? true)) ? true : false)>
            <label class="form-check-label" for="activeSwitch">Active</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Address</label>
        <textarea name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $vendor->address ?? '') }}</textarea>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Save</button>
    <a href="{{ route('purchase.vendors.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

