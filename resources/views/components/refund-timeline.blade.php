@props(['rows', 'compact' => false])
{{-- Feature F5: exactly what you'd get back, by date, before you commit. --}}
<ol class="ex-timeline {{ $compact ? 'is-compact' : '' }}" aria-label="Refund timeline">
    @foreach ($rows as $row)
        <li class="ex-timeline-step tone-{{ $row['percent'] === 100 ? 'full' : ($row['percent'] > 0 ? 'part' : 'none') }} {{ $row['past'] ? 'is-past' : '' }}">
            <div class="ex-timeline-when">
                @if ($row['until'])
                    Until {{ $row['until']->format('D j M, H:i') }}
                @else
                    After that
                @endif
            </div>
            <div class="ex-timeline-what">
                @if ($row['percent'] > 0)
                    <strong>{{ money($row['amount']) }}</strong> back <span class="text-muted">({{ $row['percent'] }}%)</span>
                @else
                    No refund
                @endif
            </div>
        </li>
    @endforeach
</ol>
<p class="small text-muted mt-2 mb-0">Times are local to the venue. Refunds are simulated in this demo.</p>
