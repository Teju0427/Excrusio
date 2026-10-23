@extends('layouts.panel', ['title' => $listing->exists ? 'Edit '.$listing->title : 'New '.$type->label()])
@section('panel')
@php
    $d = match ($type->value) { 'stay' => $listing->stayDetail, 'dining' => $listing->diningDetail, default => $listing->activityDetail };
    $v = fn ($key, $default = null) => old($key, $default);
    $creating = ! $listing->exists;
    $priceLabel = match ($type->value) { 'stay' => $creating ? 'Price per night (₹)' : 'Display “from” price (₹)', 'dining' => 'Average cost per person (₹)', default => 'Price per person (₹)' };
@endphp
<a href="{{ route('host.dashboard') }}" class="small"><i class="bi bi-arrow-left me-1"></i>Overview</a>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mt-2 mb-4">
    <div>
        <div class="ex-eyebrow mb-1">{{ $creating ? 'New listing' : 'Edit listing' }} · {{ $type->label() }}</div>
        <h1 class="mb-0" style="font-size: 2.1rem">{{ $creating ? 'Tell guests about your '.mb_strtolower($type->label()) : $listing->title }}</h1>
    </div>
    @unless ($creating)
        <span class="ex-badge ex-badge-{{ $listing->status->value === 'published' ? 'success' : ($listing->status->value === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_', ' ', $listing->status->value)) }}</span>
    @endunless
</div>
@if (! $creating && $listing->status->value === 'rejected' && $listing->rejection_reason)
    <div class="alert alert-warning"><strong>Not approved:</strong> {{ $listing->rejection_reason }} — fix it and save to resubmit.</div>
@endif
@if ($creating)
    <div class="ex-demo-note mb-4"><i class="bi bi-shield-check me-1"></i>New listings are checked by an administrator before guests can see them. Photo uploads are switched off in the demo, so a tasteful placeholder is shown.</div>
@endif

