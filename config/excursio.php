<?php

return [
    // Platform fee added at checkout, in percent. 0 by default — no invented charges.
    'service_fee_percent' => (float) env('EXCURSIO_SERVICE_FEE_PERCENT', 0),

    // Minutes an unpaid checkout holds rooms/seats.
    'hold_minutes' => (int) env('EXCURSIO_HOLD_MINUTES', 10),

    // Hours a hotel has to approve a request-to-book.
    'approval_hours' => (int) env('EXCURSIO_APPROVAL_HOURS', 24),

    // Password for the seeded demo accounts. Set in .env; never commit a real one.
    'demo_password' => env('DEMO_PASSWORD', 'excursio-demo'),

    // Show one-click demo login buttons on the sign-in page.
    'demo_logins' => (bool) env('EXCURSIO_DEMO_LOGINS', true),

    // Payment gateway: "demo" (default) or "razorpay_test" (later phase).
    'payment_gateway' => env('EXCURSIO_PAYMENT_GATEWAY', 'demo'),

    // Uploads are disabled on hosts with ephemeral disks (Render free tier).
    'uploads_enabled' => (bool) env('EXCURSIO_UPLOADS_ENABLED', false),

    // Allow `php artisan excursio:demo-reset` (wipes everything) on a production deployment.
    // Only for the public portfolio demo — never enable on a real site.
    'demo_reset_allowed' => (bool) env('EXCURSIO_DEMO_RESET', false),

    'currency' => 'INR',
];
