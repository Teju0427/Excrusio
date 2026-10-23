@extends('layouts.panel', ['title' => 'Offline booking'])
@section('panel')
<a href="{{ route('host.bookings') }}" class="small"><i class="bi bi-arrow-left me-1"></i>Bookings</a>
<h1 class="mt-2 mb-1" style="font-size: 2.2rem">Add a phone or walk-in booking</h1>
<p class="text-muted mb-4">{{ $listing->title }} · This uses the same rooms and seats as online bookings, so the website never offers something you’ve already given away.</p>
<form method="POST" action="{{ route('host.offline.store', $listing) }}" class="ex-panel" novalidate>@csrf
    <div class="row g-2">
        <div class="col-md-6"><x-field name="guest_name" label="Guest name" required /></div>
        <div class="col-md-6"><x-field name="guest_phone" label="Guest phone" type="tel" /></div>
        @if ($listing->listing_type->value === 'stay')
            <div class="col-md-6">
                <label class="form-label" for="u">Room type</label>
                <select id="u" name="stay_unit_id" class="form-select">@foreach ($listing->stayUnits as $u)<option value="{{ $u->id }}" @selected(old('stay_unit_id') == $u->id)>{{ $u->name }} ({{ $u->quantity }} total, sleeps {{ $u->max_guests }})</option>@endforeach</select>
            </div>
            <div class="col-md-3"><x-field name="rooms" label="Rooms" type="number" value="1" min="1" required /></div>
            <div class="col-md-3"><x-field name="guests" label="Guests" type="number" value="2" min="1" required /></div>
            <div class="col-md-6"><x-field name="check_in" label="Check-in" type="date" required data-ex-date-start="f-check_out" /></div>
            <div class="col-md-6"><x-field name="check_out" label="Check-out" type="date" required /></div>
        @else
            <div class="col-md-8">
                <label class="form-label" for="s">Session</label>
                <select id="s" name="time_slot_id" class="form-select">@foreach ($slots as $slot)<option value="{{ $slot->id }}" @selected(old('time_slot_id') == $slot->id)>{{ $slot->starts_at->format('D j M · H:i') }} ({{ $slot->capacity }} capacity)</option>@endforeach</select>
            </div>
            <div class="col-md-4"><x-field name="guests" label="Guests" type="number" value="2" min="1" required /></div>
        @endif
        <div class="col-12"><label class="form-label" for="n">Notes <span class="text-muted fw-normal">(optional)</span></label><textarea id="n" name="notes" class="form-control" rows="2" maxlength="500">{{ old('notes') }}</textarea></div>
    </div>
    <button class="btn btn-ex-primary mt-3" type="submit">Add booking</button>
</form>
@endsection
