<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'whatsapp_number',
        'session_id',
        'section_id',
        'status',
        'cart_items',
        'current_step',
        'last_activity',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'selected_market_id',
        'order_id',
    ];

    protected $casts = [
        'cart_items' => 'array',
        'last_activity' => 'datetime',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function market()
    {
        return $this->belongsTo(Market::class, 'selected_market_id');
    }

    public function addToCart(array $item): void
    {
        $cart = $this->cart_items ?? [];
        $cart[] = $item;
        $this->update(['cart_items' => $cart]);
    }

    public function clearCart(): void
    {
        $this->update(['cart_items' => []]);
    }

    public function updateActivity(): void
    {
        $this->update(['last_activity' => now()]);
    }

    public function isExpired(): bool
    {
        return $this->last_activity && $this->last_activity->diffInHours(now()) > 24;
    }

    public function generateSectionId(): string
    {
        return 'SEC_' . time() . '_' . substr(md5($this->whatsapp_number), 0, 6);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isOngoing(): bool
    {
        return $this->status === 'ongoing';
    }
}
