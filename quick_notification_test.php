<?php

// Quick notification test - no autoloader needed
$whatsappBotUrl = 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com';

// Get phone number from command line or use default
$phone = $argv[1] ?? '08012345678'; // Replace with your real phone number

echo "🧪 Quick Notification Test\n";
echo "=========================\n\n";

echo "📱 Testing phone: {$phone}\n\n";

// Test 1: Check bot status
echo "1️⃣ Checking bot status...\n";
$statusResponse = file_get_contents("{$whatsappBotUrl}/status");
if ($statusResponse) {
    $statusData = json_decode($statusResponse, true);
    echo "✅ Bot ready: " . ($statusData['whatsapp_ready'] ? 'Yes' : 'No') . "\n";

    if (!$statusData['whatsapp_ready']) {
        echo "❌ Bot is not ready! Please scan QR code at: {$whatsappBotUrl}/qr\n";
        exit(1);
    }
} else {
    echo "❌ Cannot reach bot\n";
    exit(1);
}

// Test 2: Send test notification
echo "\n2️⃣ Sending test notification...\n";
$notificationData = [
    'order_id' => 1,
    'order_number' => 'TEST123',
    'status' => 'confirmed',
    'message' => '🧪 Test notification from FoodStuff Store!',
    'whatsapp_number' => $phone
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($notificationData)
    ]
]);

$response = file_get_contents("{$whatsappBotUrl}/order-status-update", false, $context);

if ($response) {
    $result = json_decode($response, true);
    if ($result['success'] ?? false) {
        echo "✅ Notification sent successfully!\n";
        echo "📱 Check your WhatsApp for the message.\n";
    } else {
        echo "❌ Notification failed: " . ($result['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "❌ Failed to send notification\n";
}

echo "\n�� Test complete!\n";
