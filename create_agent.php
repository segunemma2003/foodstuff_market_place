<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Agent;
use App\Models\Market;
use Illuminate\Support\Facades\Hash;

try {
    // First, ensure we have a market
    $market = Market::first();
    if (!$market) {
        echo "❌ No markets found. Please create a market first.\n";
        exit;
    }

    // Check if agent already exists
    $agent = Agent::where('email', 'agent@foodstuff.store')->first();

    if ($agent) {
        echo "Agent user already exists!\n";
        echo "Email: agent@foodstuff.store\n";
        echo "Password: agent123\n";
    } else {
        // Create agent user
        $agent = Agent::create([
            'market_id' => $market->id,
            'first_name' => 'Test',
            'last_name' => 'Agent',
            'email' => 'agent@foodstuff.store',
            'phone' => '+2341234567890',
            'password' => 'agent123', // Will be hashed by model mutator
            'is_active' => true,
        ]);

        echo "✅ Agent user created successfully!\n";
        echo "Email: agent@foodstuff.store\n";
        echo "Password: agent123\n";
        echo "Agent ID: " . $agent->id . "\n";
        echo "Market: " . $market->name . "\n";
    }

    echo "\n🔗 Agent API Endpoints:\n";
    echo "Base URL: https://foodstuff-store-api.herokuapp.com/api/v1\n";
    echo "Login: POST /auth/agent/login\n";
    echo "Orders: GET /agent/orders\n";
    echo "Products: GET /agent/products\n";
    echo "Earnings: GET /agent/earnings\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
