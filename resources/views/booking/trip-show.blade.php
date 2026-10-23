@extends('layouts.panel', ['title' => 'Trip '.$group->reference])
@section('panel')
<div class="ex-eyebrow mb-1">Trip {{ $group->reference }}</div>
<h1 class="mb-4" style="font-size: 2.4rem">Your trip</h1>
<div class="d-grid gap-3">
    @foreach ($bookings as $b)
        <a href="{{ route('bookings.show', $b) }}" class="ex-panel d-flex gap-3 align-items-center text-reset">
            <div class="ex-trip-time text-center"><div class="small text-muted">{{ $b->starts_at->format('D') }}</div><div class="fw-semibold">{{ $b->starts_at->format('j M') }}</div><div class="small">{{ $b->starts_at->format('H:i') }}</div></div>
            <div class="flex-grow-1"><div class="fw-semibold">{{ $b->listing->title }}</div><div class="small text-muted">{{ $b->reference }} · {{ $b->listing->destination->name }}</div></div>
            <span class="ex-badge ex-badge-{{ $b->status->tone() }}">{{ $b->status->label() }}</span>
        </a>
    @endforeach
</div>
<p class="mt-3 fw-semibold">Trip total: {{ money($group->total) }} <span class="small text-muted fw-normal">({{ $group->payment_status->label() }})</span></p>
@endsection
