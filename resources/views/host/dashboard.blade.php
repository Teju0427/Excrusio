@extends('layouts.panel', ['title' => 'Host overview'])
@section('panel')
@php
    $types = \App\Enums\ListingType::cases();
    $hostTypes = collect($types)->filter(fn ($t) => $listings->contains(fn ($l) => $l->listing_type === $t));
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <div class="ex-eyebrow mb-1">Host</div>
        <h1 class="mb-0" style="font-size: 2.4rem">Overview</h1>
    </div>
    <div class="dropdown">
        <button class="btn btn-ex-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg me-1"></i>New listing</button>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach ($types as $t)<li><a class="dropdown-item" href="{{ route('host.listings.create', ['type' => $t->value]) }}"><i class="bi {{ $t->icon() }} me-2"></i>{{ $t->label() }}</a></li>@endforeach
        </ul>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat label="Requests to answer" :value="$stats['pending']" icon="bi-inbox" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Confirmed, next 30 days" :value="$stats['upcoming30']" icon="bi-calendar-check" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Room occupancy, next 30 days" :value="$stats['occupancy'] === null ? '—' : $stats['occupancy'].'%'" icon="bi-house" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Net takings, last 30 days" :value="money($stats['revenue30'])" icon="bi-wallet2" hint="Demo payments minus refunds" /></div>
</div>
@if ($stats['pending'])
    <div class="alert ex-demo-note d-flex justify-content-between align-items-center mb-4">
        <span><i class="bi bi-hourglass-split me-1"></i>{{ $stats['pending'] }} booking {{ Str::plural('request', $stats['pending']) }} waiting for your answer.</span>
        <a class="btn btn-sm btn-ex-primary" href="{{ route('host.bookings') }}">Review</a>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <x-chart id="host-trend" type="line" title="New bookings per week" note="last 12 weeks · this week so far"
            :labels="$stats['weeks']"
            :series="$hostTypes->map(fn ($t) => ['name' => $t->plural(), 'color' => $t->chartColor(), 'data' => $stats['trend'][$t->value]])->values()->all()" />
    </div>
    <div class="col-xl-5">
        <x-chart id="host-listings" type="hbar" title="Bookings by listing" note="last 90 days" :height="max(180, 44 * count($stats['perListing']))"
            :labels="$stats['perListing']->keys()->all()"
            :series="[['name' => 'Bookings', 'color' => '#243D35', 'data' => $stats['perListing']->values()->all()]]" />
    </div>
</div>
@if ($stats['seatFill'] !== null)
    <p class="small text-muted mb-4"><i class="bi bi-people me-1"></i>Tables and sessions in the next 14 days are <strong>{{ $stats['seatFill'] }}%</strong> booked.</p>
@endif

<div class="ex-panel p-0 overflow-hidden">
    <div class="px-4 pt-4 pb-2"><h2 class="h5 mb-0">Your listings</h2></div>
    @if ($listings->isEmpty())
        <div class="ex-empty"><i class="bi bi-house-add"></i>You haven’t listed anything yet. Use <strong>New listing</strong> to start.</div>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th class="ps-4">Listing</th><th>Status</th><th class="text-end">Bookings</th><th class="text-end pe-4">Manage</th></tr></thead>
                <tbody>
                    @foreach ($listings as $l)
                        <tr>
                            <td class="ps-4">
                                <a href="{{ $l->url() }}" class="fw-medium">{{ $l->title }}</a>
                                <div class="small text-muted"><span class="ex-type-chip ex-type-{{ $l->listing_type->value }} me-1">{{ $l->listing_type->label() }}</span>{{ $l->destination->name }}</div>
                            </td>
                            <td>
                                <span class="ex-badge ex-badge-{{ $l->status->value === 'published' ? 'success' : ($l->status->value === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_', ' ', $l->status->value)) }}</span>
                                @if ($l->rejection_reason)<div class="small text-muted mt-1">{{ $l->rejection_reason }}</div>@endif
                            </td>
                            <td class="text-end">{{ $l->bookings_count }}</td>
                            <td class="text-end pe-4 text-nowrap">
                                <a class="btn btn-sm btn-ex-outline" href="{{ route('host.availability', $l) }}" title="Availability and prices"><i class="bi bi-sliders"></i><span class="visually-hidden">Availability for {{ $l->title }}</span></a>
                                <a class="btn btn-sm btn-ex-outline" href="{{ route('host.calendar', ['listing' => $l->slug]) }}" title="Calendar"><i class="bi bi-calendar3"></i><span class="visually-hidden">Calendar for {{ $l->title }}</span></a>
                                <a class="btn btn-sm btn-ex-outline" href="{{ route('host.listings.edit', $l) }}" title="Edit details"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit {{ $l->title }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}" defer></script>
<script src="{{ asset('js/charts.js') }}?v={{ filemtime(public_path('js/charts.js')) }}" defer></script>
@endpush
