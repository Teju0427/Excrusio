<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hosts see published reviews of their listings and can reply once (editable). */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $ids = $request->user()->listings()->pluck('id');

        return view('host.reviews', [
            'reviews' => Review::approved()->whereIn('listing_id', $ids)
                ->with('listing:id,title,slug,listing_type', 'customer:id,name', 'booking:id,reference,starts_at')
                ->orderByRaw('host_reply is null desc')->latest()->paginate(15),
            'summary' => $request->user()->listings()->where('reviews_count', '>', 0)->orderByDesc('reviews_count')->get(['id', 'title', 'rating_avg', 'reviews_count']),
        ]);
    }

    public function reply(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        abort_unless($review->status === 'approved' && $review->listing()->where('provider_id', $request->user()->id)->exists(), 404);
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:1000', 'not_regex:/(https?:\/\/|www\.)/i']], ['reply.not_regex' => 'Links aren’t allowed in replies.']);
        $reviews->reply($review, $request->user(), trim((string) ($data['reply'] ?? '')));

        return back()->with('status', filled($data['reply'] ?? null) ? 'Reply published.' : 'Reply removed.');
    }
}
