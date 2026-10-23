<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · Excursio' : 'Excursio — Stay somewhere special. Experience something extraordinary.' }}</title>
    <meta name="description" content="{{ $description ?? 'Discover and book exceptional stays, memorable dining and unforgettable experiences in one place.' }}">
    <meta property="og:title" content="{{ $title ?? 'Excursio' }}">
    <meta property="og:description" content="{{ $description ?? 'Stay somewhere special. Experience something extraordinary.' }}">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#243D35">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('vendor/fonts/cormorant-garamond-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('vendor/fonts/inter-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/excursio.css') }}?v={{ filemtime(public_path('css/excursio.css')) }}">
    @stack('head')
</head>
<body>
    <a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-ex-primary" href="#main" style="z-index:2000">Skip to content</a>

    @include('partials.nav')

    <main id="main">
        {{-- Pages that show messages themselves set $ownFlash; everything else gets this one. --}}
        @if (session('status') && empty($ownFlash))
            <div class="container pt-4"><div class="alert alert-success mb-0" role="status">{{ session('status') }}</div></div>
        @endif
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('js/excursio.js') }}?v={{ filemtime(public_path('js/excursio.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
