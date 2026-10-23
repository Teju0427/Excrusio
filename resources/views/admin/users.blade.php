@extends('layouts.panel', ['title' => 'Users & hosts'])
@section('panel')
<h1 class="mb-4" style="font-size: 2.4rem">Users & hosts</h1>
<form method="get" class="ex-panel mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label small" for="u-q">Search</label><input id="u-q" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email"></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="u-role">Role</label>
            <select id="u-role" class="form-select" name="role"><option value="">All</option>@foreach (['customer' => 'Guests', 'provider' => 'Hosts', 'admin' => 'Admins'] as $v => $l)<option value="{{ $v }}" @selected(($filters['role'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="u-status">Status</label>
            <select id="u-status" class="form-select" name="status"><option value="">All</option>@foreach (['pending' => 'Awaiting approval', 'active' => 'Active', 'suspended' => 'Suspended'] as $v => $l)<option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-1 d-grid"><button class="btn btn-ex-primary" aria-label="Filter"><i class="bi bi-funnel"></i></button></div>
    </div>
</form>
<div class="ex-panel p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th class="ps-4">User</th><th>Role</th><th>Status</th><th class="text-end pe-4">Action</th></tr></thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td class="ps-4"><div class="fw-medium">{{ $u->name }}</div><div class="small text-muted">{{ $u->email }}</div></td>
                        <td class="small">{{ $u->role->label() }}</td>
                        <td>
                            @php($tone = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'muted'][$u->status->value])
                            <span class="ex-badge ex-badge-{{ $tone }}">{{ ['active' => 'Active', 'pending' => 'Awaiting approval', 'suspended' => 'Suspended'][$u->status->value] }}</span>
                        </td>
                        <td class="text-end pe-4">
                            @if ($u->is_demo)
                                <span class="small text-muted">Demo account</span>
                            @elseif (! $u->isAdmin())
                                <form method="POST" action="{{ route('admin.users.status', $u) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    @if ($u->status->value === 'pending')
                                        <button name="status" value="active" class="btn btn-sm btn-ex-primary">Approve</button>
                                    @elseif ($u->status->value === 'active')
                                        <button name="status" value="suspended" class="btn btn-sm btn-ex-outline">Suspend</button>
                                    @else
                                        <button name="status" value="active" class="btn btn-sm btn-ex-outline">Reactivate</button>
                                    @endif
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-5">No users match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
