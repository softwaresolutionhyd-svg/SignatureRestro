@if ($errors->any())
<div class="alert alert-danger mb-3"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card border-0 shadow-sm" style="max-width:680px;">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $contact?->name) }}" required placeholder="e.g. Ahmed Khan">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <div class="d-flex gap-2">
                    <select name="category" class="form-select @error('category') is-invalid @enderror">
                        <option value="">— Select —</option>
                        @foreach(($categoryOptions ?? \App\Models\Contact::categoryOptions()) as $value => $label)
                            <option value="{{ $value }}" @selected(old('category', $contact?->category) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-text">Nayi category <a href="{{ route('contacts.index') }}#contact-categories">Contacts list</a> se add karein.</div>
                @error('category')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                    value="{{ old('phone', $contact?->phone) }}" placeholder="+92 300 0000000">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email', $contact?->email) }}" placeholder="email@example.com">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="{{ old('address', $contact?->address) }}"
                    placeholder="Street, area…">
            </div>
            <div class="col-md-4">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="{{ old('city', $contact?->city) }}"
                    placeholder="Karachi, Lahore…">
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"
                    placeholder="Any additional info…">{{ old('notes', $contact?->notes) }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="active" id="activeToggle" value="1"
                        {{ old('active', $contact?->active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="activeToggle">Active</label>
                </div>
            </div>
        </div>
    </div>
</div>
