@props(['booking'])
@php($pb = $booking->price_breakdown ?? [])
<ul class="list-unstyled small mb-0 ex-price-lines">
    @if ($booking->check_in && ! empty($pb['nights']))
        @foreach ($pb['nights'] as $n)
            <li><span>{{ \Carbon\Carbon::parse($n['date'])->format('D j M') }}@if (! empty($n['label'])) · {{ $n['label'] }}@endif @if (($pb['rooms'] ?? 1) > 1) × {{ $pb['rooms'] }}@endif</span><span>{{ money($n['price'] * ($pb['rooms'] ?? 1)) }}</span></li>
        @endforeach
    @elseif ($booking->total > 0)
        <li><span>{{ money($booking->unit_price) }} × {{ $booking->guests }} {{ $booking->listing_type->value === 'dining' ? 'deposit' : Str::plural('person', $booking->guests) }}</span><span>{{ money($booking->subtotal) }}</span></li>
    @endif
    @if ($booking->fees)<li><span>Service fee</span><span>{{ money($booking->fees) }}</span></li>@endif
    <li class="ex-price-total"><span>Total</span><span>{{ $booking->total ? money($booking->total) : 'Pay at the venue' }}</span></li>
</ul>
