@extends('layouts.app', ['title' => 'Sign in', 'ownFlash' => true])
@section('content')
<x-auth-card title="Welcome back" lead="Sign in to manage your stays, tables and experiences.">
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label small" for="remember">Keep me signed in</label>
            </div>
            <a class="small" href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button class="btn btn-ex-primary w-100" type="submit">Sign in</button>
    </form>
    <p class="small text-muted text-center mt-4 mb-0">New to Excursio? <a href="{{ route('register') }}">Create an account</a></p>

    @if ($demoLogins)
        <div class="ex-demo-box mt-4">
            <div class="ex-eyebrow mb-2">Try the demo</div>
            <p class="small text-muted mb-3">Explore every role instantly — no sign-up needed.</p>
            <div class="d-grid gap-2">
                @foreach (['customer' => ['Guest', 'bi-person', 'Browse and book'], 'provider' => ['Host', 'bi-house-gear', 'Manage listings and requests'], 'admin' => ['Admin', 'bi-shield-lock', 'Moderate and view analytics']] as $role => [$label, $icon, $hint])
                    <form method="POST" action="{{ route('login.demo', $role) }}">
                        @csrf
                        <button class="btn btn-ex-outline w-100 ex-demo-btn" type="submit">
                            <i class="bi {{ $icon }}"></i>
                            <span>Continue as demo {{ $label }}<small>{{ $hint }}</small></span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif
</x-auth-card>
@endsection
