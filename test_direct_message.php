<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Direct WhatsApp Message ===\n\n";

// Test sending a direct message to the WhatsApp bot
$whatsappBotUrl = 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com';
$testNumber = '+2349036444724';

echo "Testing direct message to WhatsApp bot...\n";
echo "WhatsApp Number: {$testNumber}\n";
echo "Bot URL: {$whatsappBotUrl}\n\n";

// Test 1: Check if bot is ready
echo "=== Test 1: Checking Bot Status ===\n";
try {
    $response = Http::timeout(10)->get($whatsappBotUrl . '/health');

    if ($response->successful()) {
        $healthData = $response->json();
        echo "✅ Bot Health Check: SUCCESS\n";
        echo "WhatsApp Ready: " . ($healthData['whatsapp_ready'] ? 'YES' : 'NO') . "\n";
        echo "Status: " . $healthData['status'] . "\n";
        echo "Timestamp: " . $healthData['timestamp'] . "\n\n";
    } else {
        echo "❌ Bot Health Check: FAILED (HTTP " . $response->status() . ")\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Bot Health Check: ERROR - " . $e->getMessage() . "\n\n";
}

// Test 2: Try sending a simple test message
echo "=== Test 2: Sending Simple Test Message ===\n";
$simpleData = [
    'section_id' => 'direct-test',
    'status' => 'test',
    'message' => 'Hello! This is a direct test message.',
    'whatsapp_number' => $testNumber,
    'order_number' => 'DIRECT-TEST-' . time(),
];

try {
    $response = Http::timeout(15)->post($whatsappBotUrl . '/order-status-update', $simpleData);

    if ($response->successful()) {
        echo "✅ Direct Message: SUCCESS\n";
        echo "Response: " . $response->body() . "\n\n";
    } else {
        echo "❌ Direct Message: FAILED (HTTP " . $response->status() . ")\n";
        echo "Response: " . $response->body() . "\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Direct Message: ERROR - " . $e->getMessage() . "\n\n";
}

// Test 3: Try with different message format
echo "=== Test 3: Alternative Message Format ===\n";
$altData = [
    'section_id' => 'alt-test',
    'status' => 'notification',
    'message' => 'Test notification from admin system',
    'whatsapp_number' => $testNumber,
    'order_number' => 'ALT-TEST-' . time(),
];

try {
    $response = Http::timeout(15)->post($whatsappBotUrl . '/order-status-update', $altData);

    if ($response->successful()) {
        echo "✅ Alternative Message: SUCCESS\n";
        echo "Response: " . $response->body() . "\n\n";
    } else {
        echo "❌ Alternative Message: FAILED (HTTP " . $response->status() . ")\n";
        echo "Response: " . $response->body() . "\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Alternative Message: ERROR - " . $e->getMessage() . "\n\n";
}

echo "=== Test Complete ===\n";
