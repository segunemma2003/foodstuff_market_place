<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'is_active',
        'is_suspended',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_suspended' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function marketProducts(): HasMany
    {
        return $this->hasMany(MarketProduct::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(AgentEarning::class);
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function isAvailable(): bool
    {
        return $this->is_active && !$this->is_suspended;
    }

    public function getActiveOrdersCount(): int
    {
        return $this->orders()->whereIn('status', [
            'assigned',
            'preparing',
            'ready_for_delivery',
            'out_for_delivery'
        ])->count();
    }
}
