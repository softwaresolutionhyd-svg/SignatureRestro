@extends('layouts.admin')

@section('title', 'Dashboard — ' . config('app.name'))

@section('content')
@php($u = auth()->user())
<div class="odoo-launcher">

    {{-- iPhone-style clock & date --}}
    <div class="iphone-clock-wrap">
        <div class="iphone-time" id="iphoneTime">00:00</div>
        <div class="iphone-date" id="iphoneDate">Monday, 1 January</div>
    </div>

    {{-- Greeting row --}}
    <div class="odoo-launcher-meta">
        <span class="odoo-meta-greeting">
            Good <span id="greetWord">{{ date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') }}</span>,
            <strong>{{ $u->name }}</strong>
        </span>
        <span class="odoo-meta-badge">{{ $u->role }}</span>
    </div>

    @if(!$u->isSuperAdmin() && !$u->hasAnyModuleLauncherAccess() && !$linkedEmployee)
        <div class="alert alert-warning shadow-sm mb-3" style="max-width: 520px; margin-left: auto; margin-right: auto;">
            Aap ke account par abhi koi <strong>module access</strong> set nahi. App use karne ke liye admin se modules ki ijazat lein.
        </div>
    @endif

    {{-- App grid — tiles sirf un modules ke jo user ko allow hon (super admin = sab) --}}
    <div class="odoo-app-grid">

        @if($u->canViewModule('analytics'))
        <a class="odoo-app" href="{{ route('analytics') }}">
            <div class="odoo-icon" style="--icon-color:#7c3aed;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="22" width="8" height="14" rx="2" fill="currentColor" opacity="0.8"/>
                    <rect x="16" y="14" width="8" height="22" rx="2" fill="currentColor"/>
                    <rect x="28" y="6" width="8" height="30" rx="2" fill="currentColor" opacity="0.8"/>
                    <path d="M4 18l10-8 8 5 10-10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.5"/>
                </svg>
            </div>
            <span class="odoo-label">Analytics</span>
        </a>
        @endif

        @if($u->canViewModule('restaurant-pos'))
        <a class="odoo-app" href="{{ route('restaurant-pos.index') }}">
            <div class="odoo-icon" style="--icon-color:#0d9488;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="6" width="32" height="28" rx="4" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M4 14h32" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="24" r="3" fill="currentColor" opacity="0.8"/>
                    <circle cx="20" cy="24" r="3" fill="currentColor" opacity="0.8"/>
                    <circle cx="28" cy="24" r="3" fill="currentColor" opacity="0.8"/>
                </svg>
            </div>
            <span class="odoo-label">Restaurant POS</span>
        </a>
        @endif

        @if($u->canAccessPosClosing())
        <a class="odoo-app" href="{{ route('restaurant-pos.closing') }}">
            <div class="odoo-icon" style="--icon-color:#b45309;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="6" y="8" width="28" height="24" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M12 16h16M12 22h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="28" cy="26" r="6" fill="currentColor" opacity="0.85"/>
                    <path d="M26 26h4M28 24v4" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="odoo-label">Closing</span>
        </a>
        @endif

        @if($u->canViewModule('order-taker'))
        <a class="odoo-app" href="{{ route('order-taker.index') }}">
            <div class="odoo-icon" style="--icon-color:#14b8a6;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="6" y="10" width="28" height="22" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M12 18h16M12 24h10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    <circle cx="30" cy="12" r="5" fill="currentColor" opacity="0.85"/>
                    <path d="M28 12h4M30 10v4" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="odoo-label">Order Taker</span>
        </a>
        @endif

        @if($u->canViewModule('kitchen'))
        <a class="odoo-app" href="{{ route('kitchen.index') }}">
            <div class="odoo-icon" style="--icon-color:#ea580c;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="7" y="10" width="26" height="22" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M12 16h16M12 22h10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    <path d="M20 6v6M16 8h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="odoo-label">Kitchen</span>
        </a>
        @endif

        @if($u->canViewModule('order-status'))
        <a class="odoo-app" href="{{ route('order-status.index') }}">
            <div class="odoo-icon" style="--icon-color:#2563eb;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="6" y="8" width="28" height="24" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M12 16h16M12 22h12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    <circle cx="30" cy="14" r="4" fill="currentColor" opacity="0.85"/>
                </svg>
            </div>
            <span class="odoo-label">Order Status</span>
        </a>
        @endif

        @if($u->canViewModule('purchase'))
        <a class="odoo-app" href="{{ route('purchase.index') }}">
            <div class="odoo-icon" style="--icon-color:#22c55e;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 8h4l4 16h16l4-12H14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="18" cy="30" r="2.5" fill="currentColor"/>
                    <circle cx="28" cy="30" r="2.5" fill="currentColor"/>
                </svg>
            </div>
            <span class="odoo-label">Purchase</span>
        </a>
        @endif

        @if($u->canViewModule('inventory'))
        <a class="odoo-app" href="{{ route('inventory.index') }}">
            <div class="odoo-icon" style="--icon-color:#0ea5e9;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 4L34 12v16L20 36 6 28V12L20 4z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>
                    <path d="M20 4v32M6 12l14 8 14-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.7"/>
                </svg>
            </div>
            <span class="odoo-label">Inventory</span>
        </a>
        @endif

        @if($u->canViewModule('manufacturing'))
        <a class="odoo-app" href="{{ route('manufacturing.index') }}">
            <div class="odoo-icon" style="--icon-color:#6366f1;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 28V14l12-6 12 6v14l-12 6-12-6z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
                    <path d="M20 8v24M8 14l12 6 12-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.75"/>
                    <circle cx="20" cy="21" r="3" fill="currentColor" opacity="0.85"/>
                </svg>
            </div>
            <span class="odoo-label">Manufacturing</span>
        </a>
        @endif

        @if($u->canViewModule('maintenance'))
        <a class="odoo-app" href="{{ route('maintenance.index') }}">
            <div class="odoo-icon" style="--icon-color:#0f766e;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M24 8l8 8-4 4-8-8M8 32l9-3 12-12-6-6-12 12-3 9z" stroke="currentColor" stroke-width="2.3" stroke-linejoin="round"/>
                    <circle cx="11.5" cy="28.5" r="1.7" fill="currentColor"/>
                </svg>
            </div>
            <span class="odoo-label">Maintenance</span>
        </a>
        @endif

        @if($u->canViewModule('hr'))
        <a class="odoo-app" href="{{ route('hr.index') }}">
            <div class="odoo-icon" style="--icon-color:#ec4899;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="16" cy="13" r="5" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M6 32c0-5.523 4.477-10 10-10s10 4.477 10 10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="30" cy="15" r="3.5" stroke="currentColor" stroke-width="2" opacity="0.7"/>
                    <path d="M34 30c0-3.866-1.79-7-4-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.7"/>
                </svg>
            </div>
            <span class="odoo-label">HR</span>
        </a>
        @endif

        @if($u->canViewModule('contacts'))
        <a class="odoo-app" href="{{ route('contacts.index') }}">
            <div class="odoo-icon" style="--icon-color:#0ea5e9;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="20" cy="14" r="6" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M8 34c0-6.627 5.373-12 12-12s12 5.373 12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M30 6v8M26 10h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="odoo-label">Contacts</span>
        </a>
        @endif

        @if($u->canViewModule('credit-book'))
        <a class="odoo-app" href="{{ route('credit-book.index') }}">
            <div class="odoo-icon" style="--icon-color:#ef4444;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="5" y="6" width="30" height="28" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M5 14h30" stroke="currentColor" stroke-width="2"/>
                    <path d="M13 22h14M13 28h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".7"/>
                    <circle cx="30" cy="8" r="5" fill="currentColor" opacity=".9"/>
                    <path d="M30 6v4M28 8h4" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="odoo-label">Credit Book</span>
        </a>
        @endif

        @if($u->canViewModule('calendar'))
        <a class="odoo-app" href="{{ route('calendar.index') }}">
            <div class="odoo-icon" style="--icon-color:#f59e0b;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="5" y="8" width="30" height="27" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M5 16h30" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M13 6v5M27 6v5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    <rect x="10" y="21" width="5" height="4" rx="1" fill="currentColor" opacity="0.7"/>
                    <rect x="18" y="21" width="5" height="4" rx="1" fill="currentColor"/>
                    <rect x="26" y="21" width="5" height="4" rx="1" fill="currentColor" opacity="0.5"/>
                    <rect x="10" y="28" width="5" height="4" rx="1" fill="currentColor" opacity="0.5"/>
                    <rect x="18" y="28" width="5" height="4" rx="1" fill="currentColor" opacity="0.7"/>
                </svg>
            </div>
            <span class="odoo-label">Calendar</span>
        </a>
        @endif

        @if($u->canViewModule('expenses'))
        <a class="odoo-app" href="{{ route('expenses.index') }}">
            <div class="odoo-icon" style="--icon-color:#14b8a6;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="6" y="8" width="28" height="24" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M6 14h28" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M13 21h14M13 26h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.7"/>
                    <circle cx="30" cy="30" r="7" fill="currentColor" opacity="0.15"/>
                    <path d="M30 26v4l2.5 2.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="odoo-label">Expenses</span>
        </a>
        @endif

        @if($u->canViewModule('accounts'))
        <a class="odoo-app" href="{{ route('accounts.index') }}">
            <div class="odoo-icon" style="--icon-color:#2563eb;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="5" y="6" width="30" height="28" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M5 14h30" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 22h8M12 27h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.75"/>
                    <circle cx="30" cy="30" r="8" fill="currentColor" opacity="0.15"/>
                    <path d="M27 30h6M30 27v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="odoo-label">Accounts</span>
        </a>
        @endif

        @if($u->canViewModule('reports'))
        <a class="odoo-app" href="{{ route('reports.index') }}">
            <div class="odoo-icon" style="--icon-color:#f97316;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="22" width="8" height="14" rx="2" fill="currentColor" opacity="0.8"/>
                    <rect x="16" y="14" width="8" height="22" rx="2" fill="currentColor"/>
                    <rect x="28" y="6" width="8" height="30" rx="2" fill="currentColor" opacity="0.8"/>
                    <path d="M4 10l10-6 8 5 10-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.6"/>
                </svg>
            </div>
            <span class="odoo-label">Reports</span>
        </a>
        @endif

        @if($u->isSuperAdmin())
        <a class="odoo-app" href="{{ route('settings.index') }}">
            <div class="odoo-icon" style="--icon-color:#94a3b8;">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="20" cy="20" r="5" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M20 4v4M20 32v4M4 20h4M32 20h4M7.5 7.5l2.8 2.8M29.7 29.7l2.8 2.8M29.7 10.3l2.8-2.8M7.5 32.5l2.8-2.8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="odoo-label">Settings</span>
        </a>
        @endif

    </div>

</div>
@endsection

@section('scripts')
<script>
(function () {
    const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const greets = { morning: [5,11], afternoon: [12,17], evening: [18,23] };

    function pad(n) { return String(n).padStart(2, '0'); }

    function tick() {
        const now = new Date();
        const h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();

        // Time — blinking colon
        const colon = s % 2 === 0 ? ':' : '<span style="opacity:.25">:</span>';
        document.getElementById('iphoneTime').innerHTML = pad(h) + colon + pad(m);

        // Date
        document.getElementById('iphoneDate').textContent =
            days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()];

        // Dynamic greeting
        const greetEl = document.getElementById('greetWord');
        if (greetEl) {
            let word = 'evening';
            if (h >= 5  && h <= 11) word = 'morning';
            else if (h >= 12 && h <= 17) word = 'afternoon';
            greetEl.textContent = word;
        }
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endsection
