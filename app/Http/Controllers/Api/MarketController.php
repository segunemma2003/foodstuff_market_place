<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\MarketProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketController extends Controller
{
    public function getNearbyMarkets(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'numeric|min:0.1|max:50', // Default 5km, max 50km
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->radius ?? 5; // Default 5km

        $markets = Market::where('is_active', true)
            ->get()
            ->filter(function ($market) use ($latitude, $longitude, $radius) {
                $distance = $market->calculateDistance($latitude, $longitude);
                return $distance <= $radius;
            })
            ->map(function ($market) use ($latitude, $longitude) {
                $distance = $market->calculateDistance($latitude, $longitude);
                return [
                    'id' => $market->id,
                    'name' => $market->name,
                    'address' => $market->address,
                    'latitude' => $market->latitude,
                    'longitude' => $market->longitude,
                    'distance' => round($distance, 2),
                    'phone' => $market->phone,
                    'email' => $market->email,
                ];
            })
            ->sortBy('distance')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $markets,
            'count' => $markets->count(),
        ]);
    }

    public function getProducts(Request $request, Market $market): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'search' => 'nullable|string|max:100',
        ]);

        $query = MarketProduct::with(['product.category', 'agent'])
            ->where('market_id', $market->id)
            ->where('is_available', true);

        if ($request->category_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        if ($request->search) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        $products = $query->get()->map(function ($marketProduct) {
            return [
                'id' => $marketProduct->id,
                'product_id' => $marketProduct->product_id,
                'name' => $marketProduct->product->name,
                'description' => $marketProduct->product->description,
                'image' => $marketProduct->product->image,
                'unit' => $marketProduct->product->unit,
                'price' => $marketProduct->price,
                'stock_quantity' => $marketProduct->stock_quantity,
                'category' => [
                    'id' => $marketProduct->product->category->id,
                    'name' => $marketProduct->product->category->name,
                ],
                'agent' => [
                    'id' => $marketProduct->agent->id,
                    'name' => $marketProduct->agent->full_name,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $products,
            'market' => [
                'id' => $market->id,
                'name' => $market->name,
                'address' => $market->address,
            ],
        ]);
    }
}
