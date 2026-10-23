@props(['booking'])
@php($l = $booking->listing)
<div class="d-flex gap-3 align-items-start">
    <div class="rounded overflow-hidden flex-shrink-0" style="width:96px;height:72px">
        <x-media :path="$l->coverImage?->path" :alt="$l->title" :type="$booking->listing_type->value" class="w-100 h-100 object-fit-cover" />
    </div>
    <div class="flex-grow-1">
        <span class="ex-type-chip ex-type-{{ $booking->listing_type->value }} mb-1"><i class="bi {{ $booking->listing_type->icon() }}"></i>{{ $booking->listing_type->label() }}</span>
        <div class="fw-semibold">{{ $l->title }}</div>
        <div class="small text-muted">{{ $l->destination->name }}, {{ $l->destination->country }}</div>
    </div>
</div>
<dl class="ex-dl mt-3 mb-0">
    @if ($booking->check_in)
        <dt>Dates</dt><dd>{{ $booking->check_in->format('D j M') }} → {{ $booking->check_out->format('D j M Y') }} · {{ $booking->nights }} {{ Str::plural('night', $booking->nights) }}</dd>
        <dt>Room</dt><dd>{{ $booking->rooms }} × {{ $booking->stayUnit?->name }}</dd>
        <dt>Guests</dt><dd>{{ $booking->guests }}</dd>
    @else
        <dt>When</dt><dd>{{ $booking->starts_at->format('D j M Y · H:i') }}–{{ $booking->ends_at->format('H:i') }} <span class="text-muted">(local time)</span></dd>
        <dt>{{ $booking->listing_type->value === 'dining' ? 'Guests' : 'People' }}</dt><dd>{{ $booking->guests }}</dd>
    @endif
</dl>
