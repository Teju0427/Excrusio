@extends('layouts.app', ['title' => 'Checkout', 'ownFlash' => true])
@section('content')
@php
    $isActivity = $booking->listing_type->value === 'activity';
    $isRequest = $booking->listing_type->value === 'stay' && $booking->listing->booking_mode->value === 'request';
    $act = $booking->listing->activityDetail;
    $u = auth()->user();
@endphp
<section class="py-4 py-lg-5">
    <div class="container">
        <a href="{{ $booking->listing->url() }}" class="small"><i class="bi bi-arrow-left me-1"></i>Back to {{ $booking->listing->title }}</a>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-2 mb-4">
            <h1 class="mb-0" style="font-size: clamp(2rem, 4vw, 2.8rem)">{{ $isRequest ? 'Request to book' : 'Confirm and pay' }}</h1>
            <div class="ex-hold" data-ex-countdown="{{ $booking->hold_expires_at->toIso8601String() }}" role="timer" aria-live="off">
                <i class="bi bi-hourglass-split me-1"></i>Held for you for <strong data-ex-countdown-text>10:00</strong>
            </div>
        </div>

        @if ($errors->has('booking'))<div class="alert alert-danger" role="alert">{{ $errors->first('booking') }}</div>@endif

        <form method="POST" action="{{ route('checkout.pay', $booking) }}" class="row g-4" novalidate>
            @csrf
            <div class="col-lg-7">
                <div class="ex-panel mb-4">
                    <h2 class="h5 mb-3">Your details</h2>
                    <div class="row g-2">
                        <div class="col-md-6"><x-field name="guest_name" label="Full name" :value="$u->name" autocomplete="name" required /></div>
                        <div class="col-md-6"><x-field name="guest_email" label="Email" type="email" :value="$u->email" autocomplete="email" required /></div>
                        <div class="col-md-6"><x-field name="guest_phone" label="Phone" type="tel" :value="$u->phone" autocomplete="tel" /></div>
                    </div>
                    <label for="f-special_requests" class="form-label">Special requests <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="f-special_requests" name="special_requests" class="form-control" rows="2" maxlength="500" placeholder="{{ $booking->listing_type->value === 'dining' ? 'Allergies, occasion, seating preference…' : 'Arrival time, accessibility needs…' }}">{{ old('special_requests') }}</textarea>
                </div>

                @if ($isActivity)
                    <div class="ex-panel mb-4">
                        <h2 class="h5 mb-1">Participants</h2>
                        <p class="small text-muted mb-3">The guide needs everyone’s name and age.@if ($act?->min_age) Minimum age: {{ $act->min_age }}.@endif</p>
                        @for ($i = 0; $i < $booking->guests; $i++)
                            <div class="row g-2 mb-2">
                                <div class="col-8"><label class="form-label small" for="p{{ $i }}n">Participant {{ $i + 1 }} name</label>
                                    <input id="p{{ $i }}n" class="form-control @error("participants.$i.full_name") is-invalid @enderror" name="participants[{{ $i }}][full_name]" value="{{ old("participants.$i.full_name", $i === 0 ? $u->name : '') }}" required></div>
                                <div class="col-4"><label class="form-label small" for="p{{ $i }}a">Age</label>
                                    <input id="p{{ $i }}a" type="number" min="0" max="110" class="form-control @error("participants.$i.age") is-invalid @enderror" name="participants[{{ $i }}][age]" value="{{ old("participants.$i.age") }}" required></div>
                            </div>
                        @endfor
                        @if ($act?->safety_info)<div class="small ex-demo-note mt-3"><i class="bi bi-shield-check me-1"></i>{{ $act->safety_info }}</div>@endif
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="acknowledged" value="1" id="ack" @checked(old('acknowledged'))>
                            <label class="form-check-label small" for="ack">Everyone in my group meets the age and safety requirements above.</label>
                        </div>
                    </div>
                @endif

                <div class="ex-panel mb-4">
                    <h2 class="h5 mb-3">If your plans change</h2>
                    <x-refund-timeline :rows="$timeline" />
                </div>

                <div class="ex-panel ex-pay">
                    <h2 class="h5 mb-2">Payment</h2>
                    @if ($booking->total > 0)
                        <div class="ex-demo-pay mb-3">
                            <i class="bi bi-credit-card-2-front"></i>
                            <div><strong>Demo payment</strong><div class="small">No card details needed and no money is taken. This simulates a successful payment so you can see the full booking flow.</div></div>
                        </div>
                        @if ($isRequest)<p class="small text-muted">The host has {{ config('excursio.approval_hours') }} hours to accept. If they decline or don’t reply, you’re refunded in full.</p>@endif
                        <button class="btn btn-ex-primary btn-lg w-100" type="submit"><i class="bi bi-lock me-1"></i>{{ $isRequest ? 'Send request & pay' : 'Pay' }} {{ money($booking->total) }} <span class="small opacity-75">(demo)</span></button>
                    @else
                        <p class="small text-muted">No deposit is needed — you’ll pay at the restaurant.</p>
                        <button class="btn btn-ex-primary btn-lg w-100" type="submit">Confirm reservation</button>
                    @endif
                </div>
            </div>

            <aside class="col-lg-5">
                <div class="ex-panel position-sticky" style="top: 96px">
                    <x-booking-summary :booking="$booking" />
                    <hr>
                    <x-price-breakdown :booking="$booking" />
                    <p class="small text-muted mt-3 mb-0">Reference {{ $booking->reference }}</p>
                </div>
            </aside>
        </form>
    </div>
</section>
@endsection
