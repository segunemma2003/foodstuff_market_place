<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeolocationController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:3|max:200',
            'limit' => 'integer|min:1|max:10',
        ]);

        $query = $request->input('query');
        $limit = $request->limit ?? 5;

        // Use OpenStreetMap Nominatim API (free)
        try {
            $cacheKey = "geolocation_search_{$query}_{$limit}";

            $results = Cache::remember($cacheKey, 3600, function () use ($query, $limit) {
                $response = Http::get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => $limit,
                    'addressdetails' => 1,
                    'countrycodes' => 'ng', // Nigeria only
                ]);

                if ($response->successful()) {
                    return $response->json();
                }

                return [];
            });

            $formattedResults = collect($results)->map(function ($result) {
                return [
                    'display_name' => $result['display_name'],
                    'latitude' => (float) $result['lat'],
                    'longitude' => (float) $result['lon'],
                    'type' => $result['type'],
                    'importance' => $result['importance'],
                    'address' => $result['address'] ?? [],
                ];
            })->filter();

            return response()->json([
                'success' => true,
                'data' => $formattedResults,
                'count' => $formattedResults->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Geolocation service temporarily unavailable',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reverse(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;

        try {
            $cacheKey = "geolocation_reverse_{$latitude}_{$longitude}";

            $result = Cache::remember($cacheKey, 3600, function () use ($latitude, $longitude) {
                $response = Http::get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'json',
                    'addressdetails' => 1,
                ]);

                if ($response->successful()) {
                    return $response->json();
                }

                return null;
            });

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location not found',
                ], 404);
            }

            $formattedResult = [
                'display_name' => $result['display_name'],
                'latitude' => (float) $result['lat'],
                'longitude' => (float) $result['lon'],
                'address' => $result['address'] ?? [],
                'type' => $result['type'] ?? 'unknown',
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedResult,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Geolocation service temporarily unavailable',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function calculateDistance(Request $request): JsonResponse
    {
        $request->validate([
            'lat1' => 'required|numeric|between:-90,90',
            'lon1' => 'required|numeric|between:-180,180',
            'lat2' => 'required|numeric|between:-90,90',
            'lon2' => 'required|numeric|between:-180,180',
        ]);

        $lat1 = $request->lat1;
        $lon1 = $request->lon1;
        $lat2 = $request->lat2;
        $lon2 = $request->lon2;

        $distance = $this->haversineDistance($lat1, $lon1, $lat2, $lon2);

        return response()->json([
            'success' => true,
            'data' => [
                'distance_km' => round($distance, 2),
                'distance_miles' => round($distance * 0.621371, 2),
                'coordinates' => [
                    'from' => ['lat' => $lat1, 'lon' => $lon1],
                    'to' => ['lat' => $lat2, 'lon' => $lon2],
                ],
            ],
        ]);
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
