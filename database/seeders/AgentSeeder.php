<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'market_id' => 1, // Central Market Lagos
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@foodstuff.store',
                'phone' => '+2348012345678',
                'password' => 'john',
                'is_active' => true,
                'is_suspended' => false,
            ],
            [
                'market_id' => 1, // Central Market Lagos
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@foodstuff.store',
                'phone' => '+2348023456789',
                'password' => 'jane',
                'is_active' => true,
                'is_suspended' => false,
            ],
            [
                'market_id' => 2, // Victoria Island Market
                'first_name' => 'Mike',
                'last_name' => 'Johnson',
                'email' => 'mike.johnson@foodstuff.store',
                'phone' => '+2348034567890',
                'password' => 'mike',
                'is_active' => true,
                'is_suspended' => false,
            ],
            [
                'market_id' => 2, // Victoria Island Market
                'first_name' => 'Sarah',
                'last_name' => 'Williams',
                'email' => 'sarah.williams@foodstuff.store',
                'phone' => '+2348045678901',
                'password' => 'sarah',
                'is_active' => true,
                'is_suspended' => false,
            ],
            [
                'market_id' => 3, // Ikeja Market
                'first_name' => 'David',
                'last_name' => 'Brown',
                'email' => 'david.brown@foodstuff.store',
                'phone' => '+2348056789012',
                'password' => 'david',
                'is_active' => true,
                'is_suspended' => false,
            ],
        ];

        foreach ($agents as $agent) {
            Agent::create($agent);
        }
    }
}
