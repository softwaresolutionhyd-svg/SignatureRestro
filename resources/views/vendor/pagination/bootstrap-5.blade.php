@if ($paginator->hasPages())
    @php
        $allowedPerPage = [20, 50, 150];
        $perPage = (int) request()->query('per_page', (int) request()->session()->get('ui.per_page', 20));
        $perPage = in_array($perPage, $allowedPerPage, true) ? $perPage : 20;
        $queryWithoutPage = request()->except(['page', 'per_page']);
        $withPerPage = function (?string $url) use ($perPage): ?string {
            if ($url === null || $url === '') {
                return $url;
            }

            return $url.(str_contains($url, '?') ? '&' : '?').'per_page='.$perPage;
        };
    @endphp

    <nav class="d-flex justify-items-center justify-content-between">
        <div class="d-flex justify-content-between flex-fill d-sm-none">
            <ul class="pagination">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">@lang('pagination.previous')</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $withPerPage($paginator->previousPageUrl()) }}" rel="prev">@lang('pagination.previous')</a>
                    </li>
                @endif

                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $withPerPage($paginator->nextPageUrl()) }}" rel="next">@lang('pagination.next')</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">@lang('pagination.next')</span>
                    </li>
                @endif
            </ul>
        </div>

        <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between gap-2 flex-wrap">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <p class="small text-muted mb-0">
                    {!! __('Showing') !!}
                    <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
                    {!! __('of') !!}
                    <span class="fw-semibold">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>

                <form method="GET" action="{{ request()->url() }}" class="d-flex align-items-center gap-2">
                    @foreach ($queryWithoutPage as $key => $value)
                        @if (is_array($value))
                            @foreach ($value as $subValue)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $subValue }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="per_page" class="small text-muted mb-0">Show</label>
                    <select id="per_page" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach ($allowedPerPage as $size)
                            <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div>
                <ul class="pagination mb-0">
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                            <span class="page-link" aria-hidden="true">&lsaquo;</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $withPerPage($paginator->previousPageUrl()) }}" rel="prev" aria-label="@lang('pagination.previous')">&lsaquo;</a>
                        </li>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                                @else
                                    <li class="page-item"><a class="page-link" href="{{ $withPerPage($url) }}">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $withPerPage($paginator->nextPageUrl()) }}" rel="next" aria-label="@lang('pagination.next')">&rsaquo;</a>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                            <span class="page-link" aria-hidden="true">&rsaquo;</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
@endif
