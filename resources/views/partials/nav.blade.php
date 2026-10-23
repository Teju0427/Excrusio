<nav class="navbar navbar-expand-lg ex-nav sticky-top" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">
            <x-logo />
            Excursio
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#exNav" aria-controls="exNav" aria-expanded="false" aria-label="Open menu">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="collapse navbar-collapse" id="exNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link {{ request()->is('stays*') ? 'active' : '' }}" href="{{ route('listings.index', 'stays') }}">Stays</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->is('dining*') ? 'active' : '' }}" href="{{ route('listings.index', 'dining') }}">Dining</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->is('experiences*') ? 'active' : '' }}" href="{{ route('listings.index', 'experiences') }}">Experiences</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->is('destinations*') ? 'active' : '' }}" href="{{ route('destinations.index') }}">Destinations</a></li>
                @if (! auth()->check() || auth()->user()->isCustomer())
                    <li class="nav-item"><a class="nav-link {{ request()->is('bookings*') ? 'active' : '' }}" href="{{ route('bookings.index') }}">My Bookings</a></li>
                @endif
            </ul>
                        @if (Route::has('login'))
                <div class="d-flex gap-2 mt-3 mt-lg-0 align-items-center">
                    <a class="btn btn-sm btn-link text-reset px-2" href="{{ route('search') }}" aria-label="Search"><i class="bi bi-search"></i><span class="d-lg-none ms-2">Search</span></a>
                    @if (auth()->user()?->isCustomer())
                        @php($tripCount = count(session('trip_cart', [])))
                        <a class="btn btn-sm btn-link text-reset px-2 position-relative" href="{{ route('trip.index') }}" aria-label="Trip cart, {{ $tripCount }} {{ Str::plural('item', $tripCount) }}">
                            <i class="bi bi-bag"></i><span class="d-lg-none ms-2">Trip</span>
                            @if ($tripCount)<span class="ex-cart-count">{{ $tripCount }}</span>@endif
                        </a>
                    @endif
                    @auth
                        @php($unread = auth()->user()->unreadNotifications()->count())
                        <a class="btn btn-sm btn-link text-reset px-2 position-relative" href="{{ route('notifications.index') }}" aria-label="Notifications, {{ $unread }} unread">
                            <i class="bi bi-bell"></i><span class="d-lg-none ms-2">Notifications</span>
                            @if ($unread)<span class="ex-bell-count">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ex-outline dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="ex-avatar ex-avatar-sm">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                                {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><h6 class="dropdown-header">{{ auth()->user()->role->label() }}</h6></li>
                                <li><a class="dropdown-item" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                @if (auth()->user()->isCustomer())
                                    <li><a class="dropdown-item" href="{{ route('bookings.index') }}"><i class="bi bi-journal-check me-2"></i>My bookings</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form></li>
                            </ul>
                        </div>
                    @else
                        <a class="btn btn-sm btn-ex-outline" href="{{ route('login') }}">Sign in</a>
                        <a class="btn btn-sm btn-ex-primary" href="{{ route('register') }}">Join Excursio</a>
                    @endauth
                </div>
            @endif
        </div>
    </div>
</nav>
