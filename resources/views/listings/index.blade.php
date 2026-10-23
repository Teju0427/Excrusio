@extends('layouts.app', ['title' => $type->plural()])

@php
    $seg = $type->routeSegment();
    $isStay = $type->value === 'stay';
    $isDining = $type->value === 'dining';
    $isActivity = $type->value === 'activity';
    $f = $filters;
    $chipLabels = [
        'q' => fn ($v) => '“'.$v.'”',
        'category' => fn ($v) => optional($categories->firstWhere('slug', $v))->name ?? $v,
        'guests' => fn ($v) => $v.' '.($isActivity ? Str::plural('person', $v) : Str::plural('guest', $v)),
        'rooms' => fn ($v) => $v.' '.Str::plural('room', $v),
        'min_price' => fn ($v) => 'From ₹'.number_format($v),
        'max_price' => fn ($v) => 'Up to ₹'.number_format($v),
        'check_in' => fn ($v) => 'In '.\Carbon\Carbon::parse($v)->format('j M'),
        'check_out' => fn ($v) => 'Out '.\Carbon\Carbon::parse($v)->format('j M'),
        'date' => fn ($v) => \Carbon\Carbon::parse($v)->format('D j M'),
        'time' => fn ($v) => ucfirst($v),
        'cuisine' => fn ($v) => $v,
        'price_band' => fn ($v) => str_repeat('₹', $v),
        'difficulty' => fn ($v) => ucfirst($v),
        'duration' => fn ($v) => ['short' => 'Up to 2 h', 'half' => 'Half day', 'full' => 'Full day'][$v],
        'rating' => fn ($v) => $v.'+ stars',
    ];
    $sorts = ['recommended' => 'Recommended', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'newest' => 'Newest']
        + ($hasReviews ? ['rating' => 'Top rated'] : []) + ($isActivity ? ['duration' => 'Shortest first'] : []);
@endphp

@section('content')
<header class="ex-page-head pb-4">
    <div class="container">
        <span class="ex-type-chip ex-type-{{ $type->value }}"><i class="bi {{ $type->icon() }}"></i>{{ $type->plural() }}</span>
        <h1 class="mt-3 mb-2" style="font-size: clamp(2.2rem, 4vw, 3.2rem)">
            {{ ['stay' => 'Places to stay', 'dining' => 'Tables worth travelling for', 'activity' => 'Adventures & experiences'][$type->value] }}
        </h1>
        <p class="ex-lead mb-0" aria-live="polite">
            {{ $listings->total() }} {{ Str::plural('result', $listings->total()) }}
            @if (! empty($f['check_in']) || ! empty($f['date'])) with availability for your dates @endif
        </p>
    </div>
</header>

