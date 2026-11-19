{{-- Shared Super Admin reason prompt. Include once per page when auth user is Super Admin. --}}
@php($saUser = auth()->user())
@if($saUser?->isSuperAdmin())
<div class="modal fade" id="superAdminReasonModal" tabindex="-1" aria-labelledby="superAdminReasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="superAdminReasonModalLabel">Reason required</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-2" id="saReasonHelp">
                    Super Admin changes require a reason (saved in the audit log).
                </p>
                <label class="form-label" for="saReasonInput">Reason <span class="text-danger">*</span></label>
                <textarea id="saReasonInput" class="form-control" rows="3" maxlength="500"
                          placeholder="Why are you making this change?" required></textarea>
                <div class="invalid-feedback d-none" id="saReasonError">Reason is required.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saReasonConfirmBtn">Continue</button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    if (window.SuperAdminReason) return;

    const modalEl = document.getElementById('superAdminReasonModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const input = document.getElementById('saReasonInput');
    const err = document.getElementById('saReasonError');
    const help = document.getElementById('saReasonHelp');
    const confirmBtn = document.getElementById('saReasonConfirmBtn');
    let pending = null;

    function showError(show) {
        if (!err) return;
        err.classList.toggle('d-none', !show);
        input?.classList.toggle('is-invalid', !!show);
    }

    confirmBtn?.addEventListener('click', function () {
        const reason = (input?.value || '').trim();
        if (!reason) {
            showError(true);
            input?.focus();
            return;
        }
        showError(false);
        modal.hide();
        const cb = pending;
        pending = null;
        if (typeof cb === 'function') cb(reason);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        pending = null;
        showError(false);
    });

    window.SuperAdminReason = {
        /**
         * prompt({ title?, help?, onConfirm(reason) })
         */
        prompt: function (opts) {
            opts = opts || {};
            pending = opts.onConfirm || null;
            if (document.getElementById('superAdminReasonModalLabel') && opts.title) {
                document.getElementById('superAdminReasonModalLabel').textContent = opts.title;
            }
            if (help && opts.help) help.textContent = opts.help;
            if (input) {
                input.value = '';
                input.classList.remove('is-invalid');
            }
            showError(false);
            modal.show();
            setTimeout(function () { input?.focus(); }, 300);
        },

        /** Attach to a form: intercept submit, require reason into hidden input name. */
        bindForm: function (form, fieldName) {
            if (!form || form.dataset.saReasonBound === '1') return;
            form.dataset.saReasonBound = '1';
            fieldName = fieldName || 'admin_reason';
            form.addEventListener('submit', function (e) {
                let field = form.querySelector('[name="' + fieldName + '"]');
                if (!field) {
                    field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = fieldName;
                    form.appendChild(field);
                }
                if ((field.value || '').trim()) return;
                e.preventDefault();
                window.SuperAdminReason.prompt({
                    title: 'Reason required',
                    help: 'Enter a reason before saving this Super Admin change.',
                    onConfirm: function (reason) {
                        field.value = reason;
                        if (typeof form.requestSubmit === 'function') form.requestSubmit();
                        else form.submit();
                    }
                });
            });
        },

        /** Fill reason then submit a form (delete / begin-edit). */
        submitFormWithReason: function (form, title) {
            if (!form) return;
            window.SuperAdminReason.prompt({
                title: title || 'Reason required',
                help: 'This action is logged with your reason, date and time.',
                onConfirm: function (reason) {
                    let field = form.querySelector('[name="admin_reason"]');
                    if (!field) {
                        field = document.createElement('input');
                        field.type = 'hidden';
                        field.name = 'admin_reason';
                        form.appendChild(field);
                    }
                    field.value = reason;
                    if (typeof form.requestSubmit === 'function') form.requestSubmit();
                    else form.submit();
                }
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-sa-require-reason="1"]').forEach(function (form) {
            window.SuperAdminReason.bindForm(form);
        });
        document.querySelectorAll('form[data-sa-require-reason-on-password="1"]').forEach(function (form) {
            if (form.dataset.saReasonBound === '1') return;
            form.dataset.saReasonBound = '1';
            form.addEventListener('submit', function (e) {
                const pwd = form.querySelector('[name="password"]');
                if (!pwd || !(pwd.value || '').trim()) return;
                let field = form.querySelector('[name="admin_reason"]');
                if (!field) {
                    field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = 'admin_reason';
                    form.appendChild(field);
                }
                if ((field.value || '').trim()) return;
                e.preventDefault();
                window.SuperAdminReason.prompt({
                    title: 'Reason for password change',
                    help: 'Password changes by Super Admin are logged with reason and date/time.',
                    onConfirm: function (reason) {
                        field.value = reason;
                        if (typeof form.requestSubmit === 'function') form.requestSubmit();
                        else form.submit();
                    }
                });
            });
        });
        document.querySelectorAll('[data-sa-reason-submit]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const form = btn.closest('form');
                window.SuperAdminReason.submitFormWithReason(form, btn.getAttribute('data-sa-reason-title') || 'Reason required');
            });
        });
    });
})();
</script>
@endpush
@endonce
@endif
