<?php

namespace App\Enums;

enum CancellationPolicy: string
{
    case Flexible = 'flexible';
    case Moderate = 'moderate';
    case Strict = 'strict';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function summary(): string
    {
        return match ($this) {
            self::Flexible => 'Full refund up to 24 hours before start.',
            self::Moderate => 'Full refund up to 5 days before; 50% until 24 hours before.',
            self::Strict => '50% refund up to 7 days before; no refund after that.',
        };
    }
}
