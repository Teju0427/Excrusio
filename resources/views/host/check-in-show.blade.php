@extends('layouts.panel', ['title' => 'Check in '.$booking->reference])
@section('panel')
<a href="{{ route('host.check-in') }}" class="small"><i class="bi bi-arrow-left me-1"></i>Back to check-in</a>
<div class="ex-panel mt-3" style="max-width: 560px">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="ex-eyebrow">{{ $booking->listing->listing_type->label() }}</div>
            <h1 class="h3 mb-1">{{ $booking->guest_name }}</h1>
            <div class="text-muted">{{ $booking->listing->title }}</div>
        </div>
        <span class="ex-badge ex-badge-{{ $booking->status->tone() }}">{{ $booking->status->label() }}</span>
    </div>
    <dl class="row small mb-4">
        <dt class="col-5 text-muted fw-normal">Reference</dt><dd class="col-7 fw-medium">{{ $booking->reference }}</dd>
        <dt class="col-5 text-muted fw-normal">When</dt>
        <dd class="col-7">
            @if ($booking->stayUnit) {{ $booking->check_in->format('D j M') }} → {{ $booking->check_out->format('D j M') }} · {{ $booking->rooms }} × {{ $booking->stayUnit->name }}
            @else {{ $booking->starts_at->format('D j M, H:i') }} @endif
        </dd>
        <dt class="col-5 text-muted fw-normal">Guests</dt><dd class="col-7">{{ $booking->guests }}</dd>
        @if ($booking->special_requests ?? null)<dt class="col-5 text-muted fw-normal">Requests</dt><dd class="col-7">{{ $booking->special_requests }}</dd>@endif
    </dl>
    @if ($booking->checked_in_at)
        <div class="alert alert-success mb-0"><i class="bi bi-check2-circle me-1"></i>Checked in {{ $booking->checked_in_at->timezone($booking->listing->destination->timezone)->format('j M, H:i') }} (local time).</div>
    @else
        <form method="POST" action="{{ route('host.check-in.store', $booking->checkin_token) }}">
            @csrf
            <button class="btn btn-ex-primary btn-lg w-100"><i class="bi bi-person-check me-1"></i>Check in guest</button>
        </form>
    @endif
</div>
@endsection
