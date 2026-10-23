@extends('layouts.app', ['title' => 'Page expired'])
@section('content')
<section class="ex-section"><div class="container text-center" style="max-width: 560px">
    <div class="ex-eyebrow mb-2">419</div>
    <h1 style="font-size: 2.8rem">This page expired</h1>
    <p class="ex-lead mx-auto">For your security, forms expire after a while. Please go back, refresh the page and try again.</p>
    <a href="{{ url()->previous() }}" class="btn btn-ex-primary mt-3">Go back</a>
</div></section>
@endsection
