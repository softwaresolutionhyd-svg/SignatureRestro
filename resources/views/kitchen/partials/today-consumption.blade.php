@php
    $todayLabel = $todayLabel ?? now()->format('d M Y');
@endphp

<div class="kitchen-today-consumption">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-secondary small">Current session — served + paid</span>
        <span class="kitchen-summary-date">{{ $todayLabel }}</span>
    </div>
    <p class="kitchen-summary-hint mb-2">Is POS session me kitchen served items aur jo sale orders paid hue — recipe ke hisaab se total consumption.</p>
    @if(empty($sessionOpen))
        <div class="kitchen-summary-empty">POS session open nahi — pehle cashier/manager session start karein.</div>
    @elseif(($todayConsumption ?? []) === [])
        <div class="kitchen-summary-empty">Is session me abhi koi consumption nahi (served / paid).</div>
    @else
        <ul class="kitchen-summary-list list-unstyled mb-0">
            @foreach($todayConsumption as $row)
                <li class="kitchen-summary-row">
                    <span class="kitchen-summary-name">{{ $row['name'] }}</span>
                    <span class="kitchen-summary-qty">{{ fmt_num($row['qty'], 3) }} {{ $row['uom'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
