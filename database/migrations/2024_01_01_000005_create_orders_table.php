<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('whatsapp_number');
            $table->string('customer_name');
            $table->string('delivery_address');
            $table->decimal('delivery_latitude', 10, 8);
            $table->decimal('delivery_longitude', 11, 8);
            $table->foreignId('market_id')->constrained();
            $table->foreignId('agent_id')->nullable()->constrained();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', [
                'pending',
                'confirmed',
                'paid',
                'assigned',
                'preparing',
                'ready_for_delivery',
                'out_for_delivery',
                'delivered',
                'cancelled',
                'failed'
            ])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->string('paystack_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'market_id']);
            $table->index('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