<form method="POST" action="{{ $creating ? route('host.listings.store') : route('host.listings.update', $listing) }}" novalidate>
    @csrf
    @unless ($creating) @method('PUT') @endunless
    <input type="hidden" name="listing_type" value="{{ $type->value }}">

    <div class="ex-panel mb-4">
        <h2 class="h5 mb-3">Basics</h2>
        <x-field name="title" label="Title" :value="$listing->title" required maxlength="150" />
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="f-category_id">Category</label>
                <select id="f-category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                    <option value="">Choose…</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($v('category_id', $listing->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="f-destination_id">Destination</label>
                <select id="f-destination_id" name="destination_id" class="form-select @error('destination_id') is-invalid @enderror" required>
                    <option value="">Choose…</option>
                    @foreach ($destinations as $dest)<option value="{{ $dest->id }}" @selected($v('destination_id', $listing->destination_id) == $dest->id)>{{ $dest->name }}, {{ $dest->country }}</option>@endforeach
                </select>
                @error('destination_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <x-field name="summary" label="One-line summary" :value="$listing->summary" required maxlength="300" help="Shown on cards and search results (20–300 characters)." />
        <div class="mb-3">
            <label class="form-label" for="f-description">Description</label>
            <textarea id="f-description" name="description" rows="6" class="form-control @error('description') is-invalid @enderror" required maxlength="5000">{{ $v('description', $listing->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-field name="address" label="Address" :value="$listing->address" maxlength="255" />
        <div class="row">
            <div class="col-6"><x-field name="latitude" label="Latitude" type="number" step="any" :value="$listing->latitude" help="For the map." /></div>
            <div class="col-6"><x-field name="longitude" label="Longitude" type="number" step="any" :value="$listing->longitude" /></div>
        </div>
    </div>

    <div class="ex-panel mb-4">
        <h2 class="h5 mb-3">Price & booking rules</h2>
        <div class="row">
            <div class="col-md-4"><x-field name="price" :label="$priceLabel" type="number" min="0" step="1" :value="$listing->base_price !== null ? $listing->base_price / 100 : null" required /></div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="f-booking_mode">How guests book</label>
                <select id="f-booking_mode" name="booking_mode" class="form-select">
                    <option value="instant" @selected($v('booking_mode', $listing->booking_mode?->value ?? 'instant') === 'instant')>Instant confirmation</option>
                    <option value="request" @selected($v('booking_mode', $listing->booking_mode?->value) === 'request')>Request — I approve each booking</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="f-cancellation_policy">Cancellation</label>
                <select id="f-cancellation_policy" name="cancellation_policy" class="form-select">
                    @foreach ($policies as $p)<option value="{{ $p->value }}" @selected($v('cancellation_policy', $listing->cancellation_policy?->value ?? 'moderate') === $p->value)>{{ $p->label() }}</option>@endforeach
                </select>
            </div>
        </div>
        @if (! $creating && $type->value === 'stay')
            <p class="small text-muted mb-0">Nightly prices for each room type are set on the <a href="{{ route('host.availability', $listing) }}">availability page</a>.</p>
        @endif
    </div>

    <div class="ex-panel mb-4">
        <h2 class="h5 mb-3">{{ $type->label() }} details</h2>
        @if ($type->value === 'stay')
            <div class="row">
                <div class="col-6 col-md-3"><x-field name="check_in_time" label="Check-in from" type="time" :value="substr($d->check_in_time ?? '14:00', 0, 5)" required /></div>
                <div class="col-6 col-md-3"><x-field name="check_out_time" label="Check-out by" type="time" :value="substr($d->check_out_time ?? '11:00', 0, 5)" required /></div>
                <div class="col-6 col-md-3"><x-field name="min_nights" label="Min nights" type="number" min="1" max="30" :value="$d->min_nights ?? 1" required /></div>
                <div class="col-6 col-md-3"><x-field name="max_nights" label="Max nights" type="number" min="1" max="90" :value="$d->max_nights ?? 30" required /></div>
            </div>
            <label class="form-label" for="f-house_rules">House rules <span class="text-muted fw-normal">(optional)</span></label>
            <textarea id="f-house_rules" name="house_rules" rows="3" class="form-control" maxlength="2000">{{ $v('house_rules', $d->house_rules ?? '') }}</textarea>
        @elseif ($type->value === 'dining')
            <div class="row">
                <div class="col-md-4"><x-field name="cuisine" label="Cuisine" :value="$d->cuisine ?? null" required maxlength="80" /></div>
                <div class="col-6 col-md-2 mb-3"><label class="form-label" for="f-price_band">Price band</label>
                    <select id="f-price_band" name="price_band" class="form-select">@for ($i = 1; $i <= 4; $i++)<option value="{{ $i }}" @selected($v('price_band', $d->price_band ?? 2) == $i)>{{ str_repeat('₹', $i) }}</option>@endfor</select></div>
                <div class="col-6 col-md-3"><x-field name="max_party_size" label="Largest table" type="number" min="1" max="30" :value="$d->max_party_size ?? 8" required /></div>
                <div class="col-md-3"><x-field name="deposit" label="Deposit per guest (₹)" type="number" min="0" :value="isset($d) ? $d->deposit_per_guest / 100 : 0" required help="0 = pay at the restaurant." /></div>
            </div>
            <x-field name="dress_code" label="Dress code" :value="$d->dress_code ?? null" maxlength="80" />
            <label class="form-label" for="f-policies">Table policies <span class="text-muted fw-normal">(optional)</span></label>
            <textarea id="f-policies" name="policies" rows="3" class="form-control" maxlength="2000">{{ $v('policies', $d->policies ?? '') }}</textarea>
        @else
            <div class="row">
                <div class="col-6 col-md-3"><x-field name="duration_minutes" label="Duration (min)" type="number" min="15" max="1440" :value="$d->duration_minutes ?? 120" required /></div>
                <div class="col-6 col-md-3 mb-3"><label class="form-label" for="f-difficulty">Difficulty</label>
                    <select id="f-difficulty" name="difficulty" class="form-select"><option value="">—</option>@foreach (['easy', 'moderate', 'challenging'] as $lvl)<option value="{{ $lvl }}" @selected($v('difficulty', $d->difficulty ?? null) === $lvl)>{{ ucfirst($lvl) }}</option>@endforeach</select></div>
                <div class="col-6 col-md-3"><x-field name="min_age" label="Minimum age" type="number" min="0" max="99" :value="$d->min_age ?? null" /></div>
                <div class="col-6 col-md-3"><x-field name="booking_cutoff_hours" label="Book at least (hours ahead)" type="number" min="0" max="168" :value="$d->booking_cutoff_hours ?? 12" required /></div>
            </div>
            @foreach (['inclusions' => 'What’s included', 'equipment' => 'Equipment provided', 'safety_info' => 'Safety information'] as $key => $label)
                <label class="form-label" for="f-{{ $key }}">{{ $label }} <span class="text-muted fw-normal">(optional)</span></label>
                <textarea id="f-{{ $key }}" name="{{ $key }}" rows="2" class="form-control mb-3" maxlength="2000">{{ $v($key, $d->$key ?? '') }}</textarea>
            @endforeach
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="f-weather" name="weather_dependent" value="1" @checked($v('weather_dependent', $d->weather_dependent ?? false))><label class="form-check-label" for="f-weather">Depends on the weather (shows the forecast and allows weather cancellations)</label></div>
        @endif
    </div>

    @if ($creating)
        <div class="ex-panel mb-4">
            @if ($type->value === 'stay')
                <h2 class="h5 mb-1">First room type</h2>
                <p class="small text-muted mb-3">Uses the nightly price above. Add more room types after submitting.</p>
                <div class="row">
                    <div class="col-md-6"><x-field name="unit_name" label="Name" required maxlength="120" placeholder="Deluxe Double" /></div>
                    <div class="col-6 col-md-3"><x-field name="unit_quantity" label="How many rooms" type="number" min="1" max="500" :value="1" required /></div>
                    <div class="col-6 col-md-3"><x-field name="unit_max_guests" label="Max guests" type="number" min="1" max="30" :value="2" required /></div>
                </div>
            @else
                <h2 class="h5 mb-1">When you’re open</h2>
                <p class="small text-muted mb-3">Bookable sessions are created from this pattern. You can add different hours per day later.</p>
                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Days</legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($weekdays as $i => $wd)
                            <input type="checkbox" class="btn-check" name="weekdays[]" value="{{ $i }}" id="wd{{ $i }}" @checked(in_array($i, old('weekdays', [5, 6])))><label class="btn btn-sm btn-ex-outline" for="wd{{ $i }}">{{ $wd }}</label>
                        @endforeach
                    </div>
                    @error('weekdays')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                </fieldset>
                <div class="row">
                    <div class="col-6 col-md"><x-field name="start_time" label="First start" type="time" :value="$type->value === 'dining' ? '19:00' : '09:00'" required /></div>
                    <div class="col-6 col-md"><x-field name="end_time" label="Last end" type="time" :value="$type->value === 'dining' ? '22:30' : '17:00'" required /></div>
                    <div class="col-4 col-md"><x-field name="interval_minutes" label="Every (min)" type="number" min="15" :value="$type->value === 'dining' ? 30 : 120" required /></div>
                    <div class="col-4 col-md"><x-field name="slot_length_minutes" label="Length (min)" type="number" min="15" :value="$type->value === 'dining' ? 90 : 120" required /></div>
                    <div class="col-4 col-md"><x-field name="capacity" :label="$type->value === 'dining' ? 'Seats' : 'Places'" type="number" min="1" :value="$type->value === 'dining' ? 24 : 10" required /></div>
                </div>
            @endif
        </div>
    @endif

    <div class="d-flex gap-2 justify-content-end">
        <a href="{{ route('host.dashboard') }}" class="btn btn-ex-outline">Cancel</a>
        <button class="btn btn-ex-primary">{{ $creating ? 'Submit for review' : 'Save changes' }}</button>
    </div>
</form>
@endsection
