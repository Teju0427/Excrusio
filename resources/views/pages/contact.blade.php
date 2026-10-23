@extends('layouts.app', ['title' => 'Contact', 'ownFlash' => true])
@section('content')
<section class="ex-section"><div class="container" style="max-width: 680px">
    <div class="ex-eyebrow mb-2">Contact</div>
    <h1 style="font-size: clamp(2.2rem, 4vw, 3rem)">How can we help?</h1>
    <hr class="ex-rule">
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <p class="text-muted mb-4">Questions about a booking, hosting on Excursio, or this demo. For a specific booking, include its reference (EXC-…).</p>
    <form method="POST" action="{{ route('contact') }}" class="ex-panel" novalidate>
        @csrf
        <div class="ex-hp" aria-hidden="true"><label for="f-website">Website</label><input id="f-website" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="row">
            <div class="col-md-6"><x-field name="name" label="Your name" :value="auth()->user()?->name" required autocomplete="name" maxlength="120" /></div>
            <div class="col-md-6"><x-field name="email" label="Email" type="email" :value="auth()->user()?->email" required autocomplete="email" maxlength="160" /></div>
        </div>
        <x-field name="subject" label="Subject" required maxlength="160" />
        <div class="mb-3">
            <label for="f-message" class="form-label">Message</label>
            <textarea id="f-message" name="message" rows="6" required minlength="10" maxlength="3000" class="form-control @error('message') is-invalid @enderror" @error('message') aria-invalid="true" aria-describedby="e-message" @enderror>{{ old('message') }}</textarea>
            @error('message')<div id="e-message" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-ex-primary">Send message</button>
        <p class="small text-muted mt-3 mb-0">Your message is stored so the team can read it. It isn’t shared or used for marketing.</p>
    </form>
</div></section>
@endsection
