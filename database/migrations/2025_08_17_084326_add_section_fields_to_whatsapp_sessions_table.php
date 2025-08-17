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
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('section_id')->nullable()->after('session_id');
            $table->decimal('delivery_latitude', 10, 8)->nullable()->after('delivery_address');
            $table->decimal('delivery_longitude', 11, 8)->nullable()->after('delivery_latitude');
            $table->unsignedBigInteger('selected_market_id')->nullable()->after('delivery_longitude');
            $table->unsignedBigInteger('order_id')->nullable()->after('selected_market_id');

            $table->foreign('selected_market_id')->references('id')->on('markets')->onDelete('set null');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropForeign(['selected_market_id', 'order_id']);
            $table->dropColumn(['section_id', 'delivery_latitude', 'delivery_longitude', 'selected_market_id', 'order_id']);
        });
    }
};
