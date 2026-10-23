@extends('layouts.app', ['title' => $listing->title, 'description' => $listing->summary, 'ownFlash' => true])

@section('content')
@php($d = $listing->destination)
<section class="pt-4 pt-lg-5">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('listings.index', $type->routeSegment()) }}">{{ $type->plural() }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $listing->title }}</li>
            </ol>
        </nav>
        <span class="ex-type-chip ex-type-{{ $type->value }}"><i class="bi {{ $type->icon() }}"></i>{{ $listing->category->name }}</span>
        <div class="d-flex justify-content-between align-items-start gap-3">
            <h1 class="mt-3 mb-2" style="font-size: clamp(2.2rem, 4.5vw, 3.6rem)">{{ $listing->title }}</h1>
            @if (auth()->user()?->isCustomer())
                <x-wishlist-button :listing="$listing" :saved="$saved" class="mt-3 flex-shrink-0" />
            @elseif (! auth()->check())
                <a href="{{ route('login') }}" class="ex-heart mt-3 flex-shrink-0" aria-label="Sign in to save {{ $listing->title }}"><i class="bi bi-heart"></i></a>
            @endif
        </div>
        <p class="text-muted mb-4"><i class="bi bi-geo-alt me-1"></i>{{ $d->region }}, {{ $d->name }}, {{ $d->country }}
            @if ($d->local_name)<span class="ex-serif ms-1" style="color: var(--ex-gold-text); font-size: 1.1rem">{{ $d->local_name }}</span>@endif
            <span class="mx-2">·</span>
            @if ($listing->reviews_count)<a href="#reviews" class="ex-rating text-reset"><i class="bi bi-star-fill"></i>{{ number_format($listing->rating_avg, 1) }} ({{ $listing->reviews_count }} {{ Str::plural('review', $listing->reviews_count) }})</a>@else<span>No reviews yet</span>@endif
        </p>

        {{-- Gallery adapts to the number of photos (1, 2 or 3) --}}
        @php($gallery = $listing->images->take(3))
        <div class="ex-gallery mb-5" data-count="{{ max(1, $gallery->count()) }}">
            @forelse ($gallery as $i => $img)
                <div><x-media :path="$img->path" :alt="$img->alt" :type="$type->value" :eager="$i === 0" sizes="(min-width: 768px) 66vw, 100vw" /></div>
            @empty
                <div><x-media :path="null" :alt="$listing->title" :type="$type->value" /></div>
            @endforelse
        </div>

        <div class="row g-4 g-lg-5">
            <div class="col-lg-7">
                <p class="fs-5 ex-serif" style="font-size:1.5rem!important; line-height:1.4">{{ $listing->summary }}</p>
                <hr class="ex-rule">
                @foreach (preg_split('/\n\s*\n/', $listing->description) as $para)
                    <p>{{ $para }}</p>
                @endforeach

                @if ($listing->amenities->isNotEmpty())
                    <h2 class="h3 mt-5 mb-3">{{ $type->value === 'activity' ? 'What’s included' : 'Amenities' }}</h2>
                    <ul class="row list-unstyled g-2">
                        @foreach ($listing->amenities as $a)<li class="col-6"><i class="bi {{ $a->icon }} me-2 text-muted"></i>{{ $a->name }}</li>@endforeach
                    </ul>
                @endif

                @if ($type->value === 'stay')
                    <h2 class="h3 mt-5 mb-3">Rooms & options</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Room</th><th>Sleeps</th><th>Beds</th><th class="text-end">From / night</th></tr></thead>
                            <tbody>
                                @foreach ($listing->stayUnits as $u)
                                    <tr><td class="fw-medium">{{ $u->name }}</td><td>{{ $u->max_guests }}</td><td class="text-muted small">{{ $u->bed_configuration }}</td><td class="text-end fw-semibold">{{ money($u->price_per_night) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($listing->stayDetail)
                        <p class="small text-muted">Check-in from {{ substr($listing->stayDetail->check_in_time, 0, 5) }} · Check-out by {{ substr($listing->stayDetail->check_out_time, 0, 5) }} · Minimum {{ $listing->stayDetail->min_nights }} {{ Str::plural('night', $listing->stayDetail->min_nights) }}</p>
                    @endif
                @elseif ($type->value === 'dining' && $listing->diningDetail)
                    <h2 class="h3 mt-5 mb-3">Good to know</h2>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-egg-fried me-2 text-muted"></i>{{ $listing->diningDetail->cuisine }} · {{ str_repeat('₹', $listing->diningDetail->price_band) }}</li>
                        <li class="mb-2"><i class="bi bi-people me-2 text-muted"></i>Parties of up to {{ $listing->diningDetail->max_party_size }}</li>
                        @if ($listing->diningDetail->dress_code)<li class="mb-2"><i class="bi bi-person-badge me-2 text-muted"></i>{{ $listing->diningDetail->dress_code }}</li>@endif
                        <li class="mb-2"><i class="bi bi-wallet2 me-2 text-muted"></i>{{ $listing->diningDetail->deposit_per_guest ? 'Deposit '.money($listing->diningDetail->deposit_per_guest).' per guest (demo)' : 'No deposit — pay at the restaurant' }}</li>
                    </ul>
                @elseif ($type->value === 'activity' && $act = $listing->activityDetail)
                    <h2 class="h3 mt-5 mb-3">Before you go</h2>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-hourglass-split me-2 text-muted"></i>{{ intdiv($act->duration_minutes, 60) }}h{{ $act->duration_minutes % 60 ? ' '.($act->duration_minutes % 60).'m' : '' }}</li>
                        @if ($act->difficulty)<li class="mb-2"><i class="bi bi-bar-chart me-2 text-muted"></i>{{ ucfirst($act->difficulty) }}</li>@endif
                        @if ($act->min_age)<li class="mb-2"><i class="bi bi-person-check me-2 text-muted"></i>Minimum age {{ $act->min_age }}</li>@endif
                        @if ($act->weather_dependent)<li class="mb-2"><i class="bi bi-cloud-sun me-2 text-muted"></i>Weather-dependent: full refund if the provider cancels for weather</li>@endif
                    </ul>
                    @if ($act->safety_info)<div class="ex-panel small mt-3"><strong class="d-block mb-1">Safety</strong>{{ $act->safety_info }}</div>@endif
                @endif

                @if ($forecast)
                    <h2 class="h3 mt-5 mb-1">Weather this week</h2>
                    <p class="small text-muted mb-3">Forecast for the activity area by Open-Meteo. If the provider cancels for weather, you get a full refund.</p>
                    <div class="ex-forecast" role="list">
                        @foreach ($forecast as $day)
                            <div class="ex-forecast-day" role="listitem" title="{{ $day['label'] }}">
                                <div class="small fw-medium">{{ \Carbon\Carbon::parse($day['date'])->format('D') }}</div>
                                <i class="bi {{ $day['icon'] }}" aria-hidden="true"></i>
                                <div class="small">{{ $day['max'] }}° <span class="text-muted">{{ $day['min'] }}°</span></div>
                                @if ($day['rain'] !== null)<div class="ex-forecast-rain"><i class="bi bi-droplet"></i>{{ $day['rain'] }}%</div>@endif
                                <span class="visually-hidden">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($listing->latitude)
                    <h2 class="h3 mt-5 mb-1">Location</h2>
                    <p class="small text-muted mb-3">{{ $listing->address }} · approximate area shown; the exact address is shared after booking.</p>
                    <div id="ex-map" class="ex-map" data-lat="{{ $listing->latitude }}" data-lng="{{ $listing->longitude }}" data-title="{{ $listing->title }}" role="img" aria-label="Map of the area around {{ $listing->title }}"></div>
                @endif

                <h2 class="h3 mt-5 mb-3">Cancellation policy</h2>
                <p><strong>{{ $listing->cancellation_policy->label() }}.</strong> {{ $listing->cancellation_policy->summary() }}</p>

                @include('listings.partials.reviews')

                @auth
                    <details class="mt-4 small">
                        <summary class="text-muted"><i class="bi bi-flag me-1"></i>Report this listing</summary>
                        <form method="POST" action="{{ route('listings.report', $listing) }}" class="ex-panel mt-2">
                            @csrf
                            <label class="form-label" for="rep-reason">What’s wrong?</label>
                            <select id="rep-reason" name="reason" class="form-select form-select-sm mb-2" required>
                                @foreach (\App\Http\Controllers\ReportController::REASONS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                            <label class="form-label" for="rep-details">Details <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea id="rep-details" name="details" rows="2" maxlength="1000" class="form-control form-control-sm mb-2"></textarea>
                            <button class="btn btn-sm btn-ex-outline">Send to Excursio</button>
                        </form>
                    </details>
                @endauth
            </div>

            <aside class="col-lg-5">
                <div class="ex-panel ex-book position-sticky" style="top: 96px">
                    <div class="ex-price fs-4 mb-1">
                        <span class="ex-price-from">From</span>
                        {{ money($type->value === 'stay' ? ($listing->stayUnits->min('price_per_night') ?? $listing->base_price) : $listing->base_price) }} <small>{{ $listing->priceUnitLabel() }}</small>
                    </div>
                    <p class="small text-muted mb-3">
                        @if ($type->value === 'dining')
                            {{ $listing->diningDetail?->deposit_per_guest ? 'Deposit of '.money($listing->diningDetail->deposit_per_guest).' per guest (demo).' : 'No deposit — pay at the restaurant.' }}
                        @else
                            {{ $listing->booking_mode->value === 'request' ? 'Request to book — the host confirms within 24 hours.' : 'Instant confirmation.' }}
                        @endif
                    </p>

                    @if (session('status'))<div class="alert alert-success small py-2" role="status">{{ session('status') }}</div>@endif
                    @if ($errors->has('booking'))<div class="alert alert-danger small py-2" role="alert">{{ $errors->first('booking') }}</div>@endif
                    @if ($type->value === 'stay')
                        <form class="ex-avail" data-ex-stay="{{ route('availability.stay', $listing) }}" @include('listings.partials.book-attrs') novalidate>
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label small" for="a-in">Check-in</label><input id="a-in" type="date" class="form-control" name="check_in" min="{{ $today }}" required data-ex-date-start="a-out"></div>
                                <div class="col-6"><label class="form-label small" for="a-out">Check-out</label><input id="a-out" type="date" class="form-control" name="check_out" min="{{ $today }}" required></div>
                                <div class="col-6"><label class="form-label small" for="a-guests">Guests</label><input id="a-guests" type="number" class="form-control" name="guests" min="1" max="20" value="2" required></div>
                                <div class="col-6"><label class="form-label small" for="a-rooms">Rooms</label><input id="a-rooms" type="number" class="form-control" name="rooms" min="1" max="10" value="1"></div>
                            </div>
                            <button class="btn btn-ex-primary w-100 mt-3" type="submit">Check availability</button>
                        </form>
                    @else
                        <form class="ex-avail" data-ex-slots="{{ route('availability.slots', $listing) }}" data-unit="{{ $type->value === 'dining' ? 'seats' : 'places' }}" @include('listings.partials.book-attrs') novalidate>
                            <div class="row g-2">
                                <div class="col-7"><label class="form-label small" for="a-date">Date</label><input id="a-date" type="date" class="form-control" name="date" min="{{ $today }}" max="{{ $maxDate }}" value="{{ $today }}" required></div>
                                <div class="col-5"><label class="form-label small" for="a-guests">{{ $type->value === 'dining' ? 'Guests' : 'People' }}</label><input id="a-guests" type="number" class="form-control" name="guests" min="1" max="{{ $listing->diningDetail?->max_party_size ?? 20 }}" value="2" required></div>
                            </div>
                            <button class="btn btn-ex-primary w-100 mt-3" type="submit">See available times</button>
                        </form>
                    @endif

                    <div class="ex-avail-result mt-3" aria-live="polite"></div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-clock me-1"></i>Times and dates are local to {{ $d->name }}. You won’t be charged until checkout (demo payments only).</p>
                </div>
            </aside>
        </div>
    </div>
</section>
<div class="pb-5"></div>
@endsection

@if ($listing->latitude)
    @push('head')<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">@endpush
    @push('scripts')<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>@endpush
@endif
