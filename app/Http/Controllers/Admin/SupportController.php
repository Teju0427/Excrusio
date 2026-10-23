<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController;
use App\Models\ContactMessage;
use App\Models\Report;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Reports about listings and messages from the contact form. */
class SupportController extends Controller
{
    public function reports(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'resolved', 'dismissed'], true) ? $request->query('status') : 'open';

        return view('admin.reports', [
            'status' => $status,
            'counts' => Report::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'reports' => Report::where('status', $status)->with('reporter:id,name,email', 'reportable')->latest()->paginate(20)->withQueryString(),
            'reasons' => ReportController::REASONS,
        ]);
    }

    public function resolve(Request $request, Report $report, AuditLogger $audit): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:resolved,dismissed']])['status'];
        $report->forceFill(['status' => $status, 'resolved_by' => $request->user()->id, 'resolved_at' => now()])->save();
        $audit->log('report.'.$status, $report, ['status' => 'open'], ['status' => $status]);

        return back()->with('status', 'Report marked as '.$status.'.');
    }

    public function messages(): View
    {
        return view('admin.messages', ['messages' => ContactMessage::orderByRaw('read_at is null desc')->latest()->paginate(20)]);
    }

    public function readMessage(ContactMessage $message): RedirectResponse
    {
        $message->forceFill(['read_at' => $message->read_at ? null : now()])->save();

        return back();
    }
}
