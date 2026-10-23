<?php

namespace App\Enums;

enum ListingType: string
{
    case Stay = 'stay';
    case Dining = 'dining';
    case Activity = 'activity';

    public function label(): string
    {
        return match ($this) {
            self::Stay => 'Stay',
            self::Dining => 'Dining',
            self::Activity => 'Experience',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Stay => 'Stays',
            self::Dining => 'Dining',
            self::Activity => 'Experiences',
        };
    }

    /** Public URL segment, e.g. /stays, /dining, /experiences */
    public function routeSegment(): string
    {
        return match ($this) {
            self::Stay => 'stays',
            self::Dining => 'dining',
            self::Activity => 'experiences',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Stay => 'bi-house-heart',
            self::Dining => 'bi-cup-hot',
            self::Activity => 'bi-compass',
        };
    }

    /** Two-letter code used in booking references: EXC-ST-XXXXXX */
    public function code(): string
    {
        return match ($this) {
            self::Stay => 'ST',
            self::Dining => 'DN',
            self::Activity => 'AC',
        };
    }

    /** Fixed chart colour per category (validated for colour-blind separation; always shown with a legend or table). */
    public function chartColor(): string
    {
        return match ($this) {
            self::Stay => '#1F8A5F',
            self::Dining => '#D4881C',
            self::Activity => '#2F74C0',
        };
    }

    public static function fromSegment(string $segment): self
    {
        foreach (self::cases() as $case) {
            if ($case->routeSegment() === $segment) {
                return $case;
            }
        }
        abort(404);
    }
}
