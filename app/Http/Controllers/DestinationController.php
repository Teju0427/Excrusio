<?php

namespace App\Http\Controllers;

use App\Enums\ListingType;
use App\Models\Destination;
use Illuminate\View\View;

class DestinationController extends Controller
{
    public function index(): View
    {
        $destinations = Destination::orderBy('sort_order')
            ->withCount(['listings' => fn ($q) => $q->published()])->get();

        return view('destinations.index', compact('destinations'));
    }

    public function show(Destination $destination): View
    {
        $rows = [];
        foreach (ListingType::cases() as $type) {
            $rows[$type->value] = $destination->listings()->published()->ofType($type)->forCards()
                ->orderByDesc('is_featured')->get();
        }

        return view('destinations.show', compact('destination', 'rows'));
    }
}
