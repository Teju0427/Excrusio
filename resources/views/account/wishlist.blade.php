@extends('layouts.panel', ['title' => 'Saved'])
@section('panel')
<h1 class="mb-4" style="font-size: 2.4rem">Saved for later</h1>
@if ($listings->isEmpty())
    <div class="ex-panel ex-empty">
        <i class="bi bi-heart"></i>
        <p class="mb-3">Tap the heart on any stay, restaurant or experience to save it here.</p>
        <a class="btn btn-ex-primary" href="{{ route('home') }}">Start exploring</a>
    </div>
@else
    <div class="row g-4">
        @foreach ($listings as $listing)
            <div class="col-md-6 col-xl-4"><x-listing-card :listing="$listing" :saved="true" /></div>
        @endforeach
    </div>
@endif
@endsection
