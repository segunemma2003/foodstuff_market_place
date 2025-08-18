<?php

// Test script to verify Paystack signature verification
$webhookUrl = 'https://foodstuff-admin-api-6f0912a00bcb.herokuapp.com/api/v1/payment-callback';

// Sample Paystack webhook data
$webhookData = [
    'event' => 'charge.success',
    'data' => [
        'id' => 123456789,
        'domain' => 'test',
        'amount' => 2265254,
        'currency' => 'NGN',
        'source' => 'card',
        'reason' => 'Foodstuff order payment',
        'recipient' => null,
        'status' => 'success',
        'reference' => 'FS20250817SqWoWa',
        'gateway_response' => 'Approved',
        'paid_at' => '2024-01-18T10:30:00.000Z',
        'created_at' => '2024-01-18T10:25:00.000Z',
        'channel' => 'card',
        'ip_address' => '127.0.0.1',
        'metadata' => [
            'order_id' => '30',
            'order_number' => 'FS20250817SqWoWa',
            'customer_name' => 'Test Customer',
            'customer_phone' => '2349036444724',
            'section_id' => 'SEC_1755483037_4f357b',
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

// Use the actual Paystack secret key for signature generation
$secretKey = 'sk_test_8728d9cc0d3667908eb172be83026d4e1a5340b1';
$signature = hash_hmac('sha512', $jsonData, $secretKey);

echo "=== Testing Paystack Signature Verification ===\n";
echo "Secret Key: " . substr($secretKey, 0, 10) . "...\n";
echo "Generated Signature: " . substr($signature, 0, 20) . "...\n";
echo "Payload Length: " . strlen($jsonData) . " bytes\n\n";

// Test 1: Valid signature
echo "Test 1: Valid Signature\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData),
    'X-Paystack-Signature: ' . $signature,
    'User-Agent: Paystack/2.0',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 2: Invalid signature
echo "Test 2: Invalid Signature\n";
$invalidSignature = 'invalid_signature_12345';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData),
    'X-Paystack-Signature: ' . $invalidSignature,
    'User-Agent: Paystack/2.0',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 3: No signature
echo "Test 3: No Signature\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData),
    'User-Agent: Paystack/2.0',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response: $response\n\n";

echo "Signature verification tests completed!\n";
