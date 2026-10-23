@extends('layouts.panel', ['title' => 'Reviews'])
@section('panel')
<h1 class="mb-2" style="font-size: 2.4rem">Reviews</h1>
<p class="text-muted mb-4">Published reviews of your listings. Only guests you checked in can review, and Excursio checks each review before it goes live. A short, kind reply goes a long way.</p>
@if ($summary->isNotEmpty())
    <div class="ex-panel mb-4 p-0 overflow-hidden">
        <div class="table-responsive"><table class="table table-sm align-middle small mb-0">
            <thead><tr><th class="ps-3">Listing</th><th class="text-end">Rating</th><th class="text-end pe-3">Reviews</th></tr></thead>
            <tbody>@foreach ($summary as $l)<tr><td class="ps-3">{{ $l->title }}</td><td class="text-end"><i class="bi bi-star-fill" style="color: var(--ex-gold)" aria-hidden="true"></i> {{ number_format($l->rating_avg, 1) }}</td><td class="text-end pe-3">{{ $l->reviews_count }}</td></tr>@endforeach</tbody>
        </table></div>
    </div>
@endif
@forelse ($reviews as $r)
    <article class="ex-panel mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-semibold">{{ $r->publicName() }} · <a href="{{ $r->listing->url() }}#reviews">{{ $r->listing->title }}</a></div>
                <small class="text-muted">{{ $r->booking->reference }} · visited {{ $r->booking->starts_at->format('j M Y') }} · published {{ $r->moderated_at?->diffForHumans() }}</small>
            </div>
            <div class="ex-stars" role="img" aria-label="{{ $r->rating }} out of 5 stars">@for ($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $r->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
        </div>
        <p class="mt-2 mb-3" style="white-space: pre-line">{{ $r->body }}</p>
        <form method="POST" action="{{ route('host.reviews.reply', $r) }}">
            @csrf @method('PUT')
            <label class="form-label small" for="reply{{ $r->id }}">{{ $r->host_reply ? 'Your reply' : 'Reply publicly' }}</label>
            <textarea id="reply{{ $r->id }}" name="reply" rows="2" maxlength="1000" class="form-control form-control-sm mb-2">{{ $r->host_reply }}</textarea>
            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-ex-primary">{{ $r->host_reply ? 'Update reply' : 'Post reply' }}</button>
            </div>
        </form>
    </article>
@empty
    <div class="ex-panel ex-empty"><i class="bi bi-star"></i>No published reviews yet. Guests can review after you check them in.</div>
@endforelse
<div class="mt-3">{{ $reviews->links() }}</div>
@endsection
