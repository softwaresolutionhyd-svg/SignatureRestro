@php
    $activeLocale = app()->getLocale();
    $labels = [
        'en' => __('English'),
        'ur' => __('Urdu'),
    ];
@endphp

<form method="POST"
      action="{{ route('locale.switch') }}"
      class="locale-switcher d-inline-flex align-items-center gap-1 flex-shrink-0"
      aria-label="{{ __('Language') }}">
    @csrf
    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
    <input type="hidden" name="locale" value="">

    @foreach (['en', 'ur'] as $localeCode)
        <button type="submit"
                name="locale"
                value="{{ $localeCode }}"
                class="btn btn-sm locale-switcher-btn {{ $activeLocale === $localeCode ? 'btn-primary' : 'btn-outline-secondary' }}">
            <span class="locale-label-full">{{ $labels[$localeCode] }}</span>
            <span class="locale-label-short">{{ strtoupper($localeCode) }}</span>
        </button>
    @endforeach
</form>
