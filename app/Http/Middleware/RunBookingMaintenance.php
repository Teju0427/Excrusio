<?php

namespace App\Http\Middleware;

use App\Services\BookingMaintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RunBookingMaintenance
{
    public function __construct(private BookingMaintenance $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->maintenance->runThrottled();

        return $next($request);
    }
}
