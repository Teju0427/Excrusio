@extends('layouts.app')

@section('content')
{{-- ================================================================ HERO --}}
<section class="ex-hero" aria-labelledby="hero-title">
    @php($hero = seed_image('images/listings/hero.webp'))
    <div class="ex-hero-media {{ $hero ? '' : 'is-fallback' }}">
        @if ($hero)<img src="{{ $hero }}" alt="" fetchpriority="high">@endif
    </div>
    <div class="ex-hero-content">
        <div class="container">
            <div class="ex-eyebrow mb-3">Stays · Dining · Experiences</div>
            <h1 id="hero-title">Find your next extraordinary experience.</h1>
            <p class="ex-lead mt-3 mb-0">Exceptional stays, memorable dining, and unforgettable adventures — all in one place.</p>

            {{-- Discovery panel: one form per category; JS disables hidden panes so only one set of fields is sent. --}}
            <div class="ex-search" data-ex-search>
                <div class="ex-search-tabs" role="tablist" aria-label="What would you like to book?">
                    <button type="button" class="ex-search-tab" role="tab" id="tab-stay" aria-controls="pane-stay" aria-selected="true"><i class="bi bi-house-heart"></i>Stays</button>
                    <button type="button" class="ex-search-tab" role="tab" id="tab-dining" aria-controls="pane-dining" aria-selected="false" tabindex="-1"><i class="bi bi-cup-hot"></i>Dining</button>
                    <button type="button" class="ex-search-tab" role="tab" id="tab-activity" aria-controls="pane-activity" aria-selected="false" tabindex="-1"><i class="bi bi-compass"></i>Activities</button>
                </div>

                <form id="pane-stay" class="ex-search-pane" role="tabpanel" aria-labelledby="tab-stay" action="{{ route('listings.index', 'stays') }}" method="get">
                    <div class="ex-search-fields" style="--ex-cols: 4">
                        <div class="ex-field"><label for="s-dest">Destination</label><input id="s-dest" name="q" list="ex-destinations" placeholder="Kyoto, Coorg, Swiss Alps…" autocomplete="off"></div>
                        <div class="ex-field"><label for="s-in">Check-in</label><input id="s-in" type="date" name="check_in" min="{{ $today }}" data-ex-date-start="s-out"></div>
                        <div class="ex-field"><label for="s-out">Check-out</label><input id="s-out" type="date" name="check_out" min="{{ $tomorrow }}"></div>
                        <div class="ex-field"><label for="s-guests">Guests</label><select id="s-guests" name="guests">@for ($i = 1; $i <= 10; $i++)<option value="{{ $i }}" @selected($i === 2)>{{ $i }} {{ Str::plural('guest', $i) }}</option>@endfor</select></div>
                        <div class="ex-field"><label for="s-rooms">Rooms</label><select id="s-rooms" name="rooms">@for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }} {{ Str::plural('room', $i) }}</option>@endfor</select></div>
                        <div class="ex-search-submit"><button class="btn btn-ex-primary" type="submit"><i class="bi bi-search me-2"></i>Search stays</button></div>
                    </div>
                </form>

                <form id="pane-dining" class="ex-search-pane" role="tabpanel" aria-labelledby="tab-dining" action="{{ route('listings.index', 'dining') }}" method="get" hidden>
                    <div class="ex-search-fields" style="--ex-cols: 3">
                        <div class="ex-field"><label for="d-dest">Destination or location</label><input id="d-dest" name="q" list="ex-destinations" placeholder="Udaipur, Tuscany…" autocomplete="off"></div>
                        <div class="ex-field"><label for="d-date">Date</label><input id="d-date" type="date" name="date" min="{{ $today }}"></div>
                        <div class="ex-field"><label for="d-time">Time</label><select id="d-time" name="time"><option value="">Any time</option><option value="breakfast">Breakfast (8–11)</option><option value="lunch">Lunch (12–15)</option><option value="dinner">Dinner (19–22)</option></select></div>
                        <div class="ex-field"><label for="d-guests">Guests</label><select id="d-guests" name="guests">@for ($i = 1; $i <= 12; $i++)<option value="{{ $i }}" @selected($i === 2)>{{ $i }} {{ Str::plural('guest', $i) }}</option>@endfor</select></div>
                        <div class="ex-search-submit"><button class="btn btn-ex-primary" type="submit"><i class="bi bi-search me-2"></i>Find a table</button></div>
                    </div>
                </form>

                <form id="pane-activity" class="ex-search-pane" role="tabpanel" aria-labelledby="tab-activity" action="{{ route('listings.index', 'experiences') }}" method="get" hidden>
                    <div class="ex-search-fields" style="--ex-cols: 3">
                        <div class="ex-field"><label for="a-dest">Destination</label><input id="a-dest" name="q" list="ex-destinations" placeholder="Manali, Bali, Iceland…" autocomplete="off"></div>
                        <div class="ex-field"><label for="a-cat">Activity type</label><select id="a-cat" name="category"><option value="">All activities</option>@foreach ($activityCategories as $c)<option value="{{ $c->slug }}">{{ $c->name }}</option>@endforeach</select></div>
                        <div class="ex-field"><label for="a-date">Date</label><input id="a-date" type="date" name="date" min="{{ $today }}"></div>
                        <div class="ex-field"><label for="a-people">Participants</label><select id="a-people" name="guests">@for ($i = 1; $i <= 12; $i++)<option value="{{ $i }}" @selected($i === 2)>{{ $i }} {{ Str::plural('person', $i) }}</option>@endfor</select></div>
                        <div class="ex-search-submit"><button class="btn btn-ex-primary" type="submit"><i class="bi bi-search me-2"></i>Find experiences</button></div>
                    </div>
                </form>

                <datalist id="ex-destinations">
                    @foreach ($destinationNames as $name)<option value="{{ $name }}">@endforeach
                </datalist>
            </div>
        </div>
    </div>
