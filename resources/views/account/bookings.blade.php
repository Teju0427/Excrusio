@extends('layouts.panel', ['title' => 'My bookings'])
@section('panel')
<h1 class="mb-3" style="font-size: 2.4rem">My bookings</h1>
<nav class="ex-tabs mb-4" aria-label="Booking groups">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('bookings.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }} <span class="ex-tab-count">{{ $counts[$key] }}</span></a>
    @endforeach
</nav>
@if ($bookings->isEmpty())
    <div class="ex-panel ex-empty">
        <i class="bi bi-journal"></i>
        <p class="mb-3">{{ match ($tab) { 'upcoming' => 'No upcoming bookings.', 'pending' => 'No requests waiting for a host.', 'past' => 'No past bookings yet.', default => 'Nothing cancelled.' } }}</p>
        @if ($tab === 'upcoming')<a class="btn btn-ex-primary" href="{{ route('home') }}">Find something to book</a>@endif
    </div>
@else
    <div class="d-grid gap-3">
        @foreach ($bookings as $b)
            <a href="{{ route('bookings.show', $b) }}" class="ex-panel d-flex gap-3 align-items-center text-reset">
                <div class="rounded overflow-hidden flex-shrink-0" style="width:96px;height:72px"><x-media :path="$b->listing->coverImage?->path" :alt="$b->listing->title" :type="$b->listing_type->value" class="w-100 h-100 object-fit-cover" /></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">{{ $b->listing->title }}</div>
                    <div class="small text-muted">{{ $b->reference }} · {{ $b->starts_at->format('D j M Y') }}@if ($b->time_slot_id), {{ $b->starts_at->format('H:i') }}@endif · {{ $b->listing->destination->name }}</div>
                </div>
                <span class="ex-badge ex-badge-{{ $b->status->tone() }}">{{ $b->status->label() }}</span>
            </a>
        @endforeach
    </div>
    <div class="mt-4">{{ $bookings->links() }}</div>
@endif
@endsection
