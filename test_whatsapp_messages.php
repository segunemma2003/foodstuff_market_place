<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== WhatsApp Bot Message Testing ===\n\n";

// Get all active WhatsApp sessions
$sessions = \App\Models\WhatsappSession::where('status', 'active')->get();

if ($sessions->isEmpty()) {
    echo "No active WhatsApp sessions found.\n";
    exit;
}

echo "Found " . $sessions->count() . " active sessions:\n";
foreach ($sessions as $session) {
    echo "- Section ID: {$session->section_id}, Number: {$session->whatsapp_number}, Created: {$session->created_at}\n";
}

echo "\n=== Sending Test Messages ===\n";

$whatsappBotUrl = env('WHATSAPP_BOT_URL', 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com');
$testMessage = "🧪 TEST MESSAGE: This is a test message from the admin system to verify WhatsApp bot functionality. Please ignore this message.";

foreach ($sessions as $session) {
    echo "\nSending to Section ID: {$session->section_id} ({$session->whatsapp_number})...\n";

    $data = [
        'section_id' => $session->section_id,
        'status' => 'test',
        'message' => $testMessage,
        'whatsapp_number' => $session->whatsapp_number,
        'order_number' => 'TEST-' . time(),
    ];

    try {
        $response = Http::timeout(10)->post($whatsappBotUrl . '/order-status-update', $data);

        if ($response->successful()) {
            echo "✅ SUCCESS: Message sent successfully\n";
            echo "   Response: " . $response->body() . "\n";
        } else {
            echo "❌ FAILED: HTTP " . $response->status() . "\n";
            echo "   Response: " . $response->body() . "\n";
        }
    } catch (\Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }

    // Small delay between messages
    sleep(1);
}

echo "\n=== Test Complete ===\n";
