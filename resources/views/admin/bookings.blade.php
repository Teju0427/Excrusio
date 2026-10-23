@extends('layouts.panel', ['title' => 'Bookings'])
@section('panel')
<h1 class="mb-3" style="font-size: 2.4rem">Bookings</h1>
<form method="GET" class="ex-panel mb-4 row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label small" for="q">Reference, guest name or email</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" maxlength="80"></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="status">Status</label><select id="status" name="status" class="form-select form-select-sm"><option value="">Any</option>@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="type">Category</label><select id="type" name="type" class="form-select form-select-sm"><option value="">Any</option>@foreach ($types as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->plural() }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="from">Starts from</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="to">to</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
    <div class="col-12 d-flex gap-2 justify-content-end"><a href="{{ route('admin.bookings') }}" class="btn btn-sm btn-link text-muted">Clear</a><button class="btn btn-sm btn-ex-primary">Filter</button></div>
</form>
<div class="ex-panel p-0 overflow-hidden">
    <div class="table-responsive"><table class="table align-middle small mb-0">
        <thead><tr><th class="ps-3">Reference</th><th>Guest</th><th>Listing</th><th>Starts</th><th class="text-end">Total</th><th>Status</th><th class="pe-3"></th></tr></thead>
        <tbody>
            @forelse ($bookings as $b)
                <tr>
                    <td class="ps-3 text-nowrap"><a href="{{ route('bookings.show', $b) }}">{{ $b->reference }}</a>@if ($b->source === 'offline')<span class="ex-badge ex-badge-muted ms-1">Offline</span>@endif</td>
                    <td>{{ $b->guest_name }}</td>
                    <td>{{ $b->listing->title }}<div class="text-muted">{{ $b->listing->destination->name }}</div></td>
                    <td class="text-nowrap">{{ $b->starts_at->format('j M Y') }}</td>
                    <td class="text-end text-nowrap">{{ money($b->total) }}</td>
                    <td><span class="ex-badge ex-badge-{{ $b->status->tone() }}">{{ $b->status->label() }}</span></td>
                    <td class="pe-3 text-end">
                        @if (in_array($b->status->value, ['pending', 'confirmed', 'awaiting_payment']))
                            <details class="ex-inline-action">
                                <summary class="btn btn-sm btn-ex-outline">Cancel</summary>
                                <form method="POST" action="{{ route('admin.bookings.cancel', $b) }}" class="d-flex gap-2 mt-2">@csrf
                                    <label class="visually-hidden" for="cr{{ $b->id }}">Reason</label>
                                    <input id="cr{{ $b->id }}" name="reason" class="form-control form-control-sm" required minlength="5" maxlength="200" placeholder="Reason (guest sees this)">
                                    <button class="btn btn-sm btn-ex-primary text-nowrap">Cancel & refund</button>
                                </form>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No bookings match.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
<div class="mt-3">{{ $bookings->links() }}</div>
@endsection
