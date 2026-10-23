<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking, ReviewService $reviews): RedirectResponse
    {
        abort_unless($request->user()->can('view', $booking), 404);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            // No links: keeps spam and phishing out of public pages.
            'body' => ['required', 'string', 'min:20', 'max:2000', 'not_regex:/(https?:\/\/|www\.)/i'],
        ], ['body.not_regex' => 'Please leave links out of your review.', 'body.min' => 'Tell other travellers a little more (at least 20 characters).']);

        try {
            $reviews->submit($booking, $request->user(), (int) $data['rating'], $data['body']);
        } catch (BookingException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', 'Thanks! Your review will appear once our team has checked it (usually within a day).');
    }
}
