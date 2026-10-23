<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every response.
 * The CSP allows scripts only from this site (no inline JavaScript anywhere in the views),
 * images from this site + OpenStreetMap tiles, and no framing by other sites.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        if (! headers_sent()) {
            header_remove('X-Powered-By');   // don't advertise the PHP version
        }

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        // Camera only for this site (host QR scanner); nothing else.
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');

        // Laravel's own debug error page uses inline scripts; leave it alone in local debugging.
        $debugError = config('app.debug') && $response->getStatusCode() >= 500;
        if (! $debugError && ! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', implode('; ', array_filter([
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",          // style="" attributes; no external CSS
                "img-src 'self' data: blob: https://tile.openstreetmap.org",
                "font-src 'self'",
                "connect-src 'self'",
                "media-src 'self' blob:",
                "worker-src 'self' blob:",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                $request->isSecure() ? 'upgrade-insecure-requests' : null,
            ])));
        }

        if ($request->isSecure() && app()->isProduction()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
