@extends('layouts.app', ['title' => 'Privacy'])
@section('content')
<section class="ex-section"><div class="container" style="max-width: 760px">
    <h1 style="font-size: 2.6rem">Privacy</h1>
    <hr class="ex-rule">
    <p>Excursio stores only what the demo needs: your name, email, optional phone number, hashed password, and the bookings and reviews you create. Passwords are hashed and never stored in plain text.</p>
    <p>Session data is kept in the database to keep you signed in. No advertising or third-party tracking scripts are used.</p>
    <p>Outdoor activity pages request a public weather forecast from Open-Meteo using the venue's coordinates only — never your location.</p>
    <p>Because this is a demo, please do not enter real personal or payment information.</p>
</div></section>
@endsection
