@php
    $todayLabel = $todayLabel ?? now()->format('d M Y');
@endphp

<div class="kitchen-today-consumption">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-secondary small">Served + paid orders se ingredients</span>
        <span class="kitchen-summary-date">{{ $todayLabel }}</span>
    </div>
    <p class="kitchen-summary-hint mb-2">Aaj kitchen ne jo served mark ki hain ya POS par jin orders ko paid kiya — recipe ke hisaab se total consumption.</p>
    @if(($todayConsumption ?? []) === [])
        <div class="kitchen-summary-empty">Aaj abhi koi consumption nahi (served / paid).</div>
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
