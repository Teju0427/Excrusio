<?php

namespace App\Payments;

use Illuminate\Support\Str;

/** Simulated payments: always succeeds, moves no money, collects no card details. */
class DemoGateway implements PaymentGateway
{
    public function charge(int $amountPaise, string $description, array $context = []): array
    {
        return ['reference' => 'DEMO-'.Str::upper(Str::random(10)), 'status' => 'succeeded', 'meta' => ['simulated' => true]];
    }

    public function refund(string $chargeReference, int $amountPaise): array
    {
        return ['reference' => 'DEMO-RF-'.Str::upper(Str::random(8)), 'status' => 'succeeded', 'meta' => ['simulated' => true, 'charge' => $chargeReference]];
    }

    public function name(): string
    {
        return 'demo';
    }
}
