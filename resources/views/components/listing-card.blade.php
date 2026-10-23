@props(['listing', 'saved' => null])
@php
    use App\Enums\ListingType;
    $type = $listing->listing_type;
@endphp
<div class="ex-card-wrap">
<a href="{{ $listing->url() }}" class="ex-card" aria-label="{{ $listing->title }}, {{ $listing->destination->name }}">
    <div class="ex-card-media">
        <x-media :path="$listing->coverImage?->path" :alt="$listing->coverImage?->alt ?? $listing->title" :type="$type->value" :label="$listing->destination->name" />
        <span class="ex-type-chip"><i class="bi {{ $type->icon() }}"></i>{{ $listing->category->name }}</span>
    </div>
    <div class="ex-card-body">
        <div class="ex-card-loc">
            <i class="bi bi-geo-alt"></i>{{ $listing->destination->name }}, {{ $listing->destination->country }}
            @if ($listing->destination->local_name && preg_match('/[^\x{0000}-\x{024F}]/u', $listing->destination->local_name))<span class="ex-local">· {{ $listing->destination->local_name }}</span>@endif
        </div>
        <div class="ex-card-title">{{ $listing->title }}</div>
        <div class="ex-card-meta">
            @if ($type === ListingType::Stay)
                <span><i class="bi bi-people"></i>Up to {{ $listing->max_unit_guests ?? '—' }} guests</span>
                @if ($listing->stayDetail)<span><i class="bi bi-clock"></i>Check-in {{ substr($listing->stayDetail->check_in_time, 0, 5) }}</span>@endif
            @elseif ($type === ListingType::Dining && $listing->diningDetail)
                <span><i class="bi bi-egg-fried"></i>{{ $listing->diningDetail->cuisine }}</span>
                <span title="Price range">{{ str_repeat('₹', $listing->diningDetail->price_band) }}<span class="opacity-25">{{ str_repeat('₹', 4 - $listing->diningDetail->price_band) }}</span></span>
            @elseif ($type === ListingType::Activity && $listing->activityDetail)
                @php($mins = $listing->activityDetail->duration_minutes)
                <span><i class="bi bi-hourglass-split"></i>{{ $mins >= 60 ? rtrim(rtrim(number_format($mins / 60, 1), '0'), '.').' h' : $mins.' min' }}</span>
                @if ($listing->activityDetail->difficulty)<span><i class="bi bi-bar-chart"></i>{{ ucfirst($listing->activityDetail->difficulty) }}</span>@endif
            @endif
        </div>
        <div class="ex-card-foot">
            <div class="ex-price">
                <span class="ex-price-from">From</span>
                {{ money($listing->fromPrice()) }} <small>{{ $listing->priceUnitLabel() }}</small>
            </div>
            @if ($listing->reviews_count > 0)
                <span class="ex-rating"><i class="bi bi-star-fill"></i>{{ number_format($listing->rating_avg, 1) }} <span class="text-muted">({{ $listing->reviews_count }})</span></span>
            @else
                <span class="ex-rating is-empty">No reviews yet</span>
            @endif
        </div>
    </div>
</a>
@if ($saved !== null)
    <x-wishlist-button :listing="$listing" :saved="$saved" class="ex-heart-card" />
@endif
</div>
