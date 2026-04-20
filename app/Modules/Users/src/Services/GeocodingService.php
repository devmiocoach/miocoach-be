<?php

namespace App\Modules\Users\Services;

use Illuminate\Support\Facades\Http;

class GeocodingService
{
    public function geocode(string $city): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => config('app.name') . '/1.0',
        ])->get('https://nominatim.openstreetmap.org/search', [
            'q'      => $city,
            'format' => 'json',
            'limit'  => 1,
        ]);

        $results = $response->json();

        if (empty($results)) {
            return null;
        }

        return [
            'lat' => $results[0]['lat'],
            'lng' => $results[0]['lon'],
        ];
    }
}
