<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function marketProducts(): HasMany
    {
        return $this->hasMany(MarketProduct::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getActiveAgents()
    {
        return $this->agents()->where('is_active', true)->where('is_suspended', false);
    }

    public function getAvailableAgents()
    {
        return $this->getActiveAgents()->whereDoesntHave('orders', function ($query) {
            $query->whereIn('status', ['assigned', 'preparing', 'ready_for_delivery', 'out_for_delivery']);
        });
    }

    public function calculateDistance($lat, $lng): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat - $this->latitude);
        $lngDelta = deg2rad($lng - $this->longitude);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
