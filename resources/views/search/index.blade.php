@extends('layouts.app', ['title' => $term ? 'Search: '.$term : 'Search'])
@section('content')
<header class="ex-page-head">
    <div class="container">
        <div class="ex-eyebrow mb-2">Search everything</div>
        <form method="get" action="{{ route('search') }}" role="search" class="ex-big-search">
            <i class="bi bi-search"></i>
            <label for="gs" class="visually-hidden">Search destinations, stays, restaurants and experiences</label>
            <input id="gs" name="q" value="{{ $term }}" placeholder="Try “Kyoto”, “Nihon”, “skiing” or “Kodagu”" autofocus>
            <button class="btn btn-ex-primary" type="submit">Search</button>
        </form>
        @if ($term)<p class="text-muted mt-3 mb-0">{{ collect($rows)->sum('total') }} results for “{{ $term }}”</p>@endif
    </div>
</header>
@foreach ($rows as $type => $row)
    @php($t = \App\Enums\ListingType::from($type))
    <section class="ex-section {{ $loop->even ? 'ex-section-cream' : '' }}" style="padding: 3rem 0">
        <div class="container">
            <x-section-head :type="$type" :title="$t->plural().' ('.$row['total'].')'"
                :link="$row['total'] > 4 ? route('listings.index', [$t->routeSegment(), 'q' => $term ?: null]) : null" :link-label="'See all '.$row['total']" />
            @include('pages.partials.card-row', ['items' => $row['items'], 'empty' => 'No '.strtolower($t->plural()).' match “'.$term.'”.'])
        </div>
    </section>
@endforeach
@endsection
