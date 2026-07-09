@php
    $booking = $booking ?? null;
    $defaultOnlineCategory = $defaultOnlineCategory ?? \App\Models\RoomCategory::defaultOnlineCategory();
    $defaultOnlineCategoryId = $defaultOnlineCategory?->id;
    $onlineCategoryLabel = $defaultOnlineCategory?->name ?? \App\Models\RoomCategory::DEFAULT_ONLINE_CATEGORY_NAME;
    $isOnlineOld = old('booking_type', $booking?->booking_type ?? 'manual') === \App\Models\RoomBooking::TYPE_ONLINE;
    $selectedId = old('room_category_id', $booking?->room_category_id ?? request('room_category_id') ?? ($isOnlineOld ? $defaultOnlineCategoryId : ''));
@endphp
<div class="col-md-3" id="room-type-field-wrap">
    <label class="form-label">Room type *</label>
    <input type="hidden" name="room_category_id" id="room_category_id_value" value="{{ $selectedId }}">
    <div id="room-type-online-display" class="{{ $isOnlineOld ? '' : 'd-none' }}">
        <input type="text" class="form-control bg-light" id="room_type_online_label"
               value="{{ $onlineCategoryLabel }}" readonly tabindex="-1" aria-readonly="true">
    </div>
    <select id="room_category_id" name="room_category_select" class="form-select {{ $isOnlineOld ? 'd-none' : '' }}"
            data-online-category-id="{{ $defaultOnlineCategoryId }}"
            data-online-category-name="{{ $onlineCategoryLabel }}">
        <option value="">Select room type</option>
        @foreach($categories as $cat)
            <option value="{{ $cat->id }}" @selected((string) $selectedId === (string) $cat->id)>{{ $cat->name }}</option>
        @endforeach
    </select>
</div>
