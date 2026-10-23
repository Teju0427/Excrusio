@extends('layouts.panel', ['title' => 'Listings'])
@section('panel')
<h1 class="mb-3" style="font-size: 2.4rem">Listings</h1>
<nav class="ex-tabs mb-4" aria-label="Listing status">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.listings', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}" @if($status === $key) aria-current="page" @endif>{{ $label }} <span class="ex-tab-count">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>
@forelse ($listings as $l)
    <div class="ex-panel mb-3">
        <div class="d-flex flex-wrap gap-3">
            <div class="rounded overflow-hidden flex-shrink-0" style="width:120px;height:90px"><x-media :path="$l->coverImage?->path" :alt="$l->title" :type="$l->listing_type->value" class="w-100 h-100 object-fit-cover" /></div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                    <span class="ex-type-chip ex-type-{{ $l->listing_type->value }}">{{ $l->listing_type->label() }}</span>
                    <a href="{{ $l->url() }}" class="fw-semibold">{{ $l->title }}</a>
                </div>
                <div class="small text-muted mb-2">{{ $l->category->name }} · {{ $l->destination->name }} · by {{ $l->provider->name }} · from {{ money($l->base_price) }} · {{ $l->listing_type->value === 'stay' ? $l->stay_units_count.' room types' : $l->schedules_count.' schedule rules' }} · updated {{ $l->updated_at->diffForHumans() }}</div>
                <p class="small mb-0">{{ $l->summary }}</p>
                @if ($l->rejection_reason)<p class="small text-muted mb-0 mt-1"><strong>Reason given:</strong> {{ $l->rejection_reason }}</p>@endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end mt-3 border-top pt-3">
            @if ($status !== 'published')
                <form method="POST" action="{{ route('admin.listings.moderate', $l) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-ex-primary"><i class="bi bi-check2 me-1"></i>{{ $status === 'archived' ? 'Republish' : 'Approve' }}</button></form>
            @endif
            @if ($status === 'pending_review')
                <form method="POST" action="{{ route('admin.listings.moderate', $l) }}" class="d-flex gap-2">@csrf @method('PATCH')
                    <input type="hidden" name="decision" value="reject">
                    <label class="visually-hidden" for="rr{{ $l->id }}">Reason</label>
                    <input id="rr{{ $l->id }}" name="reason" required maxlength="255" class="form-control form-control-sm" placeholder="What should the host change?" style="min-width: 240px">
                    <button class="btn btn-sm btn-ex-outline">Send back</button>
                </form>
            @endif
            @if ($status === 'published')
                <form method="POST" action="{{ route('admin.listings.moderate', $l) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="unpublish"><button class="btn btn-sm btn-ex-outline"><i class="bi bi-eye-slash me-1"></i>Unpublish</button></form>
            @endif
        </div>
        @error('reason')<div class="small text-danger mt-2">{{ $message }}</div>@enderror
    </div>
@empty
    <div class="ex-panel ex-empty"><i class="bi bi-patch-check"></i>{{ $status === 'pending_review' ? 'Nothing waiting for review.' : 'No listings here.' }}</div>
@endforelse
<div class="mt-3">{{ $listings->links() }}</div>
@endsection
