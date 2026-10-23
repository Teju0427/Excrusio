@extends('layouts.app', ['title' => $destination->name, 'description' => $destination->summary])

@section('content')
<section class="ex-hero" style="min-height: 52vh">
    @php($hero = seed_image($destination->hero_image))
    <div class="ex-hero-media {{ $hero ? '' : 'is-fallback' }}">@if ($hero)<img src="{{ $hero }}" alt="">@endif</div>
    <div class="ex-hero-content" style="padding-top: 6rem">
        <div class="container">
            <div class="ex-eyebrow mb-2">{{ $destination->region }} · {{ $destination->country }}</div>
            <h1>{{ $destination->name }} @if ($destination->local_name)<span class="ex-serif" style="color:#E3D3B0; font-size: .55em">{{ $destination->local_name }}</span>@endif</h1>
            <p class="ex-lead mt-2 mb-0">{{ $destination->summary }}</p>
        </div>
    </div>
</section>

@foreach ($rows as $type => $listings)
    @php($t = \App\Enums\ListingType::from($type))
    <section class="ex-section {{ $loop->odd ? '' : 'ex-section-cream' }}">
        <div class="container">
            <x-section-head :type="$type" :title="$t->plural().' in '.$destination->name" />
            @include('pages.partials.card-row', ['items' => $listings, 'empty' => 'No '.strtolower($t->plural()).' here yet.'])
        </div>
    </section>
@endforeach
@endsection
