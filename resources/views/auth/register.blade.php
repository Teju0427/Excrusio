@extends('layouts.app', ['title' => 'Join Excursio', 'ownFlash' => true])
@section('content')
<x-auth-card title="Join Excursio" lead="One account for every booking — or list your own place.">
    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf
        <fieldset class="mb-4">
            <legend class="form-label">I want to…</legend>
            <div class="ex-choice">
                @foreach (['customer' => ['bi-compass', 'Book trips', 'Stays, tables and experiences'], 'provider' => ['bi-house-gear', 'Host guests', 'List a stay, restaurant or activity']] as $value => [$icon, $label, $hint])
                    <label class="ex-choice-item">
                        <input type="radio" name="account_type" value="{{ $value }}" @checked(old('account_type', $type) === $value)>
                        <span><i class="bi {{ $icon }}"></i><strong>{{ $label }}</strong><small>{{ $hint }}</small></span>
                    </label>
                @endforeach
            </div>
            @error('account_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            <p class="form-text mb-0">New hosts are reviewed by an administrator before they can publish listings.</p>
        </fieldset>
        <x-field name="name" label="Full name" autocomplete="name" required />
        <x-field name="email" label="Email" type="email" autocomplete="email" required />
        <x-field name="phone" label="Phone" type="tel" autocomplete="tel" />
        <x-field name="password" label="Password" type="password" autocomplete="new-password" help="At least 8 characters, with letters and numbers." required />
        <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        <div class="form-check mb-4">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" @checked(old('terms'))>
            <label class="form-check-label small" for="terms">I understand this is a demo with simulated payments, and I accept the <a href="{{ route('pages.terms') }}" target="_blank">terms</a>.</label>
            @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-ex-primary w-100" type="submit">Create account</button>
    </form>
    <p class="small text-muted text-center mt-4 mb-0">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</x-auth-card>
@endsection
