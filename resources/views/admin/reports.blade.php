@extends('layouts.panel', ['title' => 'Reports'])
@section('panel')
<h1 class="mb-3" style="font-size: 2.4rem">Reports</h1>
<nav class="ex-tabs mb-4" aria-label="Report status">
    @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $key => $label)
        <a href="{{ route('admin.reports', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}" @if($status === $key) aria-current="page" @endif>{{ $label }} <span class="ex-tab-count">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>
@forelse ($reports as $r)
    <div class="ex-panel mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-semibold">{{ $reasons[$r->reason] ?? $r->reason }}</div>
                <div class="small text-muted">
                    @if ($r->reportable instanceof \App\Models\Listing)<a href="{{ $r->reportable->url() }}">{{ $r->reportable->title }}</a>@else{{ class_basename($r->reportable_type) }} #{{ $r->reportable_id }}@endif
                    · reported by {{ $r->reporter->name }} · {{ $r->created_at->diffForHumans() }}
                </div>
            </div>
            @if ($status === 'open')
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('admin.reports.resolve', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="resolved"><button class="btn btn-sm btn-ex-primary">Resolved</button></form>
                    <form method="POST" action="{{ route('admin.reports.resolve', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="dismissed"><button class="btn btn-sm btn-ex-outline">Dismiss</button></form>
                </div>
            @else
                <span class="small text-muted">{{ ucfirst($r->status) }} {{ $r->resolved_at?->diffForHumans() }}</span>
            @endif
        </div>
        @if ($r->details)<p class="small mb-0 mt-2 border-start ps-3">{{ $r->details }}</p>@endif
    </div>
@empty
    <div class="ex-panel ex-empty"><i class="bi bi-flag"></i>No {{ $status }} reports.</div>
@endforelse
<div class="mt-3">{{ $reports->links() }}</div>
@endsection
