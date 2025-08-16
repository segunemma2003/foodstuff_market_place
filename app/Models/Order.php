<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'whatsapp_number',
        'customer_name',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'market_id',
        'agent_id',
        'subtotal',
        'delivery_fee',
        'total_amount',
        'status',
        'payment_reference',
        'paystack_reference',
        'paid_at',
        'assigned_at',
        'delivered_at',
        'notes',
    ];

    protected $casts = [
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'assigned_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(AgentEarning::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function updateStatus(string $status, string $message = '', array $metadata = []): void
    {
        $this->update(['status' => $status]);

        // Log status change
        $this->statusLogs()->create([
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata,
        ]);

        // Update timestamps based on status
        switch ($status) {
            case 'paid':
                $this->update(['paid_at' => now()]);
                break;
            case 'assigned':
                $this->update(['assigned_at' => now()]);
                break;
            case 'delivered':
                $this->update(['delivered_at' => now()]);
                break;
        }
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isAssigned(): bool
    {
        return !is_null($this->agent_id);
    }

    public function canBeAssigned(): bool
    {
        return $this->status === 'paid' && is_null($this->agent_id);
    }
}
