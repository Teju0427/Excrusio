@extends('layouts.panel', ['title' => 'Reviews'])
@section('panel')
<h1 class="mb-2" style="font-size: 2.4rem">Reviews</h1>
<p class="text-muted mb-3">Every review is from a guest the host checked in. Publish it unless it breaks the rules: personal details, abuse, off-topic, or advertising.</p>
<nav class="ex-tabs mb-4" aria-label="Review status">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.reviews', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}" @if($status === $key) aria-current="page" @endif>{{ $label }} <span class="ex-tab-count">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>
@forelse ($reviews as $r)
    <article class="ex-panel mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <a href="{{ $r->listing->url() }}" class="fw-semibold">{{ $r->listing->title }}</a>
                <div class="small text-muted">{{ $r->customer->name }} · <a href="{{ route('bookings.show', $r->booking->reference) }}">{{ $r->booking->reference }}</a> · checked in {{ $r->booking->checked_in_at?->format('j M Y') ?? '—' }} · written {{ $r->created_at->diffForHumans() }}</div>
            </div>
            <div class="ex-stars" role="img" aria-label="{{ $r->rating }} out of 5 stars">@for ($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $r->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
        </div>
        <p class="mt-2 mb-3" style="white-space: pre-line">{{ $r->body }}</p>
        @if ($r->rejection_reason)<p class="small text-muted"><strong>Reason given:</strong> {{ $r->rejection_reason }}</p>@endif
        <div class="d-flex flex-wrap gap-2 justify-content-end border-top pt-3">
            @if ($status !== 'approved')
                <form method="POST" action="{{ route('admin.reviews.moderate', $r) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-ex-primary"><i class="bi bi-check2 me-1"></i>Publish</button></form>
            @endif
            @if ($status !== 'rejected')
                <form method="POST" action="{{ route('admin.reviews.moderate', $r) }}" class="d-flex gap-2">@csrf @method('PATCH')
                    <input type="hidden" name="decision" value="reject">
                    <label class="visually-hidden" for="rr{{ $r->id }}">Reason</label>
                    <input id="rr{{ $r->id }}" name="reason" required maxlength="255" class="form-control form-control-sm" placeholder="Reason (guest sees this)" style="min-width: 220px">
                    <button class="btn btn-sm btn-ex-outline">{{ $status === 'approved' ? 'Take down' : 'Reject' }}</button>
                </form>
            @endif
        </div>
    </article>
@empty
    <div class="ex-panel ex-empty"><i class="bi bi-star"></i>{{ $status === 'pending' ? 'No reviews waiting.' : 'Nothing here.' }}</div>
@endforelse
<div class="mt-3">{{ $reviews->links() }}</div>
@endsection
