@extends('layouts.app', ['title' => 'Not allowed'])
@section('content')
<section class="ex-section"><div class="container text-center" style="max-width: 560px">
    <div class="ex-eyebrow mb-2">403</div>
    <h1 style="font-size: 2.8rem">This area isn’t open to you</h1>
    <p class="ex-lead mx-auto">{{ $exception->getMessage() ?: 'Your account doesn’t have access to this page.' }}</p>
    <a href="{{ route('home') }}" class="btn btn-ex-primary mt-3">Back to Excursio</a>
</div></section>
@endsection
