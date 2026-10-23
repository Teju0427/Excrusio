@extends('layouts.panel', ['title' => 'My account'])
@section('panel')
<div class="ex-eyebrow mb-1">My account</div>
<h1 class="mb-4" style="font-size: 2.4rem">Hello, {{ explode(' ', $user->name)[0] }}</h1>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><x-stat label="Upcoming" :value="$upcomingCount" icon="bi-calendar-event" /></div>
    <div class="col-6 col-md-3"><x-stat label="Awaiting host" :value="$pendingCount" icon="bi-hourglass-split" /></div>
    <div class="col-6 col-md-3"><x-stat label="Completed" :value="$pastCount" icon="bi-check2-circle" /></div>
    <div class="col-6 col-md-3"><x-stat label="Saved" :value="$wishlistCount" icon="bi-heart" /></div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="ex-panel h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Coming up</h2>
                <a class="small" href="{{ route('bookings.index') }}">All bookings</a>
            </div>
            @forelse ($next as $b)
                <a href="{{ route('bookings.show', $b) }}" class="d-flex gap-3 align-items-center text-reset border-top py-3">
                    <div class="rounded overflow-hidden flex-shrink-0" style="width:72px;height:56px"><x-media :path="$b->listing->coverImage?->path" :alt="$b->listing->title" :type="$b->listing_type->value" class="w-100 h-100 object-fit-cover" /></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $b->listing->title }}</div>
                        <div class="small text-muted">{{ $b->starts_at->format('D j M') }}@if ($b->time_slot_id) · {{ $b->starts_at->format('H:i') }}@endif · {{ $b->listing->destination->name }}</div>
                    </div>
                    <span class="ex-badge ex-badge-{{ $b->status->tone() }}">{{ $b->status->label() }}</span>
                </a>
            @empty
                <p class="text-muted mb-3">Nothing booked yet.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-ex-primary" href="{{ route('listings.index', 'stays') }}"><i class="bi bi-house-heart me-1"></i>Stays</a>
                    <a class="btn btn-ex-outline" href="{{ route('listings.index', 'dining') }}"><i class="bi bi-cup-hot me-1"></i>Dining</a>
                    <a class="btn btn-ex-outline" href="{{ route('listings.index', 'experiences') }}"><i class="bi bi-compass me-1"></i>Experiences</a>
                </div>
            @endforelse
        </div>
    </div>
    <div class="col-xl-5">
        <div class="ex-panel mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Notifications</h2>
                <a class="small" href="{{ route('notifications.index') }}">See all</a>
            </div>
            @include('account.partials.notification-list', ['items' => $notifications, 'compact' => true])
        </div>
        @if ($waitlist->isNotEmpty())
            <div class="ex-panel">
                <h2 class="h5 mb-2">Waitlists</h2>
                @foreach ($waitlist as $w)
                    <div class="d-flex flex-wrap gap-2 align-items-center border-top py-2 small">
                        <div class="flex-grow-1">
                            <div class="fw-medium">{{ $w->timeSlot->listing->title }}</div>
                            <div class="text-muted">{{ $w->timeSlot->starts_at->format('D j M, H:i') }} · {{ $w->party_size }} {{ Str::plural('person', $w->party_size) }}</div>
                        </div>
                        @if ($w->status === 'offered' && $w->offer_expires_at?->isFuture())
                            <a class="btn btn-sm btn-ex-primary" href="{{ route('waitlist.claim', $w) }}">Claim — {{ $w->offer_expires_at->diffForHumans(null, true) }} left</a>
                        @else
                            <span class="ex-badge ex-badge-muted">Waiting</span>
                            <form method="POST" action="{{ route('waitlist.leave', $w) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted p-0">Leave</button></form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
