@extends('layouts.panel', ['title' => 'Audit log'])
@section('panel')
@php $prefixes = $actions->map(fn ($a) => Str::before($a, '.'))->unique()->values(); @endphp
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <h1 class="mb-1" style="font-size: 2.4rem">Audit log</h1>
        <p class="text-muted mb-0">Every booking decision, refund, approval and setting change — who, what and when.</p>
    </div>
    <form method="GET" class="d-flex gap-2">
        <label class="visually-hidden" for="action">Filter</label>
        <select id="action" name="action" class="form-select form-select-sm" data-autosubmit>
            <option value="">All actions</option>
            @foreach ($prefixes as $p)<option value="{{ $p }}." @selected(($filters['action'] ?? '') === $p.'.')>{{ ucfirst(str_replace('_', ' ', $p)) }}</option>@endforeach
        </select>
        <noscript><button class="btn btn-sm btn-ex-outline">Filter</button></noscript>
    </form>
</div>
<div class="ex-panel p-0 overflow-hidden">
    <div class="table-responsive"><table class="table table-sm align-middle small mb-0">
        <thead><tr><th class="ps-3">When</th><th>Who</th><th>Action</th><th>Record</th><th class="pe-3">Change</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="ps-3 text-nowrap" title="{{ $log->created_at->timezone('Asia/Kolkata')->format('j M Y H:i:s') }} IST">{{ $log->created_at->diffForHumans() }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}@if ($log->user)<div class="text-muted">{{ $log->user->role->label() }}</div>@endif</td>
                    <td><code class="text-reset">{{ $log->action }}</code></td>
                    <td class="text-nowrap">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                    <td class="pe-3 text-muted" style="max-width: 320px">
                        @foreach (($log->new_values ?? []) as $k => $v)
                            <span class="d-inline-block me-2">{{ $k }}: @if (array_key_exists($k, $log->old_values ?? []) && ! is_array($log->old_values[$k]))<s>{{ Str::limit((string) $log->old_values[$k], 30) }}</s> → @endif{{ is_array($v) ? json_encode($v) : Str::limit((string) $v, 40) }}</span>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Nothing recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
