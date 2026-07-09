@php
    $account = $account ?? null;
    $isEdit = (bool) $account;
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control" value="{{ old('code', $account->code ?? '') }}"
               @if($isEdit && ($account->is_system ?? false)) readonly @endif required maxlength="20">
    </div>
    <div class="col-md-5">
        <label class="form-label">Account Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $account->name ?? '') }}" required maxlength="150">
    </div>
    <div class="col-md-4">
        <label class="form-label">Type <span class="text-danger">*</span></label>
        <select name="type" class="form-select" required>
            @foreach($typeLabels as $val => $label)
                <option value="{{ $val }}" @selected(old('type', $account->type ?? '') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Parent Account</label>
        <select name="parent_id" class="form-select">
            <option value="">— None —</option>
            @foreach($parents as $parent)
                <option value="{{ $parent->id }}" @selected((string) old('parent_id', $account->parent_id ?? '') === (string) $parent->id)>
                    {{ $parent->code }} — {{ $parent->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label d-block">Status</label>
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="active" value="0">
            <input class="form-check-input" type="checkbox" name="active" value="1" id="account_active"
                   @checked(old('active', $account->active ?? true))>
            <label class="form-check-label" for="account_active">Active</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2" maxlength="500">{{ old('description', $account->description ?? '') }}</textarea>
    </div>
</div>
