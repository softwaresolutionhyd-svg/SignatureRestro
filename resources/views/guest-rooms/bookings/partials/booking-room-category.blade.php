@php
    $booking = $booking ?? null;
    $categoryId = old('room_category_id', $booking?->room_category_id ?? '');
    $categoryName = '';
    if ($categoryId && isset($categories)) {
        $categoryName = $categories->firstWhere('id', (int) $categoryId)?->name ?? '';
    }
@endphp
<div class="col-md-4">
    <label class="form-label">Room category</label>
    <input type="text"
           id="room_category_display"
           class="form-control bg-light"
           readonly
           value="{{ $categoryName }}"
           placeholder="Select room(s) to auto-fill">
    <input type="hidden" name="room_category_id" id="room_category_id" value="{{ $categoryId }}">
    <p id="room-category-msg" class="small text-warning mb-0 mt-1 d-none"></p>
</div>
