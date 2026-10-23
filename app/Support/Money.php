<?php

namespace App\Support;

/**
 * Formats paise as Indian rupees with Indian digit grouping: 1234567_00 → ₹12,34,567.
 * Done by hand so it works without the PHP intl extension on free hosts.
 */
class Money
{
    public static function format(int $paise, bool $showPaise = false): string
    {
        $negative = $paise < 0;
        $paise = abs($paise);
        $rupees = intdiv($paise, 100);
        $digits = (string) $rupees;

        if (strlen($digits) > 3) {
            $last3 = substr($digits, -3);
            $rest = substr($digits, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $digits = $rest.','.$last3;
        }

        $out = '₹'.$digits;
        if ($showPaise) {
            $out .= '.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
        }

        return ($negative ? '−' : '').$out;
    }
}
