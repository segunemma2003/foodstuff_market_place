<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Configuration
$laravelApiUrl = 'https://foodstuff-store-api-39172343a322.herokuapp.com';
$whatsappBotUrl = 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com';

// Test data
$testPhone = '08012345678'; // Replace with real phone number
$testOrderNumber = 'FS20241201001';
$testStatus = 'confirmed';
$testMessage = 'Your order has been confirmed!';

echo "🔍 Debugging Notification Flow\n";
echo "==============================\n\n";

// Test 1: Check WhatsApp Bot Status
echo "1️⃣ Checking WhatsApp Bot Status...\n";
try {
    $response = Http::timeout(10)->get("{$whatsappBotUrl}/status");
    if ($response->successful()) {
        $data = $response->json();
        echo "✅ Bot Status: " . ($data['whatsapp_ready'] ? 'Ready' : 'Not Ready') . "\n";
        echo "   QR Generated: " . ($data['qr_generated'] ? 'Yes' : 'No') . "\n";

        if (!$data['whatsapp_ready']) {
            echo "⚠️  WhatsApp Bot is not ready. This is likely the main issue!\n";
            echo "   Please check: https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com/qr\n";
        }
    } else {
        echo "❌ Failed to get bot status: {$response->status()}\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking bot status: {$e->getMessage()}\n";
}

echo "\n";

// Test 2: Test Direct WhatsApp Bot Notification
echo "2️⃣ Testing Direct WhatsApp Bot Notification...\n";
try {
    $botData = [
        'order_id' => 1,
        'order_number' => $testOrderNumber,
        'status' => $testStatus,
        'message' => $testMessage,
        'whatsapp_number' => $testPhone
    ];

    echo "   Sending data to bot: " . json_encode($botData) . "\n";

    $response = Http::timeout(15)->post("{$whatsappBotUrl}/order-status-update", $botData);

    if ($response->successful()) {
        $data = $response->json();
        if ($data['success'] ?? false) {
            echo "   ✅ Bot notification successful\n";
        } else {
            echo "   ❌ Bot notification failed: {$data['message']}\n";
        }
    } else {
        echo "   ❌ Bot HTTP error: {$response->status()}\n";
        echo "   Response: {$response->body()}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Bot exception: {$e->getMessage()}\n";
}

echo "\n";

// Test 3: Test Laravel API Order Status Update
echo "3️⃣ Testing Laravel API Order Status Update...\n";
try {
    $laravelData = [
        'order_id' => 1,
        'order_number' => $testOrderNumber,
        'status' => $testStatus,
        'message' => $testMessage,
        'whatsapp_number' => $testPhone
    ];

    echo "   Sending data to Laravel API: " . json_encode($laravelData) . "\n";

    $response = Http::timeout(15)->post("{$laravelApiUrl}/api/v1/order-status-update", $laravelData);

    if ($response->successful()) {
        $data = $response->json();
        if ($data['success'] ?? false) {
            echo "   ✅ Laravel API notification successful\n";
        } else {
            echo "   ❌ Laravel API notification failed: {$data['message']}\n";
        }
    } else {
        echo "   ❌ Laravel API HTTP error: {$response->status()}\n";
        echo "   Response: {$response->body()}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Laravel API exception: {$e->getMessage()}\n";
}

echo "\n";

// Test 4: Test Laravel API WhatsApp Service
echo "4️⃣ Testing Laravel API WhatsApp Service...\n";
try {
    $serviceData = [
        'phone' => $testPhone,
        'message' => "🧪 Test message from Laravel WhatsApp Service\n\nOrder: {$testOrderNumber}\nStatus: {$testStatus}\nMessage: {$testMessage}"
    ];

    echo "   Sending data to WhatsApp service: " . json_encode($serviceData) . "\n";

    $response = Http::timeout(15)->post("{$laravelApiUrl}/api/v1/whatsapp/send-message", $serviceData);

    if ($response->successful()) {
        $data = $response->json();
        if ($data['success'] ?? false) {
            echo "   ✅ WhatsApp service successful\n";
        } else {
            echo "   ❌ WhatsApp service failed: {$data['message']}\n";
        }
    } else {
        echo "   ❌ WhatsApp service HTTP error: {$response->status()}\n";
        echo "   Response: {$response->body()}\n";
    }
} catch (Exception $e) {
    echo "   ❌ WhatsApp service exception: {$e->getMessage()}\n";
}

echo "\n";

// Test 5: Test Direct Message to WhatsApp Bot
echo "5️⃣ Testing Direct Message to WhatsApp Bot...\n";
try {
    $directData = [
        'phone' => $testPhone,
        'message' => "🧪 Direct test message to WhatsApp Bot\n\nOrder: {$testOrderNumber}\nStatus: {$testStatus}"
    ];

    echo "   Sending direct message: " . json_encode($directData) . "\n";

    $response = Http::timeout(15)->post("{$whatsappBotUrl}/send-message", $directData);

    if ($response->successful()) {
        $data = $response->json();
        if ($data['success'] ?? false) {
            echo "   ✅ Direct message successful\n";
        } else {
            echo "   ❌ Direct message failed: {$data['message']}\n";
        }
    } else {
        echo "   ❌ Direct message HTTP error: {$response->status()}\n";
        echo "   Response: {$response->body()}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Direct message exception: {$e->getMessage()}\n";
}

echo "\n";
echo "🏁 Debug Complete!\n\n";

echo "💡 Analysis:\n";
echo "- If Test 1 fails: WhatsApp Bot is not connected\n";
echo "- If Test 2 fails: Issue with bot's notification endpoint\n";
echo "- If Test 3 fails: Issue with Laravel API endpoint\n";
echo "- If Test 4 fails: Issue with Laravel WhatsApp service\n";
echo "- If Test 5 fails: Issue with bot's message sending\n";
echo "\n🔧 Next Steps:\n";
echo "1. Check WhatsApp Bot QR code connection\n";
echo "2. Verify phone number format\n";
echo "3. Check network connectivity between services\n";
echo "4. Review logs for detailed error messages\n";
