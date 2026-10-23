<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Feature F4: daily forecast for outdoor activities from Open-Meteo (free, no API key).
 * Cached for 3 hours per location. If the service is unavailable the page simply hides
 * the forecast — weather never blocks a booking.
 */
class WeatherService
{
    public const FORECAST_DAYS = 14;

    /** @return list<array{date: string, code: int, label: string, icon: string, max: int, min: int, rain: ?int}>|null */
    public function forecast(float $lat, float $lng, string $timezone): ?array
    {
        $key = sprintf('weather:%.2f:%.2f', $lat, $lng);

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === 'unavailable' ? null : $cached;
        }

        try {
            $response = Http::timeout(4)->retry(1, 200, throw: false)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => round($lat, 3),
                'longitude' => round($lng, 3),
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                'timezone' => $timezone,
                'forecast_days' => self::FORECAST_DAYS,
            ]);
            $daily = $response->successful() ? $response->json('daily') : null;
        } catch (Throwable) {
            $daily = null;
        }

        if (! is_array($daily) || empty($daily['time'])) {
            Cache::put($key, 'unavailable', now()->addMinutes(10));   // try again soon, but don't hammer the API

            return null;
        }

        $days = [];
        foreach ($daily['time'] as $i => $date) {
            $code = (int) ($daily['weather_code'][$i] ?? 0);
            [$label, $icon] = self::describe($code);
            $days[] = [
                'date' => $date,
                'code' => $code,
                'label' => $label,
                'icon' => $icon,
                'max' => (int) round($daily['temperature_2m_max'][$i] ?? 0),
                'min' => (int) round($daily['temperature_2m_min'][$i] ?? 0),
                'rain' => isset($daily['precipitation_probability_max'][$i]) ? (int) $daily['precipitation_probability_max'][$i] : null,
            ];
        }
        Cache::put($key, $days, now()->addHours(3));

        return $days;
    }

    /** The forecast for one date, or null if it's outside the forecast window. */
    public function forDate(float $lat, float $lng, string $timezone, string $date): ?array
    {
        return collect($this->forecast($lat, $lng, $timezone) ?? [])->firstWhere('date', $date);
    }

    /** WMO weather codes → short label + Bootstrap icon. */
    public static function describe(int $code): array
    {
        return match (true) {
            $code === 0 => ['Clear', 'bi-sun'],
            $code <= 2 => ['Partly cloudy', 'bi-cloud-sun'],
            $code === 3 => ['Overcast', 'bi-cloud'],
            $code <= 48 => ['Fog', 'bi-cloud-fog2'],
            $code <= 57 => ['Drizzle', 'bi-cloud-drizzle'],
            $code <= 67 => ['Rain', 'bi-cloud-rain'],
            $code <= 77 => ['Snow', 'bi-cloud-snow'],
            $code <= 82 => ['Showers', 'bi-cloud-rain-heavy'],
            $code <= 86 => ['Snow showers', 'bi-cloud-snow'],
            default => ['Thunderstorm', 'bi-cloud-lightning-rain'],
        };
    }
}
