@extends('layouts.panel', ['title' => 'Booking '.$booking->reference])
@section('panel')
@php
    $l = $booking->listing;
    $s = $booking->status->value;
    $isGuest = auth()->id() === $booking->customer_id;
    $isHost = auth()->user()->can('manage', $booking) && ! auth()->user()->isAdmin();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="ex-eyebrow mb-1">Booking {{ $booking->reference }} @if ($booking->source === 'offline') · added by host @endif</div>
        <h1 class="mb-2" style="font-size: 2.2rem">{{ $l->title }}</h1>
        <span class="ex-badge ex-badge-{{ $booking->status->tone() }}">{{ $booking->status->label() }}</span>
        <span class="ex-badge ex-badge-muted ms-1">{{ $booking->payment_status->label() }}</span>
    </div>
    <button type="button" class="btn btn-sm btn-ex-outline d-print-none" data-print><i class="bi bi-printer me-1"></i>Print receipt</button>
</div>

@if ($s === 'awaiting_payment' && $isGuest)
    <div class="ex-demo-note mb-3"><i class="bi bi-hourglass-split me-1"></i>This booking isn’t finished yet. <a href="{{ route('checkout.show', $booking) }}">Continue to checkout</a></div>
@elseif ($s === 'pending')
    <div class="ex-demo-note mb-3"><i class="bi bi-hourglass-split me-1"></i>Waiting for the host to accept. They have until {{ $booking->response_due_at?->timezone('Asia/Kolkata')->format('D j M, H:i') }} IST — otherwise you’re refunded in full.</div>