<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row g-4">
            {{-- ======================================================= FILTERS --}}
            <aside class="col-lg-3">
                <button class="btn btn-ex-outline w-100 d-lg-none mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#filters" aria-expanded="false" aria-controls="filters">
                    <i class="bi bi-sliders me-1"></i>Filters @if (count($f)) <span class="badge text-bg-dark ms-1">{{ count($f) }}</span> @endif
                </button>
                <form method="get" id="filters" class="collapse d-lg-block ex-filters" role="search" aria-label="Filter {{ strtolower($type->plural()) }}">
                    @if ($filterErrors->any())
                        <div class="alert alert-warning small py-2" role="alert">
                            @foreach ($filterErrors->all() as $error)<div>{{ $error }}</div>@endforeach
                            <div class="mt-1 opacity-75">These filters were ignored.</div>
                        </div>
                    @endif

                    <div class="ex-filter-group">
                        <label class="form-label" for="f-q">Destination or name</label>
                        <input id="f-q" class="form-control" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Try “Nihon” or “Kodagu”">
                    </div>

                    @if ($isStay)
                        <div class="ex-filter-group">
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label" for="f-in">Check-in</label><input id="f-in" type="date" class="form-control" name="check_in" value="{{ $f['check_in'] ?? '' }}" min="{{ now('Asia/Kolkata')->toDateString() }}" data-ex-date-start="f-out"></div>
                                <div class="col-6"><label class="form-label" for="f-out">Check-out</label><input id="f-out" type="date" class="form-control" name="check_out" value="{{ $f['check_out'] ?? '' }}" min="{{ now('Asia/Kolkata')->addDay()->toDateString() }}"></div>
                                <div class="col-6"><label class="form-label" for="f-guests">Guests</label><input id="f-guests" type="number" class="form-control" name="guests" min="1" max="20" value="{{ $f['guests'] ?? '' }}"></div>
                                <div class="col-6"><label class="form-label" for="f-rooms">Rooms</label><input id="f-rooms" type="number" class="form-control" name="rooms" min="1" max="10" value="{{ $f['rooms'] ?? '' }}"></div>
                            </div>
                        </div>
                    @else
                        <div class="ex-filter-group">
                            <div class="row g-2">
                                <div class="col-7"><label class="form-label" for="f-date">Date</label><input id="f-date" type="date" class="form-control" name="date" value="{{ $f['date'] ?? '' }}" min="{{ now('Asia/Kolkata')->toDateString() }}" max="{{ now('Asia/Kolkata')->addDays(59)->toDateString() }}"></div>
                                <div class="col-5"><label class="form-label" for="f-guests">{{ $isActivity ? 'People' : 'Guests' }}</label><input id="f-guests" type="number" class="form-control" name="guests" min="1" max="20" value="{{ $f['guests'] ?? '' }}"></div>
                                <div class="col-12">
                                    <label class="form-label" for="f-time">Time of day</label>
                                    <select id="f-time" class="form-select" name="time">
                                        <option value="">Any time</option>
                                        @foreach ($isDining ? ['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner'] : ['morning' => 'Morning', 'afternoon' => 'Afternoon & evening'] as $v => $l)
                                            <option value="{{ $v }}" @selected(($f['time'] ?? '') === $v)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="ex-filter-group">
                        <label class="form-label" for="f-cat">{{ $isDining ? 'Dining type' : ($isActivity ? 'Activity' : 'Property type') }}</label>
                        <select id="f-cat" class="form-select" name="category">
                            <option value="">All</option>
                            @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected(($f['category'] ?? '') === $c->slug)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>

                    @if ($isDining)
                        <div class="ex-filter-group">
                            <label class="form-label" for="f-cuisine">Cuisine</label>
                            <select id="f-cuisine" class="form-select" name="cuisine">
                                <option value="">Any cuisine</option>
                                @foreach ($cuisines as $c)<option value="{{ $c }}" @selected(($f['cuisine'] ?? '') === $c)>{{ $c }}</option>@endforeach
                            </select>
                        </div>
                        <fieldset class="ex-filter-group">
                            <legend class="form-label">Price range</legend>
                            <div class="btn-group w-100" role="group">
                                @foreach ([1, 2, 3, 4] as $band)
                                    <input type="radio" class="btn-check" name="price_band" id="pb{{ $band }}" value="{{ $band }}" @checked((int) ($f['price_band'] ?? 0) === $band)>
                                    <label class="btn btn-sm btn-ex-outline" for="pb{{ $band }}">{{ str_repeat('₹', $band) }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    @if ($isActivity)
                        <div class="ex-filter-group">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label" for="f-diff">Difficulty</label>
                                    <select id="f-diff" class="form-select" name="difficulty"><option value="">Any</option>@foreach (['easy' => 'Easy', 'moderate' => 'Moderate', 'challenging' => 'Challenging'] as $v => $l)<option value="{{ $v }}" @selected(($f['difficulty'] ?? '') === $v)>{{ $l }}</option>@endforeach</select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="f-dur">Duration</label>
                                    <select id="f-dur" class="form-select" name="duration"><option value="">Any</option>@foreach (['short' => 'Up to 2 h', 'half' => 'Half day', 'full' => 'Full day'] as $v => $l)<option value="{{ $v }}" @selected(($f['duration'] ?? '') === $v)>{{ $l }}</option>@endforeach</select>
                                </div>
                            </div>
                        </div>
                    @endif

                    @unless ($isDining)
                        <fieldset class="ex-filter-group">
                            <legend class="form-label">{{ $isStay ? 'Price per night (₹)' : 'Price per person (₹)' }}</legend>
                            <div class="row g-2">
                                <div class="col-6"><label class="visually-hidden" for="f-min">Minimum</label><input id="f-min" type="number" class="form-control" name="min_price" min="0" step="500" placeholder="Min" value="{{ $f['min_price'] ?? '' }}"></div>
                                <div class="col-6"><label class="visually-hidden" for="f-max">Maximum</label><input id="f-max" type="number" class="form-control" name="max_price" min="0" step="500" placeholder="Max" value="{{ $f['max_price'] ?? '' }}"></div>
                            </div>
                        </fieldset>
                    @endunless

                    @if ($amenities->isNotEmpty())
                        <fieldset class="ex-filter-group">
                            <legend class="form-label">{{ $isActivity ? 'Includes' : 'Amenities' }}</legend>
                            @foreach ($amenities as $a)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="{{ $a->slug }}" id="am-{{ $a->slug }}" @checked(in_array($a->slug, $f['amenities'] ?? []))>
                                    <label class="form-check-label small" for="am-{{ $a->slug }}"><i class="bi {{ $a->icon }} me-1 text-muted"></i>{{ $a->name }}</label>
                                </div>
                            @endforeach
                        </fieldset>
                    @endif

                    @if ($hasReviews)
                        <div class="ex-filter-group">
                            <label class="form-label" for="f-rating">Guest rating</label>
                            <select id="f-rating" class="form-select" name="rating"><option value="">Any</option>@foreach (['4.5', '4', '3.5', '3'] as $r)<option value="{{ $r }}" @selected(($f['rating'] ?? '') == $r)>{{ $r }}+ stars</option>@endforeach</select>
                        </div>
                    @endif

                    <input type="hidden" name="sort" value="{{ $f['sort'] ?? 'recommended' }}">
                    <div class="d-grid gap-2">
                        <button class="btn btn-ex-primary" type="submit"><i class="bi bi-search me-1"></i>Show results</button>
                        @if (count($f))<a class="btn btn-sm btn-link" href="{{ route('listings.index', $seg) }}">Clear all filters</a>@endif
                    </div>
                </form>
            </aside>

            {{-- ======================================================= RESULTS --}}
            <div class="col-lg-9">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex flex-wrap gap-2" aria-label="Active filters">
                        @foreach (collect($f)->except(['sort', 'amenities']) as $key => $value)
                            @if (isset($chipLabels[$key]))
                                <a class="ex-chip" href="{{ route('listings.index', [$seg] + collect(request()->query())->except($key, 'page')->all()) }}" aria-label="Remove filter {{ $chipLabels[$key]($value) }}">{{ $chipLabels[$key]($value) }}<i class="bi bi-x"></i></a>
                            @endif
                        @endforeach
                        @foreach ($f['amenities'] ?? [] as $slug)
                            @php($rest = array_values(array_diff($f['amenities'], [$slug])))
                            <a class="ex-chip" href="{{ route('listings.index', [$seg] + collect(request()->query())->except('amenities', 'page')->all() + ['amenities' => $rest]) }}">{{ optional($amenities->firstWhere('slug', $slug))->name ?? $slug }}<i class="bi bi-x"></i></a>
                        @endforeach
                    </div>
                    <form method="get" class="d-flex align-items-center gap-2 ms-auto" data-ex-autosubmit>
                        @foreach (collect(request()->query())->except(['sort', 'page']) as $k => $v)
                            @if (is_array($v)) @foreach ($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
                            @else <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                        @endforeach
                        <label for="sort" class="small text-muted text-nowrap">Sort by</label>
                        <select id="sort" name="sort" class="form-select form-select-sm" style="min-width: 180px">
                            @foreach ($sorts as $v => $l)<option value="{{ $v }}" @selected(($f['sort'] ?? 'recommended') === $v)>{{ $l }}</option>@endforeach
                        </select>
                        <noscript><button class="btn btn-sm btn-ex-outline">Sort</button></noscript>
                    </form>
                </div>

                @if ($listings->isEmpty())
                    <div class="ex-empty ex-panel">
                        <i class="bi bi-binoculars"></i>
                        <p class="mb-1 fw-medium text-body">Nothing matches all of these filters.</p>
                        <p class="small mb-3">Try different dates, fewer filters or another destination.</p>
                        <a class="btn btn-ex-outline" href="{{ route('listings.index', $seg) }}">Show all {{ strtolower($type->plural()) }}</a>
                    </div>
                @else
                    <div class="row g-4">
                        @foreach ($listings as $listing)
                            <div class="col-md-6 col-xl-4">
                                <x-listing-card :listing="$listing" :saved="auth()->user()?->isCustomer() ? in_array($listing->id, $savedIds) : null" />
                            </div>
                        @endforeach
                    </div>
                    <nav class="mt-5 d-flex justify-content-center" aria-label="Results pages">{{ $listings->links() }}</nav>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
