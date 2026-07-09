@props([
    'name',
    'label' => null,
    'value' => null,
    'required' => false,
    'min' => null,
    'max' => null,
    'class' => 'form-control',
    'id' => null,
    'hint' => null,
    'useOld' => true,
])
@php
    $inputId = $id ?? $name;
    $displayValue = $useOld ? form_date_value($name, $value) : (string) ($value ?? '');
@endphp
<div {{ $attributes->merge(['class' => '']) }}>
    @if($label)
        <label class="form-label" for="{{ $inputId }}">{{ $label }}@if($required)<span class="text-danger"> *</span>@endif</label>
    @endif
    <input type="text"
           name="{{ $name }}"
           id="{{ $inputId }}"
           class="{{ $class }} js-date-dmy @error($name) is-invalid @enderror"
           value="{{ $displayValue }}"
           placeholder="DD-MM-YYYY"
           autocomplete="off"
           @if($required) required @endif
           @if($min) data-min-date="{{ fmt_date($min) }}" data-min-date-iso="{{ fmt_date_input($min) }}" @endif
           @if($max) data-max-date="{{ fmt_date($max) }}" data-max-date-iso="{{ fmt_date_input($max) }}" @endif>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
</div>

@once
    @push('head')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script>
        (function () {
            function initDateDmy() {
                if (typeof flatpickr === 'undefined') return;
                document.querySelectorAll('.js-date-dmy:not([data-fp-init])').forEach(function (el) {
                    el.setAttribute('data-fp-init', '1');
                    var opts = {
                        dateFormat: 'd-m-Y',
                        allowInput: true,
                        disableMobile: true,
                    };
                    if (el.dataset.minDateIso) {
                        opts.minDate = el.dataset.minDateIso;
                    } else if (el.dataset.minDate) {
                        opts.minDate = flatpickr.parseDate(el.dataset.minDate, 'd-m-Y');
                    }
                    if (el.dataset.maxDateIso) {
                        opts.maxDate = el.dataset.maxDateIso;
                    } else if (el.dataset.maxDate) {
                        opts.maxDate = flatpickr.parseDate(el.dataset.maxDate, 'd-m-Y');
                    }
                    if (el.value) {
                        var picked = flatpickr.parseDate(el.value, 'd-m-Y');
                        if (picked) {
                            opts.defaultDate = picked;
                        }
                    }
                    flatpickr(el, opts);
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDateDmy);
            } else {
                initDateDmy();
            }
        })();
        </script>
    @endpush
@endonce
