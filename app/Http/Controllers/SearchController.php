<?php

namespace App\Http\Controllers;

use App\Enums\ListingType;
use App\Models\Listing;
use App\Services\ListingSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Global search across stays, dining and experiences — one row per category. */
class SearchController extends Controller
{
    public function __invoke(Request $request, ListingSearchService $search): View
    {
        $term = trim((string) $request->validate(['q' => ['nullable', 'string', 'max:80']])['q'] ?? '');
        $rows = [];

        foreach (ListingType::cases() as $type) {
            $query = Listing::published()->ofType($type)->forCards();
            $search->applyText($query, $term ?: null);
            $rows[$type->value] = ['total' => (clone $query)->count(), 'items' => $query->orderByDesc('is_featured')->orderBy('listings.id')->limit(4)->get()];
        }

        return view('search.index', ['term' => $term, 'rows' => $rows]);
    }
}
