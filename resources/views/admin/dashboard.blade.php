@extends('layouts.panel', ['title' => 'Admin overview'])
@section('panel')
@php
    $types = \App\Enums\ListingType::cases();
    $statusOrder = ['pending' => 'Awaiting host', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'rejected' => 'Declined'];
@endphp
<div class="ex-eyebrow mb-1">Administrator</div>
<h1 class="mb-4" style="font-size: 2.4rem">Platform overview</h1>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat label="Guests" :value="number_format($stats['users']['customer'] ?? 0)" icon="bi-person" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Published listings" :value="$stats['listings']['published'] ?? 0" icon="bi-grid" :hint="($stats['users']['provider'] ?? 0).' hosts'" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Net takings, 30 days" :value="money($stats['revenue30'])" icon="bi-wallet2" hint="Demo payments minus refunds" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Cancellation rate, 90 days" :value="$stats['cancellationRate'] === null ? '—' : $stats['cancellationRate'].'%'" icon="bi-x-circle" /></div>
</div>

@php
    $todo = array_filter([
        [$stats['moderation'], 'listing', 'waiting for review', route('admin.listings')],
        [$pendingReviews, 'review', 'to moderate', route('admin.reviews')],
        [$pendingHosts, 'new host', 'waiting for approval', route('admin.users', ['status' => 'pending'])],
        [$openReports, 'open report', 'to look at', route('admin.reports')],
        [$unreadMessages, 'unread message', '', route('admin.messages')],
    ], fn ($t) => $t[0] > 0);
@endphp
@if ($todo)
    <div class="ex-panel mb-4 py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="fw-semibold me-1"><i class="bi bi-list-check me-1"></i>To do</span>
            @foreach ($todo as [$n, $what, $suffix, $url])
                <a href="{{ $url }}" class="ex-badge ex-badge-warning">{{ $n }} {{ Str::plural($what, $n) }} {{ $suffix }}</a>
            @endforeach
        </div>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <x-chart id="admin-trend" type="line" title="New bookings per week, by category" note="last 12 weeks · this week so far" :height="280"
            :labels="$stats['weeks']"
            :series="collect($types)->map(fn ($t) => ['name' => $t->plural(), 'color' => $t->chartColor(), 'data' => $stats['trend'][$t->value]])->all()" />
    </div>
    <div class="col-xl-4">
        <x-chart id="admin-types" type="doughnut" title="Bookings by category" note="excl. cancelled" :height="280"
            :labels="collect($types)->map->plural()->all()"
            :series="[['name' => 'Bookings', 'data' => array_values($stats['byType']), 'colors' => collect($types)->map->chartColor()->all()]]" />
    </div>
</div>
<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <x-chart id="admin-dest" type="hbar" title="Most-booked destinations" note="excl. cancelled" :height="300"
            :labels="$stats['destinations']->keys()->all()"
            :series="[['name' => 'Bookings', 'color' => '#243D35', 'data' => $stats['destinations']->values()->all()]]" />
    </div>
    <div class="col-xl-6">
        <x-chart id="admin-status" type="hbar" title="Bookings by status" note="all time" :height="300"
            :labels="array_values($statusOrder)"
            :series="[['name' => 'Bookings', 'color' => '#243D35', 'data' => collect(array_keys($statusOrder))->map(fn ($k) => (int) ($stats['byStatus'][$k] ?? 0))->all()]]" />
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="ex-panel h-100">
            <h2 class="h6 mb-3">Most-booked experiences</h2>
            <div class="table-responsive"><table class="table table-sm small mb-0">
                <thead><tr><th>Experience</th><th class="text-end">Bookings</th><th class="text-end">People</th></tr></thead>
                <tbody>
                    @forelse ($stats['topActivities'] as $a)
                        <tr><td>{{ $a->title }}</td><td class="text-end">{{ number_format($a->bookings) }}</td><td class="text-end">{{ number_format($a->people) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">No bookings yet.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="ex-panel h-100">
            <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Recent activity</h2><a class="small" href="{{ route('admin.audit') }}">Audit log</a></div>
            @if ($recentAudit->isEmpty())
                <p class="text-muted small mb-0">No activity recorded yet.</p>
            @else
                <ul class="list-unstyled mb-0 small">
                    @foreach ($recentAudit as $log)
                        <li class="d-flex justify-content-between border-top py-2 gap-3">
                            <span><code class="text-reset">{{ $log->action }}</code> · {{ $log->user?->name ?? 'System' }}</span>
                            <span class="text-muted text-nowrap">{{ $log->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
<p class="small text-muted mt-4 mb-0"><i class="bi bi-info-circle me-1"></i>Figures are calculated live from the database. Demo bookings are synthetic; money shown is simulated.</p>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}" defer></script>
<script src="{{ asset('js/charts.js') }}?v={{ filemtime(public_path('js/charts.js')) }}" defer></script>
@endpush
