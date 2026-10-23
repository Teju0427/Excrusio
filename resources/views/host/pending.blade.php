@extends('layouts.app', ['title' => 'Awaiting approval'])
@section('content')
<section class="ex-section"><div class="container text-center" style="max-width: 600px">
    <span class="ex-benefit-icon" style="color: var(--ex-gold-text); border-color: var(--ex-gold)"><i class="bi bi-hourglass-split"></i></span>
    <h1 style="font-size: 2.6rem">Thanks for joining as a host</h1>
    <p class="ex-lead mx-auto">An administrator reviews every new host before they can publish listings. You’ll be able to use the host tools as soon as your account is approved.</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn btn-ex-outline" type="submit">Sign out</button></form>
</div></section>
@endsection
