@extends('layouts.app', ['ownFlash' => true])
{{-- Shared shell for signed-in areas: a side menu per role + page content. --}}
@section('content')
@php
    $user = auth()->user();
    $menu = match ($user->role->value) {
        'admin' => [['admin.dashboard', 'bi-speedometer2', 'Overview'], ['admin.listings', 'bi-patch-check', 'Listings'], ['admin.reviews', 'bi-star', 'Reviews'], ['admin.bookings', 'bi-journal-check', 'Bookings'], ['admin.users', 'bi-people', 'Users & hosts'], ['admin.categories', 'bi-tags', 'Categories'], ['admin.reports', 'bi-flag', 'Reports'], ['admin.messages', 'bi-envelope', 'Messages'], ['admin.audit', 'bi-clock-history', 'Audit log']],
        'provider' => [['host.dashboard', 'bi-speedometer2', 'Overview'], ['host.bookings', 'bi-inbox', 'Bookings & requests'], ['host.calendar', 'bi-calendar3', 'Calendar'], ['host.reviews', 'bi-star', 'Reviews'], ['host.check-in', 'bi-qr-code-scan', 'Check-in']],
        default => [['account.dashboard', 'bi-speedometer2', 'Overview'], ['bookings.index', 'bi-journal-check', 'My bookings'], ['wishlist.index', 'bi-heart', 'Saved'], ['trip.index', 'bi-bag', 'Trip cart']],
    };
    $menu[] = ['notifications.index', 'bi-bell', 'Notifications'];
    $menu[] = ['profile.edit', 'bi-person-gear', 'Profile & security'];
@endphp
<div class="ex-panel-shell">
    <div class="container py-4 py-lg-5">
        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="ex-side">
                    <div class="d-flex align-items-center gap-3 mb-3 ex-side-profile">
                        <span class="ex-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="lh-sm">
                            <div class="fw-semibold">{{ $user->name }}</div>
                            <small class="text-muted">{{ $user->role->label() }}@if ($user->is_demo) · demo @endif</small>
                        </div>
                    </div>
                    <nav class="nav flex-column ex-side-nav" aria-label="Account menu">
                        @foreach ($menu as [$route, $icon, $label])
                            <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif><i class="bi {{ $icon }}"></i>{{ $label }}</a>
                        @endforeach
                    </nav>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3 ex-side-signout">
                        @csrf
                        <button class="btn btn-sm btn-ex-outline w-100" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Sign out</button>
                    </form>
                </div>
            </aside>
            <div class="col-lg-9">
                @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
                @if ($errors->has('booking'))<div class="alert alert-danger" role="alert">{{ $errors->first('booking') }}</div>@endif
                @yield('panel')
            </div>
        </div>
    </div>
</div>
@endsection
