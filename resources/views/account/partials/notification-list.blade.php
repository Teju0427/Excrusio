@if ($items->isEmpty())
    <p class="text-muted small mb-0">You’re all caught up.</p>
@else
    <ul class="list-unstyled mb-0 ex-notes">
        @foreach ($items as $n)
            <li class="{{ $n->read_at ? '' : 'is-unread' }}">
                <a href="{{ route('notifications.open', $n->id) }}" class="d-flex gap-2 text-reset py-2">
                    <span class="ex-note-dot" aria-hidden="true"></span>
                    <span class="flex-grow-1">
                        <span class="d-block {{ $compact ?? false ? 'small' : '' }}">{{ $n->data['message'] ?? 'Update' }}</span>
                        <small class="text-muted">{{ $n->created_at->diffForHumans() }}@unless ($n->read_at) · <span class="visually-hidden">unread</span><span aria-hidden="true">New</span>@endunless</small>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
