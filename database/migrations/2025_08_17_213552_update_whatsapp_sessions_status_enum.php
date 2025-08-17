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
        // Drop any indexes that reference the status column
        try {
            Schema::table('whatsapp_sessions', function (Blueprint $table) {
                $table->dropIndex('whatsapp_sessions_number_status_idx');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        try {
            Schema::table('whatsapp_sessions', function (Blueprint $table) {
                $table->dropIndex('whatsapp_sessions_section_status_idx');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        try {
            Schema::table('whatsapp_sessions', function (Blueprint $table) {
                $table->dropIndex('whatsapp_sessions_status_activity_idx');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Drop the existing enum constraint
            $table->dropColumn('status');
        });

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Recreate with new enum values
            $table->enum('status', [
                'active',
                'completed',
                'expired',
                'paid',
                'payment_failed',
                'assigned',
                'preparing',
                'ready_for_delivery',
                'out_for_delivery',
                'delivered',
                'cancelled'
            ])->default('active')->after('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Drop the new enum constraint
            $table->dropColumn('status');
        });

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Restore original enum values
            $table->enum('status', ['active', 'completed', 'expired'])->default('active')->after('session_id');
        });
    }
};
