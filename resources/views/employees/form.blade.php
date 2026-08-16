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
                <select name="staff_category_id" id="staffCategorySelect" class="form-select @error('staff_category_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach($staffCategories ?? [] as $cat)
                        <option value="{{ $cat->id }}" @selected((string)old('staff_category_id', $employee->staff_category_id ?? '') === (string)$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('staff_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label">Sub Category</label>
                <select name="staff_sub_category_id" id="staffSubCategorySelect" class="form-select @error('staff_sub_category_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach($staffCategories ?? [] as $cat)
                        @foreach($cat->subCategories ?? [] as $sub)
                            <option value="{{ $sub->id }}"
                                    data-category-id="{{ $cat->id }}"
                                    @selected((string)old('staff_sub_category_id', $employee->staff_sub_category_id ?? '') === (string)$sub->id)>
                                {{ $sub->name }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
                @error('staff_sub_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <script>
            (() => {
                const cat = document.getElementById('staffCategorySelect');
                const sub = document.getElementById('staffSubCategorySelect');
                if (!cat || !sub) return;
                const sync = () => {
                    const cid = String(cat.value || '');
                    let keep = false;
                    Array.from(sub.options).forEach((opt) => {
                        if (!opt.value) {
                            opt.hidden = false;
                            return;
                        }
                        const match = String(opt.dataset.categoryId || '') === cid;
                        opt.hidden = !match;
                        if (match && opt.selected) keep = true;
                    });
                    if (!keep) sub.value = '';
                };
                cat.addEventListener('change', sync);
                sync();
            })();
            </script>

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
                   class="d-none @error('photo') is-invalid @enderror">
            @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-sm btn-primary" id="employeePhotoCaptureBtn">
                    <i class="bi bi-camera me-1"></i> Capture image
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="employeePhotoChooseBtn">
                    <i class="bi bi-folder2-open me-1"></i> Choose image
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="employeePhotoCropBtn">
                    <i class="bi bi-crop me-1"></i> Crop image
                </button>
            </div>
            <div class="form-text small mt-2 mb-0">Camera se capture karein ya file choose karein, phir 35×45 mm crop apply karein. Max 4 MB.</div>
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
                <div class="small text-secondary mb-2">Card print karke scan hone par Present tabhi lagti hai jab us device pe Admin / Super Admin login ho. Warna login page khulega.</div>
                <div class="d-grid gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.qr-card', $employee) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer me-1"></i> Print ID card
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
<div id="employeePhotoCaptureModal" class="emp-photo-overlay d-none" role="dialog" aria-modal="true">
    <div class="emp-photo-dialog">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Capture image</strong>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-emp-photo-close="capture">Close</button>
        </div>
        <div class="emp-photo-cam-wrap border rounded overflow-hidden bg-dark mb-2">
            <video id="employeePhotoCam" playsinline autoplay muted></video>
        </div>
        <div id="employeePhotoCamError" class="text-danger small mb-2 d-none"></div>
        <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-primary" id="employeePhotoSnapBtn">
                <i class="bi bi-camera me-1"></i> Take photo
            </button>
        </div>
    </div>
</div>

<div id="employeePhotoCropModal" class="emp-photo-overlay d-none" role="dialog" aria-modal="true">
    <div class="emp-photo-dialog">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Crop image — 35 × 45 mm</strong>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-emp-photo-close="crop">Close</button>
        </div>
        <div id="employeePhotoCropStage" class="emp-photo-crop-stage mb-2">
            <img id="employeePhotoCropImg" alt="">
            <div id="employeePhotoCropFrame" class="emp-photo-crop-frame"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label small mb-1" for="employeePhotoZoom">Zoom</label>
                    <span class="small text-secondary" id="employeePhotoZoomVal">100%</span>
                </div>
                <input type="range" class="form-range" id="employeePhotoZoom" min="100" max="280" value="100">
            </div>
            <div class="col-6">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label small mb-1" for="employeePhotoBrightness">Brightness</label>
                    <span class="small text-secondary" id="employeePhotoBrightnessVal">100%</span>
                </div>
                <input type="range" class="form-range" id="employeePhotoBrightness" min="50" max="160" value="100">
            </div>
        </div>
        <div class="small text-secondary mb-3">Drag karke face frame ke beech rakhein. Zoom / brightness adjust karke Apply crop.</div>
        <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" data-emp-photo-close="crop">Cancel</button>
            <button type="button" class="btn btn-primary" id="employeePhotoApplyCropBtn">
                <i class="bi bi-crop me-1"></i> Apply crop
            </button>
        </div>
    </div>
</div>

<style>
.emp-photo-overlay {
    position: fixed; inset: 0; z-index: 1080;
    background: rgba(15, 23, 42, .62);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.emp-photo-overlay.d-none { display: none !important; }
.emp-photo-dialog {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 12px 40px rgba(0,0,0,.25);
}
.emp-photo-cam-wrap { aspect-ratio: 3 / 4; }
.emp-photo-cam-wrap video { width: 100%; height: 100%; object-fit: cover; display: block; }
.emp-photo-crop-stage {
    position: relative;
    height: 360px;
    overflow: hidden;
    background: #0f172a;
    border-radius: 8px;
    touch-action: none;
    user-select: none;
}
.emp-photo-crop-stage img {
    position: absolute;
    left: 0; top: 0;
    max-width: none;
    cursor: grab;
}
.emp-photo-crop-frame {
    position: absolute;
    left: 50%; top: 50%;
    width: 140px; height: 180px;
    transform: translate(-50%, -50%);
    border: 2px solid #fff;
    box-shadow: 0 0 0 9999px rgba(0,0,0,.55);
    pointer-events: none;
    border-radius: 4px;
}
</style>
@endonce

@once
@push('scripts')
<script>
(() => {
    const input = document.getElementById('employeePhotoInput');
    const preview = document.getElementById('employeePhotoPreview');
    const placeholder = document.getElementById('employeePhotoPlaceholder');
    const remove = document.getElementById('removeEmployeePhoto');
    const captureBtn = document.getElementById('employeePhotoCaptureBtn');
    const chooseBtn = document.getElementById('employeePhotoChooseBtn');
    const cropBtn = document.getElementById('employeePhotoCropBtn');
    const captureModal = document.getElementById('employeePhotoCaptureModal');
    const cropModal = document.getElementById('employeePhotoCropModal');
    const video = document.getElementById('employeePhotoCam');
    const camError = document.getElementById('employeePhotoCamError');
    const snapBtn = document.getElementById('employeePhotoSnapBtn');
    const stage = document.getElementById('employeePhotoCropStage');
    const cropImg = document.getElementById('employeePhotoCropImg');
    const zoom = document.getElementById('employeePhotoZoom');
    const zoomVal = document.getElementById('employeePhotoZoomVal');
    const brightness = document.getElementById('employeePhotoBrightness');
    const brightnessVal = document.getElementById('employeePhotoBrightnessVal');
    const applyBtn = document.getElementById('employeePhotoApplyCropBtn');
    if (!input || !preview || !placeholder) return;

    const OUT_W = 350;
    const OUT_H = 450;
    let stream = null;
    let baseW = 0;
    let dragging = false;
    let dragX = 0;
    let dragY = 0;
    let startLeft = 0;
    let startTop = 0;

    function brightnessFactor() {
        return Math.max(0.5, Math.min(1.6, Number(brightness?.value || 100) / 100));
    }

    function syncAdjustLabels() {
        if (zoomVal) zoomVal.textContent = String(Number(zoom?.value || 100)) + '%';
        if (brightnessVal) brightnessVal.textContent = String(Number(brightness?.value || 100)) + '%';
    }

    function applyLiveFilters() {
        if (!cropImg) return;
        cropImg.style.filter = 'brightness(' + brightnessFactor() + ')';
        syncAdjustLabels();
    }

    function showPreview(url) {
        preview.src = url;
        preview.classList.remove('d-none');
        placeholder.classList.add('d-none');
        if (remove) remove.checked = false;
    }

    function setFile(blob, name) {
        const file = new File([blob], name || 'passport.jpg', { type: 'image/jpeg' });
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        showPreview(URL.createObjectURL(blob));
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }
        if (video) video.srcObject = null;
    }

    function openOverlay(el) {
        el.classList.remove('d-none');
    }
    function closeOverlay(el) {
        el.classList.add('d-none');
        if (el === captureModal) stopCamera();
    }

    document.querySelectorAll('[data-emp-photo-close]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const which = btn.getAttribute('data-emp-photo-close');
            closeOverlay(which === 'capture' ? captureModal : cropModal);
        });
    });

    chooseBtn?.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;
        openCrop(URL.createObjectURL(file));
    });

    cropBtn?.addEventListener('click', () => {
        const src = preview.classList.contains('d-none') ? '' : preview.src;
        if (!src) {
            input.click();
            return;
        }
        openCrop(src);
    });

    async function openCamera() {
        camError.classList.add('d-none');
        camError.textContent = '';
        openOverlay(captureModal);
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 960 } },
                audio: false
            });
            video.srcObject = stream;
            await video.play();
        } catch (e) {
            camError.textContent = 'Camera nahi khuli. Browser permission dein, ya Choose image use karein.';
            camError.classList.remove('d-none');
        }
    }

    captureBtn?.addEventListener('click', openCamera);

    snapBtn?.addEventListener('click', () => {
        if (!video || video.readyState < 2) return;
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 720;
        canvas.height = video.videoHeight || 960;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob((blob) => {
            if (!blob) return;
            closeOverlay(captureModal);
            openCrop(URL.createObjectURL(blob));
        }, 'image/jpeg', 0.92);
    });

    function fitImage() {
        if (!cropImg.naturalWidth || !stage) return;
        const frameW = 140;
        const frameH = 180;
        const minScale = Math.max(frameW / cropImg.naturalWidth, frameH / cropImg.naturalHeight);
        baseW = cropImg.naturalWidth * minScale;
        const z = Number(zoom.value || 100) / 100;
        cropImg.style.width = (baseW * z) + 'px';
        cropImg.style.height = 'auto';
        const imgW = baseW * z;
        const imgH = cropImg.naturalHeight * (imgW / cropImg.naturalWidth);
        cropImg.style.left = ((stage.clientWidth - imgW) / 2) + 'px';
        cropImg.style.top = ((stage.clientHeight - imgH) / 2) + 'px';
    }

    function openCrop(url) {
        cropImg.src = url;
        zoom.value = '100';
        if (brightness) brightness.value = '100';
        applyLiveFilters();
        openOverlay(cropModal);
        cropImg.onload = () => {
            fitImage();
            applyLiveFilters();
        };
        if (cropImg.complete && cropImg.naturalWidth) {
            fitImage();
            applyLiveFilters();
        }
    }

    zoom?.addEventListener('input', () => {
        if (!baseW) return;
        const z = Number(zoom.value) / 100;
        const cx = stage.clientWidth / 2;
        const cy = stage.clientHeight / 2;
        const oldW = cropImg.getBoundingClientRect().width;
        const oldH = cropImg.getBoundingClientRect().height;
        const imgW = baseW * z;
        const imgH = cropImg.naturalHeight * (imgW / cropImg.naturalWidth);
        const left = parseFloat(cropImg.style.left || '0');
        const top = parseFloat(cropImg.style.top || '0');
        const originX = (cx - left) / Math.max(1, oldW);
        const originY = (cy - top) / Math.max(1, oldH);
        cropImg.style.width = imgW + 'px';
        cropImg.style.left = (cx - originX * imgW) + 'px';
        cropImg.style.top = (cy - originY * imgH) + 'px';
        syncAdjustLabels();
    });

    brightness?.addEventListener('input', applyLiveFilters);

    stage?.addEventListener('pointerdown', (e) => {
        dragging = true;
        dragX = e.clientX;
        dragY = e.clientY;
        startLeft = parseFloat(cropImg.style.left || '0');
        startTop = parseFloat(cropImg.style.top || '0');
        stage.setPointerCapture(e.pointerId);
        cropImg.style.cursor = 'grabbing';
    });
    stage?.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        cropImg.style.left = (startLeft + e.clientX - dragX) + 'px';
        cropImg.style.top = (startTop + e.clientY - dragY) + 'px';
    });
    stage?.addEventListener('pointerup', () => {
        dragging = false;
        cropImg.style.cursor = 'grab';
    });

    applyBtn?.addEventListener('click', () => {
        const frame = document.getElementById('employeePhotoCropFrame').getBoundingClientRect();
        const imgRect = cropImg.getBoundingClientRect();
        const scaleX = cropImg.naturalWidth / imgRect.width;
        const scaleY = cropImg.naturalHeight / imgRect.height;
        const sx = (frame.left - imgRect.left) * scaleX;
        const sy = (frame.top - imgRect.top) * scaleY;
        const sw = frame.width * scaleX;
        const sh = frame.height * scaleY;
        const canvas = document.createElement('canvas');
        canvas.width = OUT_W;
        canvas.height = OUT_H;
        const ctx = canvas.getContext('2d');
        ctx.filter = 'brightness(' + brightnessFactor() + ')';
        ctx.drawImage(cropImg, sx, sy, sw, sh, 0, 0, OUT_W, OUT_H);
        ctx.filter = 'none';
        canvas.toBlob((blob) => {
            if (!blob) return;
            setFile(blob, 'passport.jpg');
            closeOverlay(cropModal);
        }, 'image/jpeg', 0.92);
    });
})();
</script>
@endpush
@endonce
