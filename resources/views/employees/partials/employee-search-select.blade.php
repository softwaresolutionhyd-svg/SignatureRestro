@php
    $fieldId = $fieldId ?? 'employeeSearchPicker';
    $selectedId = old('employee_id', $selectedId ?? null);
    $selectedLabel = $selectedLabel ?? '';
    if ($selectedId && $selectedLabel === '' && isset($employees)) {
        $match = collect($employees)->firstWhere('id', (int) $selectedId);
        if ($match) {
            $selectedLabel = trim(($match->employee_no ?? '').' — '.($match->name ?? ''));
        }
    }
    $employeeRows = collect($employees ?? [])->map(fn ($e) => [
        'id' => $e->id,
        'label' => trim(($e->employee_no ?? '').' — '.($e->name ?? '')),
        'search' => mb_strtolower(trim(($e->employee_no ?? '').' '.($e->name ?? '')), 'UTF-8'),
    ])->values();
@endphp
<div class="employee-search-picker position-relative" id="{{ $fieldId }}" data-employees='@json($employeeRows)'>
    <label class="form-label">{{ $label ?? 'Employee' }} @if($required ?? true)<span class="text-danger">*</span>@endif</label>
    <input type="text"
           class="form-control employee-search-input"
           value="{{ $selectedLabel }}"
           placeholder="Type employee ID ya naam..."
           autocomplete="off"
           @if($required ?? true) required @endif>
    <input type="hidden" name="employee_id" class="employee-search-value" value="{{ $selectedId }}">
    <div class="employee-search-menu list-group shadow-sm d-none"></div>
</div>

@once
    @push('head')
        <style>
            .employee-search-picker .employee-search-menu {
                position: absolute;
                z-index: 1050;
                width: 100%;
                max-height: 240px;
                overflow: auto;
                margin-top: 2px;
            }
            .employee-search-picker .list-group-item {
                cursor: pointer;
                font-size: 0.925rem;
            }
            .employee-search-picker .list-group-item.active,
            .employee-search-picker .list-group-item:hover {
                background: #eef2ff;
                color: #111;
            }
        </style>
    @endpush
    @push('scripts')
        <script>
            (function () {
                function initEmployeeSearchPicker(root) {
                    if (!root || root.dataset.initialized === '1') return;
                    root.dataset.initialized = '1';

                    let employees = [];
                    try {
                        employees = JSON.parse(root.dataset.employees || '[]');
                    } catch (e) {
                        employees = [];
                    }

                    const input = root.querySelector('.employee-search-input');
                    const hidden = root.querySelector('.employee-search-value');
                    const menu = root.querySelector('.employee-search-menu');
                    if (!input || !hidden || !menu) return;

                    let activeIndex = -1;

                    function closeMenu() {
                        menu.classList.add('d-none');
                        menu.innerHTML = '';
                        activeIndex = -1;
                    }

                    function renderMenu(term) {
                        const needle = (term || '').trim().toLowerCase();
                        const list = needle === ''
                            ? employees.slice(0, 40)
                            : employees.filter((e) => e.search.includes(needle)).slice(0, 40);

                        if (list.length === 0) {
                            menu.innerHTML = '<div class="list-group-item text-secondary">No match</div>';
                            menu.classList.remove('d-none');
                            return;
                        }

                        menu.innerHTML = list.map((e, idx) =>
                            `<button type="button" class="list-group-item list-group-item-action${idx === activeIndex ? ' active' : ''}" data-id="${e.id}" data-label="${String(e.label).replace(/"/g, '&quot;')}">${e.label}</button>`
                        ).join('');
                        menu.classList.remove('d-none');
                    }

                    function pick(id, label) {
                        hidden.value = String(id);
                        input.value = label;
                        closeMenu();
                    }

                    input.addEventListener('focus', () => renderMenu(input.value));
                    input.addEventListener('input', () => {
                        hidden.value = '';
                        activeIndex = -1;
                        renderMenu(input.value);
                    });

                    menu.addEventListener('mousedown', (ev) => {
                        const btn = ev.target.closest('[data-id]');
                        if (!btn) return;
                        ev.preventDefault();
                        pick(btn.dataset.id, btn.dataset.label);
                    });

                    input.addEventListener('keydown', (ev) => {
                        const items = [...menu.querySelectorAll('[data-id]')];
                        if (ev.key === 'ArrowDown') {
                            ev.preventDefault();
                            if (menu.classList.contains('d-none')) renderMenu(input.value);
                            activeIndex = Math.min(activeIndex + 1, items.length - 1);
                            items.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
                            items[activeIndex]?.scrollIntoView({ block: 'nearest' });
                        } else if (ev.key === 'ArrowUp') {
                            ev.preventDefault();
                            activeIndex = Math.max(activeIndex - 1, 0);
                            items.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
                            items[activeIndex]?.scrollIntoView({ block: 'nearest' });
                        } else if (ev.key === 'Enter' && !menu.classList.contains('d-none') && activeIndex >= 0 && items[activeIndex]) {
                            ev.preventDefault();
                            pick(items[activeIndex].dataset.id, items[activeIndex].dataset.label);
                        } else if (ev.key === 'Escape') {
                            closeMenu();
                        }
                    });

                    document.addEventListener('click', (ev) => {
                        if (!root.contains(ev.target)) closeMenu();
                    });

                    root.closest('form')?.addEventListener('submit', (ev) => {
                        if ((hidden.value || '').trim() === '') {
                            ev.preventDefault();
                            input.focus();
                            renderMenu(input.value);
                        }
                    });
                }

                document.querySelectorAll('.employee-search-picker').forEach(initEmployeeSearchPicker);
            })();
        </script>
    @endpush
@endonce
