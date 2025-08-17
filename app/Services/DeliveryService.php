<?php

namespace App\Services;

class DeliveryService
{
    /**
     * Calculate distance between two points using Haversine formula
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles * 1.609344; // Convert to kilometers
    }

    /**
     * Calculate delivery fee based on distance (Uber/Bolt style)
     */
    public function calculateDeliveryFee(float $distance): float
    {
        $basePrice = 500; // Base delivery fee in Naira
        $perKmPrice = 50; // Additional fee per km

        return $basePrice + ($distance * $perKmPrice);
    }

    /**
     * Calculate delivery time based on distance
     */
    public function calculateDeliveryTime(float $distance): string
    {
        $baseTime = 30; // Base time in minutes
        $perKmTime = 2; // Additional time per km

        $totalMinutes = $baseTime + ($distance * $perKmTime);
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Calculate additional delivery fee for heavy items
     */
    public function calculateHeavyItemFee(float $totalWeight): float
    {
        if ($totalWeight <= 4) {
            return 0; // No additional fee for items 4kg or less
        }

        $additionalWeight = $totalWeight - 4;
        return $additionalWeight * 0.15 * 500; // 15% of base delivery fee per kg over 4kg
    }

    /**
     * Calculate service charge
     */
    public function calculateServiceCharge(float $subtotal): float
    {
        return $subtotal * 0.05; // 5% service charge
    }

    /**
     * Get nearby markets within specified distance with optional search
     */
    public function getNearbyMarkets(float $userLat, float $userLng, float $maxDistance = 30, ?string $search = null): array
    {
        $markets = \App\Models\Market::where('is_active', true);

        // Apply search filter if provided
        if ($search) {
            $markets->where('name', 'like', '%' . $search . '%')
                   ->orWhere('address', 'like', '%' . $search . '%');
        }

        $markets = $markets->get();
        $nearbyMarkets = [];

        foreach ($markets as $market) {
            $distance = $this->calculateDistance($userLat, $userLng, $market->latitude, $market->longitude);

            if ($distance <= $maxDistance) {
                $deliveryAmount = $this->calculateDeliveryFee($distance);
                $deliveryTime = $this->calculateDeliveryTime($distance);

                $nearbyMarkets[] = [
                    'id' => $market->id,
                    'name' => $market->name,
                    'address' => $market->address,
                    'distance' => round($distance, 2),
                    'delivery_amount' => $deliveryAmount,
                    'delivery_time' => $deliveryTime,
                    'latitude' => $market->latitude,
                    'longitude' => $market->longitude,
                ];
            }
        }

        // Sort by distance
        usort($nearbyMarkets, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $nearbyMarkets;
    }

    /**
     * Calculate total delivery cost including heavy item fees
     */
    public function calculateTotalDeliveryCost(float $distance, float $totalWeight): float
    {
        $baseDeliveryFee = $this->calculateDeliveryFee($distance);
        $heavyItemFee = $this->calculateHeavyItemFee($totalWeight);

        return $baseDeliveryFee + $heavyItemFee;
    }
}
