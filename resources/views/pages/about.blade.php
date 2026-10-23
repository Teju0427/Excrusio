@extends('layouts.app', ['title' => 'About'])
@section('content')
<section class="ex-section"><div class="container" style="max-width: 760px">
    <div class="ex-eyebrow mb-2">About Excursio</div>
    <h1 style="font-size: clamp(2.2rem, 4vw, 3.2rem)">Stay somewhere special. Experience something extraordinary.</h1>
    <hr class="ex-rule">
    <p class="ex-lead">Excursio brings three kinds of booking together — places to stay, tables to reserve and experiences to join — so a trip can be planned and managed in one account.</p>
    <p>It is a portfolio project built with Laravel, MySQL and Bootstrap. All hosts, listings, prices and availability are synthetic demonstration data, and payments are simulated: no real money is charged and no card details are ever requested.</p>
</div></section>
@endsection
