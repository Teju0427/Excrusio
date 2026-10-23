@extends('layouts.panel', ['title' => 'Profile & security'])
@section('panel')
<h1 class="mb-4" style="font-size: 2.4rem">Profile & security</h1>
@if ($user->is_demo)<div class="ex-demo-note mb-4"><i class="bi bi-info-circle me-1"></i>This is a shared demo account: you can edit the name and phone, but not the email or password.</div>@endif
<div class="row g-4">
    <div class="col-xl-6">
        <form method="POST" action="{{ route('profile.update') }}" class="ex-panel h-100" novalidate>
            @csrf @method('PUT')
            <h2 class="h5 mb-3">Your details</h2>
            <x-field name="name" label="Full name" :value="$user->name" autocomplete="name" required />
            <x-field name="email" label="Email" type="email" :value="$user->email" autocomplete="email" required :readonly="$user->is_demo" />
            <x-field name="phone" label="Phone" type="tel" :value="$user->phone" autocomplete="tel" />
            <button class="btn btn-ex-primary" type="submit">Save details</button>
        </form>
    </div>
    <div class="col-xl-6">
        <form method="POST" action="{{ route('profile.password') }}" class="ex-panel h-100" novalidate>
            @csrf @method('PUT')
            <h2 class="h5 mb-3">Change password</h2>
            <x-field name="current_password" label="Current password" type="password" autocomplete="current-password" required :disabled="$user->is_demo" />
            <x-field name="password" label="New password" type="password" autocomplete="new-password" help="At least 8 characters, with letters and numbers." required :disabled="$user->is_demo" />
            <x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required :disabled="$user->is_demo" />
            <button class="btn btn-ex-outline" type="submit" @disabled($user->is_demo)>Update password</button>
        </form>
    </div>
</div>
@endsection
