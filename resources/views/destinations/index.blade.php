@extends('layouts.app', ['title' => 'Destinations'])

@section('content')
<header class="ex-page-head">
    <div class="container">
        <div class="ex-eyebrow mb-2">Destinations</div>
        <h1 style="font-size: clamp(2.2rem, 4vw, 3.2rem)">Places we love</h1>
    </div>
</header>
<section class="py-5">
    <div class="container">
        <div class="row g-3 g-lg-4">
            @foreach ($destinations as $d)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ route('destinations.show', $d) }}" class="ex-dest">
                        <x-media :path="$d->hero_image" :alt="$d->name" type="stay" />
                        <div class="ex-dest-body">
                            <div><span class="ex-dest-name">{{ $d->name }}</span>@if ($d->local_name)<span class="ex-dest-local">{{ $d->local_name }}</span>@endif</div>
                            <div class="ex-dest-count">{{ $d->listings_count ? $d->listings_count.' '.Str::plural('listing', $d->listings_count) : 'Coming soon' }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
