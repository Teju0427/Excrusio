<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

/**
 * Validates browse/search filters. Unlike a normal form, a search page never bounces
 * the visitor away: invalid filters are dropped and explained on the same page.
 */
class SearchFilters
{
    /** @return array{0: array<string, mixed>, 1: MessageBag} [valid filters, errors] */
    public static function from(Request $request): array
    {
        $today = now('Asia/Kolkata')->toDateString();

        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:100'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:20'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:10'],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'amenities' => ['nullable', 'array', 'max:10'],
            'amenities.*' => ['string', 'max:60'],
            'rating' => ['nullable', 'in:3,3.5,4,4.5'],
            'sort' => ['nullable', 'in:recommended,price_asc,price_desc,newest,rating,duration'],
            'check_in' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today, 'required_with:check_out'],
            'check_out' => ['nullable', 'date_format:Y-m-d', 'after:check_in', 'required_with:check_in'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today, 'before:'.now('Asia/Kolkata')->addDays(60)->toDateString()],
            'time' => ['nullable', 'in:breakfast,lunch,dinner,morning,afternoon'],
            'cuisine' => ['nullable', 'string', 'max:60'],
            'price_band' => ['nullable', 'integer', 'between:1,4'],
            'difficulty' => ['nullable', 'in:easy,moderate,challenging'],
            'duration' => ['nullable', 'in:short,half,full'],
        ], [
            'check_in.after_or_equal' => 'Check-in can’t be in the past.',
            'check_out.after' => 'Check-out must be after check-in.',
            'check_in.required_with' => 'Please choose a check-in date too.',
            'check_out.required_with' => 'Please choose a check-out date too.',
            'date.after_or_equal' => 'Please choose today or a future date.',
            'date.before' => 'Reservations open up to 60 days ahead.',
            'guests.max' => 'Groups of more than 20 need to contact the host directly.',
        ]);

        $errors = $validator->errors();
        $valid = $validator->valid();

        // Dates only make sense as a pair; drop both if either one is invalid.
        if ($errors->hasAny(['check_in', 'check_out'])) {
            unset($valid['check_in'], $valid['check_out']);
        } elseif (! empty($valid['check_in']) && ! empty($valid['check_out'])
            && now()->parse($valid['check_in'])->diffInDays($valid['check_out']) > 30) {
            $errors->add('check_out', 'Stays can be at most 30 nights.');
            unset($valid['check_in'], $valid['check_out']);
        }
        if (isset($valid['min_price'], $valid['max_price']) && (int) $valid['max_price'] < (int) $valid['min_price']) {
            $errors->add('max_price', 'The maximum price must be higher than the minimum.');
            unset($valid['min_price'], $valid['max_price']);
        }

        $filters = array_filter($valid, fn ($v) => $v !== null && $v !== '' && $v !== []);

        return [$filters, $errors];
    }
}
