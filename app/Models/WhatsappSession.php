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
        'status',
        'cart_items',
        'current_step',
        'last_activity',
        'delivery_address',
    ];

    protected $casts = [
        'cart_items' => 'array',
        'last_activity' => 'datetime',
    ];

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
}
