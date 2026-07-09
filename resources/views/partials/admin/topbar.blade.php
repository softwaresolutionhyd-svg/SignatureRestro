<nav class="navbar navbar-expand navbar-dark admin-topbar sticky-top py-2">
    <div class="container-fluid align-items-center gap-2 flex-nowrap">
        <a href="{{ route('dashboard') }}"
           class="admin-brand-stair flex-shrink-0 d-inline-flex align-items-center justify-content-center text-decoration-none"
           title="Dashboard"
           aria-label="Stair — go to dashboard">
            <img src="{{ asset('images/stair-logo.svg') }}"
                 width="40"
                 height="40"
                 class="admin-brand-stair-img"
                 alt=""
                 loading="eager"
                 decoding="async">
        </a>

        <nav aria-label="breadcrumb" class="admin-breadcrumb flex-grow-1 min-w-0">
            <ol class="breadcrumb mb-0 admin-breadcrumb-list">
                @foreach (\App\Support\AdminBreadcrumbs::items() as $crumb)
                    <li class="breadcrumb-item {{ $crumb['url'] ? '' : 'active' }}">
                        @if ($crumb['url'])
                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        @else
                            {{ $crumb['label'] }}
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            @if(config('sync.enabled') && config('sync.role') === 'local')
            <button type="button"
                    id="sync-status-badge"
                    class="btn btn-sm border-0 bg-white bg-opacity-10 text-white d-inline-flex align-items-center gap-1"
                    title="Cloud sync status — click to sync now"
                    style="opacity:0.9;">
                <span id="sync-status-dot" class="rounded-circle d-inline-block" style="width:8px;height:8px;background:#9ca3af;"></span>
                <span id="sync-status-label" class="small">Sync</span>
            </button>
            @endif
            <span class="badge admin-topbar-badge d-none d-sm-inline">
                Role: <span class="fw-semibold">{{ auth()->user()?->role }}</span>
            </span>

            @php
                $pendingPasswordResetCount = 0;
                if (
                    auth()->user()
                    && in_array(auth()->user()->role, ['company_admin', 'super_admin'], true)
                    && \App\Models\PasswordResetRequest::tableExists()
                ) {
                    $prq = \App\Models\PasswordResetRequest::query()->where('status', 'pending');
                    if (! auth()->user()->isPlatformSuperAdmin()) {
                        $prq->whereHas('user', fn ($q) => $q->where('company_id', auth()->user()->company_id));
                    }
                    $pendingPasswordResetCount = $prq->count();
                }
            @endphp
            @if(auth()->user() && in_array(auth()->user()->role, ['company_admin', 'super_admin', 'admin'], true))
            <a href="{{ route('activity-logs.index') }}"
               class="btn btn-sm border-0 bg-white bg-opacity-10 text-white"
               title="Activity logs"
               style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                <i class="bi bi-journal-text"></i>
            </a>
            <a href="{{ route('admin.users.index') }}"
               class="btn btn-sm border-0 bg-white bg-opacity-10 text-white"
               title="Users & roles"
               style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                <i class="bi bi-person-gear"></i>
            </a>
            @if(auth()->user() && in_array(auth()->user()->role, ['company_admin', 'super_admin'], true) && \App\Models\PasswordResetRequest::tableExists())
            <a href="{{ route('admin.password-reset-requests.index') }}"
               class="btn btn-sm border-0 bg-white bg-opacity-10 text-white position-relative"
               title="Password reset requests"
               style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                <i class="bi bi-key"></i>
                @if($pendingPasswordResetCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">{{ $pendingPasswordResetCount > 99 ? '99+' : $pendingPasswordResetCount }}</span>
                @endif
            </a>
            @endif
            <a href="{{ route('settings.index') }}"
               class="btn btn-sm border-0 bg-white bg-opacity-10 text-white"
               title="Settings"
               style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                <i class="bi bi-gear"></i>
            </a>
            @endif

            @if(auth()->user()?->isPlatformSuperAdmin())
                <a href="{{ route('platform.manual-update.index') }}"
                   class="btn btn-sm border-0 bg-white bg-opacity-10 text-white"
                   title="Manual ZIP update"
                   style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                    <i class="bi bi-cloud-upload"></i>
                </a>
            @endif

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light position-relative border-0 bg-white bg-opacity-10" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="notifBtn">
                    <i class="bi bi-bell"></i>
                    <span id="notifCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
                        0
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-0 shadow border-0" style="min-width: 340px;">
                    <li class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between bg-light">
                        <div class="fw-semibold">Notifications</div>
                        <button class="btn btn-sm btn-link text-decoration-none" type="button" id="notifReadAll">Mark all read</button>
                    </li>
                    <li>
                        <div id="notifList" style="max-height: 380px; overflow: auto;"></div>
                    </li>
                    <li class="px-3 py-2 border-top text-secondary small bg-light">
                        Auto-updates every 10s
                    </li>
                </ul>
            </div>

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle border-0 bg-white bg-opacity-10" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i> <span class="d-none d-md-inline">{{ auth()->user()?->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><h6 class="dropdown-header">{{ auth()->user()?->email }}</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person me-2"></i> Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                           onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<script>
    (function () {
        const countEl = document.getElementById('notifCount');
        const listEl = document.getElementById('notifList');
        const readAllBtn = document.getElementById('notifReadAll');

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, (m) => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
            }[m]));
        }

        async function loadNotifs() {
            try {
                const res = await fetch(@json(route('notifications.index')), { headers: { 'Accept': 'application/json' }});
                if (!res.ok) return;
                const data = await res.json();

                const unread = Number(data.unread_count || 0);
                if (unread > 0) {
                    countEl.textContent = unread > 99 ? '99+' : String(unread);
                    countEl.classList.remove('d-none');
                } else {
                    countEl.classList.add('d-none');
                }

                const items = (data.notifications || []).map(n => {
                    const d = n.data || {};
                    const title = escapeHtml(d.title || 'Update');
                    const body = escapeHtml(d.body || '');
                    const when = escapeHtml((n.created_at || '').replace('T',' ').slice(0,16));
                    const isRead = !!n.read_at;
                    return `
                      <div class="px-3 py-2 border-bottom ${isRead ? '' : 'bg-light'}">
                        <div class="d-flex justify-content-between">
                          <div class="fw-semibold">${title}</div>
                          <div class="text-secondary small">${when}</div>
                        </div>
                        <div class="text-secondary small">${body}</div>
                      </div>
                    `;
                }).join('');

                listEl.innerHTML = items || `<div class="px-3 py-3 text-secondary">No notifications yet.</div>`;
            } catch (e) {}
        }

        readAllBtn?.addEventListener('click', async () => {
            try {
                const res = await fetch(@json(route('notifications.readAll')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({})
                });
                if (res.ok) loadNotifs();
            } catch (e) {}
        });

        loadNotifs();
        setInterval(loadNotifs, 10000);
    })();
</script>
