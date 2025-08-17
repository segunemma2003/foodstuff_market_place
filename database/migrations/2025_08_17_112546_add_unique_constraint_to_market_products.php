<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('market_products', function (Blueprint $table) {
            // Add unique constraint for market_id and product_name combination
            // This ensures that within each market, product names are unique
            $table->unique(['market_id', 'product_name'], 'market_products_market_id_product_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_products', function (Blueprint $table) {
            $table->dropUnique('market_products_market_id_product_name_unique');
        });
    }
};
