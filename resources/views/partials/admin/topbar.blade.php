<nav class="navbar navbar-expand navbar-dark admin-topbar sticky-top py-2">
    <div class="container-fluid align-items-center gap-2 flex-nowrap">
        <a href="{{ route('dashboard') }}"
           class="admin-brand-stair flex-shrink-0 d-inline-flex align-items-center justify-content-center text-decoration-none"
          title="{{ __('Dashboard') }}"
          aria-label="{{ __('Dashboard') }}">
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

        <div class="d-flex align-items-center gap-2 admin-topbar-actions">
            @include('partials.locale-switcher')
            @if(config('sync.enabled') && config('sync.role') === 'local')
            <button type="button"
                    id="sync-status-badge"
                    class="btn btn-sm border-0 bg-white bg-opacity-10 text-white d-inline-flex align-items-center gap-1"
                    title="{{ __('Cloud sync status - click to sync now') }}"
                    style="opacity:0.9;">
                <span id="sync-status-dot" class="rounded-circle d-inline-block" style="width:8px;height:8px;background:#9ca3af;"></span>
                <span id="sync-status-label" class="small">{{ __('Sync') }}</span>
            </button>
            @endif

            {{-- Stair activity notifications (desktop + mobile) --}}
            <div class="dropdown stair-notif-dropdown" id="stairNotifDropdown">
                <button class="btn btn-sm border-0 bg-white bg-opacity-10 text-white position-relative stair-notif-btn"
                        type="button"
                        id="stairNotifBtn"
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        data-bs-popper-config='{"strategy":"fixed"}'
                        aria-expanded="false"
                        title="{{ __('Notifications') }}"
                        aria-label="{{ __('Notifications') }}">
                    <i class="bi bi-bell"></i>
                    <span class="stair-notif-badge d-none" id="stairNotifBadge">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow border-0 stair-notif-menu" aria-labelledby="stairNotifBtn">
                    <div class="stair-notif-head">
                        <strong>{{ __('Notifications') }}</strong>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="stairNotifMarkAll">
                            {{ __('Mark all read') }}
                        </button>
                    </div>
                    <div class="stair-notif-list" id="stairNotifList">
                        <div class="stair-notif-empty text-secondary small px-3 py-4 text-center">{{ __('No new notifications') }}</div>
                    </div>
                    <div class="stair-notif-foot">
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="stairNotifEnable">
                            <i class="bi bi-phone-vibrate me-1"></i> {{ __('Enable phone / desktop alerts') }}
                        </button>
                    </div>
                </div>
            </div>

            <span class="badge admin-topbar-badge d-none d-md-inline">
                @if(auth()->user()?->loginUsername())
                    {{ __('User:') }} <span class="fw-semibold">{{ auth()->user()->loginUsername() }}</span>
                @else
                    {{ __('Role:') }} <span class="fw-semibold">{{ auth()->user()?->role }}</span>
                @endif
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
               class="btn btn-sm border-0 bg-white bg-opacity-10 text-white d-none d-sm-inline-flex"
               title="{{ __('Activity logs') }}"
               style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                <i class="bi bi-journal-text"></i>
            </a>
            <a href="{{ route('admin.users.index') }}"
               class="btn btn-sm border-0 bg-white bg-opacity-10 text-white d-none d-sm-inline-flex"
               title="{{ __('Users & roles') }}"
               style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                <i class="bi bi-person-gear"></i>
            </a>
            @if(auth()->user() && in_array(auth()->user()->role, ['company_admin', 'super_admin'], true) && \App\Models\PasswordResetRequest::tableExists())
            <a href="{{ route('admin.password-reset-requests.index') }}"
               class="btn btn-sm border-0 bg-white bg-opacity-10 text-white position-relative d-none d-sm-inline-flex"
               title="{{ __('Password reset requests') }}"
               style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                <i class="bi bi-key"></i>
                @if($pendingPasswordResetCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">{{ $pendingPasswordResetCount > 99 ? '99+' : $pendingPasswordResetCount }}</span>
                @endif
            </a>
            @endif
            <a href="{{ route('settings.index') }}"
               class="btn btn-sm border-0 bg-white bg-opacity-10 text-white"
               title="{{ __('Settings') }}"
               style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                <i class="bi bi-gear"></i>
            </a>
            @endif

            @if(auth()->user()?->isPlatformSuperAdmin())
                <a href="{{ route('platform.manual-update.index') }}"
                   class="btn btn-sm border-0 bg-white bg-opacity-10 text-white d-none d-sm-inline-flex"
                   title="{{ __('Manual ZIP update') }}"
                   style="opacity:0.75; transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.75">
                    <i class="bi bi-cloud-upload"></i>
                </a>
            @endif

            <div class="dropdown admin-user-dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle border-0 bg-white bg-opacity-10"
                        type="button"
                        id="adminUserMenu"
                        data-bs-toggle="dropdown"
                        data-bs-popper-config='{"strategy":"fixed"}'
                        aria-expanded="false"
                        aria-haspopup="true">
                    <i class="bi bi-person-circle me-1"></i> <span class="d-none d-md-inline">{{ auth()->user()?->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="adminUserMenu">
                    <li><h6 class="dropdown-header">{{ auth()->user()?->email }}</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person me-2"></i> {{ __('Profile') }}
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="#"
                           onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                            <i class="bi bi-box-arrow-right me-2"></i> {{ __('Logout') }}
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
