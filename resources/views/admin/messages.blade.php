@extends('layouts.panel', ['title' => 'Messages'])
@section('panel')
<h1 class="mb-2" style="font-size: 2.4rem">Messages</h1>
<p class="text-muted mb-4">From the public contact form. Reply by email from your own mail app.</p>
@forelse ($messages as $m)
    <details class="ex-panel mb-3" @if(! $m->read_at) open @endif>
        <summary class="d-flex flex-wrap justify-content-between gap-2">
            <span>@unless ($m->read_at)<span class="ex-badge ex-badge-warning me-2">New</span>@endunless<strong>{{ $m->subject }}</strong> · {{ $m->name }}</span>
            <small class="text-muted">{{ $m->created_at->diffForHumans() }}</small>
        </summary>
        <p class="small text-muted mt-2 mb-2">{{ $m->email }}</p>
        <p class="mb-3" style="white-space: pre-line">{{ $m->message }}</p>
        <form method="POST" action="{{ route('admin.messages.read', $m) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-ex-outline">{{ $m->read_at ? 'Mark as unread' : 'Mark as read' }}</button></form>
    </details>
@empty
    <div class="ex-panel ex-empty"><i class="bi bi-envelope-open"></i>No messages yet.</div>
@endforelse
<div class="mt-3">{{ $messages->links() }}</div>
@endsection
