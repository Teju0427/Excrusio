@extends('layouts.panel', ['title' => 'Notifications'])
@section('panel')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="mb-0" style="font-size: 2.4rem">Notifications</h1>
    @if (auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-ex-outline"><i class="bi bi-check2-all me-1"></i>Mark all as read</button></form>
    @endif
</div>
<div class="ex-panel">
    @include('account.partials.notification-list', ['items' => $notifications])
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
<p class="small text-muted mt-3"><i class="bi bi-info-circle me-1"></i>Notifications appear here only — the demo doesn’t send emails or SMS.</p>
@endsection
