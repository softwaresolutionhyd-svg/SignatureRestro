@csrf

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

    <div class="col-12">
        <label class="form-label">Address</label>
        <textarea name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $employee->address ?? '') }}</textarea>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="border rounded-3 p-3 bg-light">
            <div class="fw-semibold mb-2">Login Account (Username / Password)</div>
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
                $currentPerms = old('permissions', $employee->user?->permissions ?? []);
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

