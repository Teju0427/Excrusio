<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewModerationController extends Controller
{
    public const TABS = ['pending' => 'Waiting', 'approved' => 'Published', 'rejected' => 'Rejected'];

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), self::TABS) ? $request->query('status') : 'pending';

        return view('admin.reviews', [
            'status' => $status,
            'tabs' => self::TABS,
            'counts' => Review::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'reviews' => Review::where('status', $status)
                ->with('listing:id,title,slug,listing_type', 'customer:id,name,email', 'booking:id,reference,checked_in_at')
                ->orderBy('created_at', $status === 'pending' ? 'asc' : 'desc')->paginate(20)->withQueryString(),
        ]);
    }

    public function update(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:255'],
        ], ['reason.required_if' => 'Give the guest a short reason.']);

        $data['decision'] === 'approve'
            ? $reviews->approve($review, $request->user())
            : $reviews->reject($review, $request->user(), $data['reason']);

        return back()->with('status', $data['decision'] === 'approve' ? 'Review published.' : 'Review rejected; the guest has been told why.');
    }
}