</section>

{{-- ========================================================= ROW 1: STAYS --}}
<section class="ex-section" aria-labelledby="row-stays">
    <div class="container">
        <x-section-head id="row-stays" type="stay" title="Featured stays" lead="Chalets, ryokans and villas chosen for their setting and character."
            :link="route('listings.index', 'stays')" link-label="View all stays" />
        @include('pages.partials.card-row', ['items' => $stays, 'empty' => 'No stays are published yet.'])
    </div>
</section>

{{-- ======================================================== ROW 2: DINING --}}
<section class="ex-section ex-section-cream" aria-labelledby="row-dining">
    <div class="container">
        <x-section-head id="row-dining" type="dining" title="Exceptional dining" lead="Reserve a counter seat, a rooftop table or a long lunch among the vines."
            :link="route('listings.index', 'dining')" link-label="View all restaurants" />
        @include('pages.partials.card-row', ['items' => $dining, 'empty' => 'No restaurants are published yet.'])
    </div>
</section>

{{-- ================================================== ROW 3: EXPERIENCES --}}
<section class="ex-section" aria-labelledby="row-activities">
    <div class="container">
        <x-section-head id="row-activities" type="activity" title="Adventures & experiences" lead="Ski with a guide, walk a glacier, dive a wreck or learn a craft."
            :link="route('listings.index', 'experiences')" link-label="View all experiences" />
        @include('pages.partials.card-row', ['items' => $activities, 'empty' => 'No experiences are published yet.'])
    </div>
</section>

{{-- ================================================= ROW 4: DESTINATIONS --}}
<section class="ex-section ex-section-cream" aria-labelledby="row-destinations">
    <div class="container">
        <x-section-head id="row-destinations" eyebrow="Destination inspiration" title="Where will you go next?"
            :link="route('destinations.index')" link-label="All destinations" />
        <div class="ex-dest-grid">
            @foreach ($destinations as $i => $d)
                <a href="{{ route('destinations.show', $d) }}" class="ex-dest {{ $i === 0 ? 'is-wide' : '' }}">
                    <x-media :path="$d->hero_image" :alt="$d->name" type="stay" />
                    <div class="ex-dest-body">
                        <div><span class="ex-dest-name">{{ $d->name }}</span>@if ($d->local_name)<span class="ex-dest-local">{{ $d->local_name }}</span>@endif</div>
                        <div class="ex-dest-count">
                            @if ($d->listings_count > 0)
                                {{ $d->stays_count }} {{ Str::plural('stay', $d->stays_count) }} · {{ $d->dining_count }} dining · {{ $d->activities_count }} {{ Str::plural('experience', $d->activities_count) }}
                            @else
                                Coming soon
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ================================================== ROW 5: WHY EXCURSIO --}}
<section class="ex-section ex-section-forest" aria-labelledby="row-why">
    <div class="container">
        <div class="text-center mb-4">
            <div class="ex-eyebrow" style="color:#E3D3B0">Why Excursio</div>
            <h2 id="row-why" class="mt-2">One place for the whole journey</h2>
            <hr class="ex-rule mx-auto">
        </div>
        <div class="row text-center g-4">
            <div class="col-md-6 col-lg-3 ex-benefit"><span class="ex-benefit-icon"><i class="bi bi-gem"></i></span><h3>Unique places</h3><p>Ryokans, glass cabins and plantation bungalows — stays with a sense of place.</p></div>
            <div class="col-md-6 col-lg-3 ex-benefit"><span class="ex-benefit-icon"><i class="bi bi-grid-3x3-gap"></i></span><h3>Three ways to explore</h3><p>Book where you sleep, where you eat and what you do, side by side.</p></div>
            <div class="col-md-6 col-lg-3 ex-benefit"><span class="ex-benefit-icon"><i class="bi bi-journal-check"></i></span><h3>One account</h3><p>Every reservation, request and receipt together in My Bookings.</p></div>
            <div class="col-md-6 col-lg-3 ex-benefit"><span class="ex-benefit-icon"><i class="bi bi-shield-check"></i></span><h3>Clear status & refunds</h3><p>See exactly where each booking stands and what you'd get back if plans change.</p></div>
        </div>
    </div>
</section>
@endsection
