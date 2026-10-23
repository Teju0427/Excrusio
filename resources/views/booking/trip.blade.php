@extends('layouts.panel', ['title' => 'Trip cart'])
@section('panel')
<div class="ex-eyebrow mb-1">One checkout for everything</div>
<h1 class="mb-2" style="font-size: 2.4rem">Your trip</h1>
<p class="text-muted mb-4">Add a stay, a table and an experience, then book them together. They’re booked all at once — or not at all — and we check the timings fit.</p>

@if ($items->isEmpty())
    <div class="ex-panel ex-empty"><i class="bi bi-bag"></i><p class="mb-3">Your trip is empty. Use “Add to trip” on any stay, restaurant or experience.</p>
        <a class="btn btn-ex-primary" href="{{ route('home') }}">Start exploring</a></div>
@else
    @if ($clashes)
        <div class="alert alert-warning" role="alert"><strong>Please fix these timings before booking:</strong>
            <ul class="mb-0 mt-1">@foreach ($clashes as $c)<li>{{ $c }}</li>@endforeach</ul></div>
    @endif
    <ol class="ex-trip list-unstyled">
        @foreach ($items as $item)
            <li class="ex-panel d-flex gap-3 align-items-center mb-3">
                <div class="ex-trip-time text-center"><div class="small text-muted">{{ $item['starts']->format('D') }}</div><div class="fw-semibold">{{ $item['starts']->format('j M') }}</div><div class="small">{{ $item['starts']->format('H:i') }}</div></div>
                <div class="flex-grow-1">
                    <span class="ex-type-chip ex-type-{{ $item['listing']->listing_type->value }} mb-1"><i class="bi {{ $item['listing']->listing_type->icon() }}"></i>{{ $item['listing']->listing_type->label() }}</span>
                    <div class="fw-semibold"><a href="{{ $item['listing']->url() }}" class="text-reset">{{ $item['listing']->title }}</a></div>
                    <div class="small text-muted">{{ $item['label'] }} · {{ $item['listing']->destination->name }}</div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold">{{ $item['total'] ? money($item['total']) : 'Pay at venue' }}</div>
                    <form method="POST" action="{{ route('trip.remove', $item['index']) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted p-0">Remove</button></form>
                </div>
            </li>
        @endforeach
    </ol>
    <div class="ex-panel d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><div class="small text-muted">Trip total</div><div class="ex-price fs-4">{{ money($total) }}</div></div>
        <form method="POST" action="{{ route('trip.hold') }}">@csrf
            <button class="btn btn-ex-primary btn-lg" type="submit" @disabled($clashes || $items->count() < 2)>Book this trip</button>
        </form>
    </div>
    @if ($items->count() < 2)<p class="small text-muted mt-2">Add at least one more item to book as a trip — or book this one on its own from its page.</p>@endif
@endif
@endsection
