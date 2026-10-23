@extends('layouts.app', ['title' => 'Trip checkout'])
@section('content')
@php($u = auth()->user())
<section class="py-4 py-lg-5"><div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <h1 class="mb-0" style="font-size: clamp(2rem, 4vw, 2.8rem)">Book your trip</h1>
        <div class="ex-hold" data-ex-countdown="{{ $bookings->min('hold_expires_at')->toIso8601String() }}"><i class="bi bi-hourglass-split me-1"></i>All {{ $bookings->count() }} held for <strong data-ex-countdown-text>10:00</strong></div>
    </div>
    @if ($errors->has('booking'))<div class="alert alert-danger" role="alert">{{ $errors->first('booking') }}</div>@endif
    <form method="POST" action="{{ route('trip.pay', $group) }}" class="row g-4" novalidate>@csrf
        <div class="col-lg-7">
            <div class="ex-panel mb-4">
                <h2 class="h5 mb-3">Lead guest</h2>
                <div class="row g-2">
                    <div class="col-md-6"><x-field name="guest_name" label="Full name" :value="$u->name" required /></div>
                    <div class="col-md-6"><x-field name="guest_email" label="Email" type="email" :value="$u->email" required /></div>
                    <div class="col-md-6"><x-field name="guest_phone" label="Phone" type="tel" :value="$u->phone" /></div>
                </div>
                <label for="sr" class="form-label">Notes for all hosts <span class="text-muted fw-normal">(optional)</span></label>
                <textarea id="sr" name="special_requests" class="form-control" rows="2" maxlength="500">{{ old('special_requests') }}</textarea>
            </div>
            @foreach ($bookings->where('listing_type', \App\Enums\ListingType::Activity) as $b)
                <div class="ex-panel mb-4">
                    <h2 class="h6 mb-1">Participants — {{ $b->listing->title }}</h2>
                    <p class="small text-muted mb-2">@if ($b->listing->activityDetail?->min_age) Minimum age {{ $b->listing->activityDetail->min_age }}.@endif</p>
                    @for ($i = 0; $i < $b->guests; $i++)
                        <div class="row g-2 mb-2">
                            <div class="col-8"><input class="form-control" aria-label="Participant {{ $i + 1 }} name" placeholder="Participant {{ $i + 1 }} name" name="participants[{{ $b->id }}][{{ $i }}][full_name]" value="{{ old("participants.{$b->id}.$i.full_name") }}" required></div>
                            <div class="col-4"><input class="form-control" type="number" min="0" max="110" aria-label="Age" placeholder="Age" name="participants[{{ $b->id }}][{{ $i }}][age]" value="{{ old("participants.{$b->id}.$i.age") }}" required></div>
                        </div>
                    @endfor
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="acknowledged[{{ $b->id }}]" value="1" id="ack{{ $b->id }}"><label class="form-check-label small" for="ack{{ $b->id }}">Everyone meets the age and safety requirements.</label></div>
                </div>
            @endforeach
            <div class="ex-panel">
                <div class="ex-demo-pay mb-3"><i class="bi bi-credit-card-2-front"></i><div><strong>One demo payment for the whole trip</strong><div class="small">No card details and no real money. Everything is booked together, or nothing is.</div></div></div>
                <button class="btn btn-ex-primary btn-lg w-100" type="submit"><i class="bi bi-lock me-1"></i>{{ $group->total ? 'Pay '.money($group->total).' (demo)' : 'Confirm trip' }}</button>
            </div>
        </div>
        <aside class="col-lg-5">
            @foreach ($bookings as $b)
                <div class="ex-panel mb-3">
                    <x-booking-summary :booking="$b" />
                    <div class="d-flex justify-content-between mt-2 fw-semibold"><span>Total</span><span>{{ $b->total ? money($b->total) : 'Pay at venue' }}</span></div>
                    @if ($b->total)<details class="small mt-2"><summary>Refund timeline</summary><div class="mt-2"><x-refund-timeline :rows="$timelines[$b->id]" compact /></div></details>@endif
                </div>
            @endforeach
        </aside>
    </form>
</div></section>
@endsection
