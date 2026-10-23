@props(['path' => null, 'alt' => '', 'type' => 'stay', 'label' => null, 'eager' => false, 'sizes' => '(min-width: 992px) 33vw, (min-width: 768px) 50vw, 85vw'])
@php($src = seed_image($path))
@if ($src)
    <img src="{{ $src }}" alt="{{ $alt }}" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" sizes="{{ $sizes }}" {{ $attributes }} @if($eager) fetchpriority="high" @endif>
@else
    {{-- Seed image not downloaded yet: show a calm branded placeholder instead of a broken image. --}}
    <div class="ex-img-fallback tone-{{ $type }}" role="img" aria-label="{{ $alt }}">
        <i class="bi {{ \App\Enums\ListingType::tryFrom($type)?->icon() ?? 'bi-image' }}"></i>
        @if ($label)<span>{{ $label }}</span>@endif
    </div>
@endif