@elseif (in_array($s, ['cancelled', 'rejected', 'expired']) && $booking->cancellation_reason)
    <div class="alert alert-light border mb-3"><strong>{{ $booking->status->label() }}:</strong> {{ $booking->cancellation_reason }}
        @if ($booking->timeSlot?->status === 'cancelled' && $isGuest)<a class="ms-2" href="{{ $l->url() }}">Rebook another session →</a>@endif
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-7">
        <div class="ex-panel mb-4"><x-booking-summary :booking="$booking" />
            <dl class="ex-dl mt-2 mb-0">
                <dt>Lead guest</dt><dd>{{ $booking->guest_name }}@if ($booking->guest_phone) · {{ $booking->guest_phone }}@endif</dd>
                @if ($booking->participants->isNotEmpty())<dt>Participants</dt><dd>{{ $booking->participants->map(fn ($p) => $p->full_name.' ('.$p->age.')')->implode(', ') }}</dd>@endif
                @if ($booking->special_requests)<dt>Requests</dt><dd>{{ $booking->special_requests }}</dd>@endif
            </dl>
        </div>

        @if (in_array($s, ['confirmed', 'pending']) && $booking->total > 0)
            <div class="ex-panel mb-4">
                <h2 class="h5 mb-3">Refund timeline</h2>
                <x-refund-timeline :rows="$timeline" />
            </div>
        @endif

        @if ($isGuest && in_array($s, ['awaiting_payment', 'pending', 'confirmed']))
            <div class="ex-panel d-print-none">
                <h2 class="h5 mb-2">Cancel this booking</h2>
                <p class="small mb-3">
                    @if ($refund['amount'] > 0)
                        If you cancel now, you’ll get <strong>{{ money($refund['amount']) }}</strong> back ({{ $refund['percent'] }}%, demo).
                    @elseif ($booking->total > 0 && $s !== 'awaiting_payment')
                        If you cancel now, no refund applies under the {{ strtolower($booking->cancellation_policy->label()) }} policy.
                    @else
                        Nothing has been charged, so there’s no cost to cancel.
                    @endif
                </p>
                <form method="POST" action="{{ route('bookings.cancel', $booking) }}" data-confirm="Cancel booking {{ $booking->reference }}?">
                    @csrf
                    <label for="reason" class="form-label small">Reason <span class="text-muted">(optional)</span></label>
                    <input id="reason" name="reason" class="form-control mb-2" maxlength="255">
                    <button class="btn btn-ex-outline" type="submit">Cancel booking</button>
                </form>
            </div>
        @endif

        @if ($isGuest && in_array($s, ['confirmed', 'completed']))
            <div class="ex-panel mb-4 d-print-none" id="review">
                <h2 class="h5 mb-2">Your review</h2>
                @if ($booking->review)
                    <div class="ex-stars mb-1" role="img" aria-label="{{ $booking->review->rating }} out of 5 stars">@for ($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $booking->review->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
                    <p class="mb-2" style="white-space: pre-line">{{ $booking->review->body }}</p>
                    <span class="ex-badge ex-badge-{{ ['approved' => 'success', 'rejected' => 'muted'][$booking->review->status] ?? 'warning' }}">{{ ['pending' => 'Waiting for moderation', 'approved' => 'Published', 'rejected' => 'Not published'][$booking->review->status] }}</span>
                    @if ($booking->review->status === 'rejected' && $booking->review->rejection_reason)<p class="small text-muted mt-2 mb-0">{{ $booking->review->rejection_reason }}</p>@endif
                @elseif ($reviewBlocker)
                    <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>{{ $reviewBlocker }}</p>
                @else
                    <form method="POST" action="{{ route('reviews.store', $booking) }}" novalidate>
                        @csrf
                        <fieldset class="mb-2">
                            <legend class="form-label fs-6 mb-1">How was it?</legend>
                            <div class="ex-rating-input">
                                @for ($r = 5; $r >= 1; $r--)
                                    <input type="radio" name="rating" id="rate{{ $r }}" value="{{ $r }}" @checked(old('rating') == $r) required><label for="rate{{ $r }}" title="{{ $r }} {{ Str::plural('star', $r) }}"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">{{ $r }} {{ Str::plural('star', $r) }}</span></label>
                                @endfor
                            </div>
                            @error('rating')<div class="small text-danger">{{ $message }}</div>@enderror
                        </fieldset>
                        <label for="review-body" class="form-label">Tell other travellers about it</label>
                        <textarea id="review-body" name="body" rows="4" minlength="20" maxlength="2000" required class="form-control @error('body') is-invalid @enderror">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('review')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                        <div class="d-flex justify-content-between align-items-center mt-2 gap-2">
                            <small class="text-muted">Shown with your first name and initial after a quick check.</small>
                            <button class="btn btn-ex-primary text-nowrap">Submit review</button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        @if ($isHost && $s === 'pending')
            <div class="ex-panel d-print-none">
                <h2 class="h5 mb-3">Respond to this request</h2>
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('host.bookings.confirm', $booking) }}">@csrf<button class="btn btn-ex-primary">Accept</button></form>
                    <form method="POST" action="{{ route('host.bookings.reject', $booking) }}" class="d-flex gap-2">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Reason (optional)" maxlength="255"><button class="btn btn-ex-outline">Decline</button></form>
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-5">
        <div class="ex-panel mb-4">
            <h2 class="h5 mb-3">Receipt</h2>
            <x-price-breakdown :booking="$booking" />
            @if ($booking->refund_amount)<div class="d-flex justify-content-between small mt-2 text-success"><span>Refunded (demo)</span><span>−{{ money($booking->refund_amount) }}</span></div>@endif
            <p class="small text-muted mt-3 mb-0">Demo — no payment was taken. Booked {{ $booking->created_at->timezone('Asia/Kolkata')->format('j M Y, H:i') }} IST.</p>
        </div>
        @if ($s === 'confirmed' && $booking->checkin_token)
            <div class="ex-panel text-center">
                <h2 class="h6 mb-2">Check-in code</h2>
                @if ($booking->checked_in_at)
                    <p class="text-success mb-2"><i class="bi bi-check2-circle me-1"></i>You’re checked in.</p>
                @else
                    <div class="ex-qr mx-auto mb-2" data-qr="{{ route('host.check-in.show', $booking->checkin_token) }}" role="img" aria-label="QR code for booking {{ $booking->reference }}"></div>
                @endif
                <div class="ex-ref">{{ $booking->reference }}</div>
                <p class="small text-muted mb-0 mt-2">Show this at arrival. The host scans it, or types the reference.</p>
            </div>
            @push('scripts')<script src="{{ asset('vendor/qrcode/qrcode-generator.js') }}" defer></script>@endpush
        @endif
    </div>
</div>
@endsection
