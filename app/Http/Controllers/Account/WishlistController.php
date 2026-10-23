<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $listings = $request->user()->wishlist()->published()->forCards()->latest('wishlists.created_at')->get();

        return view('account.wishlist', compact('listings'));
    }

    /** Save or un-save a listing. Works with Fetch (JSON) and as a plain form (redirect). */
    public function toggle(Request $request, Listing $listing): JsonResponse|RedirectResponse
    {
        abort_unless(Gate::allows('view', $listing), 404);
        $result = $request->user()->wishlist()->toggle($listing->id);
        $saved = ! empty($result['attached']);

        return $request->expectsJson()
            ? response()->json(['saved' => $saved])
            : back()->with('status', $saved ? 'Saved to your wishlist.' : 'Removed from your wishlist.');
    }
}
