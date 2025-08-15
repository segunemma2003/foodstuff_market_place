<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_number');
            $table->string('session_id')->unique();
            $table->enum('status', ['active', 'completed', 'expired'])->default('active');
            $table->json('cart_items')->nullable();
            $table->string('current_step')->default('greeting');
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();

            $table->index('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
