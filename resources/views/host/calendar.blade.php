@extends('layouts.panel', ['title' => 'Calendar'])
@section('panel')
@php
    $prev = $month->subMonth()->format('Y-m'); $next = $month->addMonth()->format('Y-m');
    $today = now()->toDateString();
    $lead = $month->startOfMonth()->dayOfWeekIso - 1;   // Monday-first grid
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <h1 class="mb-0" style="font-size: 2.4rem">Calendar</h1>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label for="cal-listing" class="visually-hidden">Listing</label>
        <select id="cal-listing" name="listing" class="form-select form-select-sm" data-autosubmit style="max-width: 280px">
            @foreach ($listings as $l)<option value="{{ $l->slug }}" @selected($l->is($listing))>{{ $l->title }}</option>@endforeach
        </select>
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <noscript><button class="btn btn-sm btn-ex-outline">Show</button></noscript>
    </form>
</div>

<div class="ex-panel mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-sm btn-ex-outline" href="{{ route('host.calendar', ['listing' => $listing->slug, 'month' => $prev]) }}" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
        <h2 class="h5 mb-0">{{ $month->format('F Y') }}</h2>
        <a class="btn btn-sm btn-ex-outline" href="{{ route('host.calendar', ['listing' => $listing->slug, 'month' => $next]) }}" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
    </div>
    <div class="ex-cal" role="grid" aria-label="{{ $month->format('F Y') }} — {{ $listing->title }}">
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $w)<div class="ex-cal-head" role="columnheader">{{ $w }}</div>@endforeach
        @for ($i = 0; $i < $lead; $i++)<div aria-hidden="true"></div>@endfor
        @foreach ($days as $date => $d)
            @php $pct = $d['capacity'] ? min(100, round($d['used'] / $d['capacity'] * 100)) : 0; @endphp
            <a role="gridcell" href="{{ route('host.calendar', ['listing' => $listing->slug, 'month' => $month->format('Y-m'), 'day' => $date]) }}#day"
               class="ex-cal-day {{ $date === $today ? 'is-today' : '' }} {{ $date < $today ? 'is-past' : '' }} {{ $d['blocked'] ? 'is-blocked' : '' }} {{ $day === $date ? 'is-selected' : '' }}"
               aria-label="{{ \Carbon\Carbon::parse($date)->format('l j F') }}: {{ $d['used'] }} of {{ $d['capacity'] }} {{ $d['unit'] }} booked{{ $d['blocked'] ? ', closed' : '' }}">
                <span class="ex-cal-num">{{ (int) substr($date, 8) }}</span>
                <span>
                    <span class="ex-cal-meta d-block">{{ $d['capacity'] ? $d['used'].'/'.$d['capacity'].' '.$d['unit'] : ($d['blocked'] ? 'Closed' : '—') }}</span>
                    <span class="ex-cal-bar d-block mt-1"><span style="width: {{ $pct }}%"></span></span>
                </span>
            </a>
        @endforeach
    </div>
    <div class="d-flex flex-wrap gap-3 small text-muted mt-3">
        <span><span class="d-inline-block align-middle me-1" style="width:18px;height:4px;border-radius:4px;background:var(--ex-forest)"></span>Share of {{ $listing->listing_type->value === 'stay' ? 'rooms' : 'capacity' }} booked</span>
        <span><span class="d-inline-block align-middle me-1 ex-cal-day is-blocked p-0" style="width:14px;height:14px;min-height:0"></span>Closed to new bookings</span>
        <a href="{{ route('host.availability', $listing) }}" class="ms-auto"><i class="bi bi-sliders me-1"></i>Manage availability</a>
    </div>
</div>

@if ($day)
    <div class="ex-panel" id="day">
        <h2 class="h5 mb-3">{{ \Carbon\Carbon::parse($day)->format('l j F Y') }}</h2>
        @if ($dayBookings->isEmpty())
            <p class="text-muted mb-0">No bookings on this day.</p>
        @else
            <div class="table-responsive"><table class="table table-sm align-middle small mb-0">
                <thead><tr><th>{{ $listing->listing_type->value === 'stay' ? 'Stay' : 'Time' }}</th><th>Guest</th><th>Party</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($dayBookings as $b)
                        <tr>
                            <td class="text-nowrap">@if ($b->stayUnit){{ $b->check_in->format('j M') }}–{{ $b->check_out->format('j M') }} · {{ $b->rooms }}× {{ $b->stayUnit->name }}@else{{ $b->starts_at->format('H:i') }}@endif</td>
                            <td>{{ $b->guest_name }}@if ($b->source === 'offline') <span class="ex-badge ex-badge-muted ms-1">Offline</span>@endif</td>
                            <td>{{ $b->guests }}</td>
                            <td><span class="ex-badge ex-badge-{{ $b->status->tone() }}">{{ $b->status->label() }}</span>@if ($b->checked_in_at) <i class="bi bi-person-check text-success ms-1" title="Checked in"></i>@endif</td>
                            <td class="text-end"><a href="{{ route('bookings.show', $b) }}">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
@endif
@endsection
