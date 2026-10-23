<?php

namespace App\Payments;

/**
 * The single place a real payment provider (e.g. Razorpay test mode) would plug in.
 * Excursio never sees or stores card / UPI details.
 */
interface PaymentGateway
{
    /** @return array{reference: string, status: 'succeeded'|'failed', meta?: array} */
    public function charge(int $amountPaise, string $description, array $context = []): array;

    /** @return array{reference: string, status: 'succeeded'|'failed', meta?: array} */
    public function refund(string $chargeReference, int $amountPaise): array;

    public function name(): string;
}
