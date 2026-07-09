@extends('layouts.admin')
@section('title', 'Calendar — ' . config('app.name'))

@section('content')
<div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Calendar</h4>
        <div class="text-secondary small">Schedule meetings, tasks & events</div>
    </div>
    <button class="btn btn-primary btn-sm" id="btnNewEvent">
        <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1">
            <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
        New Event
    </button>
</div>

<div class="row g-3">
    {{-- Sidebar --}}
    <div class="col-12 col-lg-3 d-flex flex-column gap-3">

        {{-- Mini month picker rendered by FullCalendar --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-2">
                <div id="miniCal"></div>
            </div>
        </div>

        {{-- Event type legend / filter --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3 small">Filter by Type</div>
            <div class="card-body py-2 px-3">
                @foreach($typeLabels as $key => $label)
                @php $col = $typeColors[$key] @endphp
                <label class="d-flex align-items-center gap-2 mb-2 cursor-pointer user-select-none">
                    <input type="checkbox" class="type-filter" value="{{ $key }}" checked
                        style="accent-color:{{ $col }};width:15px;height:15px;">
                    <span class="rounded-circle d-inline-block" style="width:10px;height:10px;background:{{ $col }};"></span>
                    <span class="small">{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Upcoming events --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3 small">Upcoming Events</div>
            <div class="card-body p-0">
                @forelse($upcoming as $ev)
                @php $col = $typeColors[$ev->event_type] ?? '#64748b' @endphp
                <div class="d-flex gap-2 px-3 py-2 border-bottom upcoming-item"
                    data-id="{{ $ev->id }}" style="cursor:pointer;">
                    <div class="flex-shrink-0 mt-1 rounded-circle" style="width:8px;height:8px;background:{{ $col }};margin-top:6px;"></div>
                    <div>
                        <div class="small fw-semibold text-truncate" style="max-width:180px;">{{ $ev->title }}</div>
                        <div class="text-secondary" style="font-size:11px;">
                            {{ $ev->all_day ? $ev->start_datetime->format('d M') : $ev->start_datetime->format('d M, H:i') }}
                        </div>
                    </div>
                </div>
                @empty
                <div class="px-3 py-3 text-secondary small">No upcoming events.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Main calendar --}}
    <div class="col-12 col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3">
                <div id="mainCal"></div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     CREATE / EDIT EVENT MODAL
     ============================================================ --}}
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0" id="eventModalHeader" style="background:linear-gradient(135deg,#7c3aed18,#7c3aed05);">
                <h5 class="modal-title fw-bold" id="eventModalTitle">New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-3">
                    {{-- Left --}}
                    <div class="col-12 col-md-8">
                        <div class="mb-3">
                            <input type="text" id="evTitle" class="form-control form-control-lg fw-semibold border-0 border-bottom rounded-0 px-0"
                                placeholder="Event title…" style="font-size:1.1rem;">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small text-secondary mb-1">Start</label>
                                <input type="datetime-local" id="evStart" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-secondary mb-1">End</label>
                                <input type="datetime-local" id="evEnd" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="evAllDay">
                                <label class="form-check-label small" for="evAllDay">All-day event</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-secondary mb-1">Location</label>
                            <input type="text" id="evLocation" class="form-control form-control-sm"
                                placeholder="e.g. Conference Room A, Zoom link…">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-secondary mb-1">Description</label>
                            <textarea id="evDesc" class="form-control form-control-sm" rows="3"
                                placeholder="Additional details…"></textarea>
                        </div>
                    </div>

                    {{-- Right --}}
                    <div class="col-12 col-md-4">
                        <div class="mb-3">
                            <label class="form-label small text-secondary mb-1">Event Type</label>
                            <select id="evType" class="form-select form-select-sm">
                                @foreach($typeLabels as $key => $label)
                                <option value="{{ $key }}" data-color="{{ $typeColors[$key] }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-secondary mb-1">Colour</label>
                            <div class="d-flex gap-2 flex-wrap" id="colorPicker">
                                @foreach(['#7c3aed','#0ea5e9','#22c55e','#f97316','#ef4444','#ec4899','#64748b','#f59e0b'] as $clr)
                                <div class="color-swatch rounded-circle" data-color="{{ $clr }}"
                                    style="width:28px;height:28px;background:{{ $clr }};cursor:pointer;border:3px solid transparent;transition:border-color .15s;"></div>
                                @endforeach
                            </div>
                            <input type="color" id="evColor" class="form-control form-control-sm mt-2" style="height:32px;padding:2px 4px;" title="Custom colour">
                        </div>

                        {{-- Colour preview strip --}}
                        <div id="evColorPreview" class="rounded p-2 mt-2 text-white text-center small fw-semibold"
                            style="background:#7c3aed;transition:background .2s;">
                            Preview
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                <button class="btn btn-outline-danger btn-sm d-none" id="btnDeleteEvent">
                    <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M5 7h10l-1 9H6L5 7z" stroke="currentColor" stroke-width="1.5"/><path d="M3 7h14M8 7V4h4v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    Delete
                </button>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary btn-sm px-4" id="btnSaveEvent">Save Event</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- EVENT DETAIL POPOVER (read-only quick view) --}}
<div class="modal fade" id="eventDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div id="edHeader" class="py-3 px-4 text-white" style="background:#7c3aed;">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="fw-bold fs-6" id="edTitle">Event Title</div>
                        <div class="small opacity-75 mt-1" id="edType">Meeting</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body py-3">
                <ul class="list-unstyled small mb-0">
                    <li class="d-flex gap-2 mb-2" id="edTimeRow">
                        <svg width="15" height="15" fill="none" viewBox="0 0 20 20" class="text-secondary flex-shrink-0 mt-1"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v4l2.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <span id="edTime" class="text-secondary"></span>
                    </li>
                    <li class="d-flex gap-2 mb-2 d-none" id="edLocationRow">
                        <svg width="15" height="15" fill="none" viewBox="0 0 20 20" class="text-secondary flex-shrink-0 mt-1"><path d="M10 2a6 6 0 0 1 6 6c0 4-6 10-6 10S4 12 4 8a6 6 0 0 1 6-6z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
                        <span id="edLocation" class="text-secondary"></span>
                    </li>
                    <li class="d-flex gap-2 d-none" id="edDescRow">
                        <svg width="15" height="15" fill="none" viewBox="0 0 20 20" class="text-secondary flex-shrink-0 mt-1"><path d="M4 6h12M4 10h8M4 14h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <span id="edDesc" class="text-secondary" style="white-space:pre-line;"></span>
                    </li>
                </ul>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-danger btn-sm" id="edBtnDelete">Delete</button>
                <button class="btn btn-outline-primary btn-sm" id="edBtnEdit">Edit</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- FullCalendar v6 global build — CSS is injected automatically by the JS bundle --}}
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

