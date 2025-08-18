<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;
use App\Models\Agent;
use Illuminate\Support\Facades\Log;

echo "Testing Agent Assignment...\n";

// Check if there are any agents
$agentsCount = Agent::count();
echo "Total agents: {$agentsCount}\n";

$activeAgents = Agent::where('is_active', true)->where('is_suspended', false)->count();
echo "Active agents: {$activeAgents}\n";

// Get a recent order
$order = Order::latest()->first();
if (!$order) {
    echo "No orders found!\n";
    exit;
}

echo "Testing with order ID: {$order->id}\n";
echo "Order market_id: {$order->market_id}\n";
echo "Current agent_id: " . ($order->agent_id ?? 'null') . "\n";

// Check if there are agents for this market
$marketAgents = Agent::where('market_id', $order->market_id)
    ->where('is_active', true)
    ->where('is_suspended', false)
    ->get();

echo "Available agents for market {$order->market_id}: " . $marketAgents->count() . "\n";

if ($marketAgents->count() > 0) {
    $agent = $marketAgents->first();
    echo "First available agent: {$agent->full_name} (ID: {$agent->id})\n";

    // Test manual assignment
    $order->update(['agent_id' => $agent->id]);
    echo "Agent assigned successfully!\n";

    // Check if order status can be updated
    try {
        $order->updateStatus('assigned', "Order assigned to agent {$agent->full_name}");
        echo "Order status updated to 'assigned'\n";
    } catch (Exception $e) {
        echo "Error updating order status: " . $e->getMessage() . "\n";
    }
} else {
    echo "No available agents for this market!\n";
}

echo "Test completed.\n";
