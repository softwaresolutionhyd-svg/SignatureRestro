@csrf

<div class="row g-3">
    <div class="col-12 col-lg-9">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label">Employee No</label>
                <input type="text" name="employee_no" value="{{ old('employee_no', $employee->employee_no ?? '') }}"
                       class="form-control @error('employee_no') is-invalid @enderror" maxlength="40"
                       placeholder="Auto-generated if left blank">
                @error('employee_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-8">
                <label class="form-label">Name</label>
                <input type="text" name="name" value="{{ old('name', $employee->name ?? '') }}"
                       class="form-control @error('name') is-invalid @enderror" required maxlength="150">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">Contact email <span class="text-secondary fw-normal">(optional)</span></label>
                <input type="email" name="email" value="{{ old('email', $employee->email ?? '') }}"
                       class="form-control @error('email') is-invalid @enderror" maxlength="200"
                       placeholder="Personal / work email">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">Mobile / WhatsApp</label>
                <input type="text" name="phone" value="{{ old('phone', $employee->phone ?? '') }}"
                       class="form-control @error('phone') is-invalid @enderror" maxlength="60"
                       placeholder="03xx xxxxxxx">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label mb-0">Designation</label>
                    <a class="small text-decoration-none" href="{{ route('employees.designations.index') }}" target="_blank">Manage</a>
                </div>
                <select name="designation_id" class="form-select @error('designation_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach($designations as $d)
                        <option value="{{ $d->id }}" @selected((string)old('designation_id', $employee->designation_id ?? '') === (string)$d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
                @error('designation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label mb-0">Staff Category</label>
                    <a class="small text-decoration-none" href="{{ route('employees.staff-categories.index') }}" target="_blank">Manage</a>
                </div>
                <select name="staff_category_id" class="form-select @error('staff_category_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach($staffCategories ?? [] as $cat)
                        <option value="{{ $cat->id }}" @selected((string)old('staff_category_id', $employee->staff_category_id ?? '') === (string)$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('staff_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label">Join Date</label>
                <input type="date" name="join_date" value="{{ old('join_date', optional($employee->join_date ?? null)->format('Y-m-d')) }}"
                       class="form-control @error('join_date') is-invalid @enderror">
                @error('join_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label">Salary</label>
                <input type="number" step="0.01" min="0" name="salary" value="{{ old('salary', $employee->salary ?? 0) }}"
                       class="form-control @error('salary') is-invalid @enderror">
                @error('salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-8 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="activeSwitch" name="active" value="1"
                           @checked(old('active', ($employee->active ?? true)) ? true : false)>
                    <label class="form-check-label" for="activeSwitch">Active</label>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">Father name</label>
                <input type="text" name="father_name" value="{{ old('father_name', $employee->father_name ?? '') }}"
                       class="form-control @error('father_name') is-invalid @enderror" maxlength="150"
                       placeholder="S/O">
                @error('father_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">CNIC no</label>
                <input type="text" name="cnic" value="{{ old('cnic', $employee->cnic ?? '') }}"
                       class="form-control @error('cnic') is-invalid @enderror" maxlength="30"
                       placeholder="35202-1234567-1" inputmode="numeric">
                @error('cnic')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label">Address</label>
                <textarea name="address" rows="2" class="form-control @error('address') is-invalid @enderror"
                          maxlength="255" placeholder="Street / house / area">{{ old('address', $employee->address ?? '') }}</textarea>
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">City</label>
                <input type="text" name="city" value="{{ old('city', $employee->city ?? '') }}"
                       class="form-control @error('city') is-invalid @enderror" maxlength="100"
                       placeholder="e.g. Lahore">
                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">District</label>
                <input type="text" name="district" value="{{ old('district', $employee->district ?? '') }}"
                       class="form-control @error('district') is-invalid @enderror" maxlength="100"
                       placeholder="e.g. Lahore">
                @error('district')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-3">
        <label class="form-label">Passport photo</label>
        <div class="border rounded-3 p-3 bg-light h-100">
            @php $existingPhoto = isset($employee) && $employee ? $employee->photoUrl() : null; @endphp
            <div class="employee-photo-preview-wrap border rounded bg-white overflow-hidden mx-auto mb-3"
                 style="width:140px;height:180px;">
                <img src="{{ $existingPhoto ?: '' }}" alt="" id="employeePhotoPreview"
                     class="w-100 h-100 {{ $existingPhoto ? '' : 'd-none' }}" style="object-fit:cover;">
                <div id="employeePhotoPlaceholder"
                     class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-secondary small {{ $existingPhoto ? 'd-none' : '' }}">
                    <i class="bi bi-person-bounding-box fs-2 mb-1"></i>
                    <span>35 × 45 mm</span>
                </div>
            </div>
            <input type="file" name="photo" id="employeePhotoInput" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm @error('photo') is-invalid @enderror">
            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text small mt-2 mb-0">JPG / PNG / WebP — max 4 MB. Center crop passport size mein save hogi.</div>
            @if($existingPhoto)
                <div class="form-check mt-2">
                    <input type="hidden" name="remove_photo" value="0">
                    <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removeEmployeePhoto">
                    <label class="form-check-label small" for="removeEmployeePhoto">Photo hata dein</label>
                </div>
            @endif
        </div>
        <div class="border rounded-3 p-3 bg-light mt-3">
            <div class="fw-semibold mb-2">Attendance QR</div>
            @if(($employee->exists ?? false) && ! empty($qrSvg ?? null))
                <style>.employee-qr-preview svg{width:100%!important;height:100%!important;display:block;}</style>
                <div class="bg-white border rounded p-2 text-center mb-2">
                    <div class="mx-auto employee-qr-preview" style="width:160px;height:160px;">
                        {!! $qrSvg !!}
                    </div>
                </div>
                <div class="small text-secondary mb-2">Card print karke scan hone par aaj ki Present automatic lag jayegi. Attendance module khula hona zaroori nahi.</div>
                <div class="d-grid gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.qr-card', $employee) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer me-1"></i> Print QR card
                    </a>
                    <button type="submit" form="employee-qr-regen-form" class="btn btn-sm btn-outline-warning"
                            onclick="return confirm('Naya QR generate hoga. Purana printed card kaam nahi karega. Continue?');">
                        <i class="bi bi-arrow-repeat me-1"></i> Regenerate QR
                    </button>
                </div>
            @else
                <div class="small text-secondary mb-0">Employee save karte hi QR automatic generate hoga. Phir yahan se card print karein.</div>
            @endif
        </div>
    </div>

    <div class="col-12">
        <div class="border rounded-3 p-3 bg-light">
            <div class="fw-semibold mb-2 d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <span>Login Account (Username / Password)</span>
                @if(($employee->exists ?? false) && $employee->user && ($employee->user->role ?? '') === 'user')
                    <button type="submit"
                            form="employee-delete-login-form"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Sirf login account delete hogi. Employee ({{ $employee->employee_no }}) delete nahi hoga. Continue?');">
                        <i class="bi bi-person-x me-1"></i> Delete Account
                    </button>
                @endif
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="account_username"
                           value="{{ old('account_username', \App\Support\LoginUsername::display($employee->user?->email)) }}"
                           class="form-control @error('account_username') is-invalid @enderror" maxlength="40"
                           placeholder="e.g. sheraz" autocomplete="off"
                           pattern="[A-Za-z0-9._-]{3,40}">
                    @error('account_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="small text-secondary mt-1">Sirf username — koi @example.com nahi. Login ke liye yehi use hoga.</div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="account_password" class="form-control @error('account_password') is-invalid @enderror" maxlength="120" autocomplete="new-password">
                    @error('account_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Confirm</label>
                    <input type="password" name="account_password_confirmation" class="form-control" maxlength="120" autocomplete="new-password">
                </div>
            </div>

            <hr class="my-3">
            @php
                use App\Support\ModuleAccess;
                $currentPerms = old('permissions') !== null
                    ? (array) old('permissions')
                    : ModuleAccess::normalize((array) ($employee->user?->permissions ?? []));
            @endphp
            <div class="fw-semibold mb-2">Module access</div>
            @include('partials.permissions-matrix', ['currentPerms' => $currentPerms])
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Save</button>
    <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@once
@push('scripts')
<script>
(() => {
    const input = document.getElementById('employeePhotoInput');
    const preview = document.getElementById('employeePhotoPreview');
    const placeholder = document.getElementById('employeePhotoPlaceholder');
    const remove = document.getElementById('removeEmployeePhoto');
    if (!input || !preview || !placeholder) return;

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        preview.src = url;
        preview.classList.remove('d-none');
        placeholder.classList.add('d-none');
        if (remove) remove.checked = false;
    });
})();
</script>
@endpush
@endonce
