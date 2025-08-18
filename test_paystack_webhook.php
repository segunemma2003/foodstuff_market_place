<?php

// Test script to simulate Paystack webhook
$webhookUrl = 'https://foodstuff-admin-api-6f0912a00bcb.herokuapp.com/api/v1/payment-callback';

// Sample Paystack webhook data for a successful payment
$webhookData = [
    'event' => 'charge.success',
    'data' => [
        'id' => 123456789,
        'domain' => 'test',
        'amount' => 2265254, // Amount in kobo (22652.54 NGN)
        'currency' => 'NGN',
        'source' => 'card',
        'reason' => 'Foodstuff order payment',
        'recipient' => null,
        'status' => 'success',
        'reference' => 'FS20250817SqWoWa', // Use an existing order reference
        'gateway_response' => 'Approved',
        'paid_at' => '2024-01-18T10:30:00.000Z',
        'created_at' => '2024-01-18T10:25:00.000Z',
        'channel' => 'card',
        'ip_address' => '127.0.0.1',
        'metadata' => [
            'order_id' => '39', // Use an existing order ID
            'order_number' => 'FS202401180001',
            'customer_name' => 'Test Customer',
            'customer_phone' => '2349036444724',
            'section_id' => 'SEC_1755483037_4f357b', // Add section_id
        ],
        'log' => [
            'time_spent' => 9,
            'attempts' => 1,
            'authentication' => 'pin',
            'errors' => 0,
            'success' => true,
            'mobile' => false,
            'input' => [],
            'channel' => null,
            'history' => [
                [
                    'type' => 'action',
                    'message' => 'Attempted to pay with card',
                    'time' => 9,
                ],
                [
                    'type' => 'success',
                    'message' => 'Successfully paid',
                    'time' => 9,
                ],
            ],
        ],
        'fees' => 1000,
        'fees_split' => null,
        'authorization' => [
            'authorization_code' => 'AUTH_123456789',
            'bin' => '408408',
            'last4' => '4081',
            'exp_month' => '12',
            'exp_year' => '2025',
            'channel' => 'card',
            'card_type' => 'visa',
            'bank' => 'TEST BANK',
            'country_code' => 'NG',
            'brand' => 'visa',
            'reusable' => true,
            'signature' => 'SIG_123456789',
            'account_name' => null,
        ],
        'customer' => [
            'id' => 123456,
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'customer_code' => 'CUS_123456789',
            'phone' => '2349036444724',
            'metadata' => null,
            'risk_action' => 'default',
        ],
        'plan' => null,
        'split' => [],
    ],
];

// Convert to JSON
$jsonData = json_encode($webhookData);

// Generate a mock Paystack signature (in real scenario, this would be the actual signature)
$secretKey = 'sk_test_1234567890abcdef'; // This should be your actual Paystack secret key
$signature = hash_hmac('sha512', $jsonData, $secretKey);

// Set up cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData),
    'X-Paystack-Signature: ' . $signature,
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "Sending Paystack webhook to: $webhookUrl\n";
echo "Webhook data: " . $jsonData . "\n";
echo "Signature: " . $signature . "\n\n";

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
