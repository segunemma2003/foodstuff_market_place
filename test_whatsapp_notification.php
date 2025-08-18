<?php

// Test script to check WhatsApp notification
$whatsappBotUrl = 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com';

// Test data for order status update
$testData = [
    'order_id' => 30,
    'order_number' => 'FS20250817SqWoWa',
    'status' => 'assigned',
    'message' => 'An agent has been assigned to your order.',
    'whatsapp_number' => '2349036444724',
];

// Convert to JSON
$jsonData = json_encode($testData);

// Set up cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $whatsappBotUrl . '/order-status-update');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData),
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "Testing WhatsApp notification...\n";
echo "URL: " . $whatsappBotUrl . "/order-status-update\n";
echo "Data: " . $jsonData . "\n\n";

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response: $response\n";

if ($error) {
    echo "cURL Error: $error\n";
}

echo "\nTest completed.\n";
