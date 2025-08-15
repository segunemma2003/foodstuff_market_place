<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'order_id',
        'amount',
        'status',
        'payment_reference',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function markAsPaid(string $paymentReference): void
    {
        $this->update([
            'status' => 'paid',
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
        ]);
    }
}
