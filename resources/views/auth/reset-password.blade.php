@extends('layouts.app', ['title' => 'Choose a new password', 'ownFlash' => true])
@section('content')
<x-auth-card title="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" :value="$email" autocomplete="email" required />
        <x-field name="password" label="New password" type="password" autocomplete="new-password" help="At least 8 characters, with letters and numbers." required />
        <x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
        <button class="btn btn-ex-primary w-100" type="submit">Reset password</button>
    </form>
</x-auth-card>
@endsection
