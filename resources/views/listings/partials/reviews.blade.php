<section id="reviews" class="mt-5" aria-labelledby="reviews-title">
    <h2 class="h3 mb-3" id="reviews-title">Guest reviews</h2>
    @if ($listing->reviews_count === 0)
        <p class="text-muted mb-0">No reviews yet. Only guests who were checked in by the host can review, so every rating here comes from a real visit.</p>
    @else
        <div class="row g-4 align-items-center mb-4">
            <div class="col-sm-4 text-center text-sm-start">
                <div class="ex-review-score">{{ number_format($listing->rating_avg, 1) }}</div>
                <div class="ex-stars" aria-hidden="true">@for ($i = 1; $i <= 5; $i++)<i class="bi {{ $listing->rating_avg >= $i - 0.25 ? 'bi-star-fill' : ($listing->rating_avg >= $i - 0.75 ? 'bi-star-half' : 'bi-star') }}"></i>@endfor</div>
                <div class="small text-muted">{{ $listing->reviews_count }} verified {{ Str::plural('review', $listing->reviews_count) }}</div>
            </div>
            <div class="col-sm-8">
                <table class="ex-review-bars w-100 small" aria-label="Rating breakdown">
                    @for ($r = 5; $r >= 1; $r--)
                        @php($n = (int) ($distribution[$r] ?? 0))
                        <tr>
                            <th scope="row" class="fw-normal text-nowrap pe-2">{{ $r }} <i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">stars</span></th>
                            <td class="w-100"><div class="ex-review-bar"><span style="width: {{ $listing->reviews_count ? round($n / $listing->reviews_count * 100) : 0 }}%"></span></div></td>
                            <td class="text-end ps-2 text-muted">{{ $n }}</td>
                        </tr>
                    @endfor
                </table>
            </div>
        </div>
        <div class="d-grid gap-3">
            @foreach ($reviews as $review)
                <article class="ex-review">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="ex-avatar ex-avatar-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($review->publicName(), 0, 1)) }}</span>
                            <div class="lh-sm">
                                <div class="fw-semibold">{{ $review->publicName() }}</div>
                                <small class="text-muted">{{ $review->booking?->starts_at?->format('F Y') }} · <i class="bi bi-patch-check-fill" aria-hidden="true" style="color: var(--ex-sage)"></i> Checked-in guest</small>
                            </div>
                        </div>
                        <div class="ex-stars small" role="img" aria-label="{{ $review->rating }} out of 5 stars">@for ($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
                    </div>
                    <p class="mb-0 mt-2" style="white-space: pre-line">{{ $review->body }}</p>
                    @if ($review->host_reply)
                        <div class="ex-review-reply mt-3">
                            <div class="small fw-semibold mb-1">Reply from {{ $listing->provider->name }}</div>
                            <p class="small mb-0" style="white-space: pre-line">{{ $review->host_reply }}</p>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
        @if ($reviews->hasPages())<div class="mt-3">{{ $reviews->links() }}</div>@endif
        @if ($listing->is_illustrative)
            <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>This is a demo listing: its reviews are sample data written for the portfolio, not from real guests.</p>
        @endif
    @endif
</section>