<style>
/* FullCalendar skin overrides */
.fc { font-family: inherit; }
.fc .fc-toolbar-title { font-size: 1.1rem; font-weight: 700; }
.fc .fc-button-primary { background: #7c3aed; border-color: #7c3aed; }
.fc .fc-button-primary:hover,
.fc .fc-button-primary:focus { background: #6d28d9; border-color: #6d28d9; }
.fc .fc-button-primary:not(:disabled).fc-button-active { background: #5b21b6; border-color: #5b21b6; }
.fc .fc-daygrid-day-number,
.fc .fc-col-header-cell-cushion { text-decoration: none; color: inherit; }
.fc .fc-day-today { background: rgba(124,58,237,.06) !important; }
.fc .fc-event { border-radius: 6px; font-size: 12px; cursor: pointer; }
.fc .fc-event:hover { filter: brightness(1.1); }
.fc-daygrid-event-dot { border-color: inherit; }

/* Mini calendar */
#miniCal .fc-toolbar-title { font-size: .85rem; }
#miniCal .fc-button { padding: .1rem .4rem; font-size: .7rem; }
#miniCal .fc-daygrid-day-number { font-size: .75rem; }
#miniCal .fc-col-header-cell-cushion { font-size: .7rem; }
#miniCal .fc-daygrid-event { display: none !important; }
#miniCal .fc-daygrid-day-top { justify-content: center; }
#miniCal .fc-scrollgrid-sync-table td { height: 28px !important; }

/* Color swatch hover */
.color-swatch:hover { transform: scale(1.15); }
.color-swatch.selected { border-color: #fff !important; box-shadow: 0 0 0 2px currentColor; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // Guard: FullCalendar must be loaded
    if (typeof FullCalendar === 'undefined') {
        document.getElementById('mainCal').innerHTML =
            '<div class="alert alert-warning m-3">FullCalendar could not load. Please check your internet connection.</div>';
        return;
    }

    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const feedUrl    = '{{ route('calendar.feed') }}';
    const storeUrl   = '{{ route('calendar.store') }}';
    const showBase   = '{{ url('calendar') }}';

    // Lazy modal helpers — only initialised when first needed
    function getModal(id) {
        const el = document.getElementById(id);
        return bootstrap.Modal.getOrCreateInstance(el);
    }

    let mainCal, miniCal;
    let editingId = null;
    let activeTypes = new Set(['meeting','task','holiday','reminder','other']);

    // ─── Helpers ─────────────────────────────────────────────
    function fmtDT(dt) {
        if (!dt) return '';
        const d = new Date(dt);
        const pad = n => String(n).padStart(2,'0');
        return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())
            +'T'+pad(d.getHours())+':'+pad(d.getMinutes());
    }

    function fmtDisplay(start, end, allDay) {
        const opts = allDay
            ? { weekday:'short', year:'numeric', month:'short', day:'numeric' }
            : { weekday:'short', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' };
        const s = new Date(start).toLocaleString([], opts);
        const e = new Date(end).toLocaleString([], opts);
        return s === e ? s : s + ' → ' + e;
    }

    // ─── Type → colour auto-fill ─────────────────────────────
    const typeColors = @json($typeColors);

    document.getElementById('evType').addEventListener('change', function () {
        const col = this.options[this.selectedIndex]?.dataset.color ?? typeColors[this.value] ?? '#7c3aed';
        setColor(col);
    });

    // ─── Colour picker ───────────────────────────────────────
    function setColor(hex) {
        document.getElementById('evColor').value = hex;
        document.getElementById('evColorPreview').style.background = hex;
        document.querySelectorAll('.color-swatch').forEach(sw => {
            sw.style.borderColor = sw.dataset.color === hex ? '#fff' : 'transparent';
            sw.style.boxShadow   = sw.dataset.color === hex ? '0 0 0 2px '+hex : 'none';
        });
    }

    document.querySelectorAll('.color-swatch').forEach(sw => {
        sw.addEventListener('click', () => setColor(sw.dataset.color));
    });

    document.getElementById('evColor').addEventListener('input', function () {
        document.getElementById('evColorPreview').style.background = this.value;
    });

    // ─── All-day toggle ──────────────────────────────────────
    document.getElementById('evAllDay').addEventListener('change', function () {
        const startEl = document.getElementById('evStart');
        const endEl   = document.getElementById('evEnd');
        if (this.checked) {
            startEl.type = 'date';
            endEl.type   = 'date';
        } else {
            startEl.type = 'datetime-local';
            endEl.type   = 'datetime-local';
        }
    });

    // ─── Open empty create form ──────────────────────────────
    function openCreateModal(start, end) {
        editingId = null;
        document.getElementById('eventModalTitle').textContent = 'New Event';
        document.getElementById('btnDeleteEvent').classList.add('d-none');
        document.getElementById('evTitle').value   = '';
        document.getElementById('evDesc').value    = '';
        document.getElementById('evLocation').value= '';
        document.getElementById('evAllDay').checked= false;
        document.getElementById('evStart').type    = 'datetime-local';
        document.getElementById('evEnd').type      = 'datetime-local';
        document.getElementById('evStart').value   = fmtDT(start ?? new Date());
        document.getElementById('evEnd').value     = fmtDT(end   ?? new Date(Date.now() + 3600000));
        document.getElementById('evType').value    = 'meeting';
        setColor(typeColors['meeting']);
        getModal('eventModal').show();
    }

    document.getElementById('btnNewEvent').addEventListener('click', () => openCreateModal());

    // ─── Open edit form ──────────────────────────────────────
    function openEditModal(id) {
        fetch(showBase+'/'+id, {
            headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
        })
        .then(r => r.json())
        .then(ev => {
            editingId = ev.id;
            document.getElementById('eventModalTitle').textContent = 'Edit Event';
            document.getElementById('btnDeleteEvent').classList.remove('d-none');
            document.getElementById('evTitle').value    = ev.title;
            document.getElementById('evDesc').value     = ev.description ?? '';
            document.getElementById('evLocation').value = ev.location ?? '';

            const allDay = !!ev.all_day;
            document.getElementById('evAllDay').checked = allDay;
            document.getElementById('evStart').type = allDay ? 'date' : 'datetime-local';
            document.getElementById('evEnd').type   = allDay ? 'date' : 'datetime-local';
            document.getElementById('evStart').value = allDay
                ? ev.start_datetime.substring(0,10)
                : fmtDT(ev.start_datetime);
            document.getElementById('evEnd').value   = allDay
                ? ev.end_datetime.substring(0,10)
                : fmtDT(ev.end_datetime);
            document.getElementById('evType').value  = ev.event_type;
            setColor(ev.color ?? typeColors[ev.event_type] ?? '#7c3aed');

            getModal('eventDetailModal').hide();
            getModal('eventModal').show();
        });
    }

    // ─── Save (create or update) ─────────────────────────────
    document.getElementById('btnSaveEvent').addEventListener('click', function () {
        const allDay = document.getElementById('evAllDay').checked;
        const payload = {
            title:          document.getElementById('evTitle').value.trim(),
            description:    document.getElementById('evDesc').value.trim(),
            location:       document.getElementById('evLocation').value.trim(),
            start_datetime: document.getElementById('evStart').value,
            end_datetime:   document.getElementById('evEnd').value,
            all_day:        allDay ? 1 : 0,
            event_type:     document.getElementById('evType').value,
            color:          document.getElementById('evColor').value,
        };

        if (!payload.title) {
            document.getElementById('evTitle').classList.add('is-invalid');
            document.getElementById('evTitle').focus();
            return;
        }
        document.getElementById('evTitle').classList.remove('is-invalid');

        const url    = editingId ? showBase+'/'+editingId : storeUrl;
        const method = editingId ? 'PUT' : 'POST';

        this.disabled = true;
        fetch(url, {
            method,
            headers:{
                'Content-Type':'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept':'application/json',
                'X-Requested-With':'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                mainCal.refetchEvents();
                miniCal.refetchEvents();
                getModal('eventModal').hide();
                refreshUpcoming();
            }
        })
        .finally(() => { this.disabled = false; });
    });

    // ─── Delete from edit modal ──────────────────────────────
    document.getElementById('btnDeleteEvent').addEventListener('click', function () {
        if (!editingId || !confirm('Delete this event?')) return;
        deleteEvent(editingId, () => getModal('eventModal').hide());
    });

    function deleteEvent(id, cb) {
        fetch(showBase+'/'+id, {
            method:'DELETE',
            headers:{ 'X-CSRF-TOKEN':csrfToken, 'Accept':'application/json' },
        })
        .then(() => {
            mainCal.refetchEvents();
            miniCal.refetchEvents();
            refreshUpcoming();
            if (cb) cb();
        });
    }

    // ─── Detail popover ──────────────────────────────────────
    function openDetailModal(fcEvent) {
        const p = fcEvent.extendedProps;
        document.getElementById('edTitle').textContent  = fcEvent.title;
        document.getElementById('edType').textContent   = (p.event_type ?? 'event').charAt(0).toUpperCase()+(p.event_type?.slice(1)??'');
        document.getElementById('edHeader').style.background = fcEvent.backgroundColor || '#7c3aed';
        document.getElementById('edTime').textContent   = fmtDisplay(fcEvent.start, fcEvent.end, fcEvent.allDay);

        const locRow  = document.getElementById('edLocationRow');
        const descRow = document.getElementById('edDescRow');
        if (p.location) {
            document.getElementById('edLocation').textContent = p.location;
            locRow.classList.remove('d-none');
        } else { locRow.classList.add('d-none'); }

        if (p.description) {
            document.getElementById('edDesc').textContent = p.description;
            descRow.classList.remove('d-none');
        } else { descRow.classList.add('d-none'); }

        document.getElementById('edBtnEdit').onclick   = () => openEditModal(fcEvent.id);
        document.getElementById('edBtnDelete').onclick = () => {
            if (confirm('Delete this event?')) deleteEvent(fcEvent.id, () => getModal('eventDetailModal').hide());
        };

        getModal('eventDetailModal').show();
    }

    // ─── Type filter checkboxes ──────────────────────────────
    document.querySelectorAll('.type-filter').forEach(cb => {
        cb.addEventListener('change', function () {
            if (this.checked) activeTypes.add(this.value);
            else activeTypes.delete(this.value);
            mainCal.refetchEvents();
        });
    });

    // ─── Refresh upcoming sidebar ────────────────────────────
    function refreshUpcoming() {
        // Soft reload — just reload the page sidebar (lightweight)
        // For a full SPA feel we'd call an API; here a simple fetch + innerHTML swap works
        fetch(window.location.href, { headers:{ 'X-Requested-With':'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc    = parser.parseFromString(html, 'text/html');
                const newList = doc.querySelector('.card .card-body.p-0');
                const oldList = document.querySelectorAll('.card .card-body.p-0')[0];
                if (newList && oldList) oldList.innerHTML = newList.innerHTML;
                bindUpcoming();
            });
    }

    function bindUpcoming() {
        document.querySelectorAll('.upcoming-item').forEach(el => {
            el.addEventListener('click', () => {
                const id = el.dataset.id;
                fetch(showBase+'/'+id, { headers:{'Accept':'application/json'} })
                    .then(r => r.json())
                    .then(ev => {
                        openDetailModal({
                            id: ev.id,
                            title: ev.title,
                            start: ev.start_datetime,
                            end:   ev.end_datetime,
                            allDay: !!ev.all_day,
                            backgroundColor: ev.color,
                            extendedProps: {
                                description: ev.description,
                                location:    ev.location,
                                event_type:  ev.event_type,
                            },
                        });
                    });
            });
        });
    }
    bindUpcoming();

    // ─── Build event sources function ────────────────────────
    function buildEventSource(cal) {
        return {
            url: feedUrl,
            method: 'GET',
            extraParams: {},
            success(events) {
                return events.filter(e => activeTypes.has(e.extendedProps?.event_type ?? 'other'));
            },
        };
    }

    // ─── Init MINI calendar ──────────────────────────────────
    miniCal = new FullCalendar.Calendar(document.getElementById('miniCal'), {
        initialView: 'dayGridMonth',
        headerToolbar: { left:'prev', center:'title', right:'next' },
        height: 'auto',
        selectable: true,
        events: buildEventSource(),
        dateClick(info) {
            mainCal.gotoDate(info.date);
            mainCal.changeView('timeGridDay');
        },
    });
    miniCal.render();

    // ─── Init MAIN calendar ──────────────────────────────────
    mainCal = new FullCalendar.Calendar(document.getElementById('mainCal'), {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        height: 'auto',
        nowIndicator: true,
        selectable: true,
        editable: true,
        eventResizableFromStart: true,
        events: buildEventSource(),

        // Click on empty date → open create modal
        dateClick(info) {
            const start = new Date(info.date);
            const end   = new Date(info.date);
            end.setHours(start.getHours() + 1);
            openCreateModal(start, end);
        },

        // Select range → open create modal
        select(info) {
            openCreateModal(info.start, info.end);
        },

        // Click on existing event → open detail popover
        eventClick(info) {
            openDetailModal(info.event);
        },

        // Drag & drop update
        eventDrop(info) {
            patchEvent(info.event);
        },

        // Resize update
        eventResize(info) {
            patchEvent(info.event);
        },
    });
    mainCal.render();

    // ─── Patch after drag/resize ─────────────────────────────
    function patchEvent(fcEv) {
        const payload = {
            start_datetime: fcEv.allDay
                ? fcEv.start.toISOString().substring(0,10)+'T00:00:00'
                : fcEv.start.toISOString().replace('Z',''),
            end_datetime: (fcEv.end ?? fcEv.start).toISOString().replace('Z',''),
            all_day: fcEv.allDay ? 1 : 0,
        };
        fetch(showBase+'/'+fcEv.id, {
            method:'PUT',
            headers:{
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':csrfToken,
                'Accept':'application/json',
            },
            body:JSON.stringify(payload),
        });
    }

    setColor(typeColors['meeting']);
}); // end DOMContentLoaded
</script>
@endsection
