<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Agent;
use App\Models\Market;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@foodstuff.store'],
            [
                'name' => 'FoodStuff Admin',
                'email' => 'admin@foodstuff.store',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        if ($admin->wasRecentlyCreated) {
            $this->command->info('✅ Admin user created successfully!');
        } else {
            $this->command->info('ℹ️ Admin user already exists!');
        }

        // Create agent user
        $market = Market::first();
        if ($market) {
            $agent = Agent::firstOrCreate(
                ['email' => 'agent@foodstuff.store'],
                [
                    'market_id' => $market->id,
                    'first_name' => 'Test',
                    'last_name' => 'Agent',
                    'email' => 'agent@foodstuff.store',
                    'phone' => '+2341234567890',
                    'password' => 'agent123', // Will be hashed by model mutator
                    'is_active' => true,
                ]
            );

            if ($agent->wasRecentlyCreated) {
                $this->command->info('✅ Agent user created successfully!');
            } else {
                $this->command->info('ℹ️ Agent user already exists!');
            }
        } else {
            $this->command->warn('⚠️ No markets found. Please run MarketSeeder first.');
        }

        $this->command->info("\n🔗 Login Credentials:");
        $this->command->info("Admin - Email: admin@foodstuff.store, Password: admin123");
        $this->command->info("Agent - Email: agent@foodstuff.store, Password: agent123");

        $this->command->info("\n🔗 API Endpoints:");
        $this->command->info("Base URL: https://foodstuff-store-api.herokuapp.com/api/v1");
        $this->command->info("Admin Login: POST /auth/login");
        $this->command->info("Agent Login: POST /auth/agent/login");
    }
}
