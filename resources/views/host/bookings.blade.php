@extends('layouts.panel', ['title' => 'Bookings & requests'])
@section('panel')
<h1 class="mb-4" style="font-size: 2.4rem">Bookings & requests</h1>

<div class="ex-panel mb-4">
    <h2 class="h5 mb-3">Requests to answer <span class="ex-badge ex-badge-warning ms-1">{{ $pending->count() }}</span></h2>
    @forelse ($pending as $b)
        <div class="d-flex flex-wrap gap-3 align-items-center border-top py-3">
            <div class="flex-grow-1">
                <a href="{{ route('bookings.show', $b) }}" class="fw-semibold">{{ $b->guest_name }}</a> · {{ $b->listing->title }}
                <div class="small text-muted">{{ $b->rooms }} × {{ $b->stayUnit?->name }} · {{ $b->check_in?->format('j M') }}–{{ $b->check_out?->format('j M') }} · {{ $b->guests }} guests · {{ money($b->total) }}</div>
                <div class="small {{ $b->response_due_at && $b->response_due_at->diffInHours(now(), true) < 6 ? 'ex-scarce' : 'text-muted' }}"><i class="bi bi-clock me-1"></i>Reply {{ $b->response_due_at?->diffForHumans() }}</div>
            </div>
            <form method="POST" action="{{ route('host.bookings.confirm', $b) }}">@csrf<button class="btn btn-sm btn-ex-primary">Accept</button></form>
            <form method="POST" action="{{ route('host.bookings.reject', $b) }}" class="d-flex gap-2">@csrf
                <label class="visually-hidden" for="r{{ $b->id }}">Reason</label>
                <input id="r{{ $b->id }}" name="reason" class="form-control form-control-sm" placeholder="Reason (optional)" maxlength="255" style="max-width: 180px">
                <button class="btn btn-sm btn-ex-outline">Decline</button>
            </form>
        </div>
    @empty
        <p class="text-muted mb-0">No requests waiting. Hotels with request-to-book listings get them here.</p>
    @endforelse
</div>

<div class="ex-panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0">Upcoming</h2>
        <div class="dropdown">
            <button class="btn btn-sm btn-ex-outline dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-telephone me-1"></i>Add offline booking</button>
            <ul class="dropdown-menu dropdown-menu-end">@foreach ($listings as $l)<li><a class="dropdown-item" href="{{ route('host.offline.create', $l) }}">{{ $l->title }}</a></li>@endforeach</ul>
        </div>
    </div>
    @if ($upcoming->isEmpty())
        <p class="text-muted mb-0">No upcoming bookings yet.</p>
    @else
        <div class="table-responsive"><table class="table align-middle small mb-0">
            <thead><tr><th>When</th><th>Guest</th><th>Listing</th><th>Source</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @foreach ($upcoming as $b)
                    <tr>
                        <td class="text-nowrap">{{ $b->starts_at->format('D j M, H:i') }}</td>
                        <td><a href="{{ route('bookings.show', $b) }}">{{ $b->guest_name }}</a><div class="text-muted">{{ $b->reference }} · {{ $b->guests }} {{ Str::plural('guest', $b->guests) }}</div></td>
                        <td>{{ $b->listing->title }}</td>
                        <td>{{ $b->source === 'offline' ? 'Phone / walk-in' : 'Online' }}</td>
                        <td class="text-end">
                            @if ($b->listing->listing_type->value === 'activity' && $b->time_slot_id)
                                <form method="POST" action="{{ route('host.slots.weather-cancel', $b->time_slot_id) }}" data-confirm="Cancel this whole session for weather? Every guest on it is refunded in full.">@csrf
                                    <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-cloud-lightning-rain me-1"></i>Cancel session (weather)</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div>

@if ($recent->isNotEmpty())
    <div class="ex-panel">
        <h2 class="h5 mb-3">Recently closed</h2>
        <ul class="list-unstyled small mb-0">
            @foreach ($recent as $b)
                <li class="d-flex justify-content-between border-top py-2"><span><a href="{{ route('bookings.show', $b) }}">{{ $b->reference }}</a> · {{ $b->guest_name }} · {{ $b->listing->title }}</span><span class="ex-badge ex-badge-{{ $b->status->tone() }}">{{ $b->status->label() }}</span></li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
