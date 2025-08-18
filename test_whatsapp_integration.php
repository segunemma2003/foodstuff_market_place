<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Test configuration
$laravelApiUrl = 'https://foodstuff-store-api-39172343a322.herokuapp.com';
$whatsappBotUrl = 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com';

// Test phone numbers (replace with real ones for testing)
$testPhoneNumbers = [
    '08012345678',      // Nigerian format
    '2348012345678',    // With country code
    '+2348012345678',   // International format
];

$testMessage = '🧪 Test message from Laravel API integration!';

echo "🧪 Testing WhatsApp Integration\n";
echo "===============================\n\n";

// Test 1: Check WhatsApp Bot Status
echo "1️⃣ Checking WhatsApp Bot Status...\n";
try {
    $response = Http::timeout(10)->get("{$whatsappBotUrl}/status");
    if ($response->successful()) {
        $data = $response->json();
        echo "✅ Bot Status: " . ($data['whatsapp_ready'] ? 'Ready' : 'Not Ready') . "\n";
        echo "   QR Generated: " . ($data['qr_generated'] ? 'Yes' : 'No') . "\n";
        echo "   Environment: {$data['environment']}\n";

        if (!$data['whatsapp_ready']) {
            echo "⚠️  WhatsApp Bot is not ready. Please check QR code connection.\n";
        }
    } else {
        echo "❌ Failed to get bot status: {$response->status()}\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking bot status: {$e->getMessage()}\n";
}

echo "\n";

// Test 2: Test Direct WhatsApp Bot Message Sending
echo "2️⃣ Testing Direct WhatsApp Bot Message Sending...\n";
foreach ($testPhoneNumbers as $phone) {
    echo "   Testing phone: {$phone}\n";
    try {
        $response = Http::timeout(10)->post("{$whatsappBotUrl}/send-message", [
            'phone' => $phone,
            'message' => $testMessage
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['success'] ?? false) {
                echo "   ✅ Success: Message sent via WhatsApp Bot\n";
            } else {
                echo "   ❌ Failed: {$data['message']}\n";
            }
        } else {
            echo "   ❌ HTTP Error: {$response->status()}\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Exception: {$e->getMessage()}\n";
    }

    // Wait between tests
    sleep(2);
}

echo "\n";

// Test 3: Test Laravel API WhatsApp Service
echo "3️⃣ Testing Laravel API WhatsApp Service...\n";
foreach ($testPhoneNumbers as $phone) {
    echo "   Testing phone: {$phone}\n";
    try {
        $response = Http::timeout(15)->post("{$laravelApiUrl}/api/v1/whatsapp/send-message", [
            'phone' => $phone,
            'message' => $testMessage
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['success'] ?? false) {
                echo "   ✅ Success: Message sent via Laravel API\n";
            } else {
                echo "   ❌ Failed: {$data['message']}\n";
            }
        } else {
            echo "   ❌ HTTP Error: {$response->status()}\n";
            echo "   Response: {$response->body()}\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Exception: {$e->getMessage()}\n";
    }

    // Wait between tests
    sleep(2);
}

echo "\n";

// Test 4: Test Order Status Update (which uses WhatsApp notifications)
echo "4️⃣ Testing Order Status Update with WhatsApp Notification...\n";
try {
    $response = Http::timeout(15)->post("{$laravelApiUrl}/api/v1/order-status-update", [
        'order_id' => 1,
        'order_number' => 'TEST123',
        'status' => 'confirmed',
        'message' => 'Test order status update',
        'whatsapp_number' => $testPhoneNumbers[0]
    ]);

    if ($response->successful()) {
        $data = $response->json();
        if ($data['success'] ?? false) {
            echo "✅ Success: Order status update sent\n";
        } else {
            echo "❌ Failed: {$data['message']}\n";
        }
    } else {
        echo "❌ HTTP Error: {$response->status()}\n";
        echo "Response: {$response->body()}\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
}

echo "\n";
echo "🏁 Integration Test Complete!\n";
echo "\n💡 Tips:\n";
echo "- Make sure WhatsApp Bot is connected (check QR code)\n";
echo "- Use real phone numbers for actual testing\n";
echo "- Check logs for detailed error information\n";
echo "- Verify Termii configuration if WhatsApp Bot fails\n";
