<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A signed-in person flags a listing for the admin team. */
class ReportController extends Controller
{
    public const REASONS = [
        'inaccurate' => 'Information is wrong or misleading',
        'safety' => 'Safety concern',
        'scam' => 'Looks like a scam',
        'offensive' => 'Offensive content',
        'other' => 'Something else',
    ];

    public function store(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()->can('view', $listing), 404);
        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', array_keys(self::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = Report::where('reporter_id', $request->user()->id)->where('status', 'open')
            ->where('reportable_type', $listing->getMorphClass())->where('reportable_id', $listing->id)->exists();
        if (! $exists) {
            $report = new Report($data);
            $report->reporter()->associate($request->user());
            $report->reportable()->associate($listing);
            $report->save();
        }

        return back()->with('status', 'Thanks — our team will look into it.');
    }
}
