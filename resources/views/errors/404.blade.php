@extends('layouts.app', ['title' => 'Page not found'])
@section('content')
<section class="ex-section"><div class="container text-center" style="max-width: 560px">
    <div class="ex-eyebrow mb-2">404</div>
    <h1 style="font-size: 2.8rem">This path leads nowhere</h1>
    <p class="ex-lead mx-auto">The page may have moved, or the link may be mistyped.</p>
    <a href="{{ route('home') }}" class="btn btn-ex-primary mt-3">Back to Excursio</a>
</div></section>
@endsection
