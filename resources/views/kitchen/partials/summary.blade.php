@php
    $todayLabel = now()->format('d M Y');
@endphp

<aside class="kitchen-summary-panel">
    <section class="kitchen-summary-card">
        <div class="kitchen-summary-card-head">
            <h6 class="mb-0">Prepare karna hai</h6>
            <span class="badge text-bg-warning">{{ count($pendingDishes) }}</span>
        </div>
        <p class="kitchen-summary-hint mb-2">Active orders se total dishes — served hone par yahan se kam hoti jayegi.</p>
        @if($pendingDishes === [])
            <div class="kitchen-summary-empty">Abhi koi pending dish nahi.</div>
        @else
            <ul class="kitchen-summary-list list-unstyled mb-0">
                @foreach($pendingDishes as $row)
                    <li class="kitchen-summary-row">
                        <span class="kitchen-summary-name">{{ $row['name'] }}</span>
                        <span class="kitchen-summary-qty">{{ fmt_num($row['qty'], 3) }} {{ $row['uom'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="kitchen-summary-card mt-3">
        <div class="kitchen-summary-card-head">
            <h6 class="mb-0">Ingredients required</h6>
            <span class="kitchen-summary-date">{{ $todayLabel }}</span>
        </div>
        <p class="kitchen-summary-hint mb-2">Active orders se recipe ingredients — served hone par yahan se kam hoti jayengi.</p>
        @if(($requiredIngredients ?? []) === [])
            <div class="kitchen-summary-empty">Abhi koi active order nahi — ingredients yahan dikhenge.</div>
        @else
            <ul class="kitchen-summary-list list-unstyled mb-0">
                @foreach($requiredIngredients as $row)
                    <li class="kitchen-summary-row">
                        <span class="kitchen-summary-name">{{ $row['name'] }}</span>
                        <span class="kitchen-summary-qty">{{ fmt_num($row['qty'], 3) }} {{ $row['uom'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</aside>
