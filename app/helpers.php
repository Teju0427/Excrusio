<?php

use App\Support\Money;

if (! function_exists('money')) {
    /** Format an amount in paise as ₹ with Indian digit grouping. */
    function money(?int $paise, bool $showPaise = false): string
    {
        return Money::format((int) $paise, $showPaise);
    }
}

if (! function_exists('seed_image')) {
    /** Returns the public URL of an image if the file exists, otherwise null (views then show a styled fallback). */
    function seed_image(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return is_file(public_path($path)) ? asset($path) : null;
    }
}
