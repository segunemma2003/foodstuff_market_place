<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== WhatsApp Bot Message Sending Debug ===\n\n";

$whatsappBotUrl = 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com';
$testNumber = '+2349036444724';

echo "Testing WhatsApp bot message sending...\n";
echo "Bot URL: {$whatsappBotUrl}\n";
echo "Test Number: {$testNumber}\n\n";

// Test 1: Check bot health and readiness
echo "=== Test 1: Bot Health Check ===\n";
try {
    $response = Http::timeout(10)->get($whatsappBotUrl . '/health');

    if ($response->successful()) {
        $healthData = $response->json();
        echo "✅ Health Check: SUCCESS\n";
        echo "WhatsApp Ready: " . ($healthData['whatsapp_ready'] ? 'YES' : 'NO') . "\n";
        echo "Status: " . $healthData['status'] . "\n";
        echo "QR Generated: " . ($healthData['qr_generated'] ? 'YES' : 'NO') . "\n";
        echo "Laravel API URL: " . $healthData['laravel_api_url'] . "\n\n";

        if (!$healthData['whatsapp_ready']) {
            echo "❌ WhatsApp client is not ready! This is the root cause.\n";
            echo "The bot needs to be properly authenticated with WhatsApp.\n\n";
            exit;
        }
    } else {
        echo "❌ Health Check: FAILED (HTTP " . $response->status() . ")\n\n";
        exit;
    }
} catch (\Exception $e) {
    echo "❌ Health Check: ERROR - " . $e->getMessage() . "\n\n";
    exit;
}

// Test 2: Try sending a very simple message
echo "=== Test 2: Simple Message Test ===\n";
$simpleData = [
    'section_id' => 'debug-test',
    'status' => 'test',
    'message' => 'Test message from debug script',
    'whatsapp_number' => $testNumber,
    'order_number' => 'DEBUG-' . time(),
];

try {
    echo "Sending data: " . json_encode($simpleData, JSON_PRETTY_PRINT) . "\n\n";

    $response = Http::timeout(15)->post($whatsappBotUrl . '/order-status-update', $simpleData);

    echo "Response Status: " . $response->status() . "\n";
    echo "Response Body: " . $response->body() . "\n\n";

    if ($response->successful()) {
        echo "✅ Simple Message: SUCCESS\n";
    } else {
        echo "❌ Simple Message: FAILED\n";

        // Try to get more details about the error
        $errorData = json_decode($response->body(), true);
        if ($errorData && isset($errorData['message'])) {
            echo "Error Message: " . $errorData['message'] . "\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Simple Message: ERROR - " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
echo "\nNext Steps:\n";
echo "1. If WhatsApp is not ready, the bot needs to be re-authenticated\n";
echo "2. If the message sending fails, check the WhatsApp bot logs\n";
echo "3. The issue is likely in the WhatsApp Web.js client configuration\n";
