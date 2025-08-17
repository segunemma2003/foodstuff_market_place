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
        // Orders table - Most critical for performance
        Schema::table('orders', function (Blueprint $table) {
            // Composite indexes for common queries
            $table->index(['market_id', 'status', 'created_at'], 'orders_market_status_created_idx');
            $table->index(['agent_id', 'status', 'created_at'], 'orders_agent_status_created_idx');
            $table->index(['whatsapp_number', 'created_at'], 'orders_whatsapp_created_idx');
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
            $table->index(['delivery_latitude', 'delivery_longitude'], 'orders_location_idx');
            
            // For date range queries
            $table->index('created_at');
            $table->index('updated_at');
        });

        // Market Products table - Critical for product queries
        Schema::table('market_products', function (Blueprint $table) {
            $table->index(['market_id', 'is_available'], 'market_products_market_available_idx');
            $table->index(['agent_id', 'is_available'], 'market_products_agent_available_idx');
            $table->index(['product_id', 'market_id'], 'market_products_product_market_idx');
            $table->index('is_available');
        });

        // Product Prices table - Critical for pricing queries
        Schema::table('product_prices', function (Blueprint $table) {
            $table->index(['market_product_id', 'is_available'], 'product_prices_market_product_available_idx');
            $table->index(['measurement_scale', 'is_available'], 'product_prices_scale_available_idx');
            $table->index('is_available');
        });

        // WhatsApp Sessions table - Critical for session management
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->index(['whatsapp_number', 'status'], 'whatsapp_sessions_number_status_idx');
            $table->index(['section_id', 'status'], 'whatsapp_sessions_section_status_idx');
            $table->index(['status', 'last_activity'], 'whatsapp_sessions_status_activity_idx');
            $table->index('last_activity');
        });

        // Order Items table - Critical for order details
        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'product_id'], 'order_items_order_product_idx');
            $table->index(['market_product_id'], 'order_items_market_product_idx');
        });

        // Agents table - Critical for agent queries
        Schema::table('agents', function (Blueprint $table) {
            $table->index(['market_id', 'is_active'], 'agents_market_active_idx');
            $table->index(['is_active', 'is_suspended'], 'agents_status_idx');
            $table->index('last_login_at');
        });

        // Markets table - Critical for location queries
        Schema::table('markets', function (Blueprint $table) {
            $table->index(['is_active'], 'markets_active_idx');
            $table->index(['latitude', 'longitude'], 'markets_location_idx');
        });

        // Products table - Critical for product queries
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'is_active'], 'products_category_active_idx');
            $table->index('is_active');
        });

        // Categories table - Critical for category queries
        Schema::table('categories', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_market_status_created_idx');
            $table->dropIndex('orders_agent_status_created_idx');
            $table->dropIndex('orders_whatsapp_created_idx');
            $table->dropIndex('orders_status_created_idx');
            $table->dropIndex('orders_location_idx');
            $table->dropIndex(['created_at']);
            $table->dropIndex(['updated_at']);
        });

        // Market Products table
        Schema::table('market_products', function (Blueprint $table) {
            $table->dropIndex('market_products_market_available_idx');
            $table->dropIndex('market_products_agent_available_idx');
            $table->dropIndex('market_products_product_market_idx');
            $table->dropIndex(['is_available']);
        });

        // Product Prices table
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropIndex('product_prices_market_product_available_idx');
            $table->dropIndex('product_prices_scale_available_idx');
            $table->dropIndex(['is_available']);
        });

        // WhatsApp Sessions table
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropIndex('whatsapp_sessions_number_status_idx');
            $table->dropIndex('whatsapp_sessions_section_status_idx');
            $table->dropIndex('whatsapp_sessions_status_activity_idx');
            $table->dropIndex(['last_activity']);
        });

        // Order Items table
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_product_idx');
            $table->dropIndex(['market_product_id']);
        });

        // Agents table
        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex('agents_market_active_idx');
            $table->dropIndex('agents_status_idx');
            $table->dropIndex(['last_login_at']);
        });

        // Markets table
        Schema::table('markets', function (Blueprint $table) {
            $table->dropIndex('markets_active_idx');
            $table->dropIndex('markets_location_idx');
        });

        // Products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_active_idx');
            $table->dropIndex(['is_active']);
        });

        // Categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
