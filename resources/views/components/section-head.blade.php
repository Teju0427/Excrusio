@props(['id' => null, 'type' => null, 'eyebrow' => null, 'title', 'lead' => null, 'link' => null, 'linkLabel' => null])
<div class="ex-section-head">
    <div>
        @if ($type)
            @php($t = \App\Enums\ListingType::from($type))
            <span class="ex-type-chip ex-type-{{ $type }}"><i class="bi {{ $t->icon() }}"></i>{{ $t->plural() }}</span>
        @elseif ($eyebrow)
            <div class="ex-eyebrow mb-2">{{ $eyebrow }}</div>
        @endif
        <h2 @if($id) id="{{ $id }}" @endif>{{ $title }}</h2>
        @if ($lead)<p class="ex-lead mt-2 mb-0">{{ $lead }}</p>@endif
    </div>
    @if ($link)
        <a class="ex-link-arrow" href="{{ $link }}">{{ $linkLabel }} <i class="bi bi-arrow-right"></i></a>
    @endif
</div>
