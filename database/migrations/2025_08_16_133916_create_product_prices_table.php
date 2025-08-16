<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_product_id')->constrained()->onDelete('cascade');
            $table->string('measurement_scale'); // e.g., "1kg", "5kg", "1 bowl", "1 paint bucket"
            $table->decimal('price', 10, 2);
            $table->integer('stock_quantity')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            // Ensure unique measurement scale per market product
            $table->unique(['market_product_id', 'measurement_scale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
