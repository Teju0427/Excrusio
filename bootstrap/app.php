<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RunBookingMaintenance;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render and Azure terminate HTTPS at a load balancer in front of the app.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureRole::class,
            'active' => EnsureAccountActive::class,
        ]);

        // Expire unpaid holds / overdue requests, complete past stays (at most once a minute).
        $middleware->web(append: [RunBookingMaintenance::class]);
        $middleware->append(SecurityHeaders::class);

        // Signed-in users who visit /login or /register go to their own dashboard.
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
