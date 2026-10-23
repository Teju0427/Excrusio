@extends('layouts.panel', ['title' => 'Availability — '.$listing->title])
@section('panel')
<a href="{{ route('host.dashboard') }}" class="small"><i class="bi bi-arrow-left me-1"></i>Overview</a>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-2 mb-4">
    <div>
        <div class="ex-eyebrow mb-1">{{ $listing->listing_type->label() }} · {{ $listing->destination->name }}</div>
        <h1 class="mb-0" style="font-size: 2.1rem">{{ $listing->title }}</h1>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-ex-outline" href="{{ route('host.calendar', ['listing' => $listing->slug]) }}"><i class="bi bi-calendar3 me-1"></i>Calendar</a>
        <a class="btn btn-sm btn-ex-outline" href="{{ route('host.listings.edit', $listing) }}"><i class="bi bi-pencil me-1"></i>Details</a>
    </div>
</div>

@if ($isStay)
    <div class="ex-panel mb-4">
        <h2 class="h5 mb-1">Room types & prices</h2>
        <p class="small text-muted mb-3">Prices are per night in rupees. Changes apply to new bookings only — existing guests keep the price they booked.</p>
        @foreach ($units as $u)
            <form method="POST" action="{{ route('host.units.update', [$listing, $u]) }}" class="row g-2 align-items-end border-top pt-3 pb-3">
                @csrf @method('PUT')
                <div class="col-md-4"><label class="form-label small" for="u{{ $u->id }}n">Name</label><input id="u{{ $u->id }}n" name="name" class="form-control form-control-sm" value="{{ $u->name }}" required maxlength="120"></div>
                <div class="col-4 col-md-2"><label class="form-label small" for="u{{ $u->id }}p">Price (₹)</label><input id="u{{ $u->id }}p" name="price" type="number" min="100" step="1" class="form-control form-control-sm" value="{{ $u->price_per_night / 100 }}" required></div>
                <div class="col-4 col-md-2"><label class="form-label small" for="u{{ $u->id }}q">Rooms</label><input id="u{{ $u->id }}q" name="quantity" type="number" min="{{ $u->peak['rooms'] }}" max="500" class="form-control form-control-sm" value="{{ $u->quantity }}" required aria-describedby="u{{ $u->id }}h"></div>
                <div class="col-4 col-md-2"><label class="form-label small" for="u{{ $u->id }}g">Max guests</label><input id="u{{ $u->id }}g" name="max_guests" type="number" min="1" max="30" class="form-control form-control-sm" value="{{ $u->max_guests }}" required></div>
                <div class="col-md-2 d-flex gap-2 align-items-center">
                    <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="u{{ $u->id }}a" @checked($u->is_active)><label class="form-check-label small" for="u{{ $u->id }}a">On sale</label></div>
                    <button class="btn btn-sm btn-ex-primary ms-auto">Save</button>
                </div>
                @if ($u->peak['rooms'])<div class="col-12 small text-muted" id="u{{ $u->id }}h">Up to {{ $u->peak['rooms'] }} booked on one night ({{ $u->peak['date'] }}), so rooms can’t go below that.</div>@endif
            </form>
        @endforeach
        <details class="border-top pt-3">
            <summary class="small fw-medium">Add a room type</summary>
            <form method="POST" action="{{ route('host.units.store', $listing) }}" class="row g-2 align-items-end mt-1">
                @csrf
                <div class="col-md-4"><label class="form-label small" for="nu-n">Name</label><input id="nu-n" name="name" class="form-control form-control-sm" required maxlength="120" placeholder="Garden Suite"></div>
                <div class="col-4 col-md-2"><label class="form-label small" for="nu-p">Price (₹)</label><input id="nu-p" name="price" type="number" min="100" class="form-control form-control-sm" required></div>
                <div class="col-4 col-md-2"><label class="form-label small" for="nu-q">Rooms</label><input id="nu-q" name="quantity" type="number" min="1" max="500" class="form-control form-control-sm" required></div>
                <div class="col-4 col-md-2"><label class="form-label small" for="nu-g">Max guests</label><input id="nu-g" name="max_guests" type="number" min="1" max="30" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><button class="btn btn-sm btn-ex-primary w-100">Add</button></div>
            </form>
        </details>
    </div>

    <div class="ex-panel">
        <h2 class="h5 mb-1">Closed dates</h2>
        <p class="small text-muted mb-3">Stop new bookings for maintenance, private events or offline sales elsewhere. Guests already booked are not affected.</p>
        <form method="POST" action="{{ route('host.blocks.store', $listing) }}" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-3"><label class="form-label small" for="b-unit">Room type</label>
                <select id="b-unit" name="stay_unit_id" class="form-select form-select-sm"><option value="">Whole property</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected(old('stay_unit_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small" for="b-from">From</label><input id="b-from" type="date" name="start_date" class="form-control form-control-sm" min="{{ now()->toDateString() }}" value="{{ old('start_date') }}" required></div>
            <div class="col-6 col-md-2"><label class="form-label small" for="b-to">Until (check-out)</label><input id="b-to" type="date" name="end_date" class="form-control form-control-sm" min="{{ now()->addDay()->toDateString() }}" value="{{ old('end_date') }}" required></div>
            <div class="col-4 col-md-1"><label class="form-label small" for="b-rooms">Rooms</label><input id="b-rooms" type="number" name="rooms" min="1" class="form-control form-control-sm" placeholder="All" value="{{ old('rooms') }}"></div>
            <div class="col-8 col-md-2"><label class="form-label small" for="b-reason">Reason</label><input id="b-reason" name="reason" maxlength="200" class="form-control form-control-sm" placeholder="Optional" value="{{ old('reason') }}"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-ex-primary w-100">Close dates</button></div>
            @foreach (['start_date', 'end_date', 'rooms'] as $f)@error($f)<div class="col-12 small text-danger">{{ $message }}</div>@enderror @endforeach
        </form>
        @forelse ($blocks as $b)
            <div class="d-flex justify-content-between align-items-center border-top py-2 small gap-2">
                <span><strong>{{ $b->start_date->format('j M') }} → {{ $b->end_date->format('j M Y') }}</strong> · {{ $b->stay_unit_id ? ($units->firstWhere('id', $b->stay_unit_id)?->name ?? 'Room type') : 'Whole property' }}{{ $b->rooms ? ' · '.$b->rooms.' '.Str::plural('room', $b->rooms) : '' }}@if ($b->reason) · <span class="text-muted">{{ $b->reason }}</span>@endif</span>
                <form method="POST" action="{{ route('host.blocks.destroy', [$listing, $b]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted p-0">Reopen</button></form>
            </div>
        @empty
            <p class="small text-muted mb-0">No closed dates.</p>
        @endforelse
    </div>
@else
    <div class="ex-panel mb-4">
        <h2 class="h5 mb-1">Weekly schedule</h2>
        <p class="small text-muted mb-3">Sessions are generated 60 days ahead from these rules. Sessions that already have guests are never changed or removed.</p>
        <form method="POST" action="{{ route('host.schedules.save', $listing) }}" id="ex-schedule">
            @csrf @method('PUT')
            <div class="table-responsive">
                <table class="table table-sm align-middle small mb-2">
                    <thead><tr><th>Day</th><th>First start</th><th>Last end</th><th>Every (min)</th><th>Length (min)</th><th>{{ $listing->listing_type->value === 'dining' ? 'Seats' : 'Places' }}</th><th></th></tr></thead>
                    <tbody data-rows>
                        @foreach (old('rules', $listing->schedules->sortBy(['weekday', 'start_time'])->map(fn ($s) => ['weekday' => $s->weekday, 'start_time' => substr($s->start_time, 0, 5), 'end_time' => substr($s->end_time, 0, 5), 'interval_minutes' => $s->interval_minutes, 'slot_length_minutes' => $s->slot_length_minutes, 'capacity' => $s->capacity])->values()->all()) ?: [['weekday' => 6, 'start_time' => '10:00', 'end_time' => '17:00', 'interval_minutes' => 60, 'slot_length_minutes' => 60, 'capacity' => 10]] as $i => $r)
                            <tr data-row>
                                <td style="min-width: 5.5rem"><select name="rules[{{ $i }}][weekday]" class="form-select form-select-sm" aria-label="Day">@foreach ($weekdays as $wi => $wd)<option value="{{ $wi }}" @selected($r['weekday'] == $wi)>{{ $wd }}</option>@endforeach</select></td>
                                <td><input type="time" name="rules[{{ $i }}][start_time]" value="{{ $r['start_time'] }}" class="form-control form-control-sm" required aria-label="First start"></td>
                                <td><input type="time" name="rules[{{ $i }}][end_time]" value="{{ $r['end_time'] }}" class="form-control form-control-sm" required aria-label="Last end"></td>
                                <td><input type="number" name="rules[{{ $i }}][interval_minutes]" value="{{ $r['interval_minutes'] }}" min="15" max="720" class="form-control form-control-sm" required aria-label="Every (minutes)"></td>
                                <td><input type="number" name="rules[{{ $i }}][slot_length_minutes]" value="{{ $r['slot_length_minutes'] }}" min="15" max="1440" class="form-control form-control-sm" required aria-label="Length (minutes)"></td>
                                <td><input type="number" name="rules[{{ $i }}][capacity]" value="{{ $r['capacity'] }}" min="1" max="500" class="form-control form-control-sm" required aria-label="Capacity"></td>
                                <td><button type="button" class="btn btn-sm btn-link text-muted" data-remove aria-label="Remove rule"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($errors->hasAny(['rules', 'rules.*']))<div class="small text-danger mb-2">{{ collect($errors->getMessages())->filter(fn ($v, $k) => str_starts_with($k, 'rules'))->flatten()->first() }}</div>@endif
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-ex-outline" data-add><i class="bi bi-plus me-1"></i>Add rule</button>
                <button class="btn btn-sm btn-ex-primary ms-auto">Save schedule</button>
            </div>
        </form>
    </div>

    <div class="ex-panel">
        <h2 class="h5 mb-1">Next 7 days</h2>
        <p class="small text-muted mb-3">Close a session to stop new bookings (booked guests keep their places), or cancel it for weather — everyone booked is refunded in full.</p>
        @forelse ($sessions->groupBy(fn ($s) => $s->starts_at->toDateString()) as $date => $daySessions)
            <h3 class="h6 mt-3 mb-2">{{ \Carbon\Carbon::parse($date)->format('l j M') }}</h3>
            <div class="ex-sessions">
            @foreach ($daySessions as $s)
                <div class="ex-session {{ $s->status !== 'open' ? 'is-'.$s->status : '' }}">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <span class="fw-semibold">{{ $s->starts_at->format('H:i') }}</span>
                        <span class="small text-muted">{{ (int) $s->booked }}/{{ $s->capacity }}</span>
                    </div>
                    @if ($s->status === 'cancelled')
                        <div class="small text-muted mt-1">Cancelled</div>
                    @else
                        <div class="d-flex gap-2 align-items-center mt-1">
                            <form method="POST" action="{{ route('host.slots.status', $s) }}">@csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $s->status === 'open' ? 'closed' : 'open' }}">
                                <button class="btn btn-link btn-sm p-0 small" aria-label="{{ $s->status === 'open' ? 'Close' : 'Reopen' }} {{ $s->starts_at->format('D j M H:i') }} session">{{ $s->status === 'open' ? 'Close' : 'Reopen' }}</button>
                            </form>
                            @if ($listing->listing_type->value === 'activity' && $s->booked)
                                <form method="POST" action="{{ route('host.slots.weather-cancel', $s) }}" data-confirm="Cancel this session and refund everyone booked?">@csrf
                                    <input type="hidden" name="reason" value="Unsafe weather"><button class="btn btn-link btn-sm p-0 small text-danger" title="Cancel for weather and refund everyone"><i class="bi bi-cloud-lightning-rain"></i><span class="visually-hidden">Weather cancel</span></button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
            </div>
        @empty
            <p class="small text-muted mb-0">No sessions in the next 7 days. Add schedule rules above.</p>
        @endforelse
    </div>
@endif
@endsection
