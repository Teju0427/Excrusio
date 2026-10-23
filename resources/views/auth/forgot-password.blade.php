@extends('layouts.app', ['title' => 'Reset password', 'ownFlash' => true])
@section('content')
<x-auth-card title="Reset your password" lead="Enter your email and we’ll send you a link to choose a new password.">
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <button class="btn btn-ex-primary w-100" type="submit">Send reset link</button>
    </form>
    <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Demo note: emails are not actually sent; the reset link is written to the server log.</p>
    <p class="small text-center mt-4 mb-0"><a href="{{ route('login') }}">Back to sign in</a></p>
</x-auth-card>
@endsection
